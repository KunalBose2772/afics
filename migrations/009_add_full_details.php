<?php
// Migration 009: Add Full Case Details Support
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating projects table for Full Detail View...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    $new_cols = [
        'visit_type' => "ENUM('Hospital', 'Patient', 'Other', 'Cashless') DEFAULT 'Hospital'",
        'member_id' => "VARCHAR(50) NULL",
        'policy_no' => "VARCHAR(50) NULL",
        'policy_type' => "VARCHAR(100) NULL",
        'inception_date' => "DATE NULL",
        'policy_mobile_no' => "VARCHAR(20) NULL",
        'intimation_mobile_no' => "VARCHAR(20) NULL",
        'internal_remark' => "TEXT NULL",
        'city' => "VARCHAR(100) NULL",
        'state' => "VARCHAR(100) NULL",
        'claim_amount' => "DECIMAL(10,2) DEFAULT 0.00",
        'is_vip' => "TINYINT(1) DEFAULT 0",
        'is_blacklisted' => "TINYINT(1) DEFAULT 0",
        'allocation_date' => "DATE NULL"
    ];

    foreach ($new_cols as $col => $def) {
        if (!in_array($col, $columns)) {
            $pdo->exec("ALTER TABLE projects ADD COLUMN $col $def");
            echo " - Added $col\n";
        }
    }

    echo "Migration 009 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
