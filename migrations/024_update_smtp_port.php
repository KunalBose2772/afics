<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Check if the setting exists first
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = 'smtp_port'");
    $stmt->execute();
    $exists = $stmt->fetchColumn();

    if ($exists) {
        $update = $pdo->prepare("UPDATE settings SET setting_value = '587' WHERE setting_key = 'smtp_port'");
        $update->execute();
        echo "Updated smtp_port to 587 successfully.\n";
    } else {
        $insert = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('smtp_port', '587')");
        $insert->execute();
        echo "Inserted smtp_port as 587 successfully.\n";
    }
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}
