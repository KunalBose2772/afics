<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating attendance table for check-out location columns...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('check_out_latitude', $columns)) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_latitude DECIMAL(10,8) DEFAULT NULL");
        echo " - Added check_out_latitude\n";
    }

    if (!in_array('check_out_longitude', $columns)) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN check_out_longitude DECIMAL(11,8) DEFAULT NULL");
        echo " - Added check_out_longitude\n";
    }

    echo "\nMigration for attendance location completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
