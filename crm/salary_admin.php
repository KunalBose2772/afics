<?php
require_once 'app_init.php';
if (!function_exists('has_permission')) {
    require_once 'auth.php';
}

require_permission('payroll');

$message = '';

// Handle Add/Deduct
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_entry'])) {
    $user_id = $_POST['user_id'];
    $type = $_POST['type']; // Incentive, Deduction, TA, Point
    $amount = $_POST['amount'];
    $desc = $_POST['description'];
    
    // Map UI type to DB enum: enum('Point','Incentive','Deduction','Allowance','Salary')
    $db_type = 'Incentive'; 
    $valid_types = ['Point', 'Incentive', 'Deduction', 'Allowance', 'Salary'];
    
    if (in_array($type, $valid_types)) {
        $db_type = $type;
    } elseif ($type == 'Food Allowance' || $type == 'TA' || $type == 'Printing Charge') {
        $db_type = 'Allowance'; // Group these as Allowance
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO salary_registry (user_id, entry_date, entry_type, amount, description, added_by) VALUES (?, NOW(), ?, ?, ?, ?)");
        $stmt->execute([$user_id, $db_type, $amount, $desc, $_SESSION['user_id']]);
        
        // Log action
        if(function_exists('log_action')) {
            log_action('SALARY_REGISTRY_ADD', "Added $db_type of $amount to User ID $user_id");
        }

        header('Location: salary_admin.php?success=1');
        exit;
    } catch (Exception $e) {
        $message = $e->getMessage();
    }
}

// Fetch Users and Logs
$users = [];
$logs = [];

try {
    // Fetch Users
    $users = $pdo->query("SELECT id, full_name, role FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();

    // Fetch Recent Logs
    $logs = $pdo->query("SELECT s.*, u.full_name FROM salary_registry s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 50")->fetchAll();
} catch (Exception $e) {
    // Check if error is "Table doesn't exist"
    if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "no such table") !== false) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS salary_registry (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                entry_date DATETIME NOT NULL,
                entry_type VARCHAR(50) NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                description TEXT,
                added_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )");
            // Retry fetch
             $users = $pdo->query("SELECT id, full_name, role FROM users WHERE role != 'admin' ORDER BY full_name")->fetchAll();
             $logs = [];
        } catch (Exception $ex) {
             $message = "Database Error: " . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Salary Registry - Documantraa</title>
    
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

    <!-- Sidebar (Mobile & Desktop) -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Salary Registry</h1>
                    <p class="text-muted mb-0 small">Manage points, incentives, and deductions</p>
                </div>
                <div>
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                        <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline ms-1">Add Entry</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle me-2"></i> Entry added successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Staff Summary (Current Month) -->
            <div class="app-card mb-4 overflow-hidden">
                <div class="card-header-v2 border-bottom bg-light">
                    <h5 class="card-title-v2 m-0"><i class="bi bi-people me-2"></i>Staff Summary (<?= date('F Y') ?>)</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="bg-light">
                            <tr>
                                <th class="py-2 ps-3">Staff Member</th>
                                <th class="py-2 text-center">Staff Type</th>
                                <th class="py-2 text-center">Points Earned</th>
                                <th class="py-2 text-center">Manual Salary</th>
                                <th class="py-2 text-end pe-3">Target</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $month = date('n');
                            $year = date('Y');
                            $summary_stmt = $pdo->prepare("
                                SELECT u.full_name, u.staff_type, u.target_points,
                                       SUM(CASE WHEN s.entry_type = 'Point' THEN s.amount ELSE 0 END) as earned_points,
                                       SUM(CASE WHEN s.entry_type = 'Salary' THEN s.amount ELSE 0 END) as manual_salary
                                FROM users u
                                LEFT JOIN salary_registry s ON u.id = s.user_id AND MONTH(s.entry_date) = ? AND YEAR(s.entry_date) = ?
                                WHERE u.role != 'admin'
                                GROUP BY u.id
                                ORDER BY u.full_name ASC
                            ");
                            $summary_stmt->execute([$month, $year]);
                            $summaries = $summary_stmt->fetchAll();
                            
                            foreach ($summaries as $sm):
                                $p_class = ($sm['earned_points'] >= $sm['target_points'] && $sm['target_points'] > 0) ? 'text-success' : 'text-main';
                            ?>
                            <tr>
                                <td class="py-2 ps-3 fw-bold"><?= htmlspecialchars($sm['full_name']) ?></td>
                                <td class="py-2 text-center small text-muted"><?= $sm['staff_type'] ?></td>
                                <td class="py-2 text-center fw-bold <?= $p_class ?>"><?= number_format($sm['earned_points'], 1) ?></td>
                                <td class="py-2 text-center text-primary">₹<?= number_format($sm['manual_salary'], 2) ?></td>
                                <td class="py-2 text-end pe-3 text-muted"><?= $sm['target_points'] ?> pts</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="app-card overflow-hidden">
                <div class="card-header-v2 border-bottom">
                    <h5 class="card-title-v2 m-0"><i class="bi bi-clock-history me-2"></i>Recent Transactions</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th class="py-3 ps-3 text-secondary fw-normal">Date</th>
                                <th class="py-3 text-secondary fw-normal">Employee</th>
                                <th class="py-3 text-secondary fw-normal">Type</th>
                                <th class="py-3 text-secondary fw-normal">Amount</th>
                                <th class="py-3 text-secondary fw-normal">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="p-5 text-center text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 opacity-25"></i>
                                    No records found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($logs as $log): 
                                $is_deduct = ($log['entry_type'] == 'Deduction');
                                $is_point = ($log['entry_type'] == 'Point');
                                $is_salary = ($log['entry_type'] == 'Salary');
                                
                                $colorClass = $is_deduct ? 'text-danger' : ($is_point ? 'text-info' : ($is_salary ? 'text-primary' : 'text-success'));
                                $bgClass = $is_deduct ? 'bg-danger-subtle text-danger' : ($is_point ? 'bg-info-subtle text-info' : ($is_salary ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success'));
                                $sign = $is_deduct ? '-' : '+';
                            ?>
                            <tr>
                                <td class="py-3 ps-3">
                                    <div class="fw-medium"><?= date('d M Y', strtotime($log['entry_date'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($log['created_at'])) ?></small>
                                </td>
                                <td class="py-3">
                                    <div class="fw-bold text-main"><?= htmlspecialchars($log['full_name'] ?? 'Unknown') ?></div>
                                </td>
                                <td class="py-3">
                                    <span class="badge rounded-pill <?= $bgClass ?>" style="font-weight: 500; font-size: 0.75rem;">
                                        <?= $log['entry_type'] ?>
                                    </span>
                                </td>
                                <td class="py-3 fw-bold <?= $colorClass ?>">
                                    <?= $sign . number_format($log['amount'], 2) ?>
                                </td>
                                <td class="py-3 text-muted small">
                                    <?= htmlspecialchars($log['description']) ?>
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

    <!-- Add Entry Modal -->
    <div class="modal fade" id="addEntryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2"><i class="bi bi-plus-circle me-2"></i>Add Manual Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="add_entry" value="1">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Employee</label>
                            <select name="user_id" class="input-v2 w-100" required>
                                <option value="">Select Employee</option>
                                <?php foreach($users as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= ucfirst($u['role']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Entry Type</label>
                            <select name="type" class="input-v2 w-100" required>
                                <option value="Point">Add Points (Target Base)</option>
                                <option value="Salary">Monthly Basic Salary (Permanent)</option>
                                <option value="Incentive">Incentive (Cash)</option>
                                <option value="Food Allowance">Food Allowance</option>
                                <option value="TA">Travel Allowance (TA)</option>
                                <option value="Printing Charge">Printing / Parcel Charge</option>
                                <option value="Deduction">Deduction (Penalty/Advance)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Value / Amount</label>
                            <input type="number" step="0.01" name="amount" class="input-v2 w-100" placeholder="0.00" required>
                        </div>

                        <div class="mb-3">
                            <label class="stat-label mb-1">Remark / Description</label>
                            <input type="text" name="description" class="input-v2 w-100" placeholder="e.g. Extra visit points, or Advance payment">
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">
                            Add Entry
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
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
            <a href="#" class="bottom-nav-icon-main" data-bs-toggle="modal" data-bs-target="#addEntryModal">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="payroll.php" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Payroll</span>
        </a>
        <a href="salary_admin.php" class="bottom-nav-item active">
            <i class="bi bi-cash-stack"></i>
            <span>Registry</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
