<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking columns in 'projects' table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('price_hospital', $columns)) {
        echo "Adding 'price_hospital' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN price_hospital DECIMAL(10,2) DEFAULT 0.00 AFTER description");
    }

    if (!in_array('price_patient', $columns)) {
        echo "Adding 'price_patient' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN price_patient DECIMAL(10,2) DEFAULT 0.00 AFTER price_hospital");
    }

    if (!in_array('price_other', $columns)) {
        echo "Adding 'price_other' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN price_other DECIMAL(10,2) DEFAULT 0.00 AFTER price_patient");
    }
    
    echo "Migration 006 complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
