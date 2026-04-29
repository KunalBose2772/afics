<?php
require_once 'app_init.php';
// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch "Notifications" (Recent Activity)
// Admin sees all recent updates, Users see their own
$query = "SELECT p.*, u.full_name as officer_name 
          FROM projects p 
          LEFT JOIN users u ON p.assigned_to = u.id 
          WHERE 1=1";

if (in_array($role, ['investigator', 'field_agent', 'fo', 'field_officer'])) {
    $query .= " AND (p.assigned_to = $user_id OR p.pt_fo_id = $user_id OR p.hp_fo_id = $user_id OR p.other_fo_id = $user_id OR p.team_manager_id = $user_id)";
}

$query .= " ORDER BY p.updated_at DESC LIMIT 20";
$notifications = $pdo->query($query)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications - Documantraa</title>
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- V2 Styles -->
    <link rel="stylesheet" href="css/app.css">
</head>
<body style="background-color: #f8fafc;">

    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <button class="btn p-0" onclick="history.back()">
                <i class="bi bi-arrow-left" style="font-size: 1.5rem; color: var(--text-main);"></i>
            </button>
            <span style="font-family: 'Lexend'; font-weight: 600; font-size: 1.1rem;">Notifications</span>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <div class="app-container" style="max-width: 800px;">
            <div class="d-none d-lg-block mb-4">
                <h1 style="font-size: 1.75rem; color: var(--text-main);">Notifications</h1>
                <p class="text-muted">Recent updates and activities.</p>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="app-card text-center py-5">
                    <i class="bi bi-bell-slash text-muted" style="font-size: 3rem; opacity: 0.5;"></i>
                    <p class="mt-3 text-muted">No new notifications.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $notif): 
                        $statusClass = 'bg-light text-secondary';
                        $icon = 'bi-info-circle';
                        if ($notif['status'] == 'Completed') { $statusClass = 'success'; $icon = 'bi-check-circle-fill'; }
                        if ($notif['status'] == 'Pending') { $statusClass = 'warning'; $icon = 'bi-exclamation-circle-fill'; }
                        if ($notif['status'] == 'In-Progress') { $statusClass = 'primary'; $icon = 'bi-activity'; }
                    ?>
                    <a href="project_details.php?id=<?= $notif['id'] ?>" class="text-decoration-none">
                        <div class="app-card p-3 d-flex align-items-start gap-3 hover-scale" style="margin: 0; transition: transform 0.2s;">
                            <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" 
                                 style="width: 40px; height: 40px; background: var(--<?= $statusClass ?>-bg); color: var(--<?= $statusClass ?>-text);">
                                <i class="bi <?= $icon ?> fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <h6 class="mb-1 text-dark fw-bold"><?= htmlspecialchars($notif['claim_number']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= date('M j, H:i', strtotime($notif['updated_at'])) ?></small>
                                </div>
                                <p class="mb-1 text-secondary small">
                                    Status updated to <span class="badge badge-v2 badge-<?= $statusClass == 'primary' ? 'process' : $statusClass ?>"><?= $notif['status'] ?></span>
                                </p>
                                <small class="text-muted d-block text-truncate"><?= htmlspecialchars($notif['title']) ?></small>
                            </div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
