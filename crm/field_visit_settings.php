<?php
require_once 'app_init.php';
require_once 'auth.php';

// Access Control
if (!in_array($_SESSION['role'], ['super_admin'])) {
    header("Location: dashboard.php");
    exit;
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $allowed_keys = [
            'visit_geofence_radius',
            'visit_auto_verify',
            'points_hospital_visit',
            'points_lab_visit',
            'points_pharmacy_visit',
            'points_patient_selfie'
        ];

        $pdo->beginTransaction();
        foreach ($allowed_keys as $key) {
            if (isset($_POST[$key])) {
                $value = sanitize_input($_POST[$key]);
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
                $stmt->execute([$key]);
                if ($stmt->fetchColumn()) {
                    $update = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $update->execute([$value, $key]);
                } else {
                    $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
                    $insert->execute([$key, $value]);
                }
            }
        }
        $pdo->commit();
        $message = "Field Visit settings updated successfully!";
        $message_type = "success";
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $message_type = "danger";
    }
}

$settings = get_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Field Visit Settings - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Field Visit Settings</h1>
                    <p class="text-muted mb-0 small">Configure geolocation radius and verification rules.</p>
                </div>
            </div>
        </header>

        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                    <?= $message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="row g-4">
                    <!-- Geolocation Settings -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Geolocation Config</h5>
                                
                                <div class="mb-4">
                                    <label class="stat-label mb-1">Geofence Radius (Meters)</label>
                                    <input type="number" name="visit_geofence_radius" class="input-v2" 
                                           value="<?= htmlspecialchars($settings['visit_geofence_radius'] ?? '200') ?>" required min="50" max="5000">
                                    <div class="small text-muted mt-1">Distance from point to auto-verify arrival.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="stat-label mb-1">Auto-Verify Evidence</label>
                                    <select name="visit_auto_verify" class="input-v2">
                                        <option value="1" <?= ($settings['visit_auto_verify'] ?? '1') == '1' ? 'selected' : '' ?>>Enabled (System AI)</option>
                                        <option value="0" <?= ($settings['visit_auto_verify'] ?? '1') == '0' ? 'selected' : '' ?>>Disabled (Manual Review)</option>
                                    </select>
                                    <div class="small text-muted mt-1">Automatically approve visit proof via Documantraa AI.</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Points Configuration -->
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-4"><i class="bi bi-star-fill text-warning me-2"></i>Visit Points (Earnings)</h5>
                                
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="stat-label mb-1">Hospital Visit</label>
                                        <input type="number" name="points_hospital_visit" class="input-v2" 
                                               value="<?= htmlspecialchars($settings['points_hospital_visit'] ?? '2') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="stat-label mb-1">Lab Visit</label>
                                        <input type="number" name="points_lab_visit" class="input-v2" 
                                               value="<?= htmlspecialchars($settings['points_lab_visit'] ?? '1') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="stat-label mb-1">Pharmacy Visit</label>
                                        <input type="number" name="points_pharmacy_visit" class="input-v2" 
                                               value="<?= htmlspecialchars($settings['points_pharmacy_visit'] ?? '1') ?>">
                                    </div>
                                    <div class="col-6">
                                        <label class="stat-label mb-1">Patient Selfie</label>
                                        <input type="number" name="points_patient_selfie" class="input-v2" 
                                               value="<?= htmlspecialchars($settings['points_patient_selfie'] ?? '1') ?>">
                                    </div>
                                </div>
                                <div class="small text-muted mt-3">Points assigned to FO for calculating incentives.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary px-5 py-2">
                        <i class="bi bi-save me-2"></i> Save Field Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/validation.js"></script>
</body>
</html>
