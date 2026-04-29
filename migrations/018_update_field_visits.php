<?php
// Migration 018: Add GPS and TA columns to field_visits
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating field_visits table for GPS tracking...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM field_visits")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'start_latitude' => "VARCHAR(50) DEFAULT NULL",
        'start_longitude' => "VARCHAR(50) DEFAULT NULL",
        'end_latitude' => "VARCHAR(50) DEFAULT NULL",
        'end_longitude' => "VARCHAR(50) DEFAULT NULL",
        'distance_km' => "DECIMAL(10,2) DEFAULT 0.00",
        'travel_allowance' => "DECIMAL(10,2) DEFAULT 0.00",
        'start_time' => "DATETIME DEFAULT NULL",
        'end_time' => "DATETIME DEFAULT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE field_visits ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 018 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
