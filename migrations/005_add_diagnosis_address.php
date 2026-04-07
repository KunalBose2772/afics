<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking columns in 'projects' table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('diagnosis', $columns)) {
        echo "Adding 'diagnosis' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN diagnosis TEXT DEFAULT NULL AFTER uhid");
    }

    if (!in_array('hospital_address', $columns)) {
        echo "Adding 'hospital_address' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN hospital_address TEXT DEFAULT NULL AFTER hospital_name");
    }
    
    echo "Migration 005 complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
