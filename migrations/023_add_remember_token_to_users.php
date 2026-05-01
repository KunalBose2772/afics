<?php
require_once __DIR__ . '/../config/db.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS remember_token VARCHAR(255) NULL");
    echo "Column 'remember_token' added or already exists.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
