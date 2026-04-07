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
            // Audit log could go here if implemented
        }
    }
    header('Location: users.php');
    exit;
}

// Handle Save/Update User
if (isset($_POST['save_user'])) {
    try {
        $id = $_POST['id'] ?? null;
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $role = $_POST['role'];
        $reporting_to = !empty($_POST['reporting_to']) ? $_POST['reporting_to'] : null;
        $employee_id = $_POST['employee_id'];
        $target_points = $_POST['target_points'] ?? 0;
        
        if ($id) {
            // Update
            $sql = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, reporting_to = ?, employee_id = ?, target_points = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $phone, $role, $reporting_to, $employee_id, $target_points, $id]);
            if (!empty($_POST['password'])) {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($_POST['password'], PASSWORD_DEFAULT), $id]);
            }
        } else {
            // Create
            $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : password_hash('123456', PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, phone, role, reporting_to, employee_id, password, target_points) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$full_name, $email, $phone, $role, $reporting_to, $employee_id, $password, $target_points]);
        }
        header('Location: users.php?success=1');
        exit;
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch Users with Search and Filter
// ALWAYS exclude Super Admin and Website Manager from general view
$whereConditions = ["u.role != 'super_admin'", "u.role != 'website_manager'"];
$params = [];

$search = trim($_GET['search'] ?? '');
$role_filter = $_GET['role'] ?? '';

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.employee_id LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($role_filter)) {
    // Double check that the user is not trying to fish for hidden roles
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
                <h1 style="font-size: 1.75rem; color: var(--text-main);">User Management</h1>
                <div class="d-flex gap-2">
                    <button type="button" class="btn-v2 btn-white-v2 border" onclick="downloadBulkIDCards()" id="bulkDownloadBtn" style="display:none;">
                        <i class="bi bi-download"></i> Cards (<span id="selectedCount">0</span>)
                    </button>
                    <!-- Link to create user -->
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
                                    <span class="user-id-badge"><?= htmlspecialchars($user['employee_id'] ?? 'DOC-' . str_pad($user['id'], 4, '0', STR_PAD_LEFT)) ?></span>
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
            <?php else: // Action Create or Edit ?>
            <div class="row justify-content-center">
                <div class="col-xl-8">
                    <div class="app-card p-4">
                        <h3 class="card-title-v2 mb-4"><?= $action == 'create' ? 'Create New User' : 'Edit User Profile' ?></h3>
                        <form method="POST">
                            <input type="hidden" name="save_user" value="1">
                            <?php if($edit_user): ?> <input type="hidden" name="id" value="<?= $edit_user['id'] ?>"> <?php endif; ?>
                            
                            <div class="row g-4">
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
                                    <input type="text" name="employee_id" class="input-v2" value="<?= $edit_user['employee_id'] ?? '' ?>" placeholder="DOC-0001">
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
                                    <label class="stat-label mb-1">Monthly Target Points</label>
                                    <input type="number" name="target_points" class="input-v2" value="<?= $edit_user['target_points'] ?? '0' ?>" placeholder="e.g. 150, 300, 500">
                                </div>
                                <div class="col-md-12">
                                    <label class="stat-label mb-1">Login Password <?= $action == 'edit' ? '<small class="text-muted">(Leave blank to keep current)</small>' : '' ?></label>
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

    <!-- Hidden ID Card Container for Generation -->
    <div id="hiddenIDCardsContainer" style="position: fixed; left: -9999px; top: 0;"></div>

     <!-- Progress Modal -->
    <div class="modal fade" id="progressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Generating ID Cards...</h5>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between mb-2 small text-muted">
                        <span>Progress</span>
                        <span><span id="currentProgress">0</span> / <span id="totalProgress">0</span></span>
                    </div>
                    <div class="progress" style="height: 10px; border-radius: 99px;">
                        <div id="progressBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                    </div>
                    <p class="text-center mt-3 mb-0 text-muted small" id="currentStatus">Preparing...</p>
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
            <a href="../user_form.php" class="bottom-nav-icon-main">
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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    
    <script>
    // Toggle Select All checkboxes
    function toggleSelectAll(checkbox) {
        document.querySelectorAll('.user-checkbox').forEach(cb => cb.checked = checkbox.checked);
        updateBulkDownloadBtn();
    }
    
    // Update bulk download button visibility
    function updateBulkDownloadBtn() {
        const count = document.querySelectorAll('.user-checkbox:checked').length;
        document.getElementById('bulkDownloadBtn').style.display = count > 0 ? 'inline-flex' : 'none';
        document.getElementById('selectedCount').textContent = count;
        
        // Update master checkbox if needed
        const total = document.querySelectorAll('.user-checkbox').length;
        document.getElementById('selectAll').checked = (count === total && total > 0);
    }
    
    // Create ID Card HTML (Replicates v1 logic but adapted for v2 styles if needed, 
    // keeping inline styles to ensure canvas capture is accurate)
    function createIDCardHTML(user) {
        const profilePicture = user.profile_picture 
            ? `<img src="../uploads/profiles/${user.profile_picture}" 
                   style="width: 126px; height: 154px; min-width: 126px; min-height: 154px; object-fit: cover; object-position: center; border-radius: 12px; border: 3px solid #1e5ba8; display: block; margin: 0 auto;">`
            : `<div class="d-flex align-items-center justify-content-center mx-auto" 
                    style="width: 126px; height: 154px; background: #e3f2fd; border-radius: 12px; border: 3px solid #1e5ba8;">
                    <i class="bi bi-person" style="font-size: 3.5rem; color: #1e5ba8;"></i>
               </div>`;
        
        return `
            <div class="id-card" style="width: 340px; height: 560px; background: #ffffff url('../assets/images/idcard bkcg.png') no-repeat center center; background-size: cover; border-radius: 12px; position: relative; overflow: hidden;">
                <div style="position: relative; z-index: 10; height: 100%; padding: 30px 25px;">
                    <div class="text-center mb-3" style="margin-top: -8px;">
                        <img src="../assets/images/documantraa_logo.png" alt="Documantraa" style="max-height: 50px;">
                    </div>
                    <div class="text-center mb-3">
                        ${profilePicture}
                    </div>
                    <div class="text-center mb-1" style="background: #1e5ba8; color: white; padding: 8px; border-radius: 8px; font-weight: bold; font-size: 1rem;">
                        ${user.full_name.toUpperCase()}
                    </div>
                    <div class="text-center mb-3" style="color: #1e5ba8; font-weight: 600; font-size: 0.9rem;">
                        ${user.role.toUpperCase().replace('_', ' ')}
                    </div>
                    <div style="color: #1e5ba8; font-size: 0.85rem; line-height: 1.8; text-align: left;">
                        <div>
                            <span style="display: inline-block; width: 70px;"><strong>ID No</strong></span> : ${user.employee_id || 'DOC-' + String(user.id).padStart(4, '0')}
                        </div>
                        <div style="word-break: break-word;">
                            <span style="display: inline-block; width: 70px;"><strong>Email</strong></span> : ${user.email}
                        </div>
                        <div>
                            <span style="display: inline-block; width: 70px;"><strong>Phone</strong></span> : ${user.phone || 'N/A'}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Download Bulk ID Cards
    async function downloadBulkIDCards() {
        const userIds = Array.from(document.querySelectorAll('.user-checkbox:checked')).map(cb => cb.value);
        if (userIds.length === 0) return;
        
        const progressModal = new bootstrap.Modal(document.getElementById('progressModal'));
        progressModal.show();
        
        try {
            document.getElementById('currentStatus').textContent = 'Fetching user data...';
            // Need to create this ajax helper or reuse existing logic
            // For now, let's assume we fetch details from a new endpoint or existing one
            // To be safe, we might need to create ../ajax/get_users_bulk.php or similar
            // OR reuse existing generation logic.
            // Since V1 had generate_bulk_id_cards.php in root, let's check path.
            
            const response = await fetch('generate_bulk_id_cards.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'user_ids=' + encodeURIComponent(JSON.stringify(userIds))
            });
            
            const data = await response.json();
            if (!data.success) throw new Error(data.error);
            
            const users = data.users;
            const total = users.length;
            document.getElementById('totalProgress').textContent = total;
            
            const zip = new JSZip();
            const idCardsFolder = zip.folder('ID_Cards');
            
            for (let i = 0; i < users.length; i++) {
                const user = users[i];
                document.getElementById('currentProgress').textContent = i + 1;
                document.getElementById('currentStatus').textContent = `Generating ID card for ${user.full_name}...`;
                document.getElementById('progressBar').style.width = Math.round(((i + 1) / total) * 100) + '%';
                
                const container = document.getElementById('hiddenIDCardsContainer');
                container.innerHTML = createIDCardHTML(user);
                
                // Wait for image load
                await new Promise(r => setTimeout(r, 500));
                
                const canvas = await html2canvas(container.querySelector('.id-card'), {
                    scale: 3, backgroundColor: '#ffffff', useCORS: true, allowTaint: true
                });
                
                const blob = await new Promise(r => canvas.toBlob(r, 'image/png'));
                idCardsFolder.file(`Documantraa_ID_${user.id}.png`, blob);
            }
            
            document.getElementById('currentStatus').textContent = 'Zipping...';
            const zipBlob = await zip.generateAsync({ type: 'blob' });
            saveAs(zipBlob, `ID_Cards_${new Date().toISOString().split('T')[0]}.zip`);
            
            progressModal.hide();
            // Reset
             document.getElementById('progressBar').style.width = '0%';
        } catch (error) {
            console.error(error);
            document.getElementById('currentStatus').textContent = 'Error: ' + error.message;
        }
    }
    </script>
</body>
</html>
