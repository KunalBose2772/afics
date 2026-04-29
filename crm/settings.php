<?php
require_once 'app_init.php';

// Access Control
if (!in_array($_SESSION['role'], ['super_admin'])) {
    // Check specific permission if not super_admin
    if(!function_exists('has_permission') || !has_permission('settings')) {
         header("Location: dashboard.php");
         exit;
    }
}

// Handle Form Submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    try {
        $allowed_keys = [
            'site_name', 'site_tagline', 'contact_phone', 'contact_email', 
            'contact_address',
            'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port',
            'whatsapp_phone', 'whatsapp_apikey'
        ];

        $pdo->beginTransaction();

        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = sanitize_input($_POST[$key]);
                
                if ($key === 'contact_email' && !empty($value) && !validate_email($value)) {
                    throw new Exception("Invalid support email address.");
                }

                // Check if key exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $update = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $update->execute([$value, $key]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$key, $value]);
                }
            }
        }

        $pdo->commit();
        $message = "System settings updated successfully!";
        $message_type = "success";
        
        // Refresh settings for display
        $settings = get_settings($pdo);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = $e->getMessage();
        $message_type = "danger";
    }
} else {
    $settings = get_settings($pdo);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Settings - Documantraa</title>
    
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

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">System Settings</h1>
                    <p class="text-muted mb-0 small">Configure application preferences and integrations.</p>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if ($message): ?>
            <div class="alert alert-<?= $message_type ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-<?= $message_type == 'success' ? 'check-circle' : 'exclamation-circle' ?>-fill me-2"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="app-card mb-4">
                    <div class="card-header-v2">
                        <span class="card-title-v2"><i class="bi bi-app-indicator me-2"></i>General Information</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Application Name</label>
                            <input type="text" name="site_name" class="form-control input-v2" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>" placeholder="e.g. Documantraa CRM">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Tagline</label>
                            <input type="text" name="site_tagline" class="form-control input-v2" value="<?= htmlspecialchars($settings['site_tagline'] ?? '') ?>" placeholder="e.g. Internal Management System">
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="card-header-v2">
                        <span class="card-title-v2"><i class="bi bi-headset me-2"></i>Support Contact</span>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Support Email</label>
                             <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" name="contact_email" class="form-control input-v2 border-start-0 ps-0" value="<?= htmlspecialchars($settings['contact_email'] ?? '') ?>">
                             </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Support Phone</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-telephone"></i></span>
                                <input type="text" name="contact_phone" class="form-control input-v2 border-start-0 ps-0" value="<?= htmlspecialchars($settings['contact_phone'] ?? '') ?>">
                            </div>
                        </div>
                         <div class="col-12">
                            <label class="form-label small fw-bold text-muted">HQ Address</label>
                            <textarea name="contact_address" class="form-control input-v2" rows="2"><?= htmlspecialchars($settings['contact_address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="app-card mb-4">
                    <div class="card-header-v2">
                        <span class="card-title-v2"><i class="bi bi-bell me-2"></i>Notification Gateways</span>
                    </div>
                    
                    <h6 class="fw-bold text-secondary mb-3 mt-1 small text-uppercase">SMTP Email Server</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">SMTP Host</label>
                            <input type="text" name="smtp_host" class="form-control input-v2" value="<?= htmlspecialchars($settings['smtp_host'] ?? 'smtp.hostinger.com') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">SMTP User</label>
                            <input type="text" name="smtp_user" class="form-control input-v2" value="<?= htmlspecialchars($settings['smtp_user'] ?? 'info@documantraa.in') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-muted">SMTP Password</label>
                            <input type="password" name="smtp_pass" class="form-control input-v2" value="<?= htmlspecialchars($settings['smtp_pass'] ?? 'l]l$+954F') ?>">
                        </div>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <h6 class="fw-bold text-secondary mb-3 small text-uppercase">WhatsApp (CallMeBot) <span class="badge bg-success-subtle text-success ms-2">Free API</span></h6>
                    <div class="alert alert-light border small text-muted">
                        To activate: Send <code>I allow callmebot to send me messages</code> to <b>+91 755 883 4483</b> on WhatsApp.
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Alert Phone Number</label>
                            <input type="text" name="whatsapp_phone" class="form-control input-v2" value="<?= htmlspecialchars($settings['whatsapp_phone'] ?? '') ?>" placeholder="e.g. 917558834483">
                            <div class="form-text">Currently configured to receive all system alerts.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">API Key</label>
                            <input type="text" name="whatsapp_apikey" class="form-control input-v2" value="<?= htmlspecialchars($settings['whatsapp_apikey'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="text-end mb-5">
                    <button type="submit" class="btn-v2 btn-primary-v2 px-5 py-2">
                        <i class="bi bi-save me-2"></i> Save Configurations
                    </button>
                </div>
            </form>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
</body>
</html>
