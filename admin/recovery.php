<?php
require_once 'db.php';

// Hardcoded recovery hash (e.g., ?hash=emergency_reset_123)
$recovery_hash = 'emergency_reset_123';

if (!isset($_GET['hash']) || $_GET['hash'] !== $recovery_hash) {
    die('Access Denied');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $new_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
    $stmt->execute([$new_password, $email]);
    
    echo "Password reset successfully for $email.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>Emergency Recovery</title>
</head>
<body>
    <h2>Emergency Password Reset</h2>
    <form method="POST">
        Email: <input type="email" name="email" required><br><br>
        New Password: <input type="password" name="password" required><br><br>
        <button type="submit">Reset Password</button>
    </form>
</body>
</html>
