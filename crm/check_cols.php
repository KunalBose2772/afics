<?php
require_once '../config/db.php';
$stmt = $pdo->query("DESC projects");
echo "<pre>";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
echo "</pre>";
?>
