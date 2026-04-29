<?php
// Migration 017: Add MRD Payment Tracking Columns
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for MRD payment tracking...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'mrd_status' => "VARCHAR(50) DEFAULT 'Pending'",
        'mrd_amount' => "DECIMAL(10,2) DEFAULT 0.00",
        'mrd_utr' => "VARCHAR(100) DEFAULT NULL",
        'mrd_qr_path' => "VARCHAR(255) DEFAULT NULL",
        'mrd_notes' => "TEXT DEFAULT NULL",
        'mrd_request_lat' => "VARCHAR(50) DEFAULT NULL",
        'mrd_request_long' => "VARCHAR(50) DEFAULT NULL",
        'mrd_payment_slip' => "VARCHAR(255) DEFAULT NULL",
        'mrd_receipt' => "VARCHAR(255) DEFAULT NULL",
        'mrd_receipt_lat' => "VARCHAR(50) DEFAULT NULL",
        'mrd_receipt_long' => "VARCHAR(50) DEFAULT NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 017 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
?>
