<?php
require_once 'app_init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';
$role = $_SESSION['role'] ?? 'staff';
$error_message = '';

try {
    $stmt = $pdo->prepare("SELECT agreement_signed FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $already_signed = (int) $stmt->fetchColumn() === 1;
} catch (PDOException $e) {
    $already_signed = true;
}

if ($already_signed) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sign_agreement'])) {
    if (empty($_POST['accept_agreement'])) {
        $error_message = 'Please accept the agreement before continuing.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET agreement_signed = 1, agreement_date = NOW() WHERE id = ?");
            $stmt->execute([$user_id]);
            $_SESSION['agreement_signed'] = 1;
            header('Location: dashboard.php');
            exit;
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare("UPDATE users SET agreement_signed = 1 WHERE id = ?");
                $stmt->execute([$user_id]);
                $_SESSION['agreement_signed'] = 1;
                header('Location: dashboard.php');
                exit;
            } catch (PDOException $inner) {
                $error_message = 'Unable to save agreement right now. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agreement - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body style="background: #f5f7fb;">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="app-card shadow-sm">
                    <div class="text-center mb-4">
                        <img src="../assets/images/documantraa_logo.png" alt="Documantraa" style="max-height: 52px;">
                    </div>
                    <h1 class="mb-2" style="font-size: 1.75rem;">Staff Agreement</h1>
                    <p class="text-muted mb-4">Welcome, <?= htmlspecialchars($full_name) ?>. Please accept this agreement once to continue using the CRM.</p>

                    <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
                    <?php endif; ?>

                    <div class="border rounded p-4 mb-4 bg-light">
                        <h2 style="font-size: 1rem;" class="mb-3">Terms</h2>
                        <p class="mb-2">You confirm that all claim data, documents, and field information handled in this portal are confidential and must be used only for authorized company work.</p>
                        <p class="mb-2">You agree to upload genuine evidence, keep account access private, and follow company process while handling assigned investigations.</p>
                        <p class="mb-0">Any misuse of client information, false uploads, or unauthorized sharing may lead to access removal and internal action.</p>
                    </div>

                    <form method="POST">
                        <input type="hidden" name="sign_agreement" value="1">
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" value="1" id="accept_agreement" name="accept_agreement" required>
                            <label class="form-check-label" for="accept_agreement">
                                I, <?= htmlspecialchars($full_name) ?> (<?= htmlspecialchars(str_replace('_', ' ', $role)) ?>), have read and accept the agreement.
                            </label>
                        </div>
                        <button type="submit" class="btn-v2 btn-primary-v2 w-100 py-3">
                            <i class="bi bi-check-circle me-2"></i>Accept And Continue
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
