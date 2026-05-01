<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';
date_default_timezone_set('Asia/Kolkata'); // Set Global Timezone to IST
// Add any basic auth checks here if needed loosely for dev
?>
