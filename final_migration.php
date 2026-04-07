<?php
// Start Session if not started
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Fix Path for Root-level Migration
require_once 'config/db.php';

// Check if user is admin
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    die("Access Denied. Please log in as an Admin to run this migration.");
}

$queries = [
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS team_manager_id INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS manager_id INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS pt_fo_id INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS hp_fo_id INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS other_fo_id INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS case_points DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS allocation_date DATETIME NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS doa DATE NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS dod DATE NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS reason_trigger TEXT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS hospital_name VARCHAR(255) NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS hospital_address TEXT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS patient_phone VARCHAR(20) NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS payment_utr VARCHAR(255) NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'Unpaid'",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS payment_confirmed_at DATETIME NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS payment_confirmed_by INT NULL",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS is_hard_copy_received TINYINT(1) DEFAULT 0",
    "ALTER TABLE projects ADD COLUMN IF NOT EXISTS is_hard_copy_overridden TINYINT(1) DEFAULT 0",
    
    // Settings Migration
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('whatsapp_phone', '917558834483')",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('whatsapp_apikey', '9934335')",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('smtp_host', 'mail.hostinger.com')",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('smtp_username', 'info@globalwebify.com')",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('smtp_password', 'Aasminpass@435989856')",
    "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('smtp_port', '465')"
];

echo "<h2>Executing Final Database Migration...</h2>";

foreach ($queries as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div style='color: green;'>✓ SUCCESS: " . htmlspecialchars($sql) . "</div>";
    } catch (PDOException $e) {
        echo "<div style='color: orange;'>! SKIPPED/ALREADY EXISTS: " . htmlspecialchars($sql) . " (" . $e->getMessage() . ")</div>";
    }
}

echo "<h3>Migration Finished. Please delete this file immediately!</h3>";
?>
