<?php
require_once 'app_init.php';
$stmt = $pdo->query("DESCRIBE attendance");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($columns, JSON_PRETTY_PRINT);
