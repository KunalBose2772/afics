<?php
require_once 'auth.php';
require_once '../config/db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM faqs WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: faqs");
    exit;
}

$stmt = $pdo->query("SELECT * FROM faqs ORDER BY sort_order ASC");
$faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>Manage FAQs - Documantraa Admin</title>
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
                        <h2 class="mb-0 fw-bold">FAQ Management</h2>
                        <p class="text-secondary mb-0">Manage frequently asked questions.</p>
                    </div>
                    <a href="faq_form" class="btn btn-success btn-lg rounded-pill px-4 fw-bold shadow"><i class="bi bi-plus-lg me-2"></i> Add New FAQ</a>
                </div>

                <div class="glass-panel overflow-hidden p-0">
                    <table class="table table-hover mb-0 text-white" style="--bs-table-bg: transparent; --bs-table-color: #fff; --bs-table-hover-bg: rgba(255,255,255,0.05);">
                        <thead style="background: rgba(0,0,0,0.3);">
                            <tr>
                                <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Order</th>
                                <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Question</th>
                                <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Answer</th>
                                <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($faqs as $faq): ?>
                            <tr>
                                <td class="p-4 border-bottom border-secondary align-middle"><?= $faq['sort_order'] ?></td>
                                <td class="p-4 border-bottom border-secondary align-middle fw-bold"><?= htmlspecialchars($faq['question']) ?></td>
                                <td class="p-4 border-bottom border-secondary align-middle text-white-50"><?= htmlspecialchars(substr($faq['answer'], 0, 100)) ?>...</td>
                                <td class="p-4 border-bottom border-secondary align-middle text-end">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="faq_form?id=<?= $faq['id'] ?>" class="btn btn-sm btn-outline-primary rounded-circle" style="width: 32px; height: 32px; padding: 0; line-height: 30px;"><i class="bi bi-pencil"></i></a>
                                        <a href="faqs?delete=<?= $faq['id'] ?>" class="btn btn-sm btn-outline-danger rounded-circle" style="width: 32px; height: 32px; padding: 0; line-height: 30px;" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
