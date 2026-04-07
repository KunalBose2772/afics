<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login');
    exit;
}
require_once '../config/db.php';

// Function to check permissions
function has_permission($module) {
    global $pdo;
    
    // Super Admin bypass
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }

    // Fetch permissions for the role
    $stmt = $pdo->prepare("SELECT modules_access FROM permissions WHERE role_name = ?");
    $stmt->execute([$_SESSION['role']]);
    $permissions = $stmt->fetchColumn();

    if ($permissions) {
        $access_list = json_decode($permissions, true);
        if (in_array('all', $access_list) || in_array($module, $access_list)) {
            return true;
        }
    }

    return false;
}

// Function to enforce permission
function require_permission($module) {
    if (!has_permission($module)) {
        die('<div class="alert alert-danger m-5">Access Denied: You do not have permission to view this module.</div>');
    }
}

// Function to log actions
function log_action($action, $details = null) {
    global $pdo;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, role, action, details, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $_SESSION['role'],
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
?>
