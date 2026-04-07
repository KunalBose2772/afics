<?php
// Migration 011: Add multi-tier workflow & complex assignments
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for complex assignments...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'team_manager_id' => "INT NULL",
        'manager_id' => "INT NULL",
        'pt_fo_id' => "INT NULL",
        'hp_fo_id' => "INT NULL",
        'other_fo_id' => "INT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 011 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
