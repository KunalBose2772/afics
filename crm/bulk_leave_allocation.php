<?php
require_once 'app_init.php';
require_once 'auth.php';

// Only HR/Admin can access
if (!in_array($_SESSION['role'], ['super_admin', 'admin', 'hr', 'hr_manager'])) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Handle Bulk Allocation
if (isset($_POST['bulk_allocate'])) {
    $role = $_POST['role'];
    $casual_leave = (int)$_POST['casual_leave'];
    $sick_leave = (int)$_POST['sick_leave'];
    $earned_leave = (int)$_POST['earned_leave'];
    $action = $_POST['action']; // 'set' or 'add'
    
    try {
        // Get all users with the selected role
        $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE role = ?");
        $stmt->execute([$role]);
        $users = $stmt->fetchAll();
        
        if (count($users) > 0) {
            if ($action === 'set') {
                // Set (replace) leave balances
                $stmt = $pdo->prepare("UPDATE users SET 
                    casual_leave_balance = ?,
                    sick_leave_balance = ?,
                    earned_leave_balance = ?
                    WHERE role = ?");
                $stmt->execute([$casual_leave, $sick_leave, $earned_leave, $role]);
            } else {
                // Add to existing balances
                $stmt = $pdo->prepare("UPDATE users SET 
                    casual_leave_balance = casual_leave_balance + ?,
                    sick_leave_balance = sick_leave_balance + ?,
                    earned_leave_balance = earned_leave_balance + ?
                    WHERE role = ?");
                $stmt->execute([$casual_leave, $sick_leave, $earned_leave, $role]);
            }
            
            $affected = count($users);
            
            // Log the action if function exists
            if (function_exists('log_action')) {
                log_action('BULK_LEAVE_ALLOCATION', 
                    "Bulk allocation for role '$role': Casual=$casual_leave, Sick=$sick_leave, Earned=$earned_leave (Action: $action, Affected: $affected users)");
            }
            
            $success = "Successfully updated leave balances for $affected " . ucwords(str_replace('_', ' ', $role)) . "(s)!";
        } else {
            $error = "No users found with role: " . ucwords(str_replace('_', ' ', $role));
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Leave Policy Template Save
if (isset($_POST['save_policy'])) {
    $policy_name = $_POST['policy_name'];
    $policy_role = $_POST['policy_role'];
    $policy_casual = (int)$_POST['policy_casual'];
    $policy_sick = (int)$_POST['policy_sick'];
    $policy_earned = (int)$_POST['policy_earned'];
    
    try {
        // Save as setting
        $policy_data = json_encode([
            'name' => $policy_name,
            'role' => $policy_role,
            'casual' => $policy_casual,
            'sick' => $policy_sick,
            'earned' => $policy_earned
        ]);
        
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_group) 
                               VALUES (?, ?, 'leave_policy') 
                               ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute(["leave_policy_$policy_role", $policy_data, $policy_data]);
        
        if (function_exists('log_action')) {
            log_action('SAVE_LEAVE_POLICY', "Saved leave policy for role '$policy_role'");
        }
        
        $success = "Leave policy saved successfully!";
    } catch (PDOException $e) {
        $error = "Error saving policy: " . $e->getMessage();
    }
}

// Fetch all roles
$roles = $pdo->query("SELECT DISTINCT role FROM users WHERE role NOT IN ('super_admin', 'website_manager') ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);

// Fetch role statistics
$role_stats = [];
foreach ($roles as $r) {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as user_count,
        AVG(casual_leave_balance) as avg_casual,
        AVG(sick_leave_balance) as avg_sick,
        AVG(earned_leave_balance) as avg_earned,
        SUM(casual_leave_balance) as total_casual,
        SUM(sick_leave_balance) as total_sick,
        SUM(earned_leave_balance) as total_earned
        FROM users WHERE role = ?");
    $stmt->execute([$r]);
    $role_stats[$r] = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bulk Leave Allocation - Documantraa</title>
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
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Bulk Leave Allocation</h1>
                    <p class="text-muted mb-0 small">Set up leave balances for employees by role</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="leaves.php" class="btn-v2 btn-white-v2">
                         <i class="bi bi-arrow-left"></i> Back to Leaves
                    </a>
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#policyModal">
                        <i class="bi bi-gear-fill"></i> <span class="d-none d-md-inline ms-1">Leave Policies</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="background: var(--success-bg); color: var(--success-text); border: none; border-radius: var(--radius-md);">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" style="background: var(--danger-bg); color: var(--danger-text); border: none; border-radius: var(--radius-md);">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <!-- Role Statistics Grid -->
            <div class="row g-4 mb-4">
                <?php foreach ($role_stats as $r => $stats): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="app-card h-100">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="card-title-v2 mb-1"><?= ucwords(str_replace('_', ' ', htmlspecialchars($r))) ?></h5>
                                <span class="badge badge-v2 badge-process rounded-pill"><?= $stats['user_count'] ?> Employees</span>
                            </div>
                            <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; background: var(--bg-secondary); color: var(--primary);">
                                <i class="bi bi-people-fill"></i>
                            </div>
                        </div>
                        
                        <div class="row g-2 small mb-3">
                            <div class="col-4 text-center p-2 rounded" style="background: var(--bg-secondary);">
                                <div class="text-muted mb-1">Casual</div>
                                <div class="fw-bold text-info"><?= round($stats['avg_casual'], 1) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">avg</div>
                            </div>
                            <div class="col-4 text-center p-2 rounded" style="background: var(--bg-secondary);">
                                <div class="text-muted mb-1">Sick</div>
                                <div class="fw-bold text-warning"><?= round($stats['avg_sick'], 1) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">avg</div>
                            </div>
                            <div class="col-4 text-center p-2 rounded" style="background: var(--bg-secondary);">
                                <div class="text-muted mb-1">Earned</div>
                                <div class="fw-bold text-success"><?= round($stats['avg_earned'], 1) ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;">avg</div>
                            </div>
                        </div>
                        
                        <button class="btn-v2 btn-white-v2 w-100" onclick="selectRole('<?= htmlspecialchars($r) ?>')">
                            <i class="bi bi-pencil me-2"></i> Allocate
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Allocation Form -->
            <div class="app-card" id="allocationForm">
                <div class="card-header-v2 border-bottom">
                    <h5 class="card-title-v2 m-0"><i class="bi bi-sliders me-2"></i>Allocate Leaves</h5>
                </div>
                <div class="p-4">
                    <form method="POST" id="bulkAllocationForm">
                        <input type="hidden" name="bulk_allocate" value="1">
                        
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Select Role</label>
                                <select name="role" id="roleSelect" class="input-v2 form-select" required>
                                    <option value="">-- Select Role --</option>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= htmlspecialchars($r) ?>">
                                        <?= ucwords(str_replace('_', ' ', htmlspecialchars($r))) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Action Type</label>
                                <select name="action" class="input-v2 form-select" required>
                                    <option value="set">Set (Replace all balances)</option>
                                    <option value="add">Add (Add to existing balances)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-info">Casual Leave (Days)</label>
                                <input type="number" name="casual_leave" class="input-v2" min="0" value="10" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-warning">Sick Leave (Days)</label>
                                <input type="number" name="sick_leave" class="input-v2" min="0" value="12" required>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-success">Earned Leave (Days)</label>
                                <input type="number" name="earned_leave" class="input-v2" min="0" value="15" required>
                            </div>
                            
                            <div class="col-12">
                                <div class="alert alert-info d-flex gap-3 align-items-start" style="background: var(--bg-secondary); border: none;">
                                    <i class="bi bi-info-circle-fill text-primary mt-1"></i>
                                    <div>
                                        <strong>Note:</strong> This will update leave balances for <strong>all employees</strong> with the selected role immediately.
                                        <div id="affectedCount" class="mt-1"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 d-flex gap-3">
                                <button type="submit" class="btn-v2 btn-primary-v2 px-5">
                                    Apply Bulk Allocation
                                </button>
                                <button type="reset" class="btn-v2 btn-white-v2">
                                    Reset Form
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Leave Policy Modal -->
    <div class="modal fade" id="policyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2"><i class="bi bi-gear me-2"></i>Leave Policy Templates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="save_policy" value="1">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Policy Name</label>
                            <input type="text" name="policy_name" class="input-v2" placeholder="e.g., Standard Annual Leave Policy" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Apply to Role</label>
                            <select name="policy_role" class="input-v2 form-select" required>
                                <option value="">-- Select Role --</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>">
                                    <?= ucwords(str_replace('_', ' ', htmlspecialchars($r))) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-info">Casual Leave</label>
                                <input type="number" name="policy_casual" class="input-v2" min="0" value="10" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-warning">Sick Leave</label>
                                <input type="number" name="policy_sick" class="input-v2" min="0" value="12" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1 text-success">Earned Leave</label>
                                <input type="number" name="policy_earned" class="input-v2" min="0" value="15" required>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-v2 btn-primary-v2">
                                Save Policy
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav (Optional for Admin but good for consistency) -->
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
            <a href="leaves.php" class="bottom-nav-icon-main">
                <i class="bi bi-calendar-event"></i>
            </a>
        </div>
        <a href="attendance.php" class="bottom-nav-item">
            <i class="bi bi-calendar-check"></i>
            <span>Attend</span>
        </a>
        <a href="payroll.php" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Pay</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectRole(role) {
            document.getElementById('roleSelect').value = role;
            document.getElementById('allocationForm').scrollIntoView({ behavior: 'smooth' });
            updateAffectedCount();
        }

        // Real-time update of affected count
        document.getElementById('roleSelect').addEventListener('change', updateAffectedCount);

        function updateAffectedCount() {
            const role = document.getElementById('roleSelect').value;
            const countDiv = document.getElementById('affectedCount');
            
            if (role) {
                const roleStats = <?= json_encode($role_stats) ?>;
                const count = roleStats[role]?.user_count || 0;
                countDiv.innerHTML = `<span class="badge badge-v2 badge-process text-dark">${count} employee(s)</span> will be affected.`;
            } else {
                countDiv.innerHTML = '';
            }
        }

        // Confirmation before submission
        document.getElementById('bulkAllocationForm').addEventListener('submit', function(e) {
            const role = document.getElementById('roleSelect').value;
            const action = document.querySelector('[name="action"]').value;
            const casual = document.querySelector('[name="casual_leave"]').value;
            const sick = document.querySelector('[name="sick_leave"]').value;
            const earned = document.querySelector('[name="earned_leave"]').value;
            
            const roleStats = <?= json_encode($role_stats) ?>;
            const count = roleStats[role]?.user_count || 0;
            
            const actionText = action === 'set' ? 'SET (replace entirely)' : 'ADD TO existing';
            const message = `Are you sure you want to ${actionText} leave balances for ${count} ${role.replace('_', ' ')}(s)?\n\n` +
                          `Casual: ${casual}\n` +
                          `Sick: ${sick}\n` +
                          `Earned: ${earned}`;
            
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
