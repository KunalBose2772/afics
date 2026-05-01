<?php
require_once 'app_init.php';
if (!function_exists('has_permission')) {
    require_once 'auth.php';
}

require_permission('payroll');

// Update Payroll (Incentives & Deductions)
if (isset($_POST['update_payroll'])) {
    $errors = [];
    $payroll_id = intval($_POST['payroll_id']);
    $incentives = floatval($_POST['incentives'] ?? 0);
    $deductions = floatval($_POST['deductions'] ?? 0);
    $advance = floatval($_POST['advance'] ?? 0);
    
    // Get current payroll record
    $stmt = $pdo->prepare("SELECT * FROM payroll WHERE id = ?");
    $stmt->execute([$payroll_id]);
    $current = $stmt->fetch();
    
    if ($current) {
        // Recalculate net salary: basic + TA + incentives - deductions - advance
        $net_salary = $current['basic_salary'] + $current['travel_allowance'] + $incentives - $deductions - $advance;
        
        // Update payroll
        $stmt = $pdo->prepare("UPDATE payroll SET incentives = ?, deductions = ?, advance = ?, net_salary = ? WHERE id = ?");
        $stmt->execute([$incentives, $deductions, $advance, $net_salary, $payroll_id]);
        
        // Log action (if available)
        if(function_exists('log_action')) {
            log_action('UPDATE_PAYROLL', "Updated payroll ID: $payroll_id - Incentives: ₹$incentives, Deductions: ₹$deductions");
        }
        header('Location: payroll.php?updated=1');
        exit;
    } else {
        $errors[] = "Payroll record not found.";
    }
}

// Approve Payroll
if (isset($_POST['approve_payroll'])) {
    $payroll_id = $_POST['payroll_id'];
    
    // Fetch details for notification
    require_once '../includes/functions.php';
    $stmt = $pdo->prepare("SELECT p.*, u.email, u.full_name FROM payroll p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
    $stmt->execute([$payroll_id]);
    $pay_record = $stmt->fetch();

    $stmt = $pdo->prepare("UPDATE payroll SET status = 'Paid' WHERE id = ?");
    $stmt->execute([$payroll_id]);
    
    // Notify Employee
    if ($pay_record) {
        $month_name = date('F', mktime(0, 0, 0, $pay_record['month'], 1));
        $template = get_email_template($pdo, 'payroll_paid', [
            'employee_name' => $pay_record['full_name'],
            'month_year' => "$month_name " . $pay_record['year'],
            'amount' => number_format($pay_record['net_salary'], 2)
        ]);
        
        $subject = "Salary Disbursed: $month_name " . $pay_record['year'];
        $body = "Your salary has been paid.";
        
        if ($template) {
            $subject = $template['subject'];
            $body = $template['body'];
        }
        
        queue_email($pdo, $pay_record['email'], $subject, $body, $_SESSION['user_id']);
    }
    if(function_exists('log_action')) {
         log_action('APPROVE_PAYROLL', "Approved payroll ID: $payroll_id");
    }
    header('Location: payroll.php?approved=1');
    exit;
}

// Delete Orphaned Record
if (isset($_GET['delete_orphaned'])) {
    $payroll_id = (int)$_GET['delete_orphaned'];
    
    // Safety check: only allow if user_id doesn't exist in users table
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = (SELECT user_id FROM payroll WHERE id = ?)");
    $stmt->execute([$payroll_id]);
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("DELETE FROM payroll WHERE id = ?");
        $stmt->execute([$payroll_id]);
        if(function_exists('log_action')) {
            log_action('DELETE_ORPHANED_PAYROLL', "Deleted orphaned payroll ID: $payroll_id");
        }
        header('Location: payroll.php?deleted=1' . (isset($_GET['status']) ? '&status=' . $_GET['status'] : ''));
        exit;
    }
}

// Bulk Approve
if (isset($_POST['bulk_approve'])) {
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Fetch pending payrolls for notification
    require_once '../includes/functions.php';
    $stmt = $pdo->prepare("SELECT p.*, u.email, u.full_name FROM payroll p JOIN users u ON p.user_id = u.id WHERE p.month = ? AND p.year = ? AND p.status = 'Pending'");
    $stmt->execute([$month, $year]);
    $pending_payrolls = $stmt->fetchAll();
    
    // Update status
    $stmt = $pdo->prepare("UPDATE payroll SET status = 'Paid' WHERE month = ? AND year = ? AND status = 'Pending'");
    $stmt->execute([$month, $year]);
    
    // Loop and Notify
    $month_name = date('F', mktime(0, 0, 0, $month, 1));
    foreach ($pending_payrolls as $record) {
        $template = get_email_template($pdo, 'payroll_paid', [
            'employee_name' => $record['full_name'],
            'month_year' => "$month_name $year",
            'amount' => number_format($record['net_salary'], 2)
        ]);
        
        $subject = "Salary Disbursed: $month_name $year";
        $body = "Your salary has been paid.";
        
        if ($template) {
            $subject = $template['subject'];
            $body = $template['body'];
        }
        
        queue_email($pdo, $record['email'], $subject, $body, $_SESSION['user_id']);
    }
    if(function_exists('log_action')) {
        log_action('BULK_APPROVE_PAYROLL', "Bulk approved payroll for $month/$year");
    }
    header('Location: payroll.php?bulk_approved=1');
    exit;
}

// Generate Payroll Logic
if (isset($_POST['generate_payroll'])) {
    $month = (int)$_POST['month'];
    $year = (int)$_POST['year'];
    
    // Get all eligible users (those with either a base salary or target points)
    $users = $pdo->query("SELECT * FROM users WHERE base_salary > 0 OR salary_base > 0")->fetchAll();
    
    $generated_count = 0;
    foreach ($users as $user) {
        $uid = $user['id'];

        // 1. Calculate Attendance
        $present_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND MONTH(date) = ? AND YEAR(date) = ? AND status = 'Present'");
        $present_stmt->execute([$uid, $month, $year]);
        $present_count = $present_stmt->fetchColumn();
        
        // 2. Fetch Registry Data (Points, Incentives, Deductions, Manual Salary)
        $reg_stmt = $pdo->prepare("SELECT entry_type, SUM(amount) as total FROM salary_registry WHERE user_id = ? AND MONTH(entry_date) = ? AND YEAR(entry_date) = ? GROUP BY entry_type");
        $reg_stmt->execute([$uid, $month, $year]);
        $registry = $reg_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $earned_points = floatval($registry['Point'] ?? 0);
        $manual_salary = floatval($registry['Salary'] ?? 0);
        $incentives = floatval($registry['Incentive'] ?? 0);
        $deductions = floatval($registry['Deduction'] ?? 0);
        $allowances = floatval($registry['Allowance'] ?? 0);
        
        // 3. Calculate TA from field visits
        $ta_stmt = $pdo->prepare("SELECT SUM(travel_allowance) as total_ta FROM field_visits WHERE user_id = ? AND status = 'Approved' AND MONTH(visit_date) = ? AND YEAR(visit_date) = ? AND ta_paid = 0");
        $ta_stmt->execute([$uid, $month, $year]);
        $total_ta = floatval($ta_stmt->fetchColumn() ?? 0) + $allowances;
        
        // 4. Determine Basic Salary
        if ($manual_salary > 0) {
            // Priority 1: Manual Salary Entry
            $basic_salary = $manual_salary;
        } elseif ($user['staff_type'] == 'Permanent' && $user['target_points'] > 0) {
            // Priority 2: Point-Based Target Salary (Permanent Staff)
            // Logic: (Earned / Target) * Base, capped at Base (or let it go higher if incentive?)
            // User says "130 point 16k Salary"
            $target = $user['target_points'];
            $base = $user['salary_base'] ?: 16000;
            
            if ($earned_points >= $target) {
                $basic_salary = $base;
                // Maybe extra points as incentive?
                if ($earned_points > $target) {
                    $extra = $earned_points - $target;
                    $incentives += ($extra * ($base / $target)); // Pro-rata bonus
                }
            } else {
                $basic_salary = ($earned_points / $target) * $base;
            }
        } else {
            // Priority 3: Standard Attendance-based Salary
            $daily_rate = $user['base_salary'] / 30;
            $basic_salary = $daily_rate * $present_count;
        }
        
        $advance = 0; // Advance is usually a deduction already in registry
        $net_salary = $basic_salary + $total_ta + $incentives - $deductions;
        
        // Check if already generated
        $exists = $pdo->prepare("SELECT id FROM payroll WHERE user_id = ? AND month = ? AND year = ?");
        $exists->execute([$uid, $month, $year]);
        
        if (!$exists->fetchColumn()) {
            $stmt = $pdo->prepare("INSERT INTO payroll (user_id, month, year, basic_salary, travel_allowance, incentives, deductions, advance, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $stmt->execute([$uid, $month, $year, $basic_salary, $total_ta, $incentives, $deductions, $advance, $net_salary]);
            
            // Mark TAs as paid
            $mark_ta = $pdo->prepare("UPDATE field_visits SET ta_paid = 1 WHERE user_id = ? AND MONTH(visit_date) = ? AND YEAR(visit_date) = ? AND status = 'Approved' AND ta_paid = 0");
            $mark_ta->execute([$uid, $month, $year]);

            // Notify Employee
            require_once '../includes/functions.php';
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
            $payslip_link = $protocol . $_SERVER['HTTP_HOST'] . '/documantraa/crm/my_payslips';
            $month_name = date('F', mktime(0, 0, 0, $month, 1));
            
            $template = get_email_template($pdo, 'payroll_generated', [
                'employee_name' => $user['full_name'],
                'month_year' => "$month_name $year",
                'payslip_link' => $payslip_link
            ]);
            
            $subject = "Payslip Available: $month_name $year";
            $body = "Your payslip for $month_name $year is available.";
            if ($template) {
                $subject = $template['subject'];
                $body = $template['body'];
            }
            queue_email($pdo, $user['email'], $subject, $body, $_SESSION['user_id']);
            
            $generated_count++;
        }
    }
    
    if(function_exists('log_action')) {
        log_action('GENERATE_PAYROLL', "Generated payroll for $generated_count employees for $month/$year");
    }
    header("Location: payroll.php?success=$generated_count&month=$month&year=$year");
    exit;
}

// Get filter parameters
$filter_month = isset($_GET['filter_month']) ? (int)$_GET['filter_month'] : date('n');
$filter_year = isset($_GET['filter_year']) ? (int)$_GET['filter_year'] : date('Y');
$show_pending = (isset($_GET['status']) && $_GET['status'] === 'Pending');

// Fetch Payroll Records
if ($show_pending) {
    $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email FROM payroll p LEFT JOIN users u ON p.user_id = u.id WHERE p.status = 'Pending' ORDER BY p.year DESC, p.month DESC, u.full_name ASC");
    $stmt->execute([]);
} else {
    $stmt = $pdo->prepare("SELECT p.*, u.full_name, u.email FROM payroll p LEFT JOIN users u ON p.user_id = u.id WHERE p.month = ? AND p.year = ? ORDER BY u.full_name ASC");
    $stmt->execute([$filter_month, $filter_year]);
}
$payrolls = $stmt->fetchAll();

// Get statistics
if ($show_pending) {
    $stats = $pdo->prepare("SELECT 
        COUNT(*) as total,
        COUNT(*) as pending,
        0 as paid,
        SUM(net_salary) as total_amount
        FROM payroll WHERE status = 'Pending'");
    $stats->execute([]);
} else {
    $stats = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid,
        SUM(net_salary) as total_amount
        FROM payroll WHERE month = ? AND year = ?");
    $stats->execute([$filter_month, $filter_year]);
}
$payroll_stats = $stats->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Payroll - Documantraa</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        @media (max-width: 767.98px) {
            .stat-small-card .stat-value { font-size: 1.5rem !important; }
            .stat-small-card .stat-label { font-size: 0.7rem !important; }
            /* Removed global text hiding rule that broke text-only buttons */
        }
    </style>
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

    <!-- Sidebar (Mobile & Desktop) -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);"><?= $show_pending ? 'Pending Payrolls' : 'Payroll Management' ?></h1>
                    <p class="text-muted mb-0 small"><?= $show_pending ? 'Viewing pending records' : 'Manage employee salaries' ?></p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-v2 btn-white-v2" data-bs-toggle="modal" data-bs-target="#generateModal">
                        <i class="bi bi-lightning-charge"></i> <span class="d-none d-md-inline ms-1">Generate</span>
                    </button>
                    
                    <?php if (!empty($payrolls) && $payroll_stats['pending'] > 0): ?>
                    <button class="btn-v2 btn-success-v2 bg-success text-white border-0" data-bs-toggle="modal" data-bs-target="#bulkApproveModal">
                        <i class="bi bi-check-all"></i> <span class="d-none d-md-inline ms-1">Approve All</span>
                    </button>
                    <?php endif; ?>
                    
                    <button onclick="downloadBulkPayslips(<?= $filter_month ?>, <?= $filter_year ?>)" class="btn-v2 btn-primary-v2" id="downloadAllBtn">
                        <i class="bi bi-file-earmark-pdf"></i> <span class="d-none d-md-inline ms-1">Download All</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?= render_form_errors($errors ?? []) ?>
            <!-- Filter Section -->
            <div class="app-card mb-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 gap-3">
                    <form method="GET" class="w-100 w-md-auto">
                        <?php if ($show_pending): ?>
                            <a href="payroll.php" class="btn-v2 btn-white-v2 px-3 w-100 w-md-auto">
                                <i class="bi bi-arrow-left me-1"></i> Show Current Month
                            </a>
                        <?php else: ?>
                            <div class="d-flex gap-2 w-100">
                                <div class="flex-grow-1 flex-md-grow-0">
                                    <select name="filter_month" class="input-v2 py-2 w-100" onchange="this.form.submit()">
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                        <option value="<?= $m ?>" <?= $m == $filter_month ? 'selected' : '' ?>>
                                            <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                        </option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="flex-grow-1 flex-md-grow-0">
                                    <select name="filter_year" class="input-v2 py-2 w-100" onchange="this.form.submit()">
                                        <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                        <option value="<?= $y ?>" <?= $y == $filter_year ? 'selected' : '' ?>><?= $y ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                        <?php endif; ?>
                    </form>

                    <?php if (!$show_pending): ?>
                        <div class="w-100 w-md-auto">
                            <a href="?status=Pending" class="btn-v2 btn-white-v2 d-flex align-items-center justify-content-center justify-content-md-start gap-2 w-100 w-md-auto">
                                <span>View All Pending</span>
                                <span class="badge bg-warning text-dark rounded-pill px-2"><?= $payroll_stats['pending'] ?? 0 ?></span>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Total Records</div>
                        <div class="stat-value text-primary"><?= $payroll_stats['total'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Pending</div>
                        <div class="stat-value text-warning"><?= $payroll_stats['pending'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Processed (Paid)</div>
                        <div class="stat-value text-success"><?= $payroll_stats['paid'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Total Amount</div>
                        <div class="stat-value text-info">₹<?= number_format($payroll_stats['total_amount'] ?? 0) ?></div>
                    </div>
                </div>
            </div>
            <!-- Payroll Table -->
             <div class="app-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th class="py-3 ps-3 text-secondary fw-normal">Employee</th>
                                <th class="py-3 text-secondary fw-normal">Basic</th>
                                <th class="py-3 text-secondary fw-normal">Inc/Ded</th>
                                <th class="py-3 text-secondary fw-normal">Net Salary</th>
                                <th class="py-3 text-secondary fw-normal">Status</th>
                                <th class="py-3 pe-3 text-end text-secondary fw-normal">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payrolls)): ?>
                            <tr>
                                <td colspan="6" class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    No records found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($payrolls as $record): ?>
                            <tr>
                                <td class="py-3 ps-3">
                                    <div class="fw-bold text-main"><?= htmlspecialchars($record['full_name'] ?? 'Unknown User') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($record['email'] ?? 'N/A') ?></small>
                                </td>
                                <td class="py-3">₹<?= number_format($record['basic_salary']) ?></td>
                                <td class="py-3">
                                    <div class="text-success small">+₹<?= number_format($record['travel_allowance'] + $record['incentives']) ?></div>
                                    <div class="text-danger small">-₹<?= number_format($record['deductions'] + $record['advance']) ?></div>
                                </td>
                                <td class="py-3 fw-bold text-success">₹<?= number_format($record['net_salary']) ?></td>
                                <td class="py-3">
                                    <?php 
                                        $statusClass = $record['status'] === 'Paid' ? 'badge-success' : 'badge-warning';
                                    ?>
                                    <span class="badge-v2 <?= $statusClass ?>"><?= $record['status'] ?></span>
                                </td>
                                <td class="py-3 pe-3 text-end">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button onclick="downloadSinglePayslip(<?= $record['id'] ?>, this)" class="btn-v2 btn-white-v2 p-1 px-2" title="Download">
                                            <i class="bi bi-download"></i>
                                        </button>
                                        <?php if ($record['status'] === 'Pending' && !empty($record['full_name'])): ?>
                                        <button class="btn-v2 btn-primary-v2 p-1 px-2" onclick="editPayroll(<?= $record['id'] ?>, '<?= htmlspecialchars($record['full_name']) ?>', <?= $record['incentives'] ?>, <?= $record['deductions'] ?>, <?= $record['advance'] ?>)" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                         </button>
                                         <button class="btn-v2 btn-success-v2 bg-success text-white p-1 px-2 border-0" onclick="approvePayroll(<?= $record['id'] ?>)" title="Approve">
                                             <i class="bi bi-check-lg"></i>
                                         </button>
                                         <?php elseif ($record['status'] === 'Pending'): ?>
                                         <a href="?delete_orphaned=<?= $record['id'] ?>&status=Pending" class="btn-v2 btn-white-v2 text-danger p-1 px-2" onclick="return confirm('Delete this orphaned record?')" title="Delete">
                                             <i class="bi bi-trash"></i>
                                         </a>
                                         <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Generate Payroll Modal -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2"><i class="bi bi-lightning-charge me-2"></i>Generate Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="generate_payroll" value="1">
                        
                        <div class="alert alert-info small mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            Generates payroll for all eligible employees based on attendance and TAs.
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="stat-label mb-1">Month</label>
                                <select name="month" class="input-v2 w-100" required>
                                    <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1">Year</label>
                                <select name="year" class="input-v2 w-100" required>
                                    <?php for ($y = date('Y'); $y >= date('Y') - 2; $y--): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Payroll Modal -->
    <div class="modal fade" id="editPayrollModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2"><i class="bi bi-pencil me-2"></i>Edit Payroll</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="update_payroll" value="1">
                        <input type="hidden" name="payroll_id" id="editPayrollId">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Employee</label>
                            <input type="text" id="editEmployeeName" class="input-v2 w-100" readonly style="background: var(--bg-secondary);">
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Incentives / Bonus (₹)</label>
                            <input type="number" step="0.01" min="0" name="incentives" id="editIncentives" class="input-v2 w-100" placeholder="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Advance (₹)</label>
                            <input type="number" step="0.01" min="0" name="advance" id="editAdvance" class="input-v2 w-100" placeholder="0.00">
                        </div>

                        <div class="mb-3">
                            <label class="stat-label mb-1">Deductions (₹)</label>
                            <input type="number" step="0.01" min="0" name="deductions" id="editDeductions" class="input-v2 w-100" placeholder="0.00">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bulk Approve Modal -->
    <div class="modal fade" id="bulkApproveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2 text-success"><i class="bi bi-check-all me-2"></i>Bulk Approve</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="bulk_approve" value="1">
                        <input type="hidden" name="month" value="<?= $filter_month ?>">
                        <input type="hidden" name="year" value="<?= $filter_year ?>">
                        
                        <div class="alert alert-warning mb-3">
                            This will approve all <strong><?= $payroll_stats['pending'] ?></strong> pending records.
                        </div>
                        
                        <div class="text-center">
                            <div class="text-muted small uppercase">Total Amount</div>
                            <div class="fs-2 fw-bold text-main">₹<?= number_format($payroll_stats['total_amount'] ?? 0) ?></div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-success-v2 bg-success text-white border-0">
                            Approve All
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Approve Form (Hidden) -->
    <form id="approveForm" method="POST" style="display: none;">
        <input type="hidden" name="approve_payroll" value="1">
        <input type="hidden" name="payroll_id" id="approvePayrollId">
    </form>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="attendance.php" class="bottom-nav-item">
            <i class="bi bi-calendar-check"></i>
            <span>Attend</span>
        </a>
        <a href="payroll.php" class="bottom-nav-item active">
            <i class="bi bi-credit-card-fill"></i>
            <span>Payroll</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
    async function downloadBulkPayslips(month, year) {
        const btn = document.getElementById('downloadAllBtn');
        await generatePDFsFromURL(`../get_bulk_payslips_admin.php?month=${month}&year=${year}&t=${new Date().getTime()}`, btn, true);
    }

    async function downloadSinglePayslip(id, btn) {
        await generatePDFsFromURL(`../get_single_payslip.php?id=${id}&t=${new Date().getTime()}`, btn, false);
    }

    // Unified PDF Generator Function
    async function generatePDFsFromURL(url, btnElement, isZip) {
        const originalText = btnElement.innerHTML;
        
        try {
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>';
            btnElement.classList.add('disabled');
            
            // Fetch Data
            const response = await fetch(url);
            if (!response.ok) throw new Error("Failed to fetch data");
            
            const data = await response.json();
            if (!data || data.length === 0) {
                alert("No payslip data found.");
                return;
            }

            // Create Overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:#333;z-index:999999;display:flex;justify-content:center;align-items:center;overflow:auto;';
            document.body.appendChild(overlay);

            // Container for rendering
            const container = document.createElement('div');
            container.style.cssText = 'background:#fff;width:700px;padding:20px;box-shadow:0 0 20px rgba(0,0,0,0.5);';
            overlay.appendChild(container); // Temporarily commented out to hide rendering if preferred

            const zip = isZip ? new JSZip() : null;
            const folder = isZip ? zip.folder("Payslips") : null;

            for (let i = 0; i < data.length; i++) {
                const item = data[i];
                if (isZip) btnElement.innerHTML = `<span class="spinner-border spinner-border-sm"></span> ${i+1}/${data.length}`;
                
                // Inject and Render
                container.innerHTML = item.html_content;
                await new Promise(resolve => setTimeout(resolve, 200)); // Render delay

                const opt = {
                    margin:       10, // mm
                    filename:     item.filename,
                    image:        { type: 'jpeg', quality: 0.98 },
                    html2canvas:  { scale: 1.5, useCORS: true, logging: false, scrollX: 0, scrollY: 0 },
                    jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                if (!isZip) {
                    await html2pdf().set(opt).from(container).save();
                } else {
                    const pdfBlob = await html2pdf().set(opt).from(container).output('blob');
                    folder.file(item.filename, pdfBlob);
                }
            }
            
            document.body.removeChild(overlay);
            
            if (isZip) {
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Zip...';
                const zipContent = await zip.generateAsync({type: "blob"});
                saveAs(zipContent, "Payslips_Bundle_" + new Date().toISOString().slice(0,10) + ".zip");
            }

        } catch (error) {
            console.error(error);
            alert("Error: " + error.message);
            const overlay = document.querySelector('div[style*="z-index:999999"]');
            if(overlay) document.body.removeChild(overlay);
        } finally {
            btnElement.innerHTML = originalText;
            btnElement.classList.remove('disabled');
        }
    }

    function approvePayroll(id) {
        if (confirm('Are you sure you want to approve this payroll?')) {
            document.getElementById('approvePayrollId').value = id;
            document.getElementById('approveForm').submit();
        }
    }
    
    function editPayroll(id, employeeName, incentives, deductions, advance) {
        document.getElementById('editPayrollId').value = id;
        document.getElementById('editEmployeeName').value = employeeName;
        document.getElementById('editIncentives').value = incentives;
        document.getElementById('editDeductions').value = deductions;
        document.getElementById('editAdvance').value = advance;
        
        var modal = new bootstrap.Modal(document.getElementById('editPayrollModal'));
        modal.show();
    }
    </script>
</body>
</html>
