<?php
require_once 'app_init.php';
require_once 'auth.php';

// Get Project ID
$pid = $_GET['id'] ?? 0;
if ($pid == 0) {
    header('Location: projects.php');
    exit;
}

// Fetch Project Details
$stmt = $pdo->prepare("SELECT p.*, c.company_name, u.full_name as officer_name, d.full_name as doctor_name, sfo.full_name as sub_fo_name, ccn.full_name as ccn_name 
                       FROM projects p 
                       JOIN clients c ON p.client_id = c.id 
                       LEFT JOIN users u ON p.assigned_to = u.id 
                       LEFT JOIN users d ON p.assigned_doctor_id = d.id 
                       LEFT JOIN users sfo ON p.sub_fo_id = sfo.id 
                       LEFT JOIN users ccn ON p.ccn_incharge_id = ccn.id 
                       WHERE p.id = ?");
$stmt->execute([$pid]);
$project = $stmt->fetch();

if (!$project) {
    echo "<h1>Claim not found</h1><a href='projects.php'>Back</a>";
    exit;
}

// Access Control
$curr_role = $_SESSION['role'] ?? '';
$is_ho_staff = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'team_manager', 'fo_manager', 'hod']);
$is_assigned = (
    $project['assigned_to'] == $_SESSION['user_id'] || 
    $project['pt_fo_id'] == $_SESSION['user_id'] || 
    $project['hp_fo_id'] == $_SESSION['user_id'] || 
    $project['other_fo_id'] == $_SESSION['user_id'] ||
    $project['assigned_doctor_id'] == $_SESSION['user_id']
);

if (!$is_ho_staff) {
    if (!$is_assigned) die("Access Denied: You are not assigned to this case.");
}

// Permissions for financial data
$is_tm = ($curr_role == 'team_manager' || $curr_role == 'fo_manager');
$show_fees = ($is_ho_staff || $is_tm || $is_assigned);

$allocation_contact = ['full_name' => 'Global Webify', 'phone' => '+91 85898 34483'];

// Calculate TAT
$created = new DateTime($project['created_at']);
$deadline = new DateTime($project['tat_deadline']);
$now = new DateTime();
$tat_diff = $deadline->diff($now);
$is_overdue = ($now > $deadline);
$tat_days = $tat_diff->days;
$tat_text = $is_overdue ? "$tat_days Days Overdue" : "$tat_days Days Left";
$tat_class = $is_overdue ? 'text-danger' : 'text-success';
if (!$is_overdue && $tat_days < 3) $tat_class = 'text-warning';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Claim #<?= htmlspecialchars($project['claim_number']) ?> - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-size: 0.9rem; font-weight: 500; }
        .detail-value { color: var(--text-main); font-weight: 600; text-align: right; font-size: 0.95rem; }
        
        @media (min-width: 992px) {
            .detail-row {
                padding: 16px 24px;
            }
        }
    </style>
</head>
<body>
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <a href="projects.php" class="text-main"><i class="bi bi-arrow-left fs-4"></i></a>
            <span class="fw-bold ms-2">Claim Details</span>
        </div>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section d-none d-lg-flex">
            <div class="header-inner">
                <div class="d-flex align-items-center gap-3">
                    <a href="projects.php" class="btn-white-v2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; min-width: 42px; padding: 0;">
                        <i class="bi bi-arrow-left text-dark" style="font-size: 1.1rem;"></i>
                    </a>
                    <div>
                        <div class="d-flex align-items-center gap-3">
                            <h1 class="mb-0" style="font-size: 1.5rem; color: var(--text-main); line-height: 1;"><?= htmlspecialchars($project['title']) ?></h1>
                            <span class="badge rounded-pill bg-light text-dark border px-3 py-1 fw-bold small text-uppercase" style="letter-spacing: 0.5px; font-size: 0.7rem;">
                                <?= $project['status'] ?>
                            </span>
                        </div>
                        <p class="text-muted mb-0 small mt-1" style="font-weight: 500;">
                            Claim #<?= htmlspecialchars($project['claim_number']) ?> 
                            <?php 
                            $dc = $pdo->prepare("SELECT COUNT(*) FROM project_documents WHERE project_id = ?");
                            $dc->execute([$pid]);
                            $doc_count = $dc->fetchColumn();
                            if($doc_count > 0): 
                            ?>
                            <span class="ms-2 badge bg-light text-primary border" style="font-size: 0.65rem; padding: 2px 6px;">
                                <i class="bi bi-paperclip"></i> <?= $doc_count ?> Files
                            </span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </header>

        <div class="app-container">
            <div class="row g-4">
                <div class="col-lg-8">
                     <div class="d-flex gap-3 mb-4">
                        <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">
                             <i class="bi bi-check-circle-fill"></i> <span class="fw-bold small">VIP Case</span>
                        </div>
                         <div class="ms-auto d-flex align-items-center gap-2 text-muted small">
                            <i class="bi bi-clock"></i> <span class="<?= $tat_class ?> fw-bold"><?= $tat_text ?></span>
                        </div>
                     </div>

                    <div class="app-card p-0 mb-4">
                         <div class="p-3 border-bottom bg-light d-flex justify-content-between">
                            <h6 class="mb-0 fw-bold text-uppercase small text-muted">Case Information</h6>
                        </div>
                        <div class="p-3">
                            <?php if($show_fees): 
                                $gross_fee = ($project['price_hospital'] ?? 0) + ($project['price_patient'] ?? 0) + ($project['price_other'] ?? 0);
                                $fine = $project['fine_amount'] ?? 0;
                                $net_payout = $gross_fee - $fine;
                            ?>
                            <div class="px-2 pt-2 pb-3 mb-4">
                                <div class="p-3 rounded-4 border bg-white shadow-sm" style="border: 2px solid #eee !important;">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-light p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-wallet2 text-primary small"></i>
                                            </div>
                                            <span class="small fw-bold text-muted">Total Gross</span>
                                        </div>
                                        <span class="fw-bold fs-6">₹<?= number_format($gross_fee, 2) ?></span>
                                    </div>
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-danger-subtle p-2 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                <i class="bi bi-shield-exclamation text-danger small"></i>
                                            </div>
                                            <span class="small fw-bold text-muted">Fines / Deductions</span>
                                        </div>
                                        <span class="fw-bold <?= $fine > 0 ? 'text-danger' : 'text-muted opacity-50' ?>">
                                            <?= $fine > 0 ? '-₹'.number_format($fine, 2) : '₹0.00' ?>
                                        </span>
                                    </div>

                                    <?php if($fine > 0): ?>
                                    <div class="alert alert-warning py-2 small mb-3 border-0 bg-warning-subtle" style="font-size: 0.7rem; border-radius: 8px;">
                                        <i class="bi bi-info-circle me-1"></i> Automatic TAT fine applied.
                                    </div>
                                    <?php endif; ?>

                                    <div class="d-flex justify-content-between align-items-center p-3 rounded-4 bg-primary text-white shadow-sm">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="bi bi-cash-stack"></i>
                                            <span class="fw-bold">Your Net Earning</span>
                                        </div>
                                        <span class="fw-bold fs-4">₹<?= number_format($net_payout, 2) ?></span>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <div class="detail-row">
                                <span class="detail-label">Scope</span>
                                <span class="detail-value"><?= htmlspecialchars($project['scope']) ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Hospital</span>
                                <span class="detail-value"><?= htmlspecialchars($project['hospital_name'] ?? 'N/A') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Patient Phone</span>
                                <span class="detail-value">
                                    <?php if(!empty($project['patient_phone'])): ?>
                                        <a href="tel:<?= $project['patient_phone'] ?>" class="text-primary text-decoration-none">
                                            <?= htmlspecialchars($project['patient_phone']) ?> <i class="bi bi-telephone"></i>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Address</span>
                                <span class="detail-value text-break">
                                    <?php if(!empty($project['hospital_address'])): ?>
                                        <a href="https://maps.google.com/?q=<?= urlencode(($project['hospital_name']??'') . ' ' . $project['hospital_address']) ?>" target="_blank" class="text-primary text-decoration-none">
                                            <?= htmlspecialchars($project['hospital_address']) ?> <i class="bi bi-box-arrow-up-right small"></i>
                                        </a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">Diagnosis</span>
                                <span class="detail-value"><?= htmlspecialchars($project['diagnosis'] ?? '-') ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">DOA / DOD</span>
                                <span class="detail-value">
                                    <?= !empty($project['doa']) ? date('d M Y', strtotime($project['doa'])) : '-' ?> 
                                    <span class="text-muted mx-1">to</span> 
                                    <?= !empty($project['dod']) ? date('d M Y', strtotime($project['dod'])) : '-' ?>
                                </span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label">UHID</span>
                                <span class="detail-value"><?= htmlspecialchars($project['uhid'] ?? '-') ?></span>
                            </div>
                             <div class="detail-row">
                                <span class="detail-label">Field Officer</span>
                                <span class="detail-value"><?= htmlspecialchars($project['officer_name'] ?? 'Unassigned') ?></span>
                            </div>
                             <div class="detail-row">
                                <span class="detail-label">Sub FO Incharge</span>
                                <span class="detail-value"><?= htmlspecialchars($project['sub_fo_name'] ?? 'Unassigned') ?></span>
                            </div>
                             <div class="detail-row">
                                <span class="detail-label">CCN Incharge</span>
                                <span class="detail-value"><?= htmlspecialchars($project['ccn_name'] ?? 'Unassigned') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="app-card mb-4">
                        <h6 class="card-title-v2 mb-3">Instructions / Description</h6>
                        <p class="text-secondary mb-0"><?= nl2br(htmlspecialchars($project['description'] ?? 'No specific instructions.')) ?></p>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="app-card p-3 mb-3">
                         <h6 class="card-title-v2 mb-3">Quick Actions</h6>
                         <div class="d-grid gap-2">
                             <a href="field_visits.php?search=<?= urlencode($project['claim_number']) ?>" class="btn-v2 btn-primary-v2 w-100 py-3">
                                 <i class="bi bi-geo-alt-fill"></i> Start Field Visit
                             </a>
                             <a href="project_documents.php?id=<?= $pid ?>" class="btn-v2 btn-white-v2 w-100 py-3">
                                 <i class="bi bi-cloud-upload"></i> Upload Documents
                             </a>
                             <?php if($is_ho_staff || $curr_role == 'doctor'): ?>
                              <a href="investigation_data.php?id=<?= $pid ?>" class="btn-v2 btn-white-v2 w-100 py-3">
                                 <i class="bi bi-clipboard-data"></i> Edit Report Data
                              </a>
                             <?php endif; ?>
                         </div>
                    </div>

                    <!-- Contact Card -->
                    <div class="app-card p-3">
                        <h6 class="card-title-v2 mb-3">Support & Coordinates</h6>
                        <div class="d-flex align-items-center">
                            <div class="bg-light rounded-circle p-2 me-3"><i class="bi bi-whatsapp text-success"></i></div>
                            <div>
                                <div class="small fw-bold">Allocation Desk</div>
                                <div class="text-muted small"><?= htmlspecialchars($allocation_contact['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
         <a href="projects.php" class="bottom-nav-item">
            <i class="bi bi-arrow-left"></i>
            <span>Back</span>
        </a>
        <a href="field_visits.php?search=<?= urlencode($project['claim_number']) ?>" class="bottom-nav-item text-primary">
            <i class="bi bi-geo-alt-fill"></i>
            <span>Visit</span>
        </a>
        <div style="position: relative; top: -20px;">
            <a href="project_documents.php?id=<?= $pid ?>" class="bottom-nav-icon-main">
                <i class="bi bi-cloud-upload"></i>
            </a>
        </div>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

