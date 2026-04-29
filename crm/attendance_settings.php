<?php
require_once 'app_init.php';
if (!function_exists('has_permission')) {
    require_once 'auth.php';
}

// Basic Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_permission('attendance');

// Handle Settings Update
if (isset($_POST['update_settings'])) {
    $shift_start = $_POST['shift_start'];
    $shift_end = $_POST['shift_end'];
    $grace_period = (int)$_POST['grace_period'];
    $half_day_hours = (int)$_POST['half_day_hours'];
    
    // Update or insert settings
    $settings = [
        'shift_start_time' => $shift_start,
        'shift_end_time' => $shift_end,
        'grace_period_minutes' => $grace_period,
        'half_day_hours' => $half_day_hours
    ];
    
    foreach ($settings as $key => $value) {
        $check = $pdo->prepare("SELECT id FROM settings WHERE setting_key = ? AND setting_group = 'Attendance'");
        $check->execute([$key]);
        
        if ($check->fetchColumn()) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ? AND setting_group = 'Attendance'");
            $stmt->execute([$value, $key]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_group, setting_key, setting_value) VALUES ('Attendance', ?, ?)");
            $stmt->execute([$key, $value]);
        }
    }
    
    // Log action if function exists
    if(function_exists('log_action')) {
        log_action('UPDATE_SHIFT_SETTINGS', "Updated shift timings: Start: $shift_start, End: $shift_end, Grace: $grace_period min");
    }
    
    header('Location: attendance_settings.php?success=1');
    exit;
}

// Fetch current settings
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'Attendance'");
$stmt->execute();
$settings_raw = $stmt->fetchAll();

$settings = [];
foreach ($settings_raw as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Default values
$shift_start = $settings['shift_start_time'] ?? '09:00';
$shift_end = $settings['shift_end_time'] ?? '18:00';
$grace_period = $settings['grace_period_minutes'] ?? 15;
$half_day_hours = $settings['half_day_hours'] ?? 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Attendance Settings - Documantraa</title>
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
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Shift Settings</h1>
                    <p class="text-muted mb-0 small">Configure working hours & rules</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="attendance.php" class="btn-v2 btn-white-v2">
                        <i class="bi bi-arrow-left"></i><span class="d-none d-sm-inline ms-1">Back</span>
                    </a>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="background: var(--success-bg); color: var(--success-text); border: none; border-radius: var(--radius-md);">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div>Settings updated successfully!</div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="app-card">
                        <div class="card-header-v2 border-bottom pb-3 mb-4">
                            <h3 class="card-title-v2 m-0"><i class="bi bi-clock me-2"></i>Global Configuration</h3>
                        </div>

                        <form method="POST">
                            <input type="hidden" name="update_settings" value="1">
                            
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="stat-label mb-1 d-block">Shift Start Time</label>
                                    <input type="time" name="shift_start" class="input-v2" value="<?= $shift_start ?>" required>
                                    <small class="text-muted small mt-1 d-block">Default office start time</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1 d-block">Shift End Time</label>
                                    <input type="time" name="shift_end" class="input-v2" value="<?= $shift_end ?>" required>
                                    <small class="text-muted small mt-1 d-block">Default office end time</small>
                                </div>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="stat-label mb-1 d-block">Grace Period (Minutes)</label>
                                    <input type="number" name="grace_period" class="input-v2" value="<?= $grace_period ?>" min="0" max="60" required>
                                    <small class="text-muted small mt-1 d-block">Late mark buffer time</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1 d-block">Half Day Hours</label>
                                    <input type="number" name="half_day_hours" class="input-v2" value="<?= $half_day_hours ?>" min="1" max="12" required>
                                    <small class="text-muted small mt-1 d-block">Minimum hours for half day</small>
                                </div>
                            </div>

                            <div class="alert alert-warning d-flex gap-3 mb-4" style="background: var(--warning-bg); border-color: transparent;">
                                <i class="bi bi-info-circle text-warning fs-5"></i>
                                <div class="small" style="color: var(--warning-text);">
                                    <strong>Rules Applied:</strong>
                                    <ul class="mb-0 mt-1 ps-3">
                                        <li>Check-in before <strong><?= date('g:i A', strtotime($shift_start)) ?> + <?= $grace_period ?>m</strong> = On Time</li>
                                        <li>Check-in after grace period = Late Arrival</li>
                                    </ul>
                                </div>
                            </div>

                            <button type="submit" class="btn-v2 btn-primary-v2 px-4">
                                <i class="bi bi-save"></i> Save Configuration
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                   <div class="app-card" style="background: var(--surface-hover);">
                        <div class="card-header-v2 border-bottom pb-3 mb-3">
                            <h3 class="card-title-v2 m-0">Current Overview</h3>
                        </div>
                        
                        <div class="mb-3 border-bottom pb-3" style="border-color: var(--border) !important;">
                            <small class="text-muted d-block uppercase mb-1" style="font-size: 0.7rem;">Working Hours</small>
                            <div class="fw-bold" style="color: var(--text-main); font-size: 1.1rem;">
                                <?= date('h:i A', strtotime($shift_start)) ?> - <?= date('h:i A', strtotime($shift_end)) ?>
                            </div>
                        </div>

                        <div class="mb-3 border-bottom pb-3" style="border-color: var(--border) !important;">
                            <small class="text-muted d-block uppercase mb-1" style="font-size: 0.7rem;">On Time Window</small>
                            <div style="color: var(--success-text); font-weight: 600;">
                                Before <?= date('h:i A', strtotime($shift_start . ' +' . $grace_period . ' minutes')) ?>
                            </div>
                        </div>

                         <div class="mb-3">
                            <small class="text-muted d-block uppercase mb-1" style="font-size: 0.7rem;">Late Tolerance</small>
                            <div style="color: var(--warning-text); font-weight: 600;">
                                <?= $grace_period ?> Minutes
                            </div>
                        </div>
                   </div>
                </div>
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
            <a href="#" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="attendance.php" class="bottom-nav-item active">
            <i class="bi bi-calendar-check"></i>
            <span>Attend</span>
        </a>
        <a href="my_earnings.php" class="bottom-nav-item">
            <i class="bi bi-currency-rupee"></i>
            <span>Earnings</span>
        </a>
    </nav>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
