<?php
require_once __DIR__ . '/../config/db.php';

// UPDATE THESE VALUES TO YOUR NEW ONES
$new_email = 'Admin123@documantraa.in'; 
$new_pass  = 'Admin123@documantraa.in'; // Assuming this was the password you meant

try {
    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'smtp_username'");
    $stmt->execute([$new_email]);

    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'smtp_password'");
    $stmt->execute([$new_pass]);

    echo "Successfully updated SMTP Username to: $new_email and Password to: [HIDDEN].\n";
    echo "The system will now use $new_email as the authorized sender.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
