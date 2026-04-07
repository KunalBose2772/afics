<?php
require_once 'auth.php';
require_once '../config/db.php';

$id = $_GET['id'] ?? null;
$service = ['title' => '', 'description' => '', 'icon_class' => 'bi bi-star', 'display_order' => 0];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
} else {
    // Auto-increment display order for new services
    $stmt = $pdo->query("SELECT MAX(display_order) FROM services");
    $maxOrder = $stmt->fetchColumn();
    $service['display_order'] = $maxOrder !== false ? $maxOrder + 1 : 1;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $long_description = $_POST['long_description'];
    $icon_class = $_POST['icon_class'] ?? ''; // Optional now
    $display_order = $_POST['display_order'];
    $image_path = $service['image_path'] ?? '';

    // Handle File Upload
    if (!empty($_FILES['service_image']['name'])) {
        $upload_dir = '../assets/images/services/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        
        $file = $_FILES['service_image'];
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'service_' . time() . '_' . rand(100,999) . '.' . $ext;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $target_path)) {
            $image_path = 'assets/images/services/' . $filename;
        }
    }

    if ($id) {
        $stmt = $pdo->prepare("UPDATE services SET title=?, description=?, long_description=?, icon_class=?, image_path=?, display_order=? WHERE id=?");
        $stmt->execute([$title, $description, $long_description, $icon_class, $image_path, $display_order, $id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO services (title, description, long_description, icon_class, image_path, display_order) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $long_description, $icon_class, $image_path, $display_order]);
    }
    header('Location: services');
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
    <title><?= $id ? 'Edit' : 'Add' ?> Service - Documantraa Admin</title>
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
                        <h2 class="mb-0 fw-bold"><?= $id ? 'Edit' : 'Add' ?> Service</h2>
                        <p class="text-secondary mb-0"><?= $id ? 'Update existing' : 'Create a new' ?> service offering.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 glare-container" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-4">
                            <div class="col-md-8">
                                <label class="form-label">Service Title</label>
                                <input type="text" name="title" class="form-control form-control-lg" value="<?= htmlspecialchars($service['title']) ?>" required placeholder="e.g. Corporate Investigation">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Display Order</label>
                                <input type="number" name="display_order" class="form-control form-control-lg" value="<?= htmlspecialchars($service['display_order']) ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Short Description</label>
                                <textarea name="description" class="form-control" rows="3" required placeholder="Brief summary for the card..."><?= htmlspecialchars($service['description']) ?></textarea>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Long Description (For Popup)</label>
                                <textarea name="long_description" class="form-control" rows="8" placeholder="Detailed description..."><?= htmlspecialchars($service['long_description'] ?? '') ?></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Service Image</label>
                                <input type="file" name="service_image" class="form-control" accept="image/*">
                                <?php if (!empty($service['image_path'])): ?>
                                    <div class="mt-2">
                                        <img src="../<?= htmlspecialchars($service['image_path']) ?>" alt="Current Image" class="img-thumbnail" style="height: 100px;">
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Icon Class (Optional Fallback)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-transparent border-secondary text-white"><i class="<?= htmlspecialchars($service['icon_class']) ?>"></i></span>
                                    <input type="text" name="icon_class" class="form-control" value="<?= htmlspecialchars($service['icon_class']) ?>" placeholder="e.g. bi bi-shield-check">
                                </div>
                                <small class="text-muted mt-2 d-block">Used if no image is uploaded.</small>
                            </div>

                            <div class="col-12 mt-5 d-flex gap-3">
                                <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow"><i class="bi bi-check-lg me-2"></i> Save Service</button>
                                <a href="services" class="btn btn-outline-light btn-lg px-5 rounded-pill"><i class="bi bi-x-lg me-2"></i> Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time icon preview
        document.querySelector('input[name="icon_class"]').addEventListener('input', function() {
            const iconClass = this.value;
            this.previousElementSibling.innerHTML = `<i class="${iconClass}"></i>`;
        });
    </script>
</body>
</html>
