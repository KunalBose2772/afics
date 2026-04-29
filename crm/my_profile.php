<?php
require_once 'app_init.php';
require_once 'auth.php';
require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// --- HANDLERS ---
$admin_profile_roles = ['super_admin', 'admin', 'hr', 'hr_manager'];
$can_manage_profile_settings = in_array($role, $admin_profile_roles);
$can_upload_profile_picture = in_array($role, ['admin', 'hr']);

// 1. Profile Picture
if (isset($_POST['update_profile_picture']) && !empty($_POST['cropped_image'])) {
    if (!$can_upload_profile_picture) { header('Location: my_profile.php?error=Access Denied'); exit; }
    $cropped_image = $_POST['cropped_image'];
    $image_parts = explode(";base64,", $cropped_image);
    $image_base64 = base64_decode($image_parts[1]);
    $new_filename = 'profile_' . $user_id . '_' . time() . '.jpg';
    
    if (!file_exists('../uploads/profiles/')) { mkdir('../uploads/profiles/', 0777, true); }
    file_put_contents('../uploads/profiles/' . $new_filename, $image_base64);
    
    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$new_filename, $user_id]);
    header('Location: my_profile.php?picture_updated=1'); exit;
}

// 2. Contact Info
if (isset($_POST['update_contact_info'])) {
    $phone = $_POST['phone'];
    $pdo->prepare("UPDATE users SET phone = ? WHERE id = ?")->execute([$phone, $user_id]);
    header('Location: my_profile.php?contact_updated=1'); exit;
}

// 3. Bank Details
if (isset($_POST['update_bank_details'])) {
    $bank_name = $_POST['bank_name'];
    $account_number = $_POST['account_number'];
    $ifsc_code = $_POST['ifsc_code'];
    $pan_number = $_POST['pan_number'];
    $pdo->prepare("UPDATE users SET bank_name = ?, account_number = ?, ifsc_code = ?, pan_number = ? WHERE id = ?")
        ->execute([$bank_name, $account_number, $ifsc_code, $pan_number, $user_id]);
    header('Location: my_profile.php?bank_updated=1'); exit;
}

// 4. Password Change
if (isset($_POST['change_password'])) {
    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];
    
    $user_pwd = $pdo->query("SELECT password FROM users WHERE id = $user_id")->fetchColumn();
    if (password_verify($current, $user_pwd)) {
        if ($new === $confirm && strlen($new) >= 6) {
            $hashed = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hashed, $user_id]);
            header('Location: my_profile.php?password_changed=1'); exit;
        } else {
            header('Location: my_profile.php?error=password_mismatch_or_short'); exit;
        }
    } else {
        header('Location: my_profile.php?error=wrong_current_password'); exit;
    }
}

// 5. Leave Application
if (isset($_POST['apply_leave'])) {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate days
    $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
    
    $stmt = $pdo->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, days_count, reason, status, applied_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
    $stmt->execute([$user_id, $leave_type, $start_date, $end_date, $days, $reason]);
    
    header('Location: my_profile.php?leave_applied=1'); exit;
}

// 6. Email Settings (SMTP)
if (isset($_POST['update_email_settings'])) {
    // Basic permissions check - usually any user can update their own personal SMTP, 
    // but code assumes $can_edit_profile which is HR/Admin. 
    // If regular users need this, we might need to relax this check. 
    // For now keeping consistent with old file logic if requested.
    // Actually old file had: if (!$can_edit_profile) { ... }
    // Let's stick to that for safety unless told otherwise.
    if (!$can_manage_profile_settings) { header('Location: my_profile.php?error=Access Denied'); exit; }
    
    $smtp_user = $_POST['smtp_username'];
    $smtp_pass = $_POST['smtp_password'];
    
    // Encrypt password if provided
    if (!empty($smtp_pass)) {
        if (!defined('SMTP_SECRET_KEY')) define('SMTP_SECRET_KEY', 'Documantraa-Secret-Key-2024'); // Fallback
        $encrypted_pass = openssl_encrypt($smtp_pass, 'AES-128-ECB', SMTP_SECRET_KEY);
        $pdo->prepare("UPDATE users SET smtp_username = ?, smtp_password = ? WHERE id = ?")
            ->execute([$smtp_user, $encrypted_pass, $user_id]);
    } else {
        $pdo->prepare("UPDATE users SET smtp_username = ? WHERE id = ?")
            ->execute([$smtp_user, $user_id]);
    }
    
    header('Location: my_profile.php?settings_updated=1'); exit;
}

// --- FETCH DATA ---
$user = $pdo->query("SELECT * FROM users WHERE id = $user_id")->fetch();
$attendance = $pdo->query("SELECT * FROM attendance WHERE user_id = $user_id ORDER BY date DESC LIMIT 30")->fetchAll();
$leaves = $pdo->query("SELECT * FROM leaves WHERE user_id = $user_id ORDER BY applied_at DESC")->fetchAll();

// Stats
$current_month = date('Y-m');
$att_stats = $pdo->query("SELECT 
    COUNT(*) as total_days,
    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days
    FROM attendance WHERE user_id = $user_id AND DATE_FORMAT(date, '%Y-%m') = '$current_month'")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - Documantraa</title>
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
                <h1 style="font-size: 1.75rem; color: var(--text-main);">My Profile</h1>
            </div>
        </header>

        <div class="app-container">
            <!-- Profile Header -->
            <div class="profile-header-card animate-fade-up" style="background: var(--primary-subtle); border-color: var(--primary); color: var(--text-main); box-shadow: none;">
                <div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: radial-gradient(circle at top right, rgba(37, 99, 235, 0.1), transparent 60%); pointer-events: none;"></div>
                <?php $imgSrc = !empty($user['profile_picture']) ? '../uploads/profiles/'.$user['profile_picture'] : '../assets/images/avatar_placeholder.png'; ?>
                <img src="<?= $imgSrc ?>" class="profile-avatar" style="border-color: white; box-shadow: var(--shadow-sm);" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($full_name) ?>&background=random'">
                <div style="flex: 1; position: relative; z-index: 2;">
                    <h2 style="font-size: 1.5rem; margin-bottom: 4px; color: var(--primary);"><?= htmlspecialchars($full_name) ?></h2>
                    <p style="opacity: 0.8; margin: 0; color: var(--text-secondary);"><?= ucwords(str_replace('_', ' ', $role)) ?></p>
                </div>
                
                <div class="d-flex gap-3 align-items-center position-relative" style="z-index: 2;">
                    <!-- Apply Leave Action (Moved into Header) -->
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                        <i class="bi bi-calendar-plus"></i> Apply Leave
                    </button>

                    <!-- Profile Settings Button -->
                    <!-- Profile Settings Button -->
                    <button class="btn rounded-circle shadow-sm d-flex align-items-center justify-content-center" 
                            style="width: 42px; height: 42px; background: #ffffff; border: none;" 
                            data-bs-toggle="modal" data-bs-target="#profileSettingsModal">
                        <i class="bi bi-gear-fill" style="font-size: 1.25rem; color: #6c757d;"></i>
                    </button>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="row g-3 mb-4 animate-fade-up delay-100">
                <div class="col-4">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Days Worked</div>
                        <div class="stat-value"><?= $att_stats['total_days'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Present</div>
                        <div class="stat-value" style="color: var(--success-text);"><?= $att_stats['present_days'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Absent</div>
                        <div class="stat-value" style="color: var(--danger-text);"><?= $att_stats['absent_days'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <!-- Activity History -->
            <div class="app-card animate-fade-up delay-200">
                <div class="card-header-v2">
                     <h3 class="card-title-v2 m-0">Activity History</h3>
                </div>
                <ul class="nav nav-tabs nav-tabs-v2" id="profileTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#attendance">Attendance</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#leaves">Leaves</button>
                    </li>
                </ul>
                <div class="tab-content" id="profileTabContent">
                    <div class="tab-pane fade show active" id="attendance">
                        <div class="table-responsive">
                            <table class="table table-hover" style="font-size: 0.9rem;">
                                <thead>
                                    <tr style="border-bottom: 2px solid var(--border);">
                                        <th class="py-3 text-secondary fw-normal">Date</th>
                                        <th class="py-3 text-secondary fw-normal">Status</th>
                                        <th class="py-3 text-secondary fw-normal text-end">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($attendance as $att): ?>
                                    <tr>
                                        <td class="py-3"><?= date('d M', strtotime($att['date'])) ?></td>
                                        <td class="py-3">
                                            <span class="badge-v2 <?= ($att['status']=='Present')?'badge-success':'badge-pending' ?>">
                                                <?= $att['status'] ?>
                                            </span>
                                        </td>
                                        <td class="py-3 text-end text-muted">
                                            <?php 
                                            if($att['check_in_time'] && $att['check_out_time']){
                                                $diff = (new DateTime($att['check_in_time']))->diff(new DateTime($att['check_out_time']));
                                                echo $diff->h.'h '.$diff->i.'m';
                                            } else echo '-';
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="leaves">
                         <div class="table-responsive">
                            <table class="table table-hover" style="font-size: 0.9rem;">
                                <thead>
                                    <tr style="border-bottom: 2px solid var(--border);">
                                        <th class="py-3 text-secondary fw-normal">Type</th>
                                        <th class="py-3 text-secondary fw-normal">Dates</th>
                                        <th class="py-3 text-secondary fw-normal text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($leaves as $leave): ?>
                                    <tr>
                                        <td class="py-3"><?= $leave['leave_type'] ?></td>
                                        <td class="py-3 text-muted"><?= date('d M', strtotime($leave['start_date'])) ?> - <?= date('d M', strtotime($leave['end_date'])) ?></td>
                                        <td class="py-3 text-end">
                                             <span class="badge-v2 <?= ($leave['status']=='Approved')?'badge-success':( ($leave['status']=='Rejected')?'badge-pending':'badge-process' ) ?>">
                                                <?= $leave['status'] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Apply Leave Modal -->
    <div class="modal fade" id="applyLeaveModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none; box-shadow: var(--shadow-lg);">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title" style="font-family: 'Lexend', sans-serif; font-weight: 600;">Apply for Leave</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST">
                        <input type="hidden" name="apply_leave" value="1">
                        <div class="mb-3">
                            <label class="stat-label mb-1 d-block">Leave Type</label>
                            <select name="leave_type" class="input-v2 form-select">
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Casual Leave">Casual Leave</option>
                                <option value="Emergency">Emergency</option>
                            </select>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="stat-label mb-1 d-block">Start Date</label>
                                <input type="date" name="start_date" class="input-v2" required>
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1 d-block">End Date</label>
                                <input type="date" name="end_date" class="input-v2" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="stat-label mb-1 d-block">Reason</label>
                            <textarea name="reason" class="input-v2" rows="3" placeholder="Brief reason for leave..."></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn-v2 btn-primary-v2">Submit Application</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Profile Settings Modal (Restored & Styled) -->
    <div class="modal fade" id="profileSettingsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none; box-shadow: var(--shadow-lg);">
                <div class="modal-header border-bottom px-4 py-3">
                    <h5 class="modal-title d-flex align-items-center gap-2" style="font-family: 'Lexend', sans-serif; font-weight: 600;">
                        <i class="bi bi-person-gear text-primary"></i> Profile Settings
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="d-flex flex-column flex-lg-row h-100">
                        <!-- Navigation Tabs -->
                        <!-- Mobile: Horizontal Scroll | Desktop: Vertical Sidebar -->
                        <div class="p-3 bg-light border-bottom border-lg-bottom-0 border-lg-end" style="min-width: 220px;">
                            <div class="nav nav-pills flex-nowrap text-nowrap flex-row flex-lg-column gap-2 scroll-touch pb-2 pb-lg-0" id="v-pills-tab" role="tablist" aria-orientation="vertical">
                                <!-- Scrollbar hidden via .scroll-touch class -->
                                <button class="nav-link active text-start d-flex align-items-center gap-2" id="v-pills-personal-tab" data-bs-toggle="pill" data-bs-target="#v-pills-personal" type="button" role="tab">
                                    <i class="bi bi-person-badge"></i> Personal Info
                                </button>
                                <?php if ($can_upload_profile_picture): ?>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-picture-tab" data-bs-toggle="pill" data-bs-target="#v-pills-picture" type="button" role="tab">
                                    <i class="bi bi-camera"></i> Profile Picture
                                </button>
                                <?php endif; ?>
                                <?php if ($can_manage_profile_settings): ?>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-password-tab" data-bs-toggle="pill" data-bs-target="#v-pills-password" type="button" role="tab">
                                    <i class="bi bi-key"></i> Password
                                </button>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-idcard-tab" data-bs-toggle="pill" data-bs-target="#v-pills-idcard" type="button" role="tab">
                                    <i class="bi bi-card-heading"></i> ID Card
                                </button>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-email-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email" type="button" role="tab">
                                    <i class="bi bi-envelope-at"></i> Email Settings
                                </button>
                                <?php else: ?>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-idcard-tab" data-bs-toggle="pill" data-bs-target="#v-pills-idcard" type="button" role="tab">
                                    <i class="bi bi-card-heading"></i> ID Card
                                </button>
                                <?php endif; ?>
                                <button class="nav-link text-start d-flex align-items-center gap-2" id="v-pills-bank-tab" data-bs-toggle="pill" data-bs-target="#v-pills-bank" type="button" role="tab">
                                    <i class="bi bi-bank"></i> Bank Details
                                </button>
                            </div>
                        </div>

                        <!-- Tab Content (Right Side) -->
                        <div class="flex-grow-1 p-4" style="max-height: 70vh; overflow-y: auto;">
                            <div class="tab-content" id="v-pills-tabContent">
                                
                                <!-- Personal Info -->
                                <div class="tab-pane fade show active" id="v-pills-personal" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">Personal Details</h6>
                                    <form method="POST">
                                        <input type="hidden" name="update_contact_info" value="1">
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Full Name</label>
                                            <input type="text" class="input-v2 bg-light" value="<?= htmlspecialchars($user['full_name']) ?>" disabled>
                                            <small class="text-muted">Contact HR to update name</small>
                                        </div>
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Email Address</label>
                                            <input type="email" class="input-v2 bg-light" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                                        </div>
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Phone Number</label>
                                            <div class="input-group">
                                                <input type="text" name="phone" class="input-v2" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                                <button type="submit" class="btn btn-primary"><i class="bi bi-save"></i></button>
                                            </div>
                                            <small class="text-success"><i class="bi bi-info-circle"></i> Appears on ID Card</small>
                                        </div>
                                    </form>
                                </div>

                                <!-- Profile Picture (Cropper) -->
                                <?php if ($can_upload_profile_picture): ?>
                                <div class="tab-pane fade" id="v-pills-picture" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">Update Profile Picture</h6>
                                    <form method="POST" enctype="multipart/form-data" id="profilePictureForm">
                                        <div class="text-center mb-4">
                                            <?php if (!empty($user['profile_picture'])): ?>
                                                <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_picture']) ?>" class="rounded-circle shadow-sm" style="width: 120px; height: 120px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle bg-light d-flex align-items-center justify-content-center mx-auto" style="width: 120px; height: 120px;">
                                                    <i class="bi bi-person fs-1 text-secondary"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Upload New Image</label>
                                            <input type="file" id="imageInput" class="form-control" accept="image/*">
                                        </div>

                                        <div id="cropperContainer" class="mb-3" style="display: none;">
                                            <img id="imageToCrop" style="max-width: 100%;">
                                        </div>

                                        <div id="croppedPreview" style="display: none;" class="text-center mb-3">
                                            <p class="small text-muted mb-1">Preview:</p>
                                            <img id="previewImage" class="rounded shadow-sm" style="width: 100px;">
                                        </div>

                                        <input type="hidden" name="cropped_image" id="croppedImageData">

                                        <div class="d-flex gap-2 justify-content-end">
                                            <button type="button" id="cropButton" class="btn btn-dark btn-sm" style="display: none;"><i class="bi bi-crop"></i> Crop</button>
                                            <button type="submit" name="update_profile_picture" id="uploadButton" class="btn btn-primary btn-sm" disabled>Upload</button>
                                        </div>
                                    </form>
                                </div>

                                <?php endif; ?>

                                <!-- Password -->
                                <?php if ($can_manage_profile_settings): ?>
                                <div class="tab-pane fade" id="v-pills-password" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">Change Password</h6>
                                    <form method="POST">
                                        <input type="hidden" name="change_password" value="1">
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Current Password</label>
                                            <input type="password" name="current_password" class="input-v2" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">New Password</label>
                                            <input type="password" name="new_password" class="input-v2" required minlength="6">
                                        </div>
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">Confirm Password</label>
                                            <input type="password" name="confirm_password" class="input-v2" required minlength="6">
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-danger text-white"><i class="bi bi-shield-lock"></i> Update Password</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>

                                <!-- ID Card -->
                                <div class="tab-pane fade" id="v-pills-idcard" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">Digital ID Card</h6>
                                    <div class="d-flex justify-content-center mb-3">
                                        <!-- ID Card HTML Structure -->
                                        <div id="idCard" style="width: 280px; height: 500px; background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); overflow: hidden; position: relative; font-family: 'Lexend', sans-serif;">
                                            <!-- Blue Top Design -->
                                            <div style="height: 120px; background: linear-gradient(135deg, #1e5ba8 0%, #0d3c7a 100%); clip-path: polygon(0 0, 100% 0, 100% 85%, 0 100%); position: absolute; top: 0; width: 100%;"></div>
                                            
                                            <div style="position: relative; z-index: 10; padding-top: 20px; text-align: center;">
                                                <div style="background: white; padding: 4px 12px; border-radius: 20px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                                                    <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 20px;">
                                                </div>
                                                <div style="color: white; font-size: 0.6rem; font-weight: 700; letter-spacing: 1px; margin-top: 8px; text-transform: uppercase;">AFICS Investigation Agency</div>
                                                
                                                <div style="margin: 15px auto 10px; width: 100px; height: 100px; padding: 3px; background: white; border-radius: 50%; box-shadow: 0 4px 10px rgba(0,0,0,0.2);">
                                                    <?php $cardImg = !empty($user['profile_picture']) ? '../uploads/profiles/'.$user['profile_picture'] : '../assets/images/avatar_placeholder.png'; ?>
                                                    <img src="<?= $cardImg ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;" crossorigin="anonymous">
                                                </div>
                                                
                                                <h5 style="color: #333; font-weight: 700; font-size: 1.1rem; margin: 0;"><?= htmlspecialchars($user['full_name']) ?></h5>
                                                <p style="color: #1e5ba8; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; margin: 0;"><?= str_replace('_', ' ', $user['role']) ?></p>
                                                
                                                <div style="padding: 15px 25px; text-align: left; font-size: 0.75rem; color: #555;">
                                                    <div style="display: flex; margin-bottom: 6px;">
                                                        <span style="width: 60px; font-weight: 600; color: #888;">EMP ID</span>
                                                        <span style="font-weight: 600;">: <?= htmlspecialchars($user['employee_id'] ?? 'AFI-DMI-' . str_pad($user['id'], 5, '0', STR_PAD_LEFT)) ?></span>
                                                    </div>
                                                    <div style="display: flex; margin-bottom: 6px;">
                                                        <span style="width: 60px; font-weight: 600; color: #888;">Phone</span>
                                                        <span style="font-weight: 600;">: <?= htmlspecialchars($user['phone'] ?? 'N/A') ?></span>
                                                    </div>
                                                    <div style="display: flex; margin-bottom: 6px;">
                                                        <span style="width: 60px; font-weight: 600; color: #888;">Email</span>
                                                        <span style="font-weight: 600; font-size: 0.7rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; width: 150px;">: <?= htmlspecialchars($user['email']) ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div style="position: absolute; bottom: 0; width: 100%; background: #f8f9fa; padding: 8px; font-size: 0.55rem; color: #888; border-top: 1px solid #eee; text-align: center;">
                                                CONFIDENTIAL PROPERTY OF AFICS
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <button onclick="downloadIDCard()" class="btn btn-outline-primary btn-sm"><i class="bi bi-download"></i> Download PNG</button>
                                    </div>
                                </div>

                                <!-- Email Settings -->
                                <?php if ($can_manage_profile_settings): ?>
                                <div class="tab-pane fade" id="v-pills-email" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">SMTP Configuration</h6>
                                    <div class="alert alert-warning py-2 mb-3 small">
                                        <i class="bi bi-exclamation-circle"></i> For personal email sending.
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="update_email_settings" value="1">
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">SMTP Username</label>
                                            <input type="email" name="smtp_username" class="input-v2" value="<?= htmlspecialchars($user['smtp_username'] ?? $user['email']) ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label class="stat-label mb-1 d-block">SMTP Password</label>
                                            <input type="password" name="smtp_password" class="input-v2" placeholder="********">
                                            <small class="text-muted">Leave blank to keep unchanged</small>
                                        </div>
                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary text-white"><i class="bi bi-save"></i> Save Settings</button>
                                        </div>
                                    </form>
                                </div>
                                <?php endif; ?>

                                <!-- Bank Details -->
                                <div class="tab-pane fade" id="v-pills-bank" role="tabpanel">
                                    <h6 class="text-uppercase text-muted fw-bold mb-3 small">Bank Information</h6>
                                    <form method="POST">
                                        <input type="hidden" name="update_bank_details" value="1">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="stat-label mb-1 d-block">Bank Name</label>
                                                <input type="text" name="bank_name" class="input-v2" value="<?= htmlspecialchars($user['bank_name'] ?? '') ?>">
                                            </div>
                                            <div class="col-12">
                                                <label class="stat-label mb-1 d-block">Account Number</label>
                                                <input type="text" name="account_number" class="input-v2" value="<?= htmlspecialchars($user['account_number'] ?? '') ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="stat-label mb-1 d-block">IFSC Code</label>
                                                <input type="text" name="ifsc_code" class="input-v2" value="<?= htmlspecialchars($user['ifsc_code'] ?? '') ?>">
                                            </div>
                                            <div class="col-6">
                                                <label class="stat-label mb-1 d-block">PAN Number</label>
                                                <input type="text" name="pan_number" class="input-v2" value="<?= htmlspecialchars($user['pan_number'] ?? '') ?>">
                                            </div>
                                        </div>
                                        <div class="text-end mt-3">
                                            <button type="submit" class="btn btn-primary text-white"><i class="bi bi-save"></i> Save Bank Info</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cropper.js library for image cropping -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>

    <!-- Image Cropper Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let cropper = null;
            const imageInput = document.getElementById('imageInput');
            const imageToCrop = document.getElementById('imageToCrop');
            const cropperContainer = document.getElementById('cropperContainer');
            const cropButton = document.getElementById('cropButton');
            const uploadButton = document.getElementById('uploadButton');
            const croppedPreview = document.getElementById('croppedPreview');
            const previewImage = document.getElementById('previewImage');
            const croppedImageData = document.getElementById('croppedImageData');

            if(imageInput) {
                imageInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        const reader = new FileReader();
                        reader.onload = function(event) {
                            imageToCrop.src = event.target.result;
                            cropperContainer.style.display = 'block';
                            cropButton.style.display = 'inline-block';
                            croppedPreview.style.display = 'none';
                            uploadButton.disabled = true;

                            if (cropper) { cropper.destroy(); }
                            cropper = new Cropper(imageToCrop, {
                                aspectRatio: 1,
                                viewMode: 1,
                                autoCropArea: 1
                            });
                        };
                        reader.readAsDataURL(file);
                    }
                });

                cropButton.addEventListener('click', function() {
                    if (cropper) {
                        const canvas = cropper.getCroppedCanvas({ width: 300, height: 300 });
                        previewImage.src = canvas.toDataURL('image/jpeg');
                        croppedPreview.style.display = 'block';
                        croppedImageData.value = canvas.toDataURL('image/jpeg');
                        uploadButton.disabled = false;
                        cropperContainer.style.display = 'none';
                        cropButton.style.display = 'none';
                    }
                });
            }
        });
    </script>

    <!-- Html2Canvas -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function downloadIDCard() {
            const element = document.getElementById("idCard");
            html2canvas(element, { scale: 3, useCORS: true }).then(canvas => {
                const link = document.createElement("a");
                document.body.appendChild(link);
                link.download = "My_ID_Card.png";
                link.href = canvas.toDataURL("image/png");
                link.target = '_blank';
                link.click();
            });
        }
    </script>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item active">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
         <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="field_visits.php" class="bottom-nav-item">
            <i class="bi bi-geo-alt"></i>
            <span>Visits</span>
        </a>
        <a href="#" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Pay</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
