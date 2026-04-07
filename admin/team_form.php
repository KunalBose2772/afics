<?php
require_once 'auth.php';
require_once '../config/db.php';

$member = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM team_members WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $role = $_POST['role'];
    $sort_order = $_POST['sort_order'];
    $facebook_url = $_POST['facebook_url'];
    $twitter_url = $_POST['twitter_url'];
    $pinterest_url = $_POST['pinterest_url'];
    
    $image_path = $member['image_path'] ?? '';

    // Handle Image Upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = '../assets/images/team/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $filename = uniqid() . '_' . basename($_FILES['image']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
            $image_path = 'assets/images/team/' . $filename;
        }
    }

    if ($member) {
        $stmt = $pdo->prepare("UPDATE team_members SET name=?, role=?, sort_order=?, facebook_url=?, twitter_url=?, pinterest_url=?, image_path=? WHERE id=?");
        $stmt->execute([$name, $role, $sort_order, $facebook_url, $twitter_url, $pinterest_url, $image_path, $member['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO team_members (name, role, sort_order, facebook_url, twitter_url, pinterest_url, image_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $role, $sort_order, $facebook_url, $twitter_url, $pinterest_url, $image_path]);
    }

    header("Location: team");
    exit;
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
    <title><?= $member ? 'Edit' : 'Add' ?> Team Member - Documantraa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
                        <h2 class="mb-0 fw-bold"><?= $member ? 'Edit' : 'Add' ?> Team Member</h2>
                        <p class="text-secondary mb-0"><?= $member ? 'Update existing' : 'Add a new' ?> team member.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 glare-container" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Name</label>
                                <input type="text" name="name" class="form-control form-control-lg" value="<?= htmlspecialchars($member['name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Role</label>
                                <input type="text" name="role" class="form-control form-control-lg" value="<?= htmlspecialchars($member['role'] ?? '') ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Profile Image</label>
                                <input type="file" name="image" class="form-control">
                                <?php if (!empty($member['image_path'])): ?>
                                    <div class="mt-2">
                                        <img src="../<?= htmlspecialchars($member['image_path']) ?>" width="100" class="rounded">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Facebook URL</label>
                                <input type="text" name="facebook_url" class="form-control" value="<?= htmlspecialchars($member['facebook_url'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Twitter URL</label>
                                <input type="text" name="twitter_url" class="form-control" value="<?= htmlspecialchars($member['twitter_url'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Pinterest URL</label>
                                <input type="text" name="pinterest_url" class="form-control" value="<?= htmlspecialchars($member['pinterest_url'] ?? '') ?>">
                            </div>

                            <div class="col-12">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="<?= htmlspecialchars($member['sort_order'] ?? '0') ?>">
                            </div>

                            <div class="col-12 mt-4 d-flex gap-3">
                                <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow"><i class="bi bi-check-lg me-2"></i> Save Member</button>
                                <a href="team" class="btn btn-outline-light btn-lg px-5 rounded-pill"><i class="bi bi-x-lg me-2"></i> Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
