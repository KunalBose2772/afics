<?php
// Migration 012: Add fine management columns
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for fine management...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'fine_amount' => "DECIMAL(10,2) DEFAULT 0.00",
        'is_fine_confirmed' => "TINYINT(1) DEFAULT 0",
        'fine_remark' => "TEXT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 012 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
