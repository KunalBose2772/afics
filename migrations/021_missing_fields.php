<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for missing fields...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('closure_conclusion', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN closure_conclusion TEXT DEFAULT NULL");
        echo " - Added closure_conclusion\n";
    }

    echo "\nMigration 021 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
