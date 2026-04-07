<?php
require_once 'auth.php';
require_once '../config/db.php';

$inquiries = $pdo->query("SELECT * FROM inquiries ORDER BY submitted_at DESC")->fetchAll();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: inquiries');
    exit;
}

// Handle Bulk Delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_ids'])) {
    $ids = $_POST['selected_ids'];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id IN ($placeholders)");
        $stmt->execute($ids);
    }
    header('Location: inquiries');
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
    <title>Inquiries - Documantraa Admin</title>
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
                <form method="POST" id="bulkDeleteForm">
                    <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                        <div>
                            <h2 class="mb-0 fw-bold">Inquiries</h2>
                            <p class="text-secondary mb-0">Messages received from the contact form.</p>
                        </div>
                        <div class="text-end d-flex gap-2 align-items-center">
                            <span class="badge bg-primary rounded-pill px-3 py-2 fs-6">Total: <?= count($inquiries) ?></span>
                            <button type="submit" name="bulk_delete" class="btn btn-danger rounded-pill px-4" onclick="return confirm('Are you sure you want to delete selected items?')"><i class="bi bi-trash me-2"></i> Bulk Delete</button>
                        </div>
                    </div>

                    <div class="glass-panel overflow-hidden p-0">
                        <table class="table table-hover mb-0 text-white" style="--bs-table-bg: transparent; --bs-table-color: #fff; --bs-table-hover-bg: rgba(255,255,255,0.05);">
                            <thead style="background: rgba(0,0,0,0.3);">
                                <tr>
                                    <th class="p-4 border-bottom border-secondary" style="width: 40px;">
                                        <input type="checkbox" class="form-check-input bg-dark border-secondary" id="selectAll">
                                    </th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Date</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Name</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Email</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Phone</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Message</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary">Service</th>
                                    <th class="p-4 text-uppercase text-secondary small fw-bold border-bottom border-secondary text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inquiries as $inquiry): ?>
                                <tr>
                                    <td class="p-4 border-bottom border-secondary align-middle">
                                        <input type="checkbox" name="selected_ids[]" value="<?= $inquiry['id'] ?>" class="form-check-input bg-dark border-secondary">
                                    </td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-nowrap text-white-50"><?= htmlspecialchars($inquiry['submitted_at']) ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle fw-bold"><?= htmlspecialchars($inquiry['name']) ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-info"><?= htmlspecialchars($inquiry['email']) ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-white-50"><?= htmlspecialchars($inquiry['phone'] ?? '-') ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-white-50"><?= nl2br(htmlspecialchars($inquiry['message'])) ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-info"><?= htmlspecialchars($inquiry['service'] ?? '-') ?></td>
                                    <td class="p-4 border-bottom border-secondary align-middle text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <a href="../crm/clients?prefill=true&name=<?= urlencode($inquiry['name']) ?>&email=<?= urlencode($inquiry['email']) ?>&phone=<?= urlencode($inquiry['phone'] ?? '') ?>" class="btn btn-sm btn-outline-success" target="_blank" title="Convert to Client">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </a>
                                            <a href="inquiries?delete=<?= $inquiry['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this inquiry?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>
