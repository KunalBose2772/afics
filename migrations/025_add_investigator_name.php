<?php
// Migration 025: Add investigator_name column to projects table
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for investigator name...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'investigator_name' => "VARCHAR(255) DEFAULT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 025 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
