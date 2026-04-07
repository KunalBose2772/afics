<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking columns in 'projects' table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('doa', $columns)) {
        echo "Adding 'doa' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN doa DATE DEFAULT NULL AFTER hospital_name");
        // Populate existing
        $pdo->exec("UPDATE projects SET doa = DATE_SUB(created_at, INTERVAL 5 DAY)");
    }

    if (!in_array('dod', $columns)) {
        echo "Adding 'dod' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN dod DATE DEFAULT NULL AFTER doa");
        // Populate existing
        $pdo->exec("UPDATE projects SET dod = DATE(created_at)");
    }

    if (!in_array('uhid', $columns)) {
        echo "Adding 'uhid' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN uhid VARCHAR(100) DEFAULT NULL AFTER dod");
    }
    
    echo "Migration 004 complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
