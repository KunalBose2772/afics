<?php
// Migration 016: Add live GPS tracking table
require_once __DIR__ . '/../config/db.php';

try {
    echo "Creating user_locations table...\n";
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        latitude DECIMAL(10,8) NOT NULL,
        longitude DECIMAL(11,8) NOT NULL,
        accuracy FLOAT NULL,
        recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX(user_id),
        INDEX(recorded_at)
    )");

    echo "Migration 016 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
