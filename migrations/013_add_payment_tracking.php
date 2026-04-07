<?php
// Migration 013: Add payment and hard copy tracking
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for payment and hard copy tracking...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'is_hard_copy_received' => "TINYINT(1) DEFAULT 0",
        'hard_copy_received_at' => "DATETIME NULL",
        'is_hard_copy_overridden' => "TINYINT(1) DEFAULT 0",
        'hard_copy_overridden_by' => "INT NULL",
        'payment_status' => "ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid'",
        'payment_confirmed_at' => "DATETIME NULL",
        'payment_confirmed_by' => "INT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 013 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
