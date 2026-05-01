<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE email_queue MODIFY COLUMN error_message TEXT NULL");
    echo "Expanded 'error_message' column to TEXT.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
