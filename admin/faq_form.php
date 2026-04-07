<?php
require_once 'auth.php';
require_once '../config/db.php';

$faq = null;
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM faqs WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $faq = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $question = $_POST['question'];
    $answer = $_POST['answer'];
    $sort_order = $_POST['sort_order'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($faq) {
        $stmt = $pdo->prepare("UPDATE faqs SET question=?, answer=?, sort_order=?, is_active=? WHERE id=?");
        $stmt->execute([$question, $answer, $sort_order, $is_active, $faq['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO faqs (question, answer, sort_order, is_active) VALUES (?, ?, ?, ?)");
        $stmt->execute([$question, $answer, $sort_order, $is_active]);
    }

    header("Location: faqs");
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
    <title><?= $faq ? 'Edit' : 'Add' ?> FAQ - Documantraa Admin</title>
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
                        <h2 class="mb-0 fw-bold"><?= $faq ? 'Edit' : 'Add' ?> FAQ</h2>
                        <p class="text-secondary mb-0"><?= $faq ? 'Update existing' : 'Create a new' ?> frequently asked question.</p>
                    </div>
                </div>

                <div class="glass-panel p-4 glare-container" style="max-width: 800px; margin: 0 auto;">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Question</label>
                            <input type="text" name="question" class="form-control form-control-lg" value="<?= htmlspecialchars($faq['question'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Answer</label>
                            <textarea name="answer" class="form-control" rows="5" required><?= htmlspecialchars($faq['answer'] ?? '') ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sort Order</label>
                            <input type="number" name="sort_order" class="form-control" value="<?= htmlspecialchars($faq['sort_order'] ?? '0') ?>">
                        </div>
                        <div class="mb-4 form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= ($faq['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="isActive">Active (Visible on website)</label>
                        </div>
                        
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill fw-bold shadow"><i class="bi bi-check-lg me-2"></i> Save FAQ</button>
                            <a href="faqs" class="btn btn-outline-light btn-lg px-5 rounded-pill"><i class="bi bi-x-lg me-2"></i> Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
