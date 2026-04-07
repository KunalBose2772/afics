<?php
require_once __DIR__ . '/../config/db.php';

try {
    // Add staff_type
    echo "Checking if 'staff_type' column exists in 'users' table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'staff_type'");
    if ($stmt->fetch()) {
        echo "Column 'staff_type' already exists.\n";
    } else {
        echo "Adding 'staff_type' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN staff_type ENUM('Permanent', 'Freelancer') DEFAULT 'Permanent' AFTER role");
        echo "Column 'staff_type' added successfully.\n";
    }

    // Add base_salary
    echo "Checking if 'base_salary' column exists in 'users' table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'base_salary'");
    if ($stmt->fetch()) {
        echo "Column 'base_salary' already exists.\n";
    } else {
        echo "Adding 'base_salary' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN base_salary DECIMAL(10,2) DEFAULT 0.00 AFTER staff_type");
        echo "Column 'base_salary' added successfully.\n";
    }

    // Add employee_id (just in case)
    echo "Checking if 'employee_id' column exists in 'users' table...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($stmt->fetch()) {
        echo "Column 'employee_id' already exists.\n";
    } else {
        echo "Adding 'employee_id' column...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN employee_id VARCHAR(50) NULL UNIQUE AFTER id");
        echo "Column 'employee_id' added successfully.\n";
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage() . "\n");
}
?>
