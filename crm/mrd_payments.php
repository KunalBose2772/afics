<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$upload_dir = __DIR__ . '/../uploads/mrd_payments/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

$message = '';
$message_type = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $action = $_POST['action'] ?? '';
    $project_id = $_POST['project_id'] ?? '';
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;

    try {
        if ($action === 'request_payment') {
            // Validation
            $req_fields = ['project_id' => 'Claim Selection', 'amount' => 'Bill Amount'];
            $errors = validate_required($req_fields, $_POST);
            
            $amount = floatval($_POST['amount'] ?? 0);
            if ($err = validate_numeric($amount, 'Bill Amount', 1)) $errors[] = $err;
            
            if (empty($errors)) {
                $notes = sanitize_input($_POST['notes'] ?? '');
                
                // Upload QR
                $qr_path = '';
                if (!empty($_FILES['qr_image']['name'])) {
                    $file = $_FILES['qr_image'];
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                    if (!in_array($ext, $allowed)) {
                        $errors[] = "Invalid QR image format. Allowed: " . implode(', ', $allowed);
                    } else {
                        $new_name = "QR_" . $project_id . "_" . time() . ".$ext";
                        move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
                        $qr_path = $new_name;
                    }
                }

                if (empty($errors)) {
                    $stmt = $pdo->prepare("UPDATE projects SET mrd_status = 'In-Review', mrd_amount = ?, mrd_qr_path = (CASE WHEN ? != '' THEN ? ELSE mrd_qr_path END), mrd_notes = ?, mrd_request_lat = ?, mrd_request_long = ? WHERE id = ?");
                    $stmt->execute([$amount, $qr_path, $qr_path, $notes, $latitude, $longitude, $project_id]);
                    $message = "Payment Request Sent with Live Location!";
                    $message_type = "success";
                }
            }

        } elseif ($action === 'mark_paid' && in_array($_SESSION['role'], ['admin', 'super_admin'])) {
             // Validation
             $req_fields = ['project_id' => 'Project ID', 'mrd_utr' => 'UTR / Transaction Reference'];
             $errors = validate_required($req_fields, $_POST);
             
             $mrd_utr = sanitize_input($_POST['mrd_utr'] ?? '');
             if (empty($errors)) {
                 if (strlen($mrd_utr) < 6) {
                     $errors[] = "UTR Reference is too short. Please enter a valid reference.";
                 } elseif (strlen($mrd_utr) > 32) {
                     $errors[] = "UTR Reference is too long. Please enter a valid reference (max 32 chars).";
                 }
             }
             
             if (empty($_FILES['payment_slip']['name'])) {
                 $errors[] = "Payment Slip image is required.";
             }

             if (empty($errors)) {
                 $slip_path = '';
                 // $mrd_utr already sanitized above

                 $file = $_FILES['payment_slip'];
                 $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                 $new_name = "SLIP_" . $project_id . "_" . time() . ".$ext";
                 move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
                 $slip_path = $new_name;

                 $stmt = $pdo->prepare("UPDATE projects SET mrd_status = 'Paid', mrd_payment_slip = ?, mrd_utr = ? WHERE id = ?");
                 $stmt->execute([$slip_path, $mrd_utr, $project_id]);
                 
                 // --- Notification Logic ---
                 $p = $pdo->prepare("SELECT p.claim_number, p.title, p.mrd_amount, u.full_name, u.email, u.phone 
                                    FROM projects p 
                                    JOIN users u ON p.assigned_to = u.id 
                                    WHERE p.id = ?");
                 $p->execute([$project_id]);
                 $staff = $p->fetch();
                 
                 if ($staff && !empty($staff['email'])) {
                     $subject = "MRD Payment Released - Claim #: " . $staff['claim_number'];
                     $body = "Dear " . $staff['full_name'] . ",\n\n"
                            . "The MRD payment request for Claim # " . $staff['claim_number'] . " (" . $staff['title'] . ") has been approved and paid.\n\n"
                            . "Amount: ₹" . number_format($staff['mrd_amount'], 2) . "\n"
                            . "Ref: " . $mrd_utr . "\n\n"
                            . "Please download the payment slip from the portal.\n\n"
                            . "Regards,\nDocumantraa CMS";
                     queue_email($pdo, $staff['email'], $subject, $body, $_SESSION['user_id']);
                 }

                 $message = "Payment marked as PAID.";
                 $message_type = "success";
             }

        } elseif ($action === 'upload_receipt') {
            if (empty($_FILES['receipt']['name'])) {
                $errors[] = "Receipt image is required.";
            }

            if (empty($errors)) {
                $receipt_path = '';
                $file = $_FILES['receipt'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $new_name = "RECEIPT_" . $project_id . "_" . time() . ".$ext";
                move_uploaded_file($file['tmp_name'], $upload_dir . $new_name);
                $receipt_path = $new_name;

                $stmt = $pdo->prepare("UPDATE projects SET mrd_status = 'Verified', mrd_receipt = ?, mrd_receipt_lat = ?, mrd_receipt_long = ? WHERE id = ?");
                $stmt->execute([$receipt_path, $latitude, $longitude, $project_id]);
                
                $message = "Final Receipt Uploaded!";
                $message_type = "success";
            }
        }

    } catch (Exception $e) {
        $errors[] = "System Error: " . $e->getMessage();
    }
}

// Fetch Active Data (In-Review: Requested by staff, Paid: Amount sent but receipt pending)
$active_mrd = $pdo->query("SELECT * FROM projects WHERE mrd_status IN ('In-Review', 'Paid') ORDER BY id DESC")->fetchAll();

// Fetch Completed Data (Verified: Receipt uploaded and confirmed)
$completed_mrd = $pdo->query("SELECT * FROM projects WHERE mrd_status = 'Verified' ORDER BY id DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>MRD Payment Gateway - Documantraa</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 32px;">
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">MRD Payments</h1>
                    <p class="text-muted mb-0 small">Secure payment gateway for Hospital MRD requests.</p>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?= render_form_errors($errors ?? []) ?>
            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2"></i> <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- New Request Form -->
            <div class="app-card mb-4">
                <div class="card-header-v2">
                    <span class="card-title-v2"><i class="bi bi-plus-circle me-2"></i>New Request</span>
                    <div id="gpsStatus" class="d-flex align-items-center gap-2 text-muted small">
                         <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                         <span>Locating...</span>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="mrdRequestForm" class="needs-validation" novalidate>
                     <input type="hidden" name="action" value="request_payment">
                     <input type="hidden" name="latitude" id="lat_req">
                     <input type="hidden" name="longitude" id="long_req">

                     <div class="row g-3">
                         <div class="col-md-4">
                             <label class="form-label small fw-bold text-muted">Claim Selection</label>
                             <select name="project_id" class="form-select input-v2" required>
                                 <option value="">Select Claim...</option>
                                 <?php 
                                 $projects = $pdo->query("SELECT id, claim_number, title FROM projects WHERE mrd_status = 'Pending'")->fetchAll();
                                 foreach($projects as $p) { echo "<option value='{$p['id']}'>{$p['claim_number']} - {$p['title']}</option>"; }
                                 ?>
                             </select>
                         </div>
                         <div class="col-md-4">
                             <label class="form-label small fw-bold text-muted">Bill Amount</label>
                             <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted">&#8377;</span>
                                <input type="number" name="amount" class="form-control input-v2 border-start-0 ps-0" placeholder="0.00" required>
                             </div>
                         </div>
                         <div class="col-md-4">
                             <label class="form-label small fw-bold text-muted">QR Code (Optional)</label>
                             <input type="file" name="qr_image" class="form-control input-v2">
                         </div>
                         <div class="col-12">
                             <label class="form-label small fw-bold text-muted">Payment Notes</label>
                             <input type="text" name="notes" class="form-control input-v2" placeholder="e.g. UPI ID or Payee Name">
                         </div>
                         <div class="col-12">
                             <button type="submit" class="btn-v2 btn-primary-v2 w-100" id="submitBtn">
                                <i class="bi bi-send-fill"></i> Send Payment Request
                             </button>
                         </div>
                     </div>
                </form>
            </div>

            <!-- Tabs for MRD Payments -->
            <ul class="nav nav-tabs mb-4 px-1" id="mrdTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold small text-uppercase" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab" aria-controls="active" aria-selected="true">
                        Active Requests (<?= count($active_mrd) ?>)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold small text-uppercase" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                        Completed History (<?= count($completed_mrd) ?>)
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="mrdTabsContent">
                <!-- Active Tab -->
                <div class="tab-pane fade show active" id="active" role="tabpanel" aria-labelledby="active-tab">
                    <?php if (empty($active_mrd)): ?>
                        <div class="text-center py-5">
                            <div class="bg-light rounded-circle d-inline-flex p-4 mb-3 text-muted">
                                <i class="bi bi-wallet2 fs-1"></i>
                            </div>
                            <p class="text-muted">No active payment requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach($active_mrd as $row): ?>
                            <div class="col-md-6 col-xl-4">
                                <div class="app-card h-100 position-relative border">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h6 class="fw-bold mb-1"><?= $row['claim_number'] ?></h6>
                                            <span class="badge badge-v2 <?= $row['mrd_status'] == 'Paid' ? 'badge-success' : 'badge-pending' ?>">
                                                <?= $row['mrd_status'] ?>
                                            </span>
                                        </div>
                                        <div class="text-end">
                                            <div class="fs-4 fw-bold text-primary">&#8377;<?= number_format($row['mrd_amount']) ?></div>
                                            <small class="text-muted">Amount</small>
                                        </div>
                                    </div>
                                    
                                    <div class="d-flex gap-2 mb-3">
                                        <?php if(isset($row['mrd_qr_path']) && $row['mrd_qr_path']): ?>
                                            <a href="../uploads/mrd_payments/<?= $row['mrd_qr_path'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-pill">
                                                <i class="bi bi-qr-code"></i> View QR
                                            </a>
                                        <?php endif; ?>
                                        <?php if(isset($row['mrd_payment_slip']) && $row['mrd_payment_slip']): ?>
                                            <a href="../uploads/mrd_payments/<?= $row['mrd_payment_slip'] ?>" target="_blank" class="btn btn-sm btn-outline-success rounded-pill">
                                                <i class="bi bi-receipt"></i> View Slip
                                            </a>
                                        <?php endif; ?>
                                        <?php if(isset($row['mrd_request_lat']) && $row['mrd_request_lat']): ?>
                                            <a href="https://maps.google.com/?q=<?= $row['mrd_request_lat'] ?>,<?= $row['mrd_request_long'] ?>" target="_blank" class="btn btn-sm btn-outline-danger rounded-pill">
                                                <i class="bi bi-geo-alt-fill"></i> Map
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-auto pt-3 border-top">
                                        <?php if(in_array($_SESSION['role'], ['admin', 'super_admin']) && $row['mrd_status'] == 'In-Review'): ?>
                                        <button class="btn-v2 btn-primary-v2 w-100" data-bs-toggle="modal" data-bs-target="#payModal<?= $row['id'] ?>">
                                            Mark as Paid
                                        </button>
                                        <?php elseif($row['mrd_status'] == 'Paid'): ?>
                                            <button class="btn-v2 btn-white-v2 w-100" data-bs-toggle="modal" data-bs-target="#receiptModal<?= $row['id'] ?>">
                                                Upload Final Receipt
                                            </button>
                                        <?php else: ?>
                                            <small class="text-muted fst-italic">Waiting for verification...</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Modals inside tab to keep context -->
                            <?php if($row['mrd_status'] == 'In-Review'): ?>
                            <div class="modal fade" id="payModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title fw-bold">Confirm Payment</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="mark_paid">
                                                <input type="hidden" name="project_id" value="<?= $row['id'] ?>">
                                                
                                                <div class="alert alert-light border mb-3">
                                                    Paying <strong>&#8377;<?= $row['mrd_amount'] ?></strong> for Claim #<?= $row['claim_number'] ?>
                                                </div>
                                                
                                                 <div class="mb-3">
                                                    <label class="stat-label mb-1">Transaction UTR / URI <span class="text-danger">*</span></label>
                                                    <input type="text" name="mrd_utr" class="input-v2 w-100" placeholder="e.g. 123456789012" required maxlength="32" pattern="[A-Za-z0-9\/\-_=#]+">
                                                    <div class="form-text small text-muted">Enter the 12-22 digit reference number.</div>
                                                </div>
                                                            
                                                <label class="form-label small fw-bold">Upload Payment Slip</label>
                                                <input type="file" name="payment_slip" class="form-control input-v2" required>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="submit" class="btn-v2 btn-primary-v2 w-100">Confirm & Release</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php elseif($row['mrd_status'] == 'Paid'): ?>
                            <div class="modal fade" id="receiptModal<?= $row['id'] ?>" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form method="POST" enctype="multipart/form-data">
                                            <div class="modal-header border-0">
                                                <h5 class="modal-title fw-bold">Final Hospital Receipt</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="upload_receipt">
                                                <input type="hidden" name="project_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="latitude" class="gps-lat-receipt">
                                                <input type="hidden" name="longitude" class="gps-long-receipt">

                                                <label class="form-label small fw-bold">Hospital Receipt Image</label>
                                                <input type="file" name="receipt" class="form-control input-v2" required>
                                                <small class="text-muted d-block mt-2"><i class="bi bi-geo-alt-fill text-danger"></i> Location will be auto-tagged.</small>
                                            </div>
                                            <div class="modal-footer border-0">
                                                <button type="submit" class="btn-v2 btn-primary-v2 w-100">Upload Receipt</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Completed Tab -->
                <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
                    <?php if (empty($completed_mrd)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-check2-all fs-1 text-muted opacity-25 d-block mb-3"></i>
                            <p class="text-muted">No completed transactions yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="app-card overflow-hidden p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-3 py-3">Claim #</th>
                                            <th class="py-3">Amount</th>
                                            <th class="py-3">UTR Reference</th>
                                            <th class="py-3">Completed On</th>
                                            <th class="pe-3 py-3 text-end">Documents</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($completed_mrd as $crow): ?>
                                        <tr>
                                            <td class="ps-3 py-3">
                                                <div class="fw-bold"><?= $crow['claim_number'] ?></div>
                                                <small class="text-muted"><?= $crow['hospital_name'] ?? 'Hospital' ?></small>
                                            </td>
                                            <td class="py-3 fw-bold text-success">₹<?= number_format($crow['mrd_amount']) ?></td>
                                            <td class="py-3 font-monospace small"><?= $crow['mrd_utr'] ?></td>
                                            <td class="py-3 text-muted">
                                                <?= date('d M, Y', strtotime($crow['allocation_date'] ?? $crow['created_at'] ?? 'now')) ?>
                                            </td>
                                            <td class="pe-3 py-3 text-end">
                                                <div class="d-flex gap-2 justify-content-end">
                                                    <?php if($crow['mrd_payment_slip']): ?>
                                                        <a href="../uploads/mrd_payments/<?= $crow['mrd_payment_slip'] ?>" target="_blank" class="btn btn-sm btn-white-v2" title="Payment Slip">
                                                            <i class="bi bi-receipt"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if($crow['mrd_receipt']): ?>
                                                        <a href="../uploads/mrd_payments/<?= $crow['mrd_receipt'] ?>" target="_blank" class="btn btn-sm btn-white-v2" title="Final Receipt">
                                                            <i class="bi bi-file-earmark-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>


        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
    <script>
        let currentLat = null, currentLong = null;

        function initGPS() {
            const statusEl = document.getElementById('gpsStatus');
            const submitBtn = document.getElementById('submitBtn');

            if (navigator.geolocation) {
                if(submitBtn) submitBtn.disabled = true;

                navigator.geolocation.watchPosition(
                    (position) => {
                        currentLat = position.coords.latitude;
                        currentLong = position.coords.longitude;
                        
                        // Inject into main form
                        const latInput = document.getElementById('lat_req');
                        const longInput = document.getElementById('long_req');
                        if(latInput) latInput.value = currentLat;
                        if(longInput) longInput.value = currentLong;
                        
                        // Inject into modal forms
                        document.querySelectorAll('.gps-lat-receipt').forEach(el => el.value = currentLat);
                        document.querySelectorAll('.gps-long-receipt').forEach(el => el.value = currentLong);

                        if(statusEl) {
                            statusEl.innerHTML = '<i class="bi bi-geo-alt-fill text-success"></i> <span class="text-success fw-bold">GPS Locked</span>';
                        }
                        if(submitBtn) submitBtn.disabled = false;
                    },
                    (error) => {
                        if(statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle"></i> GPS Denied</span>';
                    },
                    { enableHighAccuracy: true }
                );
            } else {
                if(statusEl) statusEl.innerHTML = '<span class="text-danger">GPS Not Supported</span>';
            }
        }

        function injectGPS(form) {
            if(!currentLat) {
                alert("Please enable GPS and wait for location lock.");
                return false;
            }
            form.querySelector('.gps-lat-receipt').value = currentLat;
            form.querySelector('.gps-long-receipt').value = currentLong;
            return true;
        }

        document.addEventListener('DOMContentLoaded', initGPS);
    </script>
</body>
</html>
