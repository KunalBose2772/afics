<?php
// Migration: 008_add_advanced_features.php
// Description: Adds support for Dual Allocation, Document Categories, Salary Points, and MRD Payments

require_once __DIR__ . '/../config/db.php';

try {
    // 1. Update PROJECTS table for Dual Allocation & TAT
    echo "Updating projects table...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM projects")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('assigned_doctor_id', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN assigned_doctor_id INT NULL AFTER assigned_to");
        echo " - Added assigned_doctor_id\n";
    }
    
    if (!in_array('mrd_status', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_status ENUM('Pending', 'In-Review', 'Paid', 'Rejected') DEFAULT 'Pending'");
        echo " - Added mrd_status\n";
    }

    if (!in_array('mrd_amount', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_amount DECIMAL(10,2) DEFAULT 0.00");
        echo " - Added mrd_amount\n";
    }

    if (!in_array('mrd_qr_path', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_qr_path VARCHAR(255) NULL");
        echo " - Added mrd_qr_path\n";
    }

    if (!in_array('mrd_notes', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_notes TEXT NULL");
        echo " - Added mrd_notes\n";
    }

    if (!in_array('mrd_payment_slip', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_payment_slip VARCHAR(255) NULL");
        echo " - Added mrd_payment_slip\n";
    }

    if (!in_array('mrd_receipt', $columns)) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN mrd_receipt VARCHAR(255) NULL");
        echo " - Added mrd_receipt\n";
    }

    // 2. Update PROJECT_DOCUMENTS table for Categorization & GPS
    echo "Updating project_documents table...\n";
    // Check if table exists first
    $stmt = $pdo->query("SHOW TABLES LIKE 'project_documents'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("CREATE TABLE project_documents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            uploaded_by INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
        )");
        echo " - Created project_documents table\n";
    }

    $doc_columns = $pdo->query("SHOW COLUMNS FROM project_documents")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('category', $doc_columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN category ENUM('General', 'Hospital', 'Patient', 'Investigation', 'Other') DEFAULT 'General'");
        echo " - Added category\n";
    }

    if (!in_array('document_type', $doc_columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN document_type VARCHAR(100) NULL COMMENT 'e.g. ICP, Tariff, Visit Photo'");
        echo " - Added document_type\n";
    }

    if (!in_array('gps_latitude', $doc_columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN gps_latitude DECIMAL(10,8) NULL");
        echo " - Added gps_latitude\n";
    }

    if (!in_array('gps_longitude', $doc_columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN gps_longitude DECIMAL(11,8) NULL");
        echo " - Added gps_longitude\n";
    }
    
    if (!in_array('is_hidden_from_fo', $doc_columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN is_hidden_from_fo TINYINT(1) DEFAULT 0");
        echo " - Added is_hidden_from_fo\n";
    }

    // 3. Update USERS table for Salary Points
    echo "Updating users table...\n";
    $user_columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('salary_base', $user_columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN salary_base DECIMAL(10,2) DEFAULT 15000.00");
        echo " - Added salary_base\n";
    }

    if (!in_array('target_points', $user_columns)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN target_points INT DEFAULT 130");
        echo " - Added target_points\n";
    }

    // 4. Create SALARY_REGISTRY table (Ledger for Points/Money)
    echo "Creating salary_registry table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS salary_registry (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        entry_date DATE NOT NULL,
        entry_type ENUM('Point', 'Incentive', 'Deduction', 'Allowance', 'Salary') NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Points or Money Value',
        description VARCHAR(255) NULL,
        added_by INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    echo "Migration 008 completed successfully!\n";

} catch (PDOException $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
