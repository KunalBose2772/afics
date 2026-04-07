<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$full_name = $_SESSION['full_name'] ?? 'User';

// --- Fetch Stats ---
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

if (in_array($role, ['investigator', 'field_agent', 'fo', 'field_officer'])) {
    $pending_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE assigned_to = $user_id AND status = 'Pending'")->fetchColumn();
    $process_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE assigned_to = $user_id AND status = 'In-Progress'")->fetchColumn();
    $completed_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE assigned_to = $user_id AND status = 'Completed'")->fetchColumn();
    
    // Points Logic
    $user_data = $pdo->query("SELECT target_points FROM users WHERE id = $user_id")->fetch();
    $target_pts = $user_data['target_points'] ?? 0;
    $current_pts = $pdo->query("SELECT SUM(case_points) FROM projects WHERE assigned_to = $user_id AND status = 'Completed' AND updated_at BETWEEN '$month_start' AND '$month_end'")->fetchColumn() ?? 0;
} else {
    // Admin Stats
    $today = date('Y-m-d');
    $pending_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Pending'")->fetchColumn(); 
    $process_count = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = '$today' AND status = 'Present'")->fetchColumn(); 
    $completed_count = $pdo->query("SELECT COUNT(*) FROM projects WHERE status = 'Completed'")->fetchColumn();
    
    // Total Points (Organization wide)
    $target_pts = 5000; // Example global target or sum of all users
    $current_pts = $pdo->query("SELECT SUM(case_points) FROM projects WHERE status = 'Completed' AND updated_at BETWEEN '$month_start' AND '$month_end'")->fetchColumn() ?? 0;
}

$pts_percent = $target_pts > 0 ? min(100, round(($current_pts / $target_pts) * 100)) : 0;

// --- Fetch Recent Cases (Allocated) ---
$recent_cases = [];
if ($role == 'investigator' || $role == 'field_agent') {
    $recent_cases = $pdo->query("SELECT * FROM projects WHERE assigned_to = $user_id AND status = 'Pending' ORDER BY created_at DESC LIMIT 5")->fetchAll();
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
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- V2 Styles -->
    <link rel="stylesheet" href="css/app.css">
    <style>
        .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 32px; }
        @media (min-width: 768px) {
            .stat-grid { grid-template-columns: repeat(4, 1fr); }
            .app-card[style*="grid-column: span 2"] { grid-column: span 2; }
        }
    </style>
</head>
<body>
    <!-- Mobile Top Bar (Visible only on mobile) -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 32px;">
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" style="border: none; background: none;">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <!-- Sidebar (Mobile & Desktop) -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <!-- Header Section (Gray Background) -->
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Dashboard</h1>
                    <p style="margin-top: 4px;"><?= date('l, F j') ?> &middot; <?= explode(' ', $full_name)[0] ?></p>
                </div>
                
                <!-- Notification Dot -->
                <a href="notifications.php" class="btn-white-v2 d-flex align-items-center justify-content-center text-decoration-none" style="width: 40px; height: 40px; border-radius: 50%; padding: 0; position: relative;">
                    <i class="bi bi-bell-fill" style="color: var(--text-secondary);"></i>
                    <span style="position:absolute; top: 10px; right: 10px; width: 8px; height: 8px; background: var(--danger-text); border-radius: 50%; border: 1px solid white;"></span>
                </a>
            </div>
        </header>

        <div class="app-container">
            <!-- Stats Grid -->
            <div class="stat-grid">
                <div class="app-card" style="margin:0;">
                    <div class="card-title-v2"><?= ($role=='admin') ? 'Pending' : 'Allocated' ?></div>
                    <div class="stat-value" style="color: var(--warning-text);"><?= $pending_count ?></div>
                </div>
                
                <div class="app-card" style="margin:0;">
                    <div class="card-title-v2"><?= ($role=='admin') ? 'Active' : 'In Process' ?></div>
                    <div class="stat-value" style="color: var(--primary);"><?= $process_count ?></div>
                </div>
                
                <div class="app-card" style="grid-column: span 2; margin:0; display: flex; flex-direction: column; justify-content: center; background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="card-title-v2 text-white opacity-75">Monthly Performance Points</span>
                        <span class="badge bg-white bg-opacity-25 text-white" style="font-size: 0.65rem; border: none;"><?= $target_pts ?> Target</span>
                    </div>
                    <div class="d-flex align-items-baseline gap-3 mb-2">
                        <div class="stat-value text-white"><?= (float)$current_pts ?></div>
                        <div style="font-size: 0.875rem; color: rgba(255,255,255,0.7);"><?= $target_pts - $current_pts > 0 ? ($target_pts - $current_pts).' more to goal' : 'Target Achieved!' ?></div>
                    </div>
                    <div class="progress" style="height: 6px; background: rgba(255,255,255,0.2); border-radius: 10px; overflow: visible;">
                        <div class="progress-bar bg-white" style="width: <?= $pts_percent ?>%; border-radius: 10px; position: relative;">
                            <span style="position: absolute; right: -5px; top: -20px; font-size: 0.65rem; font-weight: bold;"><?= $pts_percent ?>%</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
        <h2 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-main);">Quick Actions</h2>
        <div class="action-scroll">
            <a href="field_visits.php" class="btn-v2 btn-primary-v2 text-decoration-none">
                <i class="bi bi-camera-fill"></i> Quick Snap
            </a>
            <a href="attendance.php" class="btn-v2 btn-white-v2 text-decoration-none">
                <i class="bi bi-geo-alt"></i> Check-In
            </a>
            <a href="mrd_payments.php" class="btn-v2 btn-white-v2 text-decoration-none">
                <i class="bi bi-qr-code"></i> Scan QR
            </a>
             <a href="mailto:support@documantraa.com" class="btn-v2 btn-white-v2 text-decoration-none">
                <i class="bi bi-telephone"></i> Support
            </a>
        </div>

        <!-- Current Assignments Header -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 style="font-size: 1rem; font-weight: 600; color: var(--text-main); margin: 0;"><?= ($role == 'admin') ? 'Recent Projects' : 'Current Assignments' ?></h2>
            <a href="projects.php" class="btn-v2 btn-white-v2 text-decoration-none" style="padding: 6px 16px; font-size: 0.8rem;">View All</a>
        </div>
        
        <?php 
        // Update Query to include Pending AND In-Progress
        $assignment_status_filter = ($role == 'investigator' || $role == 'field_agent') 
            ? "assigned_to = $user_id AND status IN ('Pending', 'In-Progress')" 
            : "status IN ('Pending', 'In-Progress')";
        
        $recent_cases = $pdo->query("SELECT * FROM projects WHERE $assignment_status_filter ORDER BY created_at DESC LIMIT 5")->fetchAll();
        ?>

        <?php if (empty($recent_cases)): ?>
            <div class="app-card text-center" style="padding: 48px;">
                <i class="bi bi-inbox" style="font-size: 2.5rem; color: var(--text-muted); opacity: 0.3;"></i>
                <p class="mt-3 text-muted">All caught up! No active assignments.</p>
            </div>
        <?php else: ?>
            <?php foreach ($recent_cases as $case): ?>
                <div class="app-card assignment-card">
                    <!-- Left: Icon & Main Info -->
                    <div class="assign-left">
                        <div class="assign-icon">
                            <i class="bi bi-building"></i>
                        </div>
                        <div class="assign-main-text">
                            <div class="d-flex align-items-center gap-2">
                                <h3 class="assign-title"><?= htmlspecialchars($case['title']) ?></h3>
                                <?php 
                                    $badgeClass = 'badge-pending';
                                    if ($case['status'] == 'In-Progress') $badgeClass = 'badge-process';
                                    if ($case['status'] == 'Completed') $badgeClass = 'badge-success';
                                ?>
                                <span class="badge-v2 <?= $badgeClass ?>"><?= $case['status'] ?></span>
                            </div>
                            <p class="assign-subtitle text-truncate"><?= htmlspecialchars($case['claim_number']) ?></p>
                        </div>
                    </div>
                    
                    <!-- Right: Action Icons -->
                    <div class="assign-actions">
                        <button class="action-icon-btn" title="Call">
                            <i class="bi bi-telephone-fill"></i>
                        </button>
                        <button class="action-icon-btn" title="View Location">
                            <i class="bi bi-geo-alt-fill"></i>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <!-- Charts Section (Replicated from Original) -->
        <div class="chart-grid">
            <!-- Completion Trends -->
             <div class="app-card" style="margin: 0; height: 100%;">
                <div class="card-header-v2">
                    <span class="card-title-v2">Completion Trends</span>
                    <i class="bi bi-graph-up" style="color: var(--primary);"></i>
                </div>
                <div style="height: 250px; position: relative;">
                    <canvas id="completionChart"></canvas>
                </div>
            </div>
            
            <!-- Status Distribution -->
            <div class="app-card" style="margin: 0; height: 100%;">
                 <div class="card-header-v2">
                    <span class="card-title-v2">Case Status</span>
                    <i class="bi bi-pie-chart" style="color: var(--primary);"></i>
                </div>
                <div style="height: 250px; position: relative;">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item active">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
        <div style="position: relative; top: -20px;">
            <a href="projects.php" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="field_visits.php" class="bottom-nav-item">
            <i class="bi bi-geo-alt"></i>
            <span>Visits</span>
        </a>
        <a href="my_earnings.php" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Pay</span>
        </a>
    </nav>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart Scripts -->
    <?php
    // Fetch Data for Charts
    $months = []; $completed_counts = [];
    for ($i = 5; $i >= 0; $i--) {
        $month_start = date('Y-m-01', strtotime("-$i months"));
        $month_end = date('Y-m-t', strtotime("-$i months"));
        $months[] = date('M', strtotime("-$i months"));
        
        $query = "SELECT COUNT(*) FROM projects WHERE status = 'Completed' AND updated_at BETWEEN '$month_start' AND '$month_end'";
        if ($role == 'investigator') $query .= " AND assigned_to = $user_id";
        $completed_counts[] = $pdo->query($query)->fetchColumn();
    }
    
    $statuses = ['Pending', 'In-Progress', 'Completed', 'Hold'];
    $status_counts = [];
    foreach ($statuses as $status) {
        $query = "SELECT COUNT(*) FROM projects WHERE status = '$status'";
        if ($role == 'investigator') $query .= " AND assigned_to = $user_id";
        $status_counts[] = $pdo->query($query)->fetchColumn();
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctxCompletion = document.getElementById('completionChart').getContext('2d');
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        
        // V2 Theme Colors
        const primaryColor = '#2563ea';
        const successColor = '#047857';
        const warningColor = '#b45309';
        const dangerColor = '#b91c1c';
        const gridColor = '#e5e7eb';
        const textColor = '#6b7280';

        new Chart(ctxCompletion, {
            type: 'line',
            data: {
                labels: <?= json_encode($months) ?>,
                datasets: [{
                    label: 'Completed',
                    data: <?= json_encode($completed_counts) ?>,
                    borderColor: primaryColor,
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { grid: { color: gridColor }, ticks: { color: textColor } },
                    x: { grid: { display: false }, ticks: { color: textColor } }
                }
            }
        });

        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($statuses) ?>,
                datasets: [{
                    data: <?= json_encode($status_counts) ?>,
                    backgroundColor: [warningColor, primaryColor, successColor, textColor],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { color: textColor, boxWidth: 12 } } }
            }
        });
        
        // Desktop Grid for Charts
        if (window.innerWidth >= 992) {
             document.querySelector('.chart-grid').style.gridTemplateColumns = '2fr 1fr';
        }
    </script>

</body>
</html>
