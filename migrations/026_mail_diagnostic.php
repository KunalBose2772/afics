<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../lib/phpmailer/src/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "<h3>AFICS Mail Diagnostic Tool</h3>";
echo "<p>Testing multiple delivery methods to bypass Hostinger firewalls...</p>";

$to = "support@documantraa.in";
$subject = "Diagnostic Test";

// Fetch SMTP Settings
$s_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
$settings = $s_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$host = $settings['smtp_host'] ?? 'smtp.hostinger.com';
$user = $settings['smtp_username'] ?? 'info@documantraa.in';
$pass = $settings['smtp_password'] ?? 'l]l$+954F';

$methods = [
    "Method 1: External SMTP (Port 587 TLS)" => [
        'host' => $host, 'port' => 587, 'auth' => true, 'secure' => 'tls'
    ],
    "Method 2: External SMTP (Port 465 SSL)" => [
        'host' => $host, 'port' => 465, 'auth' => true, 'secure' => 'ssl'
    ],
    "Method 3: Localhost Relay (Port 25 No-Auth)" => [
        'host' => 'localhost', 'port' => 25, 'auth' => false, 'secure' => ''
    ],
    "Method 4: Hostinger MX1 Relay (mx1.hostinger.com:587)" => [
        'host' => 'mx1.hostinger.com', 'port' => 587, 'auth' => true, 'secure' => 'tls'
    ],
    "Method 5: Hostinger MX1 SSL (mx1.hostinger.com:465)" => [
        'host' => 'mx1.hostinger.com', 'port' => 465, 'auth' => true, 'secure' => 'ssl'
    ],
    "Method 6: Native PHP mail() function" => [
        'isMail' => true
    ]
];

echo "<p><strong>System Check:</strong><br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Disabled Functions: " . (ini_get('disable_functions') ?: 'None') . "<br>";
echo "Sendmail Path: " . ini_get('sendmail_path') . "</p>";

foreach ($methods as $name => $config) {
    echo "<strong>Testing $name...</strong> ";
    $mail = new PHPMailer(true);
    $mail->Timeout = 5; // Quick check
    
    try {
        if (isset($config['isMail'])) {
            $mail->isMail();
        } else {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->SMTPAuth = $config['auth'];
            if ($config['auth']) {
                $mail->Username = $user;
                $mail->Password = $pass;
            }
            $mail->SMTPSecure = $config['secure'];
        }
        
        $mail->setFrom($user, 'Diagnostic');
        $mail->addAddress($to);
        $mail->Subject = "$name - " . date('H:i:s');
        $mail->Body = "Diagnostic test message.";
        
        if ($mail->send()) {
            echo "<span style='color:green'>SUCCESS!</span><br>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red'>FAILED</span> - " . substr($mail->ErrorInfo, 0, 100) . "<br>";
    }
}

echo "<p>Done. Please copy this output and send it back to Antigravity.</p>";
