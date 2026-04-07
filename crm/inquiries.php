<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Permission Check
if (!function_exists('has_permission') || !has_permission('inquiries')) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
        header("Location: dashboard.php");
        exit();
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM inquiries WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: inquiries.php?success=deleted');
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
    header('Location: inquiries.php?success=bulk_deleted');
    exit;
}

$inquiries = $pdo->query("SELECT * FROM inquiries ORDER BY submitted_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Inquiries - Documantraa</title>
    
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

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Customer Inquiries</h1>
                    <p class="text-muted mb-0 small">Messages received from the contact form.</p>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge badge-v2 badge-primary"><?= count($inquiries) ?> Total</span>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> 
                <?= $_GET['success'] === 'deleted' ? 'Inquiry deleted successfully!' : 'Selected inquiries deleted!' ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" id="bulkDeleteForm">
                <div class="app-card mb-4">
                    <div class="card-header-v2">
                        <span class="card-title-v2"><i class="bi bi-chat-dots me-2"></i>All Inquiries</span>
                        <button type="submit" name="bulk_delete" class="btn-v2 btn-danger-v2 btn-sm" onclick="return confirm('Are you sure you want to delete selected items?')">
                            <i class="bi bi-trash me-1"></i> Delete Selected
                        </button>
                    </div>

                    <?php if (empty($inquiries)): ?>
                    <div class="text-center py-5">
                        <div class="bg-light rounded-circle d-inline-flex p-4 mb-3 text-muted">
                            <i class="bi bi-inbox fs-1"></i>
                        </div>
                        <p class="text-muted">No inquiries yet.</p>
                    </div>
                    <?php else: ?>
                    <!-- Desktop Table View -->
                    <div class="table-responsive d-none d-md-block">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
                                    </th>
                                    <th>Date</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Message</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inquiries as $inquiry): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="selected_ids[]" value="<?= $inquiry['id'] ?>" class="form-check-input">
                                    </td>
                                    <td class="text-muted small"><?= date('d M Y', strtotime($inquiry['submitted_at'])) ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($inquiry['name']) ?></td>
                                    <td class="text-primary small"><?= htmlspecialchars($inquiry['email']) ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars($inquiry['phone'] ?? '-') ?></td>
                                    <td class="small" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        <?= htmlspecialchars($inquiry['message']) ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <?php if (has_permission('clients')): ?>
                                            <a href="clients.php?prefill=true&name=<?= urlencode($inquiry['name']) ?>&email=<?= urlencode($inquiry['email']) ?>&phone=<?= urlencode($inquiry['phone'] ?? '') ?>" 
                                               class="btn btn-sm btn-outline-success" title="Convert to Client">
                                                <i class="bi bi-person-plus-fill"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="inquiries.php?delete=<?= $inquiry['id'] ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Delete this inquiry?')">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="d-md-none">
                        <?php foreach ($inquiries as $inquiry): ?>
                        <div class="border-bottom p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="form-check">
                                    <input type="checkbox" name="selected_ids[]" value="<?= $inquiry['id'] ?>" class="form-check-input" id="check<?= $inquiry['id'] ?>">
                                    <label class="form-check-label fw-bold" for="check<?= $inquiry['id'] ?>">
                                        <?= htmlspecialchars($inquiry['name']) ?>
                                    </label>
                                </div>
                                <span class="badge bg-light text-dark small"><?= date('d M', strtotime($inquiry['submitted_at'])) ?></span>
                            </div>
                            <div class="small text-muted mb-2">
                                <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($inquiry['email']) ?><br>
                                <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($inquiry['phone'] ?? '-') ?>
                            </div>
                            <p class="small mb-2"><?= nl2br(htmlspecialchars($inquiry['message'])) ?></p>
                            <div class="d-flex gap-2">
                                <?php if (has_permission('clients')): ?>
                                <a href="clients.php?prefill=true&name=<?= urlencode($inquiry['name']) ?>&email=<?= urlencode($inquiry['email']) ?>&phone=<?= urlencode($inquiry['phone'] ?? '') ?>" 
                                   class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-person-plus-fill me-1"></i> Convert
                                </a>
                                <?php endif; ?>
                                <a href="inquiries.php?delete=<?= $inquiry['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('Delete?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('selectAll')?.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_ids[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
    </script>
</body>
</html>
