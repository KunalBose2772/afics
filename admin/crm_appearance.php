<?php
require_once 'auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Image Upload
    if (isset($_FILES['bg_image']) && $_FILES['bg_image']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $ext = pathinfo($_FILES['bg_image']['name'], PATHINFO_EXTENSION);
        $filename = 'crm_bg_' . time() . '.' . $ext;
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['bg_image']['tmp_name'], $target_file)) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_group, setting_key, setting_value) VALUES ('CRM Appearance', 'crm_bg_image', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$filename, $filename]);
        }
    }

    // Handle Image Removal
    if (isset($_POST['remove_bg_image']) && $_POST['remove_bg_image'] == '1') {
        $stmt = $pdo->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = 'crm_bg_image'");
        $stmt->execute();
    }

    if (isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_group, setting_key, setting_value) VALUES ('CRM Appearance', ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $value, $value]);
        }
    }
    header('Location: crm_appearance?success=1');
    exit;
}

// Handle Save Preset
if (isset($_POST['save_preset']) && !empty($_POST['preset_name'])) {
    $name = trim($_POST['preset_name']);
    $preset_settings = $_POST['settings'] ?? [];
    
    // Also include background type logic if it's separate?
    // In our form, everything relevant is likely in $_POST['settings'] OR handled separately.
    // The previous form handling loops through $_POST['settings'].
    // So ensuring we capture that array is key.
    
    $json = json_encode($preset_settings);
    
    $stmt = $pdo->prepare("INSERT INTO crm_presets (name, settings_json) VALUES (?, ?)");
    $stmt->execute([$name, $json]);
    
    header('Location: crm_appearance?success=preset_saved');
    exit;
}

// Handle Delete Preset
if (isset($_GET['delete_preset'])) {
    $id = intval($_GET['delete_preset']);
    $pdo->query("DELETE FROM crm_presets WHERE id = $id");
    header('Location: crm_appearance?success=preset_deleted');
    exit;
}

// Fetch Custom Presets
$custom_presets = [];
try {
    $custom_presets = $pdo->query("SELECT * FROM crm_presets ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* Table might not exist yet if migration failed, ignore */ }

// Fetch All Settings (so sidebar logo works + CRM settings are available)
$settings = get_settings($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>CRM Appearance - Documantraa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">

    
    <!-- Pickr for Color Picking with Opacity -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/themes/classic.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/@simonwep/pickr/dist/pickr.min.js"></script>
    
    <style>
        .color-preview {
            width: 100%;
            height: 100px;
            border-radius: 8px;
            margin-bottom: 1rem;
            border: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4" style="margin-left: 280px;">
            <div class="container-fluid">
                <!-- Header Actions -->
                <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                    <div>
                        <h2 class="mb-0 fw-bold">CRM Appearance</h2>
                        <p class="text-secondary mb-0">Customize the look and feel of the CRM portal.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-info rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#savePresetModal">
                            <i class="bi bi-bookmark-plus me-2"></i> Save as Preset
                        </button>
                        <button type="submit" form="settingsForm" class="btn btn-primary rounded-pill px-4">
                            <i class="bi bi-save me-2"></i> Save Changes
                        </button>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success d-flex align-items-center" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    Settings updated successfully!
                </div>
                <?php endif; ?>

                <!-- Presets Section -->
                <div class="mb-5">
                    <h5 class="text-white mb-3 fw-bold"><i class="bi bi-palette2 me-2"></i> Quick Presets</h5>
                    
                    <ul class="nav nav-pills mb-3" id="pills-tab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active rounded-pill px-4" id="pills-system-tab" data-bs-toggle="pill" data-bs-target="#pills-system" type="button" role="tab">System Presets</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link rounded-pill px-4" id="pills-custom-tab" data-bs-toggle="pill" data-bs-target="#pills-custom" type="button" role="tab">My Presets (<?= count($custom_presets) ?>)</button>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="pills-tabContent">
                        <!-- System Presets -->
                        <div class="tab-pane fade show active" id="pills-system" role="tabpanel">
                            <div class="row g-4">
                                <div class="col-md-4">
                                    <div class="glass-card p-3 text-center h-100 hover-lift cursor-pointer" onclick="applyPreset('windows11')" style="cursor: pointer;">
                                        <div class="mb-3 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #60cdff; color: #000;">
                                            <i class="bi bi-windows fs-4"></i>
                                        </div>
                                        <h6 class="text-white fw-bold">Windows 11 Dark</h6>
                                        <p class="text-secondary small mb-0">Mica-inspired dark theme with blue accents.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="glass-card p-3 text-center h-100 hover-lift cursor-pointer" onclick="applyPreset('ios')" style="cursor: pointer;">
                                        <div class="mb-3 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #0A84FF; color: #fff;">
                                            <i class="bi bi-apple fs-4"></i>
                                        </div>
                                        <h6 class="text-white fw-bold">iOS Dark</h6>
                                        <p class="text-secondary small mb-0">Deep blacks, blur effects, and Apple blue.</p>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="glass-card p-3 text-center h-100 hover-lift cursor-pointer" onclick="applyPreset('shiny')" style="cursor: pointer;">
                                        <div class="mb-3 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: linear-gradient(45deg, #ff00cc, #333399); color: #fff;">
                                            <i class="bi bi-gem fs-4"></i>
                                        </div>
                                        <h6 class="text-white fw-bold">Shiny Glass</h6>
                                        <p class="text-secondary small mb-0">Vibrant gradients and ultra-glassy look.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Presets -->
                        <div class="tab-pane fade" id="pills-custom" role="tabpanel">
                            <div class="row g-4">
                                <?php if (empty($custom_presets)): ?>
                                    <div class="col-12 text-center text-white-50 py-5">
                                        <i class="bi bi-bookmark-plus fs-1 mb-3 d-block"></i>
                                        <p>No custom presets yet. Customize your theme and click "Save as Preset".</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($custom_presets as $preset): ?>
                                        <div class="col-md-3">
                                             <div class="glass-card p-3 text-center h-100 hover-lift position-relative">
                                                <button onclick="if(confirm('Delete preset?')) location.href='?delete_preset=<?= $preset['id'] ?>'" class="btn btn-sm btn-link text-danger position-absolute top-0 end-0 p-2"><i class="bi bi-trash"></i></button>
                                                
                                                <div class="cursor-pointer" onclick="applyCustomPreset(<?= $preset['id'] ?>)">
                                                    <div class="mb-3 rounded-circle d-inline-flex align-items-center justify-content-center border border-secondary" style="width: 50px; height: 50px; background: rgba(255,255,255,0.1); color: #fff;">
                                                        <i class="bi bi-person-badge fs-4"></i>
                                                    </div>
                                                    <h6 class="text-white fw-bold text-truncate"><?= htmlspecialchars($preset['name']) ?></h6>
                                                    <p class="text-secondary small mb-0"><?= date('M d, Y', strtotime($preset['created_at'])) ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modal for Saving Preset -->
                <div class="modal fade" id="savePresetModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content glass-panel border-0">
                            <div class="modal-header border-bottom border-secondary">
                                <h5 class="modal-title text-white">Save Current Theme as Preset</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label text-white">Preset Name</label>
                                    <input type="text" id="new_preset_name" class="form-control bg-dark text-white border-secondary" required placeholder="e.g. My Dark Theme">
                                </div>
                            </div>
                            <div class="modal-footer border-top border-secondary">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" onclick="submitPreset()" class="btn btn-primary">Save Preset</button>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="POST" id="settingsForm" enctype="multipart/form-data">
                    <div class="row g-4">

                        <!-- Background Builder -->
                        <div class="col-md-6">
                            <div class="glass-panel p-4 h-100">
                                <h5 class="mb-4 text-white"><i class="bi bi-image me-2"></i> Background Builder</h5>
                                
                                <!-- Image Upload -->
                                <div class="mb-4">
                                    <label class="form-label text-white-50 small">Background Image</label>
                                    <input type="file" name="bg_image" class="form-control bg-dark text-white border-secondary mb-2">
                                    <?php if (!empty($settings['crm_bg_image'])): ?>
                                        <div class="d-flex align-items-center justify-content-between bg-dark p-2 rounded border border-secondary">
                                            <span class="text-white small"><i class="bi bi-check-circle text-success me-2"></i> Image Set</span>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="remove_bg_image" value="1" id="removeBg">
                                                <label class="form-check-label text-danger small" for="removeBg">Remove</label>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <hr class="border-secondary opacity-25 my-4">

                                <!-- Gradient Builder -->
                                <h6 class="text-white-50 mb-3 small text-uppercase">Gradient Fallback</h6>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label text-white-50 small">Type</label>
                                        <select name="settings[crm_bg_type]" class="form-select bg-dark text-white border-secondary">
                                            <option value="radial" <?= ($settings['crm_bg_type'] ?? '') == 'radial' ? 'selected' : '' ?>>Radial</option>
                                            <option value="linear" <?= ($settings['crm_bg_type'] ?? '') == 'linear' ? 'selected' : '' ?>>Linear</option>
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label text-white-50 small">Start Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_bg_color_1]" id="crm_bg_color_1" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_bg_color_1'] ?? '#2d1b4e') ?>">
                                            <div id="picker_bg_1"></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label text-white-50 small">Mid Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_bg_color_2]" id="crm_bg_color_2" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_bg_color_2'] ?? '#1a1a2e') ?>">
                                            <div id="picker_bg_2"></div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <label class="form-label text-white-50 small">End Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_bg_color_3]" id="crm_bg_color_3" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_bg_color_3'] ?? '#000000') ?>">
                                            <div id="picker_bg_3"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>



                        <!-- Sidebar Styling -->
                        <div class="col-md-6">
                            <div class="glass-panel p-4 h-100">
                                <h5 class="mb-4 text-white"><i class="bi bi-layout-sidebar me-2"></i> Sidebar Styling</h5>
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Background</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_sidebar_bg]" id="crm_sidebar_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_sidebar_bg'] ?? '') ?>">
                                            <div id="picker_sidebar"></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Text Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_sidebar_text_color]" id="crm_sidebar_text_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_sidebar_text_color'] ?? '') ?>">
                                            <div id="picker_sidebar_text"></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Active Link</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_sidebar_active_color]" id="crm_sidebar_active_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_sidebar_active_color'] ?? '') ?>">
                                            <div id="picker_sidebar_active"></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label text-white-50 small">Icon Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_sidebar_icon_color]" id="crm_sidebar_icon_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_sidebar_icon_color'] ?? '') ?>">
                                            <div id="picker_sidebar_icon"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Graph Elements -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-pie-chart me-2"></i> Graph Elements</h5>
                                <div class="row g-3">
                                    <div class="col-md-2">
                                        <label class="form-label text-white-50 small">Color 1</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_chart_color_1]" id="crm_chart_color_1" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_chart_color_1'] ?? '') ?>">
                                            <div id="picker_chart_1"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-white-50 small">Color 2</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_chart_color_2]" id="crm_chart_color_2" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_chart_color_2'] ?? '') ?>">
                                            <div id="picker_chart_2"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-white-50 small">Color 3</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_chart_color_3]" id="crm_chart_color_3" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_chart_color_3'] ?? '') ?>">
                                            <div id="picker_chart_3"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-white-50 small">Color 4</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_chart_color_4]" id="crm_chart_color_4" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_chart_color_4'] ?? '') ?>">
                                            <div id="picker_chart_4"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label text-white-50 small">Color 5</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_chart_color_5]" id="crm_chart_color_5" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_chart_color_5'] ?? '') ?>">
                                            <div id="picker_chart_5"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Global Text & Colors -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-palette me-2"></i> Global Text & Colors</h5>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Primary Accent</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_accent_color]" id="crm_accent_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_accent_color'] ?? '') ?>">
                                            <div id="picker_accent"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Main Body Text</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_text_color]" id="crm_text_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_text_color'] ?? '') ?>">
                                            <div id="picker_text"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Headings (H1-H6)</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_heading_color]" id="crm_heading_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_heading_color'] ?? '') ?>">
                                            <div id="picker_heading"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Subtext / Muted</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_subtext_color]" id="crm_subtext_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_subtext_color'] ?? '') ?>">
                                            <div id="picker_subtext"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">Used for .text-secondary classes</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Secondary Text</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_secondary_color]" id="crm_secondary_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_secondary_color'] ?? '') ?>">
                                            <div id="picker_secondary"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">Alternative secondary text</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Tab Active Text</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_tab_active_color]" id="crm_tab_active_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_tab_active_color'] ?? '') ?>">
                                            <div id="picker_tab_active"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">For .nav-tabs .nav-link.active</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Tab Inactive Text</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_tab_inactive_color]" id="crm_tab_inactive_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_tab_inactive_color'] ?? '') ?>">
                                            <div id="picker_tab_inactive"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">For .nav-tabs .nav-link (not active)</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Links</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_link_color]" id="crm_link_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_link_color'] ?? '') ?>">
                                            <div id="picker_link"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Link Hover</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_link_hover_color]" id="crm_link_hover_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_link_hover_color'] ?? '') ?>">
                                            <div id="picker_link_hover"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics Cards Styling -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-bar-chart me-2"></i> Statistics Cards</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label text-white-50 small">Icon Color</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_stat_icon_color]" id="crm_stat_icon_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_stat_icon_color'] ?? '') ?>">
                                            <div id="picker_stat_icon"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">Icon color in dashboard stat cards</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label text-white-50 small">Icon Background</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_stat_icon_bg]" id="crm_stat_icon_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_stat_icon_bg'] ?? '') ?>">
                                            <div id="picker_stat_icon_bg"></div>
                                        </div>
                                        <small class="text-white-50" style="font-size: 0.7rem;">Background behind icons in stat cards</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Button Styling -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-hand-index-thumb me-2"></i> Button Styling</h5>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <h6 class="text-white-50 mb-3 small text-uppercase">Normal State</h6>
                                        <div class="row g-3">
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Text</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_text_color]" id="crm_btn_text_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_text_color'] ?? '') ?>">
                                                    <div id="picker_btn_text"></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Background</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_bg_color]" id="crm_btn_bg_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_bg_color'] ?? '') ?>">
                                                    <div id="picker_btn_bg"></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Border</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_border_color]" id="crm_btn_border_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_border_color'] ?? '') ?>">
                                                    <div id="picker_btn_border"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="text-white-50 mb-3 small text-uppercase">Hover State</h6>
                                        <div class="row g-3">
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Text</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_hover_text_color]" id="crm_btn_hover_text_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_hover_text_color'] ?? '') ?>">
                                                    <div id="picker_btn_hover_text"></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Background</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_hover_bg_color]" id="crm_btn_hover_bg_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_hover_bg_color'] ?? '') ?>">
                                                    <div id="picker_btn_hover_bg"></div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label text-white-50 small">Border</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_btn_hover_border_color]" id="crm_btn_hover_border_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_btn_hover_border_color'] ?? '') ?>">
                                                    <div id="picker_btn_hover_border"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Content Styling (Cards, Tables, Icons) -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-window-sidebar me-2"></i> Content Styling</h5>
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Card Title</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_card_title_color]" id="crm_card_title_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_card_title_color'] ?? '') ?>">
                                            <div id="picker_card_title"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Stat Value</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_stat_value_color]" id="crm_stat_value_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_stat_value_color'] ?? '') ?>">
                                            <div id="picker_stat_value"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Large Icons</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_icon_color]" id="crm_icon_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_icon_color'] ?? '') ?>">
                                            <div id="picker_icon"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Table Headers</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_table_head_color]" id="crm_table_head_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_table_head_color'] ?? '') ?>">
                                            <div id="picker_table_head"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Table Body Text</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_table_body_color]" id="crm_table_body_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_table_body_color'] ?? '') ?>">
                                            <div id="picker_table_body"></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label text-white-50 small">Table Borders</label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" name="settings[crm_table_border_color]" id="crm_table_border_color" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_table_border_color'] ?? '') ?>">
                                            <div id="picker_table_border"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- UI Elements (Glass, Inputs) -->
                        <div class="col-12">
                            <div class="glass-panel p-4">
                                <h5 class="mb-4 text-white"><i class="bi bi-layers me-2"></i> UI Base Elements</h5>
                                <div class="row g-3">
                                    <!-- Container & Card Backgrounds -->
                                    <div class="col-12 mb-3">
                                        <h6 class="text-white-50 small text-uppercase border-bottom border-secondary pb-2 mb-3">Container & Card Backgrounds</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Card Background</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_card_bg]" id="crm_card_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_card_bg'] ?? '') ?>">
                                                    <div id="picker_card_bg"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Glass Panel BG</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_glass_bg]" id="crm_glass_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_glass_bg'] ?? '') ?>">
                                                    <div id="picker_glass_bg"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Borders & Inputs -->
                                    <div class="col-12">
                                        <h6 class="text-white-50 small text-uppercase border-bottom border-secondary pb-2 mb-3">Borders & Inputs</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Glass Border</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_glass_border]" id="crm_glass_border" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_glass_border'] ?? '') ?>">
                                                    <div id="picker_glass_border"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Input Background</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_input_bg]" id="crm_input_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_input_bg'] ?? '') ?>">
                                                    <div id="picker_input_bg"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Dropdown Menus -->
                                    <div class="col-12">
                                        <h6 class="text-white-50 small text-uppercase border-bottom border-secondary pb-2 mb-3">Dropdown Menus</h6>
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Background</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_dropdown_bg]" id="crm_dropdown_bg" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_dropdown_bg'] ?? '') ?>">
                                                    <div id="picker_dropdown_bg"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label text-white-50 small">Text Color</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" name="settings[crm_dropdown_text]" id="crm_dropdown_text" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($settings['crm_dropdown_text'] ?? '') ?>">
                                                    <div id="picker_dropdown_text"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    

    <script>
        window.crmPickers = {};

        // Custom Presets from DB
        const customPresets = {
            <?php foreach ($custom_presets as $cp): ?>
            'custom_<?= $cp['id'] ?>': <?= $cp['settings_json'] ?: '{}' ?>,
            <?php endforeach; ?>
        };

        const createPicker = (el, inputId, onChange = null) => {
            const input = document.getElementById(inputId);
            const initialColor = input && input.value ? input.value : '#42445a';
            
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
                if(input) input.value = color.toRGBA().toString(0);
                pickr.hide();
                if(onChange) onChange(color.toRGBA().toString(0));
            });
            
            pickr.on('change', (color) => {
                 if(input) input.value = color.toRGBA().toString(0);
                 if(onChange) onChange(color.toRGBA().toString(0));
            });

            // Store global reference
            window.crmPickers[inputId] = pickr;

            return pickr;
        };

        // UI Pickers
        createPicker('#picker_glass_bg', 'crm_glass_bg');
        createPicker('#picker_glass_border', 'crm_glass_border');
        createPicker('#picker_accent', 'crm_accent_color');
        createPicker('#picker_text', 'crm_text_color');
        createPicker('#picker_sidebar', 'crm_sidebar_bg');
        
        // Extended Pickers
        createPicker('#picker_heading', 'crm_heading_color');
        createPicker('#picker_subtext', 'crm_subtext_color');
        createPicker('#picker_card_bg', 'crm_card_bg');
        createPicker('#picker_input_bg', 'crm_input_bg');
        
        createPicker('#picker_sidebar_text', 'crm_sidebar_text_color');
        createPicker('#picker_sidebar_active', 'crm_sidebar_active_color');
        createPicker('#picker_sidebar_icon', 'crm_sidebar_icon_color');
        
        createPicker('#picker_card_title', 'crm_card_title_color');
        createPicker('#picker_stat_value', 'crm_stat_value_color');
        createPicker('#picker_icon', 'crm_icon_color');
        
        createPicker('#picker_table_head', 'crm_table_head_color');
        createPicker('#picker_table_body', 'crm_table_body_color');
        createPicker('#picker_table_border', 'crm_table_border_color');
        
        createPicker('#picker_link', 'crm_link_color');
        createPicker('#picker_link_hover', 'crm_link_hover_color');
        
        // Button Pickers
        createPicker('#picker_btn_text', 'crm_btn_text_color');
        createPicker('#picker_btn_bg', 'crm_btn_bg_color');
        createPicker('#picker_btn_border', 'crm_btn_border_color');
        createPicker('#picker_btn_hover_text', 'crm_btn_hover_text_color');
        createPicker('#picker_btn_hover_bg', 'crm_btn_hover_bg_color');
        createPicker('#picker_btn_hover_border', 'crm_btn_hover_border_color');
        
        // New Pickers
        createPicker('#picker_secondary', 'crm_secondary_color');
        createPicker('#picker_tab_active', 'crm_tab_active_color');
        createPicker('#picker_tab_inactive', 'crm_tab_inactive_color');
        createPicker('#picker_stat_icon', 'crm_stat_icon_color');
        createPicker('#picker_stat_icon_bg', 'crm_stat_icon_bg');
        
        // Dropdown Pickers
        createPicker('#picker_dropdown_bg', 'crm_dropdown_bg');
        createPicker('#picker_dropdown_text', 'crm_dropdown_text');

        // Background Pickers
        createPicker('#picker_bg_1', 'crm_bg_color_1');
        createPicker('#picker_bg_2', 'crm_bg_color_2');
        createPicker('#picker_bg_3', 'crm_bg_color_3');
        
        // Chart Pickers
        for(let i=1; i<=5; i++) {
            createPicker('#picker_chart_'+i, 'crm_chart_color_'+i);
        }

        // --- PRESETS LOGIC ---
        const presets = {
            'windows11': {
                'crm_bg_type': 'linear',
                'crm_bg_color_1': '#202020',
                'crm_bg_color_2': '#1f1f1f',
                'crm_bg_color_3': '#000000',
                'crm_accent_color': '#60CDFF',
                'crm_text_color': '#ffffff',
                'crm_heading_color': '#ffffff',
                'crm_subtext_color': '#a0a0a0',
                'crm_glass_bg': 'rgba(32, 32, 32, 0.6)',
                'crm_glass_border': 'rgba(255, 255, 255, 0.06)',
                'crm_sidebar_bg': 'rgba(28, 28, 28, 0.95)',
                'crm_sidebar_text_color': '#dddddd',
                'crm_sidebar_active_color': '#60CDFF',
                'crm_sidebar_icon_color': '#a0a0a0',
                'crm_card_bg': 'rgba(255, 255, 255, 0.04)',
                'crm_input_bg': '#333333',
                'crm_dropdown_bg': '#2c2c2c',
                'crm_dropdown_text': '#ffffff',
                'crm_btn_bg_color': '#333333',
                'crm_btn_text_color': '#ffffff',
                'crm_btn_border_color': 'transparent',
                'crm_btn_hover_bg_color': '#60CDFF',
                'crm_btn_hover_text_color': '#000000'
            },
            'ios': {
                'crm_bg_type': 'radial',
                'crm_bg_color_1': '#000000',
                'crm_bg_color_2': '#121212',
                'crm_bg_color_3': '#1c1c1e',
                'crm_accent_color': '#0A84FF',
                'crm_text_color': '#ffffff',
                'crm_heading_color': '#ffffff',
                'crm_subtext_color': '#8E8E93',
                'crm_glass_bg': 'rgba(28, 28, 30, 0.65)',
                'crm_glass_border': 'rgba(84, 84, 88, 0.3)',
                'crm_sidebar_bg': '#000000',
                'crm_sidebar_text_color': '#8E8E93',
                'crm_sidebar_active_color': '#0A84FF',
                'crm_sidebar_icon_color': '#8E8E93',
                'crm_card_bg': 'rgba(44, 44, 46, 0.4)',
                'crm_input_bg': 'rgba(118, 118, 128, 0.24)',
                'crm_dropdown_bg': '#1C1C1E',
                'crm_dropdown_text': '#ffffff',
                 'crm_btn_bg_color': '#1C1C1E',
                'crm_btn_text_color': '#0A84FF',
                'crm_btn_border_color': 'transparent',
                'crm_btn_hover_bg_color': '#0A84FF',
                'crm_btn_hover_text_color': '#ffffff'
            },
            'shiny': {
                'crm_bg_type': 'linear',
                'crm_bg_color_1': '#0f0c29',
                'crm_bg_color_2': '#302b63',
                'crm_bg_color_3': '#24243e',
                'crm_accent_color': '#ff00cc',
                'crm_text_color': '#ffffff',
                'crm_heading_color': '#ffffff',
                'crm_subtext_color': '#d1d1d1',
                'crm_glass_bg': 'rgba(255, 255, 255, 0.1)',
                'crm_glass_border': 'rgba(255, 255, 255, 0.2)',
                'crm_sidebar_bg': 'rgba(0, 0, 0, 0.3)',
                'crm_sidebar_text_color': '#e0e0e0',
                'crm_sidebar_active_color': '#ff00cc',
                'crm_sidebar_icon_color': '#e0e0e0',
                'crm_card_bg': 'rgba(255, 255, 255, 0.15)',
                'crm_input_bg': 'rgba(255, 255, 255, 0.1)',
                'crm_dropdown_bg': 'rgba(40, 40, 90, 0.9)',
                'crm_dropdown_text': '#ffffff',
                'crm_btn_bg_color': 'transparent',
                'crm_btn_text_color': '#ffffff',
                'crm_btn_border_color': '#ff00cc',
                'crm_btn_hover_bg_color': '#ff00cc',
                'crm_btn_hover_text_color': '#ffffff'
            }
        };

        // Merge custom presets
        Object.assign(presets, customPresets);

        function applyCustomPreset(id) {
            applyPreset('custom_' + id);
        }

        function submitPreset() {
            const nameInput = document.getElementById('new_preset_name');
            const name = nameInput.value;
            if(!name) { alert('Please enter a name'); return; }
            
            const form = document.getElementById('settingsForm');
            
            const hiddenAction = document.createElement('input');
            hiddenAction.type = 'hidden';
            hiddenAction.name = 'save_preset';
            hiddenAction.value = '1';
            form.appendChild(hiddenAction);
            
            const hiddenName = document.createElement('input');
            hiddenName.type = 'hidden';
            hiddenName.name = 'preset_name';
            hiddenName.value = name;
            form.appendChild(hiddenName);
            
            form.submit();
        }

        function applyPreset(presetName) {
            const selected = presets[presetName];
            if(selected) {
                for (const [key, value] of Object.entries(selected)) {
                    // Update Input
                    const input = document.getElementById(key);
                    if(input) {
                        input.value = value;
                        // Update Picker if exists
                        if(window.crmPickers[key]) {
                            window.crmPickers[key].setColor(value);
                        }
                    } else {
                        // Handle Selects like 'crm_bg_type'
                         const inputs = document.getElementsByName('settings['+key+']');
                         if(inputs.length > 0) inputs[0].value = value;
                    }
                }
                
                // Show toast or alert
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-info position-fixed bottom-0 end-0 m-4';
                alertDiv.style.zIndex = '9999';
                alertDiv.innerHTML = '<i class="bi bi-info-circle me-2"></i> Preset <strong>' + presetName.toUpperCase() + '</strong> applied! Click "Save Changes" to persist.';
                document.body.appendChild(alertDiv);
                setTimeout(() => alertDiv.remove(), 4000);
            }
        }
    </script>
</body>
</html>
