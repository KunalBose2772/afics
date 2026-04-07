<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking columns in 'projects' table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('claim_number', $columns)) {
        echo "Adding 'claim_number' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN claim_number VARCHAR(100) AFTER id");
        // Populate existing with dummy
        $pdo->exec("UPDATE projects SET claim_number = CONCAT('CLM-', id, '-2025') WHERE claim_number IS NULL");
    }

    if (!in_array('scope', $columns)) {
        echo "Adding 'scope' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN scope VARCHAR(100) DEFAULT 'Full Investigation' AFTER description");
    }

    if (!in_array('hospital_name', $columns)) {
        echo "Adding 'hospital_name' column...\n";
        $pdo->exec("ALTER TABLE projects ADD COLUMN hospital_name VARCHAR(255) AFTER scope");
    }
    
    echo "Migration complete.\n";

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
