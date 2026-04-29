<?php
// Silent background worker
error_reporting(0); // Suppress output to browser
ignore_user_abort(true); // Keep running if user navigates away
set_time_limit(60); // Max 1 min

require_once '../../config/db.php';
require_once '../../includes/SMTPClient.php';

// Fetch global settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'smtp_%'");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$settings['smtp_host'] = $settings['smtp_host'] ?? 'smtp.hostinger.com';
$settings['smtp_port'] = $settings['smtp_port'] ?? 465;
$settings['smtp_encryption'] = $settings['smtp_encryption'] ?? 'ssl';

// Fetch pending emails (Limit 2 per request to keep it fast)
$stmt = $pdo->prepare("SELECT * FROM email_queue WHERE status = 'pending' AND attempts < 3 ORDER BY created_at ASC LIMIT 2");
$stmt->execute();
$emails = $stmt->fetchAll();

if (count($emails) > 0) {
    // Mark as processing
    $ids = array_column($emails, 'id');
    $pdo->query("UPDATE email_queue SET status = 'processing', updated_at = NOW() WHERE id IN (" . implode(',', $ids) . ")");

    foreach ($emails as $email) {
        $error = null;
        try {
            // Determine Credentials
            $username = $settings['smtp_username'] ?? ($settings['smtp_user'] ?? 'info@documantraa.in');
            $password = $settings['smtp_password'] ?? ($settings['smtp_pass'] ?? 'l]l$+954F');
            $fromEmail = null;
            
            if ($email['user_id']) {
                // Fetch User Credentials
                $u_stmt = $pdo->prepare("SELECT smtp_username, smtp_password, email FROM users WHERE id = ?");
                $u_stmt->execute([$email['user_id']]);
                $user = $u_stmt->fetch();
                
                if ($user && !empty($user['smtp_password'])) {
                    // Use User's Credentials
                    $username = $user['smtp_username'] ?: $user['email'];
                    $password = openssl_decrypt($user['smtp_password'], 'AES-128-ECB', SMTP_SECRET_KEY);
                } else {
                    $fromEmail = $username;
                }
            } else {
                // Use System/Admin Credentials
                $admin_stmt = $pdo->query("SELECT smtp_username, smtp_password, email FROM users WHERE role = 'super_admin' LIMIT 1");
                $admin = $admin_stmt->fetch();
                if ($admin && !empty($admin['smtp_password'])) {
                    $username = $admin['smtp_username'] ?: $admin['email'];
                    $password = openssl_decrypt($admin['smtp_password'], 'AES-128-ECB', SMTP_SECRET_KEY);
                    
                    // Use configured sender email for system notifications if available
                    if (!empty($settings['contact_form_sender_email'])) {
                        $fromEmail = $settings['contact_form_sender_email'];
                    }
                } else {
                    $fromEmail = $username;
                }
            }
            
            // Check if password decrypted
            if (empty($password)) throw new Exception("Failed to decrypt SMTP password");

            // Init Client
            $client = new SMTPClient(
                $settings['smtp_host'],
                $settings['smtp_port'],
                $settings['smtp_encryption'],
                $username,
                $password
            );
            
            // Send
            $attachments = !empty($email['attachments']) ? json_decode($email['attachments'], true) : [];
            $client->send($email['to_email'], $email['subject'], $email['body'], 'Documantraa', $attachments, $fromEmail);
            
            // Update Success
            $upd = $pdo->prepare("UPDATE email_queue SET status = 'sent', sent_at = NOW(), error_message = NULL WHERE id = ?");
            $upd->execute([$email['id']]);
            
        } catch (Exception $e) {
            // Update Failed
            $msg = substr($e->getMessage(), 0, 255);
            $upd = $pdo->prepare("UPDATE email_queue SET status = 'failed', attempts = attempts + 1, error_message = ? WHERE id = ?"); // failed logic: if attempts < 3, it will be retried next time (status=pending? no I set it to failed. Retry logic requires resetting to pending. For now, just mark failed.)
            // Actually, let's keep it 'pending' if attempts < 3.
            // But I query status='pending'. So if I set it 'failed', it won't retry.
            // Let's set it to 'pending' if attempt < 3, 'failed' if >= 3
            // Wait, my initial select is `attempts < 3`.
            // So I can leave it as 'pending' to retry. But I should store the error.
            if ($email['attempts'] + 1 >= 3) {
                $status = 'failed';
            } else {
                $status = 'pending'; // Retry
            }
            $upd->execute([$msg, $email['id']]);
             // Update status separately if needed
            $pdo->prepare("UPDATE email_queue SET status = ? WHERE id = ?")->execute([$status, $email['id']]);
        }
    }
}
?>
