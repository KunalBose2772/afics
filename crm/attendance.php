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

// Ensure database columns exist for check-out location
try {
    $check_cols = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('check_out_latitude', $check_cols)) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_latitude DECIMAL(10,8) DEFAULT NULL");
    }
    if (!in_array('check_out_longitude', $check_cols)) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_longitude DECIMAL(11,8) DEFAULT NULL");
    }
} catch (Exception $e) {
    // Fail silently if already exists or other error
}

// Handle Check-in/Check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    try {
        $user_id = $_SESSION['user_id'];
        $today = date('Y-m-d');
        $now = date('H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'];

        if (isset($_POST['check_in'])) {
            $lat = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $long = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;

            // Geofencing Check
            $u_stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $u_stmt->execute([$user_id]);
            $u_role = $u_stmt->fetchColumn();

            // Rules: Enforce for non-field officers (Office Staff)
            if ($u_role !== 'field_officer' && $u_role !== 'super_admin' && $u_role !== 'admin') {
                // Office Location (Placeholder - Example: Kolkata)
                $office_lat = 22.572645;
                $office_long = 88.363892;

                if ($lat && $long) {
                    // Haversine Formula for distance in meters
                    $earth_radius = 6371000;
                    $dLat = deg2rad($office_lat - $lat);
                    $dLon = deg2rad($office_long - $long);
                    $a = sin($dLat / 2) * sin($dLat / 2) +
                        cos(deg2rad($lat)) * cos(deg2rad($office_lat)) *
                        sin($dLon / 2) * sin($dLon / 2);
                    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                    $dist = $earth_radius * $c;

                    // Allow 500m radius (warning only, not blocking)
                    if ($dist > 500) {
                        // Could add warning logic here
                    }
                }
            }
            // Check if already checked in to prevent duplicate
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date = ?");
            $check_stmt->execute([$user_id, $today]);
            if ($check_stmt->fetchColumn() == 0) {
                $stmt = $pdo->prepare("INSERT INTO attendance (user_id, date, check_in_time, status, ip_address, check_in_latitude, check_in_longitude) VALUES (?, ?, ?, 'Present', ?, ?, ?)");
                $stmt->execute([$user_id, $today, $now, $ip, $lat, $long]);
            }
        } elseif (isset($_POST['check_out'])) {
            $lat = !empty($_POST['latitude']) ? floatval($_POST['latitude']) : null;
            $long = !empty($_POST['longitude']) ? floatval($_POST['longitude']) : null;
            
            $stmt = $pdo->prepare("UPDATE attendance SET check_out_time = ?, check_out_latitude = ?, check_out_longitude = ? WHERE user_id = ? AND date = ?");
            $stmt->execute([$now, $lat, $long, $user_id, $today]);
        }
        header('Location: attendance.php');
        exit;
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Get current month/year or from query params
$current_month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$current_year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

// Calculate prev/next month
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Fetch Today's Attendance for Current User (For Check-in/out button)
$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$my_attendance = $pdo->query("SELECT * FROM attendance WHERE user_id = $user_id AND date = '$today'")->fetch();

// Determine User to View (For Calendar & Stats)
$target_user_id = $user_id;
$viewing_self = true;
$target_user_name = "My";

if (has_permission('users')) {
    // Fetch list of all users for the dropdown
    $all_users_stmt = $pdo->query("SELECT id, full_name, employee_id FROM users ORDER BY full_name ASC");
    $all_users = $all_users_stmt->fetchAll();

    if (isset($_GET['view_user_id']) && !empty($_GET['view_user_id'])) {
        $target_user_id = (int) $_GET['view_user_id'];
        $viewing_self = ($target_user_id === $user_id);
    }
}

// Fetch Target User Name if not self
if (!$viewing_self) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = ?");
    $stmt->execute([$target_user_id]);
    $result = $stmt->fetch();
    $target_user_name = $result ? htmlspecialchars($result['full_name']) . "'s" : "Unknown";
}

// Fetch All Attendance for Admin View (today)
$all_attendance = [];
if (has_permission('users')) {
    $stmt = $pdo->query("SELECT a.*, u.full_name, u.role FROM attendance a JOIN users u ON a.user_id = u.id WHERE a.date = '$today' ORDER BY a.check_in_time DESC");
    $all_attendance = $stmt->fetchAll();
}

// Fetch employee's attendance history for the selected month
$first_day = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-01";
$last_day = date('Y-m-t', strtotime($first_day));

$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND date BETWEEN ? AND ?");
$stmt->execute([$target_user_id, $first_day, $last_day]);
$attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert to associative array for easy lookup
$attendance_map = [];
foreach ($attendance_records as $record) {
    $attendance_map[$record['date']] = $record;
}

// Fetch shift settings from database
$shift_settings_raw = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_group = 'Attendance'")->fetchAll();
$shift_settings = [];
foreach ($shift_settings_raw as $row) {
    $shift_settings[$row['setting_key']] = $row['setting_value'];
}

// Default shift settings if not configured
$shift_start_time = $shift_settings['shift_start_time'] ?? '09:00';
$grace_period_minutes = (int) ($shift_settings['grace_period_minutes'] ?? 15);

// Calculate late threshold
$late_threshold = strtotime($shift_start_time . ' +' . $grace_period_minutes . ' minutes');

// Calculate statistics for the month
$present_count = 0;
$late_count = 0;
$absent_count = 0;
$leave_count = 0;

foreach ($attendance_records as $record) {
    if ($record['status'] == 'Present') {
        $present_count++;
        if (!empty($record['check_in_time'])) {
            $check_in = strtotime($record['check_in_time']);
            if ($check_in > $late_threshold) {
                $late_count++;
            }
        }
    } elseif ($record['status'] == 'Absent') {
        $absent_count++;
    } elseif ($record['status'] == 'On Leave') {
        $leave_count++;
    }
}

// Get number of days in month
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);
$first_day_of_month = date('w', strtotime($first_day));

// Helper function to check if late
function isLate($check_in_time, $shift_start, $grace_minutes)
{
    if (empty($check_in_time))
        return false;
    $check_in = strtotime($check_in_time);
    $late_threshold = strtotime($shift_start . ' +' . $grace_minutes . ' minutes');
    return $check_in > $late_threshold;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Attendance - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .attendance-calendar {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 1.25rem;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            padding: 0.5rem 0;
            letter-spacing: 0.5px;
        }

        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-md);
            background: #ffffff;
            border: 1px solid var(--border);
            position: relative;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-xs);
        }

        @media (max-width: 400px) {
            .attendance-calendar {
                padding: 10px;
            }
            .calendar-grid {
                gap: 4px;
            }
            .calendar-day {
                border-radius: 8px;
            }
            .day-number {
                font-size: 0.85rem;
            }
        }

        .calendar-day:hover {
            background: var(--bg-hover);
            transform: scale(1.05);
            border-color: var(--primary);
        }

        .calendar-day.empty {
            background: transparent;
            border: none;
            cursor: default;
        }

        .calendar-day.empty:hover {
            transform: none;
        }

        .day-number {
            font-size: 0.95rem;
            color: var(--text-main);
            font-weight: 600;
        }

        .status-indicator {
            position: absolute;
            bottom: 6px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .status-indicator.present {
            background: #047857;
        }

        .status-indicator.late {
            background: #b45309;
        }

        .status-indicator.absent {
            background: #b91c1c;
        }

        .status-indicator.leave {
            background: #0284c7;
        }

        .legend-item .status-indicator {
            position: relative !important;
            bottom: auto !important;
            flex-shrink: 0;
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
        }

        .month-nav-btn {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text-main);
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .month-nav-btn:hover {
            background: var(--bg-hover);
            transform: scale(1.1);
            color: var(--primary);
        }

        .calendar-day.today {
            border: 2px solid var(--primary);
            box-shadow: 0 0 12px rgba(var(--primary-rgb), 0.3);
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
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Attendance</h1>
                    <p class="text-muted mb-0 small"><?= date('l, F j, Y') ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <?php if (has_permission('users')): ?>
                        <!-- Employee Filter for Admin -->
                        <form action="" method="GET" class="d-none d-sm-flex align-items-center">
                            <input type="hidden" name="month" value="<?= $current_month ?>">
                            <input type="hidden" name="year" value="<?= $current_year ?>">
                            <select name="view_user_id" class="input-v2 py-1" style="max-width: 180px; font-size: 0.85rem;" onchange="this.form.submit()">
                                <option value="<?= $_SESSION['user_id'] ?>">My Attendance</option>
                                <?php foreach ($all_users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= $target_user_id == $u['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['full_name']) ?> (<?= $u['employee_id'] ?? 'N/A' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>

                        <a href="attendance_settings.php" class="btn-v2 btn-white-v2 p-1 px-2" title="Settings">
                            <i class="bi bi-gear"></i>
                        </a>

                        <a href="export_attendance.php?month=<?= $current_month ?>&year=<?= $current_year ?>" class="btn-v2 btn-primary-v2" target="_blank">
                            <i class="bi bi-file-earmark-spreadsheet"></i><span class="d-none d-sm-inline ms-1">Export</span>
                        </a>
                    <?php endif; ?>

                    <!-- Only show check in/out if viewing self -->
                    <?php if ($viewing_self): ?>
                        <form method="POST" id="checkInForm">
                            <input type="hidden" name="latitude" id="lat">
                            <input type="hidden" name="longitude" id="long">
                            <?php if (!$my_attendance): ?>
                                <button type="button" onclick="attemptCheckIn()" class="btn-v2 btn-primary-v2">
                                    <i class="bi bi-box-arrow-in-right"></i><span class="d-none d-sm-inline ms-1">Check In</span>
                                </button>
                                <input type="hidden" name="check_in" value="1">
                            <?php elseif (!$my_attendance['check_out_time']): ?>
                                <button type="button" onclick="attemptCheckIn('check_out')" class="btn-v2" style="background: var(--danger-text); color: white;">
                                    <i class="bi bi-box-arrow-right"></i><span class="d-none d-sm-inline ms-1">Check Out</span>
                                </button>
                                <input type="hidden" name="check_out" value="1">
                            <?php else: ?>
                                <button class="btn-v2 btn-white-v2" disabled>
                                    <i class="bi bi-check-circle"></i><span class="d-none d-sm-inline ms-1">Done</span>
                                </button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?= render_form_errors($errors ?? []) ?>
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Present</div>
                        <div class="stat-value text-success"><?= $present_count ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Late Arrivals</div>
                        <div class="stat-value text-warning"><?= $late_count ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Absent</div>
                        <div class="stat-value text-danger"><?= $absent_count ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">On Leave</div>
                        <div class="stat-value text-info"><?= $leave_count ?></div>
                    </div>
                </div>
            </div>

            <!-- Calendar View -->
            <div class="app-card mb-4">
                <div class="card-header-v2">
                    <h3 class="card-title-v2 m-0"><?= $target_user_name ?> Attendance History</h3>
                </div>
                
                <div class="p-4">
                    <div class="attendance-calendar">
                        <div class="month-header">
                            <a href="?month=<?= $prev_month ?>&year=<?= $prev_year ?>&view_user_id=<?= $target_user_id ?>" class="month-nav-btn">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <h4 class="mb-0 fw-bold" style="color: var(--text-main);">
                                <?= date('F Y', strtotime("$current_year-$current_month-01")) ?>
                            </h4>
                            <a href="?month=<?= $next_month ?>&year=<?= $next_year ?>&view_user_id=<?= $target_user_id ?>" class="month-nav-btn">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>

                        <div class="calendar-grid">
                            <!-- Day headers -->
                            <?php
                            $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                            foreach ($days as $day):
                                ?>
                                <div class="calendar-day-header"><?= $day ?></div>
                            <?php endforeach; ?>

                            <!-- Empty cells before first day -->
                            <?php for ($i = 0; $i < $first_day_of_month; $i++): ?>
                                <div class="calendar-day empty"></div>
                            <?php endfor; ?>

                            <!-- Days of month -->
                            <?php for ($day = 1; $day <= $days_in_month; $day++):
                                $date = "$current_year-" . str_pad($current_month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);
                                $is_today = $date == $today;
                                $attendance = isset($attendance_map[$date]) ? $attendance_map[$date] : null;
                                $status_class = '';
                                $show_indicator = false;

                                if ($attendance && !empty($attendance['status'])) {
                                    $show_indicator = true;
                                    if ($attendance['status'] == 'Present') {
                                        $status_class = isLate($attendance['check_in_time'], $shift_start_time, $grace_period_minutes) ? 'late' : 'present';
                                    } elseif ($attendance['status'] == 'Absent') {
                                        $status_class = 'absent';
                                    } elseif ($attendance['status'] == 'On Leave') {
                                        $status_class = 'leave';
                                    } else {
                                        $show_indicator = false;
                                    }
                                }
                                ?>
                                <div class="calendar-day <?= $is_today ? 'today' : '' ?>"
                                    title="<?= $attendance ? htmlspecialchars($attendance['status']) . ($attendance['check_in_time'] ? ' - ' . date('h:i A', strtotime($attendance['check_in_time'])) : '') : 'No record' ?>">
                                    <span class="day-number"><?= $day ?></span>
                                    <?php if ($show_indicator && $status_class): ?>
                                        <div class="status-indicator <?= $status_class ?>"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <!-- Legend -->
                        <div class="d-flex justify-content-center gap-4 mt-4 flex-wrap">
                            <div class="d-flex align-items-center legend-item" style="gap: 6px;">
                                <div class="status-indicator present"></div>
                                <small class="text-muted">Present</small>
                            </div>
                            <div class="d-flex align-items-center legend-item" style="gap: 6px;">
                                <div class="status-indicator late"></div>
                                <small class="text-muted">Late</small>
                            </div>
                            <div class="d-flex align-items-center legend-item" style="gap: 6px;">
                                <div class="status-indicator absent"></div>
                                <small class="text-muted">Absent</small>
                            </div>
                            <div class="d-flex align-items-center legend-item" style="gap: 6px;">
                                <div class="status-indicator leave"></div>
                                <small class="text-muted">On Leave</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Staff Attendance Today (Admin View) -->
            <?php if (!empty($all_attendance)): ?>
                <div class="app-card">
                    <div class="card-header-v2">
                        <h3 class="card-title-v2 m-0">Staff Attendance Today</h3>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" style="font-size: 0.9rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border);">
                                    <th class="py-3 text-secondary fw-normal">Employee</th>
                                    <th class="py-3 text-secondary fw-normal">Role</th>
                                    <th class="py-3 text-secondary fw-normal">Check In</th>
                                    <th class="py-3 text-secondary fw-normal">Check Out</th>
                                    <th class="py-3 text-secondary fw-normal">Location</th>
                                    <th class="py-3 text-secondary fw-normal">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_attendance as $record): ?>
                                <tr>
                                    <td class="py-3 align-middle fw-bold"><?= htmlspecialchars($record['full_name']) ?></td>
                                    <td class="py-3 align-middle text-muted">
                                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $record['role']))) ?>
                                    </td>
                                    <td class="py-3 align-middle" style="color: var(--success-text);">
                                        <?= date('h:i A', strtotime($record['check_in_time'])) ?>
                                    </td>
                                    <td class="py-3 align-middle" style="color: var(--danger-text);">
                                        <?= $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '--:--' ?>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <div class="d-flex flex-column gap-1">
                                            <?php if (!empty($record['check_in_latitude'])): ?>
                                                <a href="https://www.google.com/maps?q=<?= htmlspecialchars($record['check_in_latitude']) ?>,<?= htmlspecialchars($record['check_in_longitude']) ?>" 
                                                   target="_blank" class="btn-v2 btn-white-v2 p-0 px-2" style="font-size: 0.75rem;" title="Check-in Location">
                                                    <i class="bi bi-geo-alt-fill text-success"></i> In
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($record['check_out_latitude'])): ?>
                                                <a href="https://www.google.com/maps?q=<?= htmlspecialchars($record['check_out_latitude']) ?>,<?= htmlspecialchars($record['check_out_longitude']) ?>" 
                                                   target="_blank" class="btn-v2 btn-white-v2 p-0 px-2" style="font-size: 0.75rem;" title="Check-out Location">
                                                    <i class="bi bi-geo-alt-fill text-danger"></i> Out
                                                </a>
                                            <?php endif; ?>

                                            <?php if (empty($record['check_in_latitude']) && empty($record['check_out_latitude'])): ?>
                                                <span class="text-muted small">N/A</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="py-3 align-middle">
                                        <span class="badge badge-v2 badge-success">Present</span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
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
    <script src="../assets/js/validation.js"></script>
    <script>
        function attemptCheckIn(type = 'check_in') {
            const checkInBtn = document.querySelector('#checkInForm button');
            const originalContent = checkInBtn.innerHTML;
            
            checkInBtn.disabled = true;
            const actionText = type === 'check_in' ? 'Checking In...' : 'Checking Out...';
            checkInBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Locating...';

            const options = { enableHighAccuracy: true, timeout: 8000, maximumAge: 0 };

            function success(position) {
                document.getElementById('lat').value = position.coords.latitude;
                document.getElementById('long').value = position.coords.longitude;
                checkInBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + actionText;
                document.getElementById('checkInForm').submit();
            }

            function error(err) {
                console.warn('GPS High Accuracy failed, retrying...', err.message);
                navigator.geolocation.getCurrentPosition(success, (err2) => {
                    let errorMsg = "Unable to retrieve your location.";
                    if(err2.code == 1) errorMsg = "Location permission denied. Please enable GPS.";
                    else if(err2.code == 2) errorMsg = "Location unavailable. Please check your signal.";
                    else if(err2.code == 3) errorMsg = "Location request timed out.";

                    if(confirm(errorMsg + "\n\nDo you want to proceed without location data?")) {
                         checkInBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>' + actionText;
                         document.getElementById('checkInForm').submit();
                    } else {
                         checkInBtn.innerHTML = originalContent;
                         checkInBtn.disabled = false;
                    }
                }, { enableHighAccuracy: false, timeout: 5000 });
            }

            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(success, error, options);
            } else {
                alert("Geolocation is not supported by this browser.");
                checkInBtn.innerHTML = originalContent;
                checkInBtn.disabled = false;
            }
        }
    </script>
</body>
</html>
