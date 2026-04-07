<?php
require_once 'app_init.php';
if (!function_exists('has_permission')) {
    require_once 'auth.php';
}

require_permission('leaves');

// Handle Approve/Reject
if (isset($_POST['review_leave'])) {
    if (!in_array($_SESSION['role'], ['super_admin', 'admin', 'hr_manager'])) {
        die("Unauthorized access");
    }

    $leave_id = $_POST['leave_id'];
    $new_status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? null;
    
    $stmt = $pdo->prepare("UPDATE leaves SET status = ?, reviewed_by = ?, reviewed_at = NOW(), remarks = ? WHERE id = ?");
    $stmt->execute([$new_status, $_SESSION['user_id'], $remarks, $leave_id]);
    
    // Update user's leave balance if approved
    if ($new_status == 'Approved') {
        $leave = $pdo->query("SELECT * FROM leaves WHERE id = $leave_id")->fetch();
        $column_map = [
            'Casual' => 'casual_leave_balance',
            'Sick' => 'sick_leave_balance',
            'Earned' => 'earned_leave_balance'
        ];
        
        if (isset($leave['leave_type']) && isset($column_map[$leave['leave_type']])) {
            $balance_col = $column_map[$leave['leave_type']];
            $stmt = $pdo->prepare("UPDATE users SET $balance_col = $balance_col - ? WHERE id = ?");
            $stmt->execute([$leave['days_count'], $leave['user_id']]);
        }
    }
    
    // Fetch leave details for email
    if (!isset($leave) || !$leave) {
        $stmt = $pdo->prepare("SELECT l.*, u.email, u.full_name FROM leaves l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
        $stmt->execute([$leave_id]);
        $leave = $stmt->fetch();
    }
    
    // Queue Notification Email
    if ($_SESSION['role'] !== 'super_admin' && $leave) {
        require_once '../includes/functions.php';

        $subject = "Leave Request " . $new_status;
        $body = "Dear " . htmlspecialchars($leave['full_name']) . ",<br><br>" .
                "Your leave request has been <strong>" . $new_status . "</strong>.<br><br>" .
                "Remarks: " . htmlspecialchars($remarks ?? 'None');

        $template = get_email_template($pdo, 'leave_decision', [
            'employee_name' => $leave['full_name'],
            'start_date' => date('d M Y', strtotime($leave['start_date'])),
            'end_date' => date('d M Y', strtotime($leave['end_date'])),
            'status' => $new_status,
            'remarks' => htmlspecialchars($remarks ?? 'None')
        ]);

        if ($template) {
            $subject = $template['subject'];
            $body = $template['body'];
        }
            
        queue_email($pdo, $leave['email'], $subject, $body, $_SESSION['user_id']);
    }

    if(function_exists('log_action')) {
         log_action('REVIEW_LEAVE', "Leave ID: $leave_id - Status: $new_status");
    }
    header('Location: leaves.php?reviewed=1');
    exit;
}

// Handle Manual Leave Addition (Admin)
if (isset($_POST['add_leave'])) {
    $user_id = $_POST['user_id'];
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
    
    // Check overlapping
    $overlap_stmt = $pdo->prepare("SELECT COUNT(*) FROM leaves WHERE user_id = ? AND status != 'Rejected' AND start_date <= ? AND end_date >= ?");
    $overlap_stmt->execute([$user_id, $end_date, $start_date]);
    
    if ($overlap_stmt->fetchColumn() > 0) {
        header('Location: leaves.php?error=' . urlencode('Leave overlap detected'));
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, days_count, reason, status, reviewed_by, reviewed_at) VALUES (?, ?, ?, ?, ?, ?, 'Approved', ?, NOW())");
    $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $days, $reason, $_SESSION['user_id']]);
    
    // Deduct
    $column_map = [
        'Casual' => 'casual_leave_balance',
        'Sick' => 'sick_leave_balance',
        'Earned' => 'earned_leave_balance'
    ];
    
    if (isset($column_map[$leave_type])) {
        $balance_col = $column_map[$leave_type];
        $stmt = $pdo->prepare("UPDATE users SET $balance_col = $balance_col - ? WHERE id = ?");
        $stmt->execute([$days, $user_id]);
    }
    
    if(function_exists('log_action')) {
        log_action('ADD_LEAVE', "Added leave for User ID: $user_id");
    }
    header('Location: leaves.php?added=1');
    exit;
}

// Fetch leaves
$isAdmin = in_array($_SESSION['role'], ['super_admin', 'admin', 'hr_manager']);
if ($isAdmin) {
    $leaves = $pdo->query("SELECT l.*, u.full_name, u.email, r.full_name as reviewed_by_name FROM leaves l JOIN users u ON l.user_id = u.id LEFT JOIN users r ON l.reviewed_by = r.id ORDER BY l.applied_at DESC")->fetchAll();
    
    $pending = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending'")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Approved'")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Rejected'")->fetchColumn();
} else {
    $user_id = $_SESSION['user_id'];
    $leaves = $pdo->query("SELECT l.*, u.full_name, u.email, r.full_name as reviewed_by_name FROM leaves l JOIN users u ON l.user_id = u.id LEFT JOIN users r ON l.reviewed_by = r.id WHERE l.user_id = $user_id ORDER BY l.applied_at DESC")->fetchAll();
    
    $pending = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Pending' AND user_id = $user_id")->fetchColumn();
    $approved = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Approved' AND user_id = $user_id")->fetchColumn();
    $rejected = $pdo->query("SELECT COUNT(*) FROM leaves WHERE status = 'Rejected' AND user_id = $user_id")->fetchColumn();
}

// Fetch users for admin dropdown
$users_list = [];
if ($isAdmin) {
    $users_list = $pdo->query("SELECT id, full_name, email FROM users WHERE role != 'super_admin' ORDER BY full_name")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave Management - Documantraa</title>
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

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Leave Management</h1>
                    <p class="text-muted mb-0 small">
                        <?= $isAdmin ? 'Manage employee leave requests' : 'View your leave status' ?>
                    </p>
                </div>
                <div>
                    <?php if ($isAdmin): ?>
                        <a href="bulk_leave_allocation.php" class="btn-v2 btn-white-v2 me-2">
                             <i class="bi bi-layers-fill"></i> <span class="d-none d-md-inline ms-1">Bulk Allocate</span>
                        </a>
                        <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#addLeaveModal">
                            <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline ms-1">Add Leave</span>
                        </button>
                    <?php else: ?>
                        <a href="my_profile.php" class="btn-v2 btn-primary-v2">
                            <i class="bi bi-calendar-plus"></i> <span class="d-none d-md-inline ms-1">Apply (Profile)</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Total Leaves</div>
                         <div class="stat-value text-primary"><?= $leaves ? count($leaves) : 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Pending</div>
                        <div class="stat-value text-warning"><?= $pending ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Approved</div>
                        <div class="stat-value text-success"><?= $approved ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                         <div class="card-title-v2 mb-2">Rejected</div>
                         <div class="stat-value text-danger"><?= $rejected ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <!-- Leaves Table -->
            <div class="app-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th class="py-3 ps-3 text-secondary fw-normal">Employee</th>
                                <th class="py-3 text-secondary fw-normal">Type</th>
                                <th class="py-3 text-secondary fw-normal">Duration</th>
                                <th class="py-3 text-secondary fw-normal">Reason</th>
                                <th class="py-3 text-secondary fw-normal">Status</th>
                                <th class="py-3 text-secondary fw-normal text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaves)): ?>
                            <tr>
                                <td colspan="6" class="p-5 text-center text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-3 opacity-25"></i>
                                    No leave records found
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($leaves as $leave): ?>
                            <tr>
                                <td class="py-3 ps-3">
                                    <div class="fw-bold text-main"><?= htmlspecialchars($leave['full_name'] ?? 'Unknown') ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($leave['email'] ?? '') ?></small>
                                </td>
                                <td class="py-3">
                                    <span class="badge rounded-pill bg-light text-dark border"><?= $leave['leave_type'] ?></span>
                                </td>
                                <td class="py-3">
                                    <div class="fw-medium text-main"><?= $leave['days_count'] ?> Days</div>
                                    <small class="text-muted">
                                        <?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?>
                                    </small>
                                </td>
                                <td class="py-3 text-muted small" style="max-width: 200px; white-space: normal;">
                                    <?= htmlspecialchars($leave['reason']) ?>
                                </td>
                                <td class="py-3">
                                    <?php
                                    $statusClass = match($leave['status']) {
                                        'Approved' => 'badge-success',
                                        'Rejected' => 'badge-pending', // Using pending style (red/orange) for rejected or custom
                                        default => 'badge-process'
                                    };
                                    // Override for Rejected to look red
                                    $badgeStyle = $leave['status'] === 'Rejected' ? 'background: var(--danger-bg); color: var(--danger-text);' : '';
                                    ?>
                                    <span class="badge badge-v2 <?= $statusClass ?>" style="<?= $badgeStyle ?>">
                                        <?= $leave['status'] ?>
                                    </span>
                                </td>
                                <td class="py-3 text-end pe-3">
                                    <?php if ($leave['status'] == 'Pending' && $isAdmin): ?>
                                        <button class="btn-v2 btn-success-v2 bg-success text-white p-1 px-2 border-0" onclick="reviewLeave(<?= $leave['id'] ?>, 'Approved')" title="Approve">
                                            <i class="bi bi-check-lg"></i>
                                        </button>
                                        <button class="btn-v2 btn-white-v2 text-danger p-1 px-2" onclick="reviewLeave(<?= $leave['id'] ?>, 'Rejected')" title="Reject">
                                            <i class="bi bi-x-lg"></i>
                                        </button>
                                    <?php endif; ?>
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

    <!-- Add Leave Modal (Admin) -->
    <div class="modal fade" id="addLeaveModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2">Add Leave (Admin)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="add_leave" value="1">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Employee</label>
                            <select name="user_id" class="input-v2 w-100" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($users_list as $user): ?>
                                <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Leave Type</label>
                            <select name="leave_type" class="input-v2 w-100" required>
                                <option value="Casual">Casual Leave</option>
                                <option value="Sick">Sick Leave</option>
                                <option value="Earned">Earned Leave</option>
                                <option value="Unpaid">Unpaid Leave</option>
                                <option value="Paternity">Paternity Leave</option>
                                <option value="Maternity">Maternity Leave</option>
                            </select>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="stat-label mb-1">Start Date</label>
                                <input type="date" name="start_date" class="input-v2 w-100" required>
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1">End Date</label>
                                <input type="date" name="end_date" class="input-v2 w-100" required>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <label class="stat-label mb-1">Reason</label>
                            <textarea name="reason" class="input-v2 w-100" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Review Modal -->
    <div class="modal fade" id="reviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: 1px solid var(--border);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2">Review Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="review_leave" value="1">
                        <input type="hidden" name="leave_id" id="reviewLeaveId">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Decision</label>
                            <select name="status" id="reviewStatus" class="input-v2 w-100" required>
                                <option value="Approved">Approve</option>
                                <option value="Rejected">Reject</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Remarks</label>
                            <textarea name="remarks" class="input-v2 w-100" rows="3" placeholder="Optional remarks..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">Submit Review</button>
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
        <a href="payroll.php" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Payroll</span>
        </a>
         <div style="position: relative; top: -20px;">
            <a href="leaves.php" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="leaves.php" class="bottom-nav-item active">
            <i class="bi bi-calendar-range"></i>
            <span>Leaves</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function reviewLeave(id, status) {
        document.getElementById('reviewLeaveId').value = id;
        document.getElementById('reviewStatus').value = status;
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }
    </script>
</body>
</html>
