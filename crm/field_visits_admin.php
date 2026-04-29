<?php
require_once 'app_init.php';
// Include auth if needed separately, app_init typically handles it or we do it here
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['super_admin', 'admin', 'manager', 'fo_manager', 'hod'])) {
    header("Location: dashboard.php");
    exit();
}

$active_tab = $_GET['tab'] ?? 'pending';

// --- Handlers (Approve/Reject) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_visit'])) {
    try {
        $visit_id = $_POST['visit_id'];
        $action = $_POST['action']; // 'approve' or 'reject'
        $review_remarks = $_POST['review_remarks'] ?? '';
        
        // Manual Adjustments
        $manual_points = (int)($_POST['manual_points'] ?? 0);
        $allowance_food = (float)($_POST['allowance_food'] ?? 0);
        $allowance_ta = (float)($_POST['allowance_ta'] ?? 0);
        $incentive = (float)($_POST['incentive'] ?? 0);
        $charge_printing = (float)($_POST['charge_printing'] ?? 0);
        $charge_parcel = (float)($_POST['charge_parcel'] ?? 0);
    
        $status = ($action === 'approve') ? 'Approved' : 'Rejected';
    
        // Calculation Logic
        $points_earned = 0;
        $earnings = 0.00;
    
        if ($status === 'Approved') {
            $stmt_info = $pdo->prepare("SELECT fv.visit_scope, fv.claim_number, u.staff_type FROM field_visits fv JOIN users u ON fv.user_id = u.id WHERE fv.id = ?");
            $stmt_info->execute([$visit_id]);
            $v_info = $stmt_info->fetch();
    
            if ($v_info) {
                // Base Logic
                if (($v_info['staff_type'] ?? 'Permanent') == 'Permanent') {
                    switch ($v_info['visit_scope']) {
                        case 'Hospital Part': $points_earned = 1; break;
                        case 'Patient Part': $points_earned = 2; break;
                        case 'Other Part': $points_earned = 1; break;
                        default: $points_earned = 1;
                    }
                } elseif ($v_info['staff_type'] == 'Freelancer') {
                    $proj = $pdo->prepare("SELECT price_hospital, price_patient, price_other FROM projects WHERE claim_number = ?");
                    $proj->execute([$v_info['claim_number']]);
                    $rates = $proj->fetch();
                    if ($rates) {
                        switch ($v_info['visit_scope']) {
                            case 'Hospital Part': $earnings = $rates['price_hospital']; break;
                            case 'Patient Part': $earnings = $rates['price_patient']; break;
                            case 'Other Part': $earnings = $rates['price_other']; break;
                            default: $earnings = 0;
                        }
                    }
                }
            }
        }
        
        $points_earned += $manual_points; 
        $total_allowance = $allowance_food + $allowance_ta + $incentive + $charge_printing + $charge_parcel;
        $earnings += (float)$total_allowance;
    
        $stmt = $pdo->prepare("UPDATE field_visits SET status = ?, reviewed_by = ?, reviewed_at = NOW(), review_remarks = ?, points_earned = ?, earnings = ? WHERE id = ?");
        $stmt->execute([$status, $_SESSION['user_id'], $review_remarks, $points_earned, $earnings, $visit_id]);
    
        header("Location: field_visits_admin.php?tab=" . strtolower($status));
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$search_sql = "";
$params = [];
if (!empty($search)) {
    $search_sql = " AND (u.full_name LIKE ? OR fv.location_name LIKE ? OR fv.claim_number LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

$where_clause = "";
switch ($active_tab) {
    case 'approved': $where_clause = "WHERE fv.status = 'Approved'"; break;
    case 'rejected': $where_clause = "WHERE fv.status = 'Rejected'"; break;
    default: $where_clause = "WHERE fv.status = 'Pending'"; break;
}

$stmt = $pdo->prepare("SELECT fv.*, u.full_name as user_name, r.full_name as reviewer_name 
                         FROM field_visits fv 
                         INNER JOIN users u ON fv.user_id = u.id
                         LEFT JOIN users r ON fv.reviewed_by = r.id 
                         $where_clause $search_sql
                         ORDER BY fv.submitted_at DESC");
$stmt->execute($params);
$visits = $stmt->fetchAll();

// Stats
$stats = $pdo->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM field_visits")->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Visits Management - Documantraa</title>
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
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Visit Management</h1>
                    <p class="text-muted mb-0 small">Review and approve employee field submissions.</p>
                </div>
                <div class="d-none d-md-block">
                     <a href="field_visit_settings.php" class="btn-v2 btn-white-v2">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Quick Stats -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="app-card p-3 mb-0 text-center">
                        <div class="stat-value text-primary"><?= $stats['total'] ?? 0 ?></div>
                        <div class="stat-label">Total Logs</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="app-card p-3 mb-0 text-center">
                        <div class="stat-value text-warning"><?= $stats['pending'] ?? 0 ?></div>
                        <div class="stat-label">Pending Review</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="app-card p-3 mb-0 text-center">
                        <div class="stat-value text-success"><?= $stats['approved'] ?? 0 ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="app-card p-3 mb-0 text-center">
                        <div class="stat-value text-danger"><?= $stats['rejected'] ?? 0 ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
            </div>

            <!-- Main Content Card -->
            <div class="app-card p-0 overflow-hidden">
                <!-- TABS -->
                 <div class="d-flex border-bottom overflow-auto" style="white-space: nowrap;">
                    <a href="?tab=pending" class="px-4 py-3 text-decoration-none border-bottom border-3 <?= $active_tab=='pending'?'border-primary text-primary fw-bold':'border-transparent text-secondary' ?>">
                        <i class="bi bi-hourglass-split me-1"></i> Pending
                    </a>
                    <a href="?tab=approved" class="px-4 py-3 text-decoration-none border-bottom border-3 <?= $active_tab=='approved'?'border-success text-success fw-bold':'border-transparent text-secondary' ?>">
                        <i class="bi bi-check-circle me-1"></i> Approved
                    </a>
                    <a href="?tab=rejected" class="px-4 py-3 text-decoration-none border-bottom border-3 <?= $active_tab=='rejected'?'border-danger text-danger fw-bold':'border-transparent text-secondary' ?>">
                        <i class="bi bi-x-circle me-1"></i> Rejected
                    </a>
                </div>

                <!-- LIST -->
                <div class="p-3">
                     <?php if (empty($visits)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                            No <?= $active_tab ?> visits found.
                        </div>
                    <?php else: ?>
                        <div class="row g-3">
                        <?php foreach ($visits as $visit): ?>
                            <div class="col-lg-6 col-xl-4">
                                <div class="border rounded p-3 bg-white h-100 position-relative">
                                    <div class="d-flex justify-content-between mb-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary-subtle text-primary rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                                <i class="bi bi-person-fill"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0 fw-bold"><?= htmlspecialchars($visit['user_name']) ?></h6>
                                                <small class="text-muted" style="font-size: 0.75rem;"><?= date('d M, h:i A', strtotime($visit['visit_date'] . ' ' . $visit['visit_time'])) ?></small>
                                            </div>
                                        </div>
                                        <span class="badge badge-v2 <?= ($visit['status']=='Approved')?'badge-success':(($visit['status']=='Rejected')?'text-bg-danger':'badge-pending') ?>"><?= $visit['status'] ?></span>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-muted d-block text-uppercase fw-bold" style="font-size: 0.7rem;">Location</small>
                                        <div class="text-truncate fw-medium"><?= htmlspecialchars($visit['location_name']) ?></div>
                                    </div>

                                    <div class="mb-3 p-2 bg-light rounded small text-secondary">
                                        <?= htmlspecialchars($visit['purpose']) ?>
                                    </div>

                                     <!-- Actions / Status Info -->
                                     <?php if ($visit['status'] === 'Pending'): ?>
                                        <div class="d-grid gap-2">
                                            <button class="btn-v2 btn-primary-v2 w-100" onclick="openReviewModal(<?= htmlspecialchars(json_encode($visit)) ?>)">Review</button>
                                        </div>
                                    <?php else: ?>
                                        <div class="border-top pt-2 mt-2">
                                            <small class="text-muted">Reviewed by <?= htmlspecialchars($visit['reviewer_name']) ?></small>
                                            <?php if($visit['review_remarks']): ?>
                                                <div class="mt-1 small fst-italic text-secondary">"<?= htmlspecialchars($visit['review_remarks']) ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Review Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                         <input type="hidden" name="review_visit" value="1">
                         <input type="hidden" name="visit_id" id="reviewVisitId">
                         
                         <div class="text-center mb-4">
                             <h6 id="reviewStaffName" class="fw-bold fs-5">Employee Name</h6>
                             <div class="text-muted small" id="reviewPurpose">Visit Purpose</div>
                         </div>
                         
                         <div class="alert alert-light border d-flex gap-3 mb-3" id="evidenceLinks">
                             <!-- Links injected via JS -->
                         </div>

                         <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Remarks</label>
                            <textarea name="review_remarks" class="input-v2" rows="2" placeholder="Optional comments..."></textarea>
                        </div>
                        
                        <div class="p-3 bg-light rounded border mb-3">
                            <label class="form-label small fw-bold text-primary mb-2">ALLOWANCES & ADJUSTMENTS</label>
                             <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="manual_points" class="input-v2 py-1 fs-xs" placeholder="± Points">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="incentive" class="input-v2 py-1 fs-xs" placeholder="Incentive (Rs)">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="allowance_food" class="input-v2 py-1 fs-xs" placeholder="Food (Rs)">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="allowance_ta" class="input-v2 py-1 fs-xs" placeholder="Travel (Rs)">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="charge_printing" class="input-v2 py-1 fs-xs" placeholder="Printing (Rs)">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="charge_parcel" class="input-v2 py-1 fs-xs" placeholder="Parcel (Rs)">
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex">
                            <button type="submit" name="action" value="reject" class="btn-v2 btn-white-v2 text-danger flex-fill">Reject</button>
                            <button type="submit" name="action" value="approve" class="btn-v2 btn-primary-v2 flex-fill">Approve</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openReviewModal(visit) {
            document.getElementById('reviewVisitId').value = visit.id;
            document.getElementById('reviewStaffName').textContent = visit.user_name;
            document.getElementById('reviewPurpose').textContent = visit.purpose;
            
            // Build evidence links
            let links = '';
            if(visit.image_path) {
                links += `<a href="../uploads/field_visits/${visit.image_path}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-image"></i> View Evidence</a>`;
            } else {
                links = '<span class="text-muted small">No evidence uploaded yet.</span>';
            }
            document.getElementById('evidenceLinks').innerHTML = links;
            
            new bootstrap.Modal(document.getElementById('reviewModal')).show();
        }
    </script>
</body>
</html>
