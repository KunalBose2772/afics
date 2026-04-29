<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('Asia/Kolkata'); // Set Timezone to IST
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Function to check permissions
function has_permission($module)
{
    global $pdo;

    if (!isset($_SESSION['role']))
        return false;

    // Super Admin bypass
    if ($_SESSION['role'] === 'super_admin') {
        return true;
    }

    // Hardcoded bypass for specific roles/modules to ensure core functionality
    $hardcoded_access = [
        'doctor' => ['projects', 'clients', 'attendance', 'leaves'],
        'incharge' => ['projects', 'clients', 'attendance', 'leaves'],
        'hod' => ['projects', 'clients', 'users', 'payroll', 'attendance', 'leaves']
    ];
    if (isset($hardcoded_access[$_SESSION['role']]) && in_array($module, $hardcoded_access[$_SESSION['role']])) {
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
function require_permission($module)
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    if (!has_permission($module)) {
        die('<div class="alert alert-danger m-5">Access Denied: You do not have permission to view this module.</div>');
    }
}

// Function to log actions
function log_action($action, $details = null)
{
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

// Basic login check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Agreement Enforcement
// Skip for agreement page, logic handlers, and logout
$skip_agreement_check = ['agreement.php', 'logout.php', 'login.php'];
$current_script = basename($_SERVER['PHP_SELF']);

if (!in_array($current_script, $skip_agreement_check) && strpos($_SERVER['REQUEST_URI'], 'ajax/') === false) {
    // Check if signed (optimization: store in session on login, but here we query for safety)
    try {
        $stmt_agree = $pdo->prepare("SELECT agreement_signed FROM users WHERE id = ?");
        $stmt_agree->execute([$_SESSION['user_id']]);
        if ($stmt_agree->fetchColumn() == 0) {
            header('Location: agreement.php');
            exit;
        }
    } catch (PDOException $e) {
        // Silently fail or log error, but don't crash page if column missing (e.g. during migration)
        // error_log($e->getMessage());
    }
}
?>