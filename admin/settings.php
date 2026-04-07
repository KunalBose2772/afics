<?php
require_once 'auth.php';
require_once '../config/db.php';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Text Settings
    if (isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            // Skip if this key is marked for removal
            if (isset($_POST['remove_image'][$key])) {
                continue; 
            }
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
    }

    // Handle Image Removal
    if (isset($_POST['remove_image'])) {
        foreach ($_POST['remove_image'] as $key => $val) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = ?");
            $stmt->execute([$key]);
        }
    }

    // Handle File Uploads
    if (!empty($_FILES)) {
        $upload_dir = '../assets/images/uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

        foreach ($_FILES as $key => $file) {
            if ($file['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $key . '_' . time() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $db_path = 'assets/images/uploads/' . $filename;
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$db_path, $key]);
                }
            }
        }
    }
    
    $success = "Settings updated successfully!";
}

// Fetch Settings grouped
$stmt = $pdo->query("SELECT * FROM settings ORDER BY setting_group DESC, id ASC");
$settings_rows = $stmt->fetchAll();
$grouped_settings = [];
foreach ($settings_rows as $row) {
    $grouped_settings[$row['setting_group']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>Global Settings - Documantraa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <!-- Pickr for Color Picking with Opacity -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4" style="margin-left: 280px;">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                    <div>
                        <h2 class="mb-0 fw-bold">Global Settings</h2>
                        <p class="text-secondary mb-0">Manage your website's configuration and design.</p>
                    </div>
                    <button type="submit" form="settingsForm" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold"><i class="bi bi-save me-2"></i> Save Changes</button>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show glass-card border-0 text-white bg-success bg-opacity-25" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= $success ?>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <ul class="nav nav-tabs mb-4 border-0" id="settingsTab" role="tablist">
                        <?php 
                        $active = 'active';
                        foreach ($grouped_settings as $group => $items): 
                            // Skip CRM Appearance - it has its own dedicated page
                            if ($group === 'CRM Appearance') {
                                continue;
                            }
                        ?>
                            <li class="nav-item me-2" role="presentation">
                                <button class="nav-link text-uppercase fw-bold rounded-top px-4 py-3 <?= $active ?>" id="<?= $group ?>-tab" data-bs-toggle="tab" data-bs-target="#<?= $group ?>" type="button" role="tab" aria-controls="<?= $group ?>" aria-selected="true"><?= $group ?></button>
                            </li>
                        <?php 
                        $active = '';
                        endforeach; 
                        ?>
                        <!-- CRM Appearance Link -->
                        <li class="nav-item me-2">
                            <a href="crm_appearance" class="nav-link text-uppercase fw-bold rounded-top px-4 py-3" style="background: rgba(96, 205, 255, 0.1); color: #60cdff;">
                                <i class="bi bi-palette me-2"></i>CRM APPEARANCE
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="settingsTabContent">
                        <?php 
                        $active = 'show active';
                        foreach ($grouped_settings as $group => $items): 
                            // Skip CRM Appearance - it has its own dedicated page
                            if ($group === 'CRM Appearance') {
                                continue;
                            }
                        ?>
                            <div class="tab-pane fade <?= $active ?>" id="<?= $group ?>" role="tabpanel" aria-labelledby="<?= $group ?>-tab">
                                <?php if ($group === 'SMTP'): ?>
                                    <div class="alert alert-info border-0 bg-info bg-opacity-10 text-info mb-4">
                                        <i class="bi bi-info-circle-fill me-2"></i>
                                        <strong>SMTP Configuration:</strong> Use this section to configure the email account that will send system notifications and receive website inquiries. 
                                        Only use the <strong>Inquiry Notification Email</strong> field if you wish to set a specific "From" address (otherwise it defaults to the username).
                                    </div>
                                <?php endif; ?>

                                <?php
                                // Custom Sort for Legal Group
                                if ($group === 'Legal') {
                                    $custom_order = [
                                        'footer_legal_privacy_text' => 1,
                                        'legal_privacy_image' => 2,
                                        'footer_legal_terms_text' => 3,
                                        'legal_terms_image' => 4,
                                        'legal_privacy_content' => 5,
                                        'legal_terms_content' => 6
                                    ];
                                    usort($items, function($a, $b) use ($custom_order) {
                                        $oa = $custom_order[$a['setting_key']] ?? 99;
                                        $ob = $custom_order[$b['setting_key']] ?? 99;
                                        return $oa <=> $ob;
                                    });
                                }
                                ?>

                                <?php if ($group === 'Legal'): ?>
                                    <style>
                                        @media (min-width: 992px) {
                                            .legal-grid {
                                                grid-template-columns: repeat(2, 1fr) !important;
                                            }
                                        }
                                    </style>
                                <?php endif; ?>

                                <div class="bento-grid <?= $group === 'Legal' ? 'legal-grid' : '' ?>">
                                    <?php foreach ($items as $item): 
                                        $col_style = '';
                                        // Make content textareas take full width
                                        if (strpos($item['setting_key'], 'content') !== false && strpos($item['setting_key'], 'legal') !== false) {
                                            $col_style = 'grid-column: 1 / -1;';
                                        }
                                    ?>
                                        <div class="glass-card d-flex flex-column h-100" style="<?= $col_style ?>">
                                            <div class="mb-3">
                                                <label class="form-label d-block">
                                                    <?= ucwords(str_replace(['_', 'smtp '], [' ', ''], $item['setting_key'])) ?>
                                                </label>
                                                <small class="text-muted" style="font-size: 0.7rem; font-family: monospace;"><?= $item['setting_key'] ?></small>
                                            </div>
                                            
                                            <div class="mt-auto">
                                                <?php 
                                                // Check explicit type first
                                                $type = $item['setting_type'] ?? '';
                                                
                                                if ($type === 'select' && $item['setting_key'] === 'smtp_encryption'): ?>
                                                    <select class="form-control form-select" name="settings[<?= $item['setting_key'] ?>]">
                                                        <option value="ssl" <?= $item['setting_value'] === 'ssl' ? 'selected' : '' ?>>SSL</option>
                                                        <option value="tls" <?= $item['setting_value'] === 'tls' ? 'selected' : '' ?>>TLS</option>
                                                        <option value="" <?= $item['setting_value'] === '' ? 'selected' : '' ?>>None</option>
                                                    </select>
                                                <?php elseif ($type === 'password'): ?>
                                                    <div class="input-group">
                                                        <input type="password" class="form-control border-end-0" id="pwd_<?= $item['setting_key'] ?>" name="settings[<?= $item['setting_key'] ?>]" value="<?= htmlspecialchars($item['setting_value']) ?>">
                                                        <button class="btn btn-outline-secondary border-start-0 text-white" type="button" onclick="const i = document.getElementById('pwd_<?= $item['setting_key'] ?>'); i.type = i.type === 'password' ? 'text' : 'password'; this.querySelector('i').classList.toggle('bi-eye'); this.querySelector('i').classList.toggle('bi-eye-slash');" style="background: rgba(0,0,0,0.2); border-color: var(--glass-border);">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                    </div>
                                                <?php elseif ((strpos($item['setting_key'], 'font_family') !== false || $item['setting_key'] === 'heading_font') && strpos($item['setting_key'], 'size') === false): 
                                                    $fonts = ['Times New Roman', 'Roboto', 'Nunito', 'Manrope', 'Cormorant Garamond', 'Poppins'];
                                                    $current_val = str_replace("'", "", $item['setting_value']); // Handle stored quotes
                                                ?>
                                                    <select class="form-control form-select" name="settings[<?= $item['setting_key'] ?>]">
                                                        <?php foreach ($fonts as $font): ?>
                                                            <option value="<?= $font ?>" <?= $current_val === $font ? 'selected' : '' ?>><?= $font ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php elseif ($type === 'text'): ?>
                                                     <input type="text" class="form-control" name="settings[<?= $item['setting_key'] ?>]" value="<?= htmlspecialchars($item['setting_value']) ?>">
                                                
                                                <?php // Fallback to existing logic based on key names
                                                elseif (strpos($item['setting_key'], 'color') !== false): ?>
                                                    <div class="input-group input-group-sm">
                                                        <input type="text" name="settings[<?= $item['setting_key'] ?>]" id="<?= $item['setting_key'] ?>" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($item['setting_value']) ?>">
                                                        <div id="picker_<?= $item['setting_key'] ?>"></div>
                                                    </div>
                                                <?php elseif (strpos($item['setting_key'], 'image') !== false || strpos($item['setting_key'], 'logo') !== false): ?>
                                                    <div class="input-group mb-3">
                                                        <input type="file" class="form-control" name="<?= $item['setting_key'] ?>" accept="image/*">
                                                    </div>
                                                    <?php if (!empty($item['setting_value'])): ?>
                                                        <div class="position-relative rounded overflow-hidden border border-secondary" style="height: 150px; background: #000;">
                                                            <img src="../<?= htmlspecialchars($item['setting_value']) ?>" alt="Preview" class="w-100 h-100 object-fit-cover" style="opacity: 0.8;">
                                                            <div class="position-absolute bottom-0 start-0 w-100 p-2 bg-dark bg-opacity-75">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="remove_image[<?= $item['setting_key'] ?>]" value="1" id="remove_<?= $item['setting_key'] ?>">
                                                                    <label class="form-check-label text-danger small fw-bold" for="remove_<?= $item['setting_key'] ?>">
                                                                        <i class="bi bi-trash"></i> Remove
                                                                    </label>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" name="settings[<?= $item['setting_key'] ?>]" value="<?= htmlspecialchars($item['setting_value']) ?>">
                                                    <?php else: ?>
                                                        <div class="text-center p-4 border border-dashed border-secondary rounded text-muted">
                                                            <i class="bi bi-image fs-1 d-block mb-2"></i>
                                                            No Image Uploaded
                                                        </div>
                                                        <input type="hidden" name="settings[<?= $item['setting_key'] ?>]" value="">
                                                    <?php endif; ?>
                                                <?php elseif ($item['setting_key'] === 'enable_animations'): ?>
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" role="switch" name="settings[<?= $item['setting_key'] ?>]" value="1" <?= $item['setting_value'] == '1' ? 'checked' : '' ?>>
                                                        <label class="form-check-label text-white">Enable Animations</label>
                                                    </div>
                                                    <input type="hidden" name="settings[<?= $item['setting_key'] ?>]" value="0"> <!-- Fallback for unchecked -->
                                                <?php elseif ($item['setting_key'] === 'animation_easing'): ?>
                                                    <select class="form-select" name="settings[<?= $item['setting_key'] ?>]">
                                                        <option value="ease" <?= $item['setting_value'] == 'ease' ? 'selected' : '' ?>>Ease</option>
                                                        <option value="ease-in" <?= $item['setting_value'] == 'ease-in' ? 'selected' : '' ?>>Ease In</option>
                                                        <option value="ease-out" <?= $item['setting_value'] == 'ease-out' ? 'selected' : '' ?>>Ease Out</option>
                                                        <option value="ease-in-out" <?= $item['setting_value'] == 'ease-in-out' ? 'selected' : '' ?>>Ease In Out</option>
                                                        <option value="linear" <?= $item['setting_value'] == 'linear' ? 'selected' : '' ?>>Linear</option>
                                                    </select>
                                                <?php elseif ($type === 'textarea' || (strpos($item['setting_key'], 'content') !== false && strpos($item['setting_key'], 'legal') !== false)): ?>
                                                    <textarea class="form-control" name="settings[<?= $item['setting_key'] ?>]" rows="10" style="min-height: 300px;"><?= htmlspecialchars($item['setting_value']) ?></textarea>
                                                <?php elseif (strpos($item['setting_key'], 'text') !== false && strlen($item['setting_value']) > 50): ?>
                                                    <textarea class="form-control" name="settings[<?= $item['setting_key'] ?>]" rows="4"><?= htmlspecialchars($item['setting_value']) ?></textarea>
                                                <?php else: ?>
                                                    <input type="text" class="form-control" 
                                                           name="settings[<?= $item['setting_key'] ?>]" 
                                                           value="<?= htmlspecialchars($item['setting_value']) ?>">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php 
                        $active = '';
                        endforeach; 
                        ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const createPicker = (el, inputId) => {
                const input = document.getElementById(inputId);
                if (!input) return;

                const initialColor = input.value ? input.value : '#42445a';
                
                const pickr = Pickr.create({
                    el: el,
                    theme: 'classic',
                    default: initialColor,
                    components: {
                        preview: true,
                        opacity: true,
                        hue: true,
                        interaction: {
                            hex: true,
                            rgba: true,
                            input: true,
                            save: true
                        }
                    }
                });

                pickr.on('save', (color) => {
                    input.value = color.toRGBA().toString(0);
                    pickr.hide();
                });
                
                pickr.on('change', (color) => {
                     input.value = color.toRGBA().toString(0);
                });
            };

            // Auto-initialize all pickers
            const pickers = document.querySelectorAll('div[id^="picker_"]');
            pickers.forEach(picker => {
                const inputId = picker.id.replace('picker_', '');
                createPicker(picker, inputId);
            });
        });
    </script>
</body>
</html>
