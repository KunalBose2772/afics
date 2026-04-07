<?php
// Migration 010: Add Investigation Review Fields
require_once __DIR__ . '/../config/db.php';

try {
    echo "Updating project_documents table for Investigation Review...\n";
    $columns = $pdo->query("SHOW COLUMNS FROM project_documents")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('review_status', $columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN review_status ENUM('Approved', 'Rejected', 'Query', 'Pending') DEFAULT 'Pending'");
        echo " - Added review_status\n";
    }

    if (!in_array('review_remark', $columns)) {
        $pdo->exec("ALTER TABLE project_documents ADD COLUMN review_remark TEXT NULL");
        echo " - Added review_remark\n";
    }

    echo "Migration 010 Completed.\n";

} catch (PDOException $e) {
    die("Migration Failed: " . $e->getMessage() . "\n");
}
