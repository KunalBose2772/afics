<?php
require_once 'auth.php';
require_once '../config/db.php';

// Only allow HR and Admin roles
if (!in_array($_SESSION['role'], ['hr', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get the list of user IDs from POST request
$user_ids = isset($_POST['user_ids']) ? json_decode($_POST['user_ids'], true) : [];

if (empty($user_ids) || !is_array($user_ids)) {
    http_response_code(400);
    echo json_encode(['error' => 'No user IDs provided']);
    exit;
}

// Fetch user data for all selected users
$placeholders = implode(',', array_fill(0, count($user_ids), '?'));
$stmt = $pdo->prepare("SELECT id, full_name, email, phone, role, profile_picture, employee_id FROM users WHERE id IN ($placeholders) ORDER BY full_name ASC");
$stmt->execute($user_ids);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    http_response_code(404);
    echo json_encode(['error' => 'No users found']);
    exit;
}

// Return user data as JSON for frontend processing
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'users' => $users,
    'count' => count($users)
]);
