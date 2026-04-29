<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for investigator contact info...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('investigator_phone', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN investigator_phone VARCHAR(100) DEFAULT NULL");
        echo " - Added investigator_phone\n";
    }
    
    if (!in_array('investigator_email', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN investigator_email VARCHAR(150) DEFAULT NULL");
        echo " - Added investigator_email\n";
    }

    echo "\nMigration 020 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
