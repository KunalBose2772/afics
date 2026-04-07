<?php
require_once __DIR__ . '/../config/db.php';

try {
    echo "Checking if 'agreement_signed' column exists in 'users' table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'agreement_signed'");
    if ($stmt->fetch()) {
        echo "Column 'agreement_signed' already exists.\n";
    } else {
        echo "Adding 'agreement_signed' column...\n";
        // Add agreement_signed column (boolean/tinyint), default 0 (not signed)
        $pdo->exec("ALTER TABLE users ADD COLUMN agreement_signed TINYINT(1) DEFAULT 0");
        echo "Column 'agreement_signed' added successfully.\n";
        
        // Add agreement_date column as well if it might be needed later (good practice)
        echo "Checking if 'agreement_date' column exists...\n";
        $stmtDate = $pdo->query("SHOW COLUMNS FROM users LIKE 'agreement_date'");
        if (!$stmtDate->fetch()) {
             $pdo->exec("ALTER TABLE users ADD COLUMN agreement_date DATETIME NULL");
             echo "Column 'agreement_date' added successfully.\n";
        }
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
