<?php
session_start();
header('Content-Type: application/json');
require_once '../../config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$lat = $_POST['lat'] ?? null;
$lon = $_POST['lon'] ?? null;
$acc = $_POST['acc'] ?? 0;

if ($lat && $lon) {
    try {
        $stmt = $pdo->prepare("INSERT INTO user_locations (user_id, latitude, longitude, accuracy, recorded_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $lat, $lon, $acc]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Missing coordinates']);
}
