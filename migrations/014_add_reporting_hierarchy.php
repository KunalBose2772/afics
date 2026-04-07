<?php
// Migration 014: Add reporting hierarchy to users
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating users table for reporting hierarchy...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('reporting_to', $columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN reporting_to INT NULL");
        echo " - Added reporting_to column.\n";
    }

    echo "Migration 014 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
