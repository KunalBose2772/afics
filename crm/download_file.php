<?php
require_once 'app_init.php';
require_once 'auth.php';

$doc_id = intval($_GET['id'] ?? 0);
if (!$doc_id) die("Invalid ID");

$stmt = $pdo->prepare("SELECT * FROM project_documents WHERE id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) die("Not found");

$file_path = '../' . $doc['file_path'];
if (!file_exists($file_path)) die("File not found");

$ext = pathinfo($file_path, PATHINFO_EXTENSION);
$filename = $doc['file_name'] ?: ('Document_' . $doc['id'] . '.' . $ext);

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
