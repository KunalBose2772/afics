<?php
require_once 'app_init.php';

// Access Control
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    // Only super_admin usually manages rights, or check permission
    if (!function_exists('has_permission') || !has_permission('rights')) {
        header("Location: dashboard.php");
        exit();
    }
}

// Logic from original rights.php
// Handle Add New Role
if (isset($_POST['add_role'])) {
    $new_role_name = trim($_POST['new_role_name']);
    $new_role_slug = strtolower(str_replace(' ', '_', $new_role_name));
    $selected_modules = $_POST['new_role_modules'] ?? [];
    
    if (!empty($new_role_slug) && $new_role_slug != 'super_admin') {
        $exists = $pdo->prepare("SELECT id FROM permissions WHERE role_name = ?");
        $exists->execute([$new_role_slug]);
        
        if (!$exists->fetch()) {
            $json_modules = json_encode($selected_modules);
            $stmt = $pdo->prepare("INSERT INTO permissions (role_name, modules_access) VALUES (?, ?)");
            $stmt->execute([$new_role_slug, $json_modules]);
            log_action('CREATE_ROLE', "Created new role: $new_role_slug");
            header('Location: rights.php?role_added=1');
            exit;
        }
    }
    header('Location: rights.php?error=duplicate');
    exit;
}

// Handle Edit Role
if (isset($_POST['edit_role'])) {
    $old_role_name = $_POST['old_role_name'];
    $new_role_name = trim($_POST['new_role_name']);
    $new_role_slug = strtolower(str_replace(' ', '_', $new_role_name));
    $selected_modules = $_POST['edit_role_modules'] ?? [];
    $protected_roles = ['super_admin', 'admin', 'hr_manager', 'investigator', 'website_manager'];
    
    if (in_array($old_role_name, $protected_roles)) {
        header('Location: rights.php?error=protected_edit');
        exit;
    }
    
    if (!empty($new_role_slug) && !in_array($new_role_slug, $protected_roles)) {
        if ($old_role_name != $new_role_slug) {
            $exists = $pdo->prepare("SELECT id FROM permissions WHERE role_name = ?");
            $exists->execute([$new_role_slug]);
            if ($exists->fetch()) {
                header('Location: rights.php?error=duplicate');
                exit;
            }
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE role = ?");
            $stmt->execute([$new_role_slug, $old_role_name]);
        }
        $json_modules = json_encode($selected_modules);
        $stmt = $pdo->prepare("UPDATE permissions SET role_name = ?, modules_access = ? WHERE role_name = ?");
        $stmt->execute([$new_role_slug, $json_modules, $old_role_name]);
        log_action('UPDATE_ROLE', "Updated role: $new_role_slug");
        header('Location: rights.php?role_updated=1');
        exit;
    }
    header('Location: rights.php?error=invalid_name');
    exit;
}

// Handle Update Permissions Matrix
if (isset($_POST['update_permissions'])) {
    foreach ($_POST['permissions'] as $role => $modules) {
        $json_modules = json_encode(array_keys($modules));
        $exists = $pdo->prepare("SELECT id FROM permissions WHERE role_name = ?");
        $exists->execute([$role]);
        if ($exists->fetch()) {
            $stmt = $pdo->prepare("UPDATE permissions SET modules_access = ? WHERE role_name = ?");
            $stmt->execute([$json_modules, $role]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO permissions (role_name, modules_access) VALUES (?, ?)");
            $stmt->execute([$role, $json_modules]);
        }
    }
    log_action('UPDATE_PERMISSIONS', 'Updated role permissions matrix');
    header('Location: rights.php?success=1');
    exit;
}

// --- Data Fetching ---
$stmt = $pdo->query("SELECT role_name FROM permissions WHERE role_name NOT IN ('super_admin', 'website_manager') ORDER BY role_name");
$all_roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
$modules = ['dashboard', 'users', 'attendance', 'payroll', 'clients', 'projects', 'leaves', 'services', 'inquiries', 'rights', 'settings'];
$current_permissions = [];
$stmt = $pdo->query("SELECT * FROM permissions WHERE role_name NOT IN ('super_admin', 'website_manager')");
while ($row = $stmt->fetch()) {
    $current_permissions[$row['role_name']] = json_decode($row['modules_access'], true) ?? [];
}
$protected_roles = ['admin', 'hr_manager', 'investigator', 'website_manager'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rights Management - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .rights-table th { background-color: var(--surface-hover); color: var(--text-main); font-weight: 600; vertical-align: middle; }
        .rights-table td { vertical-align: middle; border-color: var(--border); }
        .permission-check { width: 1.25rem; height: 1.25rem; cursor: pointer; }
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

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Rights Management</h1>
                    <p class="text-muted mb-0 small">Configure role-based access control and modules.</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                        <i class="bi bi-plus-lg"></i> Add Role
                    </button>
                    <button type="submit" form="rightsForm" class="btn-v2 btn-white-v2 d-none d-md-inline-flex">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> Permissions updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="app-card p-0 overflow-hidden">
                <form method="POST" id="rightsForm">
                    <input type="hidden" name="update_permissions" value="1">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 rights-table">
                            <thead>
                                <tr>
                                    <th class="p-3 ps-4" style="min-width: 200px;">Module Access</th>
                                    <?php foreach ($all_roles as $role): ?>
                                    <th class="p-3 text-center" style="min-width: 140px;">
                                        <div class="d-flex flex-column align-items-center gap-2">
                                            <span class="badge bg-light text-dark border px-3 py-2 rounded-pill text-uppercase fw-bold"><?= str_replace('_', ' ', $role) ?></span>
                                            <?php if (!in_array($role, $protected_roles)): ?>
                                            <button class="btn btn-sm btn-link text-muted p-0" data-bs-toggle="modal" data-bs-target="#editRoleModal<?= $role ?>" type="button">
                                                <i class="bi bi-pencil-square"></i> Edit
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td class="p-3 ps-4 fw-medium text-secondary">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="bg-primary-subtle text-primary rounded p-1 mb-0"><i class="bi bi-gear-wide-connected"></i></div>
                                            <?= ucfirst($module) ?>
                                        </div>
                                    </td>
                                    <?php foreach ($all_roles as $role): 
                                        $has_access = in_array($module, $current_permissions[$role] ?? []);
                                    ?>
                                    <td class="p-3 text-center bg-white">
                                        <div class="form-check d-inline-block">
                                            <input class="form-check-input permission-check" 
                                                   type="checkbox" 
                                                   name="permissions[<?= $role ?>][<?= $module ?>]" 
                                                   <?= $has_access ? 'checked' : '' ?>>
                                        </div>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
            
            <div class="mt-3 text-center d-md-none">
                 <button type="submit" form="rightsForm" class="btn-v2 btn-primary-v2 w-100 p-3 shadow-lg">
                    <i class="bi bi-save me-2"></i> SAVE ALL CHANGES
                </button>
            </div>

        </div>
    </div>

    <!-- Add Role Modal -->
    <div class="modal fade" id="addRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_role" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Role Name</label>
                            <input type="text" name="new_role_name" class="input-v2" placeholder="e.g. Sales Lead" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold mb-2">Initial Permissions</label>
                            <div class="row g-2">
                                <?php foreach ($modules as $module): ?>
                                <div class="col-6">
                                    <div class="form-check bg-light rounded p-2 border">
                                        <input class="form-check-input" type="checkbox" name="new_role_modules[]" value="<?= $module ?>" id="new_<?= $module ?>">
                                        <label class="form-check-label small" for="new_<?= $module ?>"><?= ucfirst($module) ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn-v2 btn-primary-v2">Create Role</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit/Edit Role Modals -->
    <?php foreach ($all_roles as $role): ?>
        <?php if (!in_array($role, $protected_roles)): ?>
        <div class="modal fade" id="editRoleModal<?= $role ?>" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Role: <?= ucfirst($role) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="edit_role" value="1">
                            <input type="hidden" name="old_role_name" value="<?= $role ?>">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Role Name</label>
                                <input type="text" name="new_role_name" class="input-v2" value="<?= str_replace('_', ' ', ucwords($role)) ?>" required>
                            </div>
                             <div class="mb-3">
                                <label class="form-label fw-bold mb-2">Permissions</label>
                                <div class="row g-2">
                                    <?php 
                                    $role_permissions = $current_permissions[$role] ?? [];
                                    foreach ($modules as $module): 
                                        $is_checked = in_array($module, $role_permissions);
                                    ?>
                                    <div class="col-6">
                                        <div class="form-check bg-light rounded p-2 border">
                                            <input class="form-check-input" type="checkbox" name="edit_role_modules[]" value="<?= $module ?>" <?= $is_checked ? 'checked' : '' ?>>
                                            <label class="form-check-label small"><?= ucfirst($module) ?></label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-v2 btn-primary-v2">Update Role</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
