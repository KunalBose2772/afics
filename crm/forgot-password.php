<?php
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$settings = get_settings($pdo);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Generate Token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Store Token securely
            $update = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?");
            $update->execute([$token, $expiry, $user['id']]);

            // Simulation of Email Sending (In production, use mail() or PHPMailer)
            // For this environment, we will display the link for testing purposes if mail isn't configured.
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/doc/crm/reset-password.php?token=" . $token;

            // Log/Send Email logic here...
            // $subject = "Password Reset Request";
            // $body = "Click here to reset your password: " . $reset_link;
            // send_email($email, $subject, $body);

            $message = "If an account exists for this email, a password reset link has been sent. <br><small>(Dev Note: <a href='$reset_link'>Click here for simulated link</a>)</small>";
        } else {
            // Generic message for security
            $message = "If an account exists for this email, a password reset link has been sent.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?= htmlspecialchars($settings['site_name'] ?? 'Documantraa') ?></title>

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

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(13, 43, 74, 0.1);
        }
    </style>
</head>

<body>
    <div class="card card-custom text-center">
        <div class="mb-4">
            <i class="bi bi-key-fill text-primary" style="font-size: 3rem;"></i>
        </div>
        <h3 class="mb-2 fw-bold" style="color: var(--primary-color);">Forgot Password?</h3>
        <p class="text-muted mb-4">Enter your email address to reset your password.</p>

        <?php if ($message): ?>
            <div class="alert alert-success text-start"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger text-start"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold small text-uppercase">Email Address</label>
                <input type="email" name="email" class="form-control" required placeholder="name@company.com">
            </div>
            <button type="submit" class="btn btn-primary w-100 rounded-pill mb-3">Send Reset Link</button>
            <a href="login.php" class="text-decoration-none text-muted small"><i class="bi bi-arrow-left me-1"></i> Back
                to Login</a>
        </form>
    </div>
</body>

</html>