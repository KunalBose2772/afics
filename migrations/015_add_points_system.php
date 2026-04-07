<?php
// Migration 015: Add points system columns
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating schema for points system...\n";
    
    // Add points to projects
    $cols_p = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('case_points', $cols_p)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN case_points DECIMAL(5,2) DEFAULT 0.00");
        echo " - Added case_points to projects\n";
    }

    // Add targets to users
    $cols_u = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('target_points', $cols_u)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN target_points INT DEFAULT 0");
        echo " - Added target_points to users\n";
    }

    echo "Migration 015 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
