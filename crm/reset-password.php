<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$settings = get_settings($pdo);
$message = '';
$error = '';
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid Request: Token is missing.');
}

// Verify Token
$stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expiry > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die('Invalid or Expired Token. Please request a new password reset.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE id = ?");
        $update->execute([$hashed_password, $user['id']]);

        $message = "Password has been reset successfully. <a href='login.php'>Click here to login</a>.";
        // Invalidate token immediately (already done by setting NULL above)
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?= htmlspecialchars($settings['site_name'] ?? 'Documantraa') ?></title>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #0d2b4a;
            --bg-color: #f4f6f9;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .card-custom {
            max-width: 500px;
            width: 100%;
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            padding: 40px;
            background: #fff;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border: none;
            padding: 12px;
            font-weight: 600;
        }

        .btn-primary:hover {
            background-color: #0a2138;
        }
    </style>
</head>

<body>
    <div class="card card-custom">
        <h3 class="mb-4 fw-bold text-center" style="color: var(--primary-color);">Reset Password</h3>

        <?php if ($message): ?>
            <div class="alert alert-success text-center"><?= $message ?></div>
        <?php elseif ($error): ?>
            <div class="alert alert-danger text-center"><?= $error ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase">New Password</label>
                    <input type="password" name="password" class="form-control" required placeholder="••••••••">
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-uppercase">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn btn-primary w-100 rounded-pill">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>