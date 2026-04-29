<?php
require_once 'app_init.php';
require_once 'auth.php';

$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

// Only Team Managers, Managers, and Admins can see substantial earnings
// Sub-FOs (investigator/fo) see 0 or limited?
// User said: "IN TEAM MANAGER UNDER FOS THEY CAN ALLOCATE THERE UNDER TEAM MEMBERS. ... TEAM MANAGER ONLY [RECV PAY].. THERE SUB FOS NO NEED TO SEE PAYMENT DETAILS."
$is_ho_staff = in_array($user_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager']);
$is_tm = ($user_role == 'team_manager' || $user_role == 'fo_manager');

$total_earned = 0;
$pending_payment = 0;
$show_financials = true; 

// Base query for completed/closed cases
$query = "SELECT p.*, c.company_name FROM projects p JOIN clients c ON p.client_id = c.id WHERE p.status IN ('FO-Closed', 'Completed')";

if (!$is_ho_staff) {
    if ($is_tm) {
        $query .= " AND p.team_manager_id = $user_id";
    } else {
        // Sub-FOs only see cases they were actually assigned to
        $query .= " AND (p.assigned_to = $user_id OR p.pt_fo_id = $user_id OR p.hp_fo_id = $user_id OR p.other_fo_id = $user_id)";
    }
}
$query .= " ORDER BY p.payment_confirmed_at DESC, p.id DESC";

$stmt = $pdo->query($query);
$projects = $stmt->fetchAll();

// Note: User previously requested to hide payment for sub-FOs, but now wants to see "How much earn" on mobile.
// Since the query already filters by assignment, showing these details to the FO for their own cases is helpful.
// Calculate totals
foreach ($projects as $p) {
    if ($show_financials) {
        $net = ($p['price_hospital'] + $p['price_patient'] + $p['price_other'] + ($p['ta_amount'] ?? 0)) 
               - ($p['fine_amount'] + ($p['tat_deduction'] ?? 0) + ($p['other_deduction'] ?? 0));
        if ($p['payment_status'] == 'Paid') {
            $total_earned += $net;
        } else {
            $pending_payment += $net;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Earnings - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .earning-card { border-radius: 1.5rem; transition: transform 0.3s ease; border: none; }
        .earning-card:hover { transform: translateY(-5px); }
        .invoice-link:hover { text-decoration: underline !important; }
    </style>
</head>
<body style="background: #f4f7f6;">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="mb-0" style="font-size: 1.8rem;">Earning Dashboard</h1>
                <p class="text-muted small mb-0">Track your case-wise payments and invoices.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="projects.php" class="btn-v2 btn-white-v2 border"><i class="bi bi-folder me-2"></i> View Cases</a>
            </div>
        </header>

        <div class="app-container">
            <?php if($show_financials): ?>
            <div class="row g-4 mb-5">
                <div class="col-md-6">
                    <div class="earning-card shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-white-50 opacity-100 fw-bold">Total Paid Earnings</small>
                                <h2 class="fw-bold mt-2 mb-0">₹<?= number_format($total_earned, 2) ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="bi bi-cash-stack fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="earning-card shadow-sm p-4 text-white" style="background: linear-gradient(135deg, #e67e22 0%, #d35400 100%);">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <small class="text-white-50 opacity-100 fw-bold">Pending Clearance</small>
                                <h2 class="fw-bold mt-2 mb-0">₹<?= number_format($pending_payment, 2) ?></h2>
                            </div>
                            <div class="rounded-circle bg-white bg-opacity-25 p-3">
                                <i class="bi bi-clock-history fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="app-card shadow-sm rounded-4 p-4 border-0">
                <h6 class="fw-bold mb-4 d-flex align-items-center"><i class="bi bi-list-check me-2 text-primary"></i> Case-wise Details</h6>
                <!-- Desktop Table View (Visible on Laptops/Desktops) -->
                <div class="table-responsive d-none d-lg-block">
                    <table class="table table-hover align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="border-0 small text-muted">Claim #</th>
                                <th class="border-0 small text-muted">Project Title</th>
                                <?php if($show_financials): ?>
                                <th class="border-0 small text-muted text-end">Gross Fee</th>
                                <th class="border-0 small text-muted text-end">TAT Fine</th>
                                <th class="border-0 small text-muted text-end">Net Payable</th>
                                <?php endif; ?>
                                <th class="border-0 small text-muted text-center">Status / UTR</th>
                                <th class="border-0 small text-muted text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($projects)): ?>
                                <tr><td colspan="7" class="text-center p-5 text-muted">No completed cases found for your account.</td></tr>
                            <?php else: ?>
                                <?php foreach($projects as $p): 
                                    $base_gross = $p['price_hospital'] + $p['price_patient'] + $p['price_other'];
                                    $additions = $p['ta_amount'] ?? 0;
                                    $gross = $base_gross + $additions;
                                    $deductions = ($p['fine_amount'] ?? 0) + ($p['tat_deduction'] ?? 0) + ($p['other_deduction'] ?? 0);
                                    $net = $gross - $deductions;
                                ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($p['claim_number']) ?></td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 200px;"><?= htmlspecialchars($p['title']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($p['company_name']) ?></small>
                                    </td>
                                    <?php if($show_financials): ?>
                                    <td class="text-end">₹<?= number_format($gross, 2) ?></td>
                                    <td class="text-end text-danger"><?= $deductions > 0 ? '-₹'.number_format($deductions, 2) : '₹0' ?></td>
                                    <td class="text-end fw-bold">₹<?= number_format($net, 2) ?></td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <?php if($p['payment_status'] == 'Paid'): ?>
                                            <div class="d-flex flex-column align-items-center">
                                                <span class="badge bg-success-subtle text-success border border-success mb-1">Paid</span>
                                                <?php if(!empty($p['payment_utr'])): ?>
                                                    <small class="text-muted" style="font-size: 0.65rem;">UTR: <?= htmlspecialchars($p['payment_utr']) ?></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="project_details.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="bi bi-eye"></i> Details
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile & Tablet Card View (Visible on small screens) -->
                <div class="d-lg-none">
                    <div class="alert alert-info py-2 small mb-3 border-0 bg-info-subtle" style="border-radius: 12px;">
                        <i class="bi bi-info-circle me-1"></i> <span style="font-size: 0.75rem;">TAT Fines are automatically calculated (5% per day after 5-day grace).</span>
                    </div>
                    <?php if(empty($projects)): ?>
                        <div class="text-center p-5 text-muted">No completed cases found.</div>
                    <?php else: ?>
                        <?php foreach($projects as $p): 
                            $base_gross = $p['price_hospital'] + $p['price_patient'] + $p['price_other'];
                            $additions = $p['ta_amount'] ?? 0;
                            $gross = $base_gross + $additions;
                            $deductions = ($p['fine_amount'] ?? 0) + ($p['tat_deduction'] ?? 0) + ($p['other_deduction'] ?? 0);
                            $net = $gross - $deductions;
                        ?>
                        <div class="earnings-mobile-card border border-light position-relative p-3 mb-3 bg-white" style="border-radius: 1.2rem; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div style="max-width: 70%;">
                                    <div class="small text-muted fw-bold mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">CLAIM #<?= htmlspecialchars($p['claim_number']) ?></div>
                                    <div class="fw-bold text-main lh-sm" style="font-size: 1rem;"><?= htmlspecialchars($p['title']) ?></div>
                                    <div class="small text-muted mt-1 opacity-75" style="font-size: 0.75rem;"><?= htmlspecialchars($p['company_name']) ?></div>
                                </div>
                                <div class="text-end">
                                    <?php if($p['payment_status'] == 'Paid'): ?>
                                        <span class="badge bg-success text-white border-0 py-1 px-3 mb-1 d-block" style="font-size: 0.65rem; border-radius: 20px;">Paid</span>
                                        <?php if(!empty($p['payment_utr'])): ?>
                                            <small class="text-muted fw-bold d-block" style="font-size: 0.6rem;">UTR: <?= htmlspecialchars($p['payment_utr']) ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark border-0 py-1 px-3 mb-2 d-block" style="font-size: 0.65rem; border-radius: 20px;">Pending</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="row g-0 mb-3 bg-light rounded-4 px-3 py-3 border border-light">
                                <div class="col-4 border-end">
                                    <small class="text-muted d-block fw-bold mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Gross</small>
                                    <div class="fw-bold" style="font-size: 0.9rem;">₹<?= number_format($gross, 0) ?></div>
                                </div>
                                <div class="col-4 border-end text-center">
                                    <small class="text-muted d-block fw-bold mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Deductions</small>
                                    <div class="fw-bold <?= $deductions > 0 ? 'text-danger' : 'text-muted opacity-50' ?>" style="font-size: 0.9rem;">
                                        <?= $deductions > 0 ? '-₹'.number_format($deductions, 0) : '₹0' ?>
                                    </div>
                                </div>
                                <div class="col-4 text-end">
                                    <small class="text-primary d-block fw-bold mb-1" style="font-size: 0.6rem; text-transform: uppercase;">Net</small>
                                    <div class="fw-bold text-primary" style="font-size: 1.1rem;">₹<?= number_format($net, 0) ?></div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="project_details.php?id=<?= $p['id'] ?>" class="btn-v2 btn-white-v2 w-100 shadow-none border" style="padding: 8px; font-size: 0.85rem; border-radius: 12px;">
                                    <i class="bi bi-eye"></i> View Full Details
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Nav Component -->
    <nav class="bottom-nav d-lg-none">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2"></i>
            <span>Home</span>
        </a>
        <a href="projects.php" class="bottom-nav-item">
            <i class="bi bi-folder"></i>
            <span>Cases</span>
        </a>
        <a href="my_earnings.php" class="bottom-nav-item active">
            <i class="bi bi-cash-stack"></i>
            <span>Earnings</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
