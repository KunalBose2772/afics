<?php
require_once 'app_init.php';

// Auth Check from v2 standard
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Only Admin/Super Admin/HR can view users (adjust as needed)
$allowed = ['super_admin', 'admin', 'hr', 'website_manager'];
if (!in_array($role, $allowed)) {
    header("Location: dashboard.php");
    exit();
}

// Handle User Deletion (Protected for super_admin/website_manager)
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Check role/email before deleting
    $check = $pdo->prepare("SELECT role, email FROM users WHERE id = ?");
    $check->execute([$id]);
    $user_to_delete = $check->fetch();

    if ($user_to_delete) {
        $protected_roles = ['super_admin', 'website_manager'];
        $protected_emails = ['admin@documantraa.in'];

        // Only allow delete if target is not protected
        if (!in_array($user_to_delete['role'], $protected_roles) && !in_array($user_to_delete['email'], $protected_emails)) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
        }
    }
    header('Location: users.php');
    exit;
}

// Handle Save/Update User
if (isset($_POST['save_user'])) {
    $errors = [];
    $id = $_POST['id'] ?? null;
    
    // Validation
    $req_fields = [
        'full_name' => 'Full Name',
        'email' => 'Email Address',
        'role' => 'Role'
    ];
    $errors = validate_required($req_fields, $_POST);
    
    $email = sanitize_input($_POST['email']);
    if (empty($errors) && !validate_email($email)) {
        $errors[] = "Invalid email format.";
    }

    if (!$id && empty($_POST['password'])) {
        $errors[] = "Password is required for new users.";
    }

    if (empty($errors)) {
        try {
            $full_name = sanitize_input($_POST['full_name']);
            $phone = sanitize_input($_POST['phone'] ?? '');
            $role_input = sanitize_input($_POST['role']);
            $reporting_to = !empty($_POST['reporting_to']) ? intval($_POST['reporting_to']) : null;
            $employee_id = sanitize_input($_POST['employee_id'] ?? '');
            $target_points = intval($_POST['target_points'] ?? 0);
            $new_profile_picture = null;

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Profile picture upload failed.");
                }

                $ext = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
                $allowed_image_types = ['jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $allowed_image_types, true)) {
                    throw new Exception("Profile picture must be JPG, PNG, or WebP.");
                }

                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                $new_profile_picture = 'profile_user_' . ($id ?: 'new') . '_' . time() . '.' . $ext;
                $target_file = $upload_dir . $new_profile_picture;

                if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    throw new Exception("Unable to save profile picture.");
                }
            }
            
            if ($id) {
                // Update
                $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, reporting_to = ?, employee_id = ?, target_points = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $email, $phone, $role_input, $reporting_to, $employee_id, $target_points, $id]);
                if ($new_profile_picture) {
                    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$new_profile_picture, $id]);
                }
                if (!empty($_POST['password'])) {
                    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
                }
            } else {
                // Create
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (full_name, email, phone, role, reporting_to, employee_id, password, target_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$full_name, $email, $phone, $role_input, $reporting_to, $employee_id, $password, $target_points]);
                $new_user_id = $pdo->lastInsertId();
                if ($new_profile_picture) {
                    $final_profile_picture = 'profile_user_' . $new_user_id . '_' . time() . '.' . pathinfo($new_profile_picture, PATHINFO_EXTENSION);
                    @rename('../uploads/profiles/' . $new_profile_picture, '../uploads/profiles/' . $final_profile_picture);
                    $pdo->prepare("UPDATE users SET profile_picture = ? WHERE id = ?")->execute([$final_profile_picture, $new_user_id]);
                }
            }
            header('Location: users.php?success=1');
            exit;
        } catch (Exception $e) {
            $errors[] = "Database Error: " . $e->getMessage();
        }
    }
}

// Fetch Users with Search and Filter
$whereConditions = ["u.role != 'super_admin'", "u.role != 'website_manager'"];
$params = [];

$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

if (!empty($role_filter)) {
    if (!in_array($role_filter, ['super_admin', 'website_manager'])) {
        $whereConditions[] = "u.role = ?";
        $params[] = $role_filter;
    }
}

$whereClause = "WHERE " . implode(' AND ', $whereConditions);
$stmt = $pdo->prepare("SELECT u.*, p.full_name as manager_name FROM users u LEFT JOIN users p ON u.reporting_to = p.id $whereClause ORDER BY u.id DESC");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Fetch potential managers for dropdown
$managers = $pdo->query("SELECT id, full_name, role FROM users WHERE role IN ('admin', 'team_manager', 'fo_manager', 'super_admin') ORDER BY full_name")->fetchAll();

// Action Data
$edit_user = null;
$action = $_GET['action'] ?? 'list';
if ($action == 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $edit_user = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>User Management - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
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
                <h1 style="font-size: 1.75rem; color: var(--text-main);">User Management</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-v2 btn-white-v2 border" onclick="downloadBulkIDCards()" id="bulkDownloadBtn" style="display:none;">
                        <i class="bi bi-download"></i> Cards (<span id="selectedCount">0</span>)
                    </button>
                    <a href="users.php?action=<?= $action == 'list' ? 'create' : 'list' ?>" class="btn-v2 <?= $action == 'list' ? 'btn-primary-v2' : 'btn-white-v2 border' ?>">
                        <i class="bi <?= $action == 'list' ? 'bi-plus-lg' : 'bi-arrow-left' ?>"></i> 
                        <span class="d-none d-sm-inline"><?= $action == 'list' ? 'Add User' : 'Back to List' ?></span>
                    </a>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if ($action == 'list'): ?>
            <div class="app-card">
                 <div class="card-header-v2">
                    <h3 class="card-title-v2 m-0">All Employees</h3>
                    <form method="GET" class="d-flex gap-2" style="width: 100%; max-width: 400px;">
                         <select name="role" class="input-v2 py-1" style="width: 140px;" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="team_manager" <?= $role_filter === 'team_manager' ? 'selected' : '' ?>>Team Manager</option>
                            <option value="fo_manager" <?= $role_filter === 'fo_manager' ? 'selected' : '' ?>>FO Manager</option>
                            <option value="field_agent" <?= $role_filter === 'field_agent' ? 'selected' : '' ?>>Field Agent</option>
                            <option value="investigator" <?= $role_filter === 'investigator' ? 'selected' : '' ?>>Investigator</option>
                            <option value="doctor" <?= $role_filter === 'doctor' ? 'selected' : '' ?>>Doctor</option>
                        </select>
                        <input type="text" name="search" class="input-v2 py-1" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover" style="font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th class="py-3 px-3"><input type="checkbox" id="selectAll" class="form-check-input" onchange="toggleSelectAll(this)"></th>
                                <th class="py-3 text-secondary fw-normal">Employee ID</th>
                                <th class="py-3 text-secondary fw-normal">Name & Work</th>
                                <th class="py-3 text-secondary fw-normal">Reports To</th>
                                <th class="py-3 text-secondary fw-normal">Role</th>
                                <th class="py-3 text-secondary fw-normal text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="py-3 px-3 align-middle">
                                    <input type="checkbox" class="form-check-input user-checkbox" value="<?= $user['id'] ?>" onchange="updateBulkDownloadBtn()">
                                </td>
                                <td class="py-3 align-middle">
                                    <span class="user-id-badge"><?= htmlspecialchars($user['employee_id'] ?? 'AFI-DMI-' . str_pad($user['id'], 5, '0', STR_PAD_LEFT)) ?></span>
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="user-avatar-small">
                                            <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-main"><?= htmlspecialchars($user['full_name'] ?? 'Unknown') ?></div>
                                            <div class="text-muted small"><?= htmlspecialchars($user['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="text-main"><?= htmlspecialchars($user['manager_name'] ?? 'Self/None') ?></div>
                                    <div class="text-muted small"><?= htmlspecialchars($user['phone'] ?? '-') ?></div>
                                </td>
                                <td class="py-3 align-middle">
                                    <span class="badge badge-v2 badge-process text-uppercase" style="font-size: 0.7rem;">
                                        <?= htmlspecialchars(str_replace('_', ' ', $user['role'])) ?>
                                    </span>
                                </td>
                                <td class="py-3 align-middle text-end">
                                    <?php 
                                    $is_protected = in_array($user['role'], ['super_admin', 'website_manager']) || $user['email'] === 'admin@documantraa.in';
                                    if (!$is_protected): 
                                    ?>
                                        <a href="users.php?action=edit&id=<?= $user['id'] ?>" class="btn-v2 btn-white-v2 p-1 px-2" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="users.php?delete=<?= $user['id'] ?>" class="btn-v2 btn-white-v2 p-1 px-2 text-danger" title="Delete" onclick="return confirm('Delete this user?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-xl-8">
                    <div class="app-card p-4">
                        <h3 class="card-title-v2 mb-4"><?= $action == 'create' ? 'Create New User' : 'Edit User Profile' ?></h3>
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            <input type="hidden" name="save_user" value="1">
                            <?php if($edit_user): ?> <input type="hidden" name="id" value="<?= $edit_user['id'] ?>"> <?php endif; ?>
                            
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="stat-label mb-1">Profile Picture</label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <?php if (!empty($edit_user['profile_picture'])): ?>
                                        <img src="../uploads/profiles/<?= htmlspecialchars($edit_user['profile_picture']) ?>" alt="Profile Picture" style="width: 72px; height: 72px; object-fit: cover; border-radius: 50%; border: 1px solid var(--border);">
                                        <?php else: ?>
                                        <div class="user-avatar-small" style="width: 72px; height: 72px; font-size: 1.5rem;">
                                            <?= strtoupper(substr($edit_user['full_name'] ?? 'U', 0, 1)) ?>
                                        </div>
                                        <?php endif; ?>
                                        <div class="flex-grow-1">
                                            <input type="file" name="profile_picture" class="form-control input-v2" accept=".jpg,.jpeg,.png,.webp">
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Full Name</label>
                                    <input type="text" name="full_name" class="input-v2" value="<?= $edit_user['full_name'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Email Address</label>
                                    <input type="email" name="email" class="input-v2" value="<?= $edit_user['email'] ?? '' ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Employee / FO ID</label>
                                    <div class="input-group">
                                        <input type="text" name="employee_id" id="employee_id" class="input-v2 form-control" value="<?= $edit_user['employee_id'] ?? '' ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="generateEmployeeID()"><i class="bi bi-arrow-clockwise"></i></button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Phone Number</label>
                                    <input type="text" name="phone" class="input-v2" value="<?= $edit_user['phone'] ?? '' ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Select Role</label>
                                    <select name="role" class="input-v2 form-select" required>
                                        <option value="investigator" <?= ($edit_user['role'] ?? '') == 'investigator' ? 'selected' : '' ?>>Investigator / FO</option>
                                        <option value="team_manager" <?= ($edit_user['role'] ?? '') == 'team_manager' ? 'selected' : '' ?>>Team Manager</option>
                                        <option value="fo_manager" <?= ($edit_user['role'] ?? '') == 'fo_manager' ? 'selected' : '' ?>>FO Manager</option>
                                        <option value="manager" <?= ($edit_user['role'] ?? '') == 'manager' ? 'selected' : '' ?>>Manager</option>
                                        <option value="doctor" <?= ($edit_user['role'] ?? '') == 'doctor' ? 'selected' : '' ?>>Doctor</option>
                                        <option value="admin" <?= ($edit_user['role'] ?? '') == 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="hod" <?= ($edit_user['role'] ?? '') == 'hod' ? 'selected' : '' ?>>HOD</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Reporting To / Manager</label>
                                    <select name="reporting_to" class="input-v2 form-select">
                                        <option value="">None (Top Level)</option>
                                        <?php foreach($managers as $m): ?>
                                            <option value="<?= $m['id'] ?>" <?= ($edit_user['reporting_to'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($m['full_name']) ?> (<?= $m['role'] ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="stat-label mb-1">Target Points</label>
                                    <input type="number" name="target_points" class="input-v2" value="<?= $edit_user['target_points'] ?? '0' ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="stat-label mb-1">Login Password</label>
                                    <input type="password" name="password" class="input-v2" <?= $action == 'create' ? 'required' : '' ?>>
                                </div>
                                
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn-v2 btn-primary-v2 py-3 w-100">
                                        <i class="bi bi-save me-2"></i> <?= $action == 'create' ? 'Register User' : 'Update Profile' ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = checkbox.checked);
        updateBulkDownloadBtn();
    }
    
    function updateBulkDownloadBtn() {
        const count = document.querySelectorAll('.user-checkbox:checked').length;
        document.getElementById('bulkDownloadBtn').style.display = count > 0 ? 'inline-flex' : 'none';
        document.getElementById('selectedCount').textContent = count;
    }

    function generateEmployeeID() {
        const rand = Math.floor(10000 + Math.random() * 90000);
        const empId = 'AFI-DMI-' + rand;
        document.getElementById('employee_id').value = empId;
    }

    <?php if ($action == 'create'): ?>
    window.addEventListener('load', function() {
        if(!document.getElementById('employee_id').value) {
            generateEmployeeID();
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
