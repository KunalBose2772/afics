<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$is_field_role = in_array($role, ['investigator', 'field_agent', 'fo', 'field_officer']);
$is_admin_view = in_array($role, ['admin', 'super_admin', 'hod', 'manager']);
$full_name = $_SESSION['full_name'] ?? 'User';

// --- Fetch Stats ---
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

if (!$is_admin_view) {
    $where_assigned = "(assigned_to = $user_id OR pt_fo_id = $user_id OR hp_fo_id = $user_id OR other_fo_id = $user_id OR team_manager_id = $user_id)";
    $pending_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE $where_assigned AND status = 'Pending'")->fetchColumn();
    $process_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE $where_assigned AND status = 'In-Progress'")->fetchColumn();
    $completed_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE $where_assigned AND status = 'Completed'")->fetchColumn();
    
    // Points Logic
    $user_data = $pdo->query("SELECT target_points FROM users WHERE id = $user_id")->fetch();
    $target_pts = $user_data['target_points'] ?? 0;
    $current_pts = $pdo->query("SELECT SUM(case_points) FROM projects WHERE assigned_to = $user_id AND status = 'Completed' AND updated_at BETWEEN '$month_start' AND '$month_end'")->fetchColumn() ?? 0;
} else {
    // Admin/Manager/HOD Stats
    $today = date('Y-m-d');
    $pending_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Pending'")->fetchColumn(); 
    $process_count = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND status = 'Present'")->fetchColumn(); 
    $completed_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Completed'")->fetchColumn();
    
    // Total Points (Organization wide)
    $target_pts = 5000; 
    $current_pts = $pdo->query("SELECT SUM(case_points) FROM projects WHERE status = 'Completed' AND updated_at BETWEEN '$month_start' AND '$month_end'")->fetchColumn() ?? 0;
}

$pts_percent = $target_pts > 0 ? min(100, round(($current_pts / $target_pts) * 100)) : 0;

// --- Fetch Recent Cases ---
if (!$is_admin_view) {
    $recent_cases = $pdo->query("SELECT * FROM projects WHERE (assigned_to = $user_id OR pt_fo_id = $user_id OR hp_fo_id = $user_id OR other_fo_id = $user_id OR team_manager_id = $user_id) AND status IN ('Pending', 'In-Progress', 'Hold', 'FO-Closed', 'Completed') ORDER BY created_at DESC LIMIT 5")->fetchAll();
} else {
    $recent_cases = $pdo->query("SELECT * FROM projects ORDER BY created_at DESC LIMIT 5")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 32px; }
        @media (min-width: 768px) {
            .stat-grid { grid-template-columns: repeat(4, 1fr); }
        }
    </style>
</head>
<body>
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
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Dashboard</h1>
                    <p style="margin-top: 4px;"><?= date('l, F j') ?> &middot; <?= explode(' ', $full_name)[0] ?></p>
                </div>
                <a href="notifications.php" class="btn-white-v2 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px; border-radius: 50%; position: relative;">
                    <i class="bi bi-bell-fill" style="color: var(--text-secondary);"></i>
                </a>
            </div>
        </header>

        <div class="app-container">
            <div class="stat-grid">
                <div class="app-card" style="margin:0;">
                    <div class="card-title-v2"><?= $is_admin_view ? 'Total Pending' : 'Allocated' ?></div>
                    <div class="stat-value" style="color: var(--warning-text);"><?= $pending_count ?></div>
                </div>
                
                <div class="app-card" style="margin:0;">
                    <div class="card-title-v2"><?= $is_admin_view ? 'Present FOs' : 'In Process' ?></div>
                    <div class="stat-value" style="color: var(--primary);"><?= $process_count ?></div>
                </div>
                
                <div class="app-card" style="grid-column: span 2; margin:0; display: flex; flex-direction: column; justify-content: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="card-title-v2 text-white opacity-75">Monthly Performance</span>
                        <span class="badge bg-white bg-opacity-25 text-white"><?= $target_pts ?> Target</span>
                    </div>
                    <div class="d-flex align-items-baseline gap-3 mb-2">
                        <div class="stat-value text-white"><?= (float)$current_pts ?></div>
                        <div style="font-size: 0.875rem; color: rgba(255,255,255,0.7);"><?= $target_pts - $current_pts > 0 ? ($target_pts - $current_pts).' to goal' : 'Goal Met!' ?></div>
                    </div>
                    <div class="progress" style="height: 6px; background: rgba(255,255,255,0.2); border-radius: 10px;">
                        <div class="progress-bar bg-white" style="width: <?= $pts_percent ?>%;"></div>
                    </div>
                </div>
            </div>

            <h2 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px;">Quick Actions</h2>
            <div class="action-scroll mb-4">
                <a href="#" class="btn-v2 btn-primary-v2 text-decoration-none"><i class="bi bi-camera"></i> Quick Snap</a>
                <a href="attendance.php" class="btn-v2 btn-white-v2 text-decoration-none"><i class="bi bi-geo-alt"></i> Check-In</a>
                <a href="#" class="btn-v2 btn-white-v2 text-decoration-none"><i class="bi bi-qr-code-scan"></i> Scan QR</a>
                <a href="#" class="btn-v2 btn-white-v2 text-decoration-none"><i class="bi bi-telephone"></i> Support</a>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 style="font-size: 1rem; font-weight: 600; margin: 0;"><?= $is_admin_view ? 'Recent Activity' : 'Current Assignments' ?></h2>
                <a href="projects.php" class="btn-v2 btn-white-v2 text-decoration-none" style="padding: 6px 16px; font-size: 0.8rem;">View All</a>
            </div>

            <?php if (empty($recent_cases)): ?>
                <div class="app-card text-center" style="padding: 48px;">
                    <i class="bi bi-inbox" style="font-size: 2.5rem; color: var(--text-muted); opacity: 0.3;"></i>
                    <p class="mt-3 text-muted">No active claims found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($recent_cases as $case): ?>
                    <div class="app-card assignment-card mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-light p-2 rounded text-primary"><i class="bi bi-building"></i></div>
                                <div>
                                    <h3 class="assign-title mb-0" style="font-size: 0.95rem; font-weight: 600;"><?= htmlspecialchars($case['title']) ?></h3>
                                    <span class="text-muted small"><?= htmlspecialchars($case['claim_number']) ?></span>
                                </div>
                            </div>
                            <span class="badge badge-v2 <?= $case['status'] == 'Pending' ? 'badge-pending' : 'badge-process' ?>"><?= $case['status'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item active"><i class="bi bi-grid-1x2-fill"></i><span>Home</span></a>
        <a href="my_profile.php" class="bottom-nav-item"><i class="bi bi-person"></i><span>Profile</span></a>
        <div style="position: relative; top: -20px;">
            <a href="projects.php" class="bottom-nav-icon-main"><i class="bi bi-plus-lg"></i></a>
        </div>
        <a href="field_visits.php" class="bottom-nav-item"><i class="bi bi-geo-alt"></i><span>Visits</span></a>
        <a href="my_earnings.php" class="bottom-nav-item"><i class="bi bi-credit-card"></i><span>Pay</span></a>
    </nav>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
