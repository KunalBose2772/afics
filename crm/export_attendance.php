<?php
require_once 'app_init.php';
require_once 'auth.php';

// Only Admin/Super Admin/HR can export
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'hr_manager'])) {
    die("Unauthorized Access");
}

$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Fetch attendance data
$start_date = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
$end_date = date('Y-m-t', strtotime($start_date));

// Fetch all users or filter? Usually export is for everyone or filtered.
// Let's export for ALL users for the selected month to be safe.
$stmt = $pdo->prepare("
    SELECT 
        u.full_name, 
        u.employee_id, 
        u.role,
        a.date, 
        a.check_in_time, 
        a.check_out_time, 
        a.status,
        a.check_in_latitude,
        a.check_in_longitude
    FROM attendance a 
    JOIN users u ON a.user_id = u.id 
    WHERE a.date BETWEEN ? AND ? 
    ORDER BY a.date DESC, u.full_name ASC
");
$stmt->execute([$start_date, $end_date]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set Headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="attendance_export_' . $month . '_' . $year . '.csv"');

$output = fopen('php://output', 'w');

// Add CSV Header
fputcsv($output, ['Employee Name', 'Employee ID', 'Role', 'Date', 'Check In', 'Check Out', 'Status', 'Latitude', 'Longitude']);

// Add Rows
foreach ($records as $row) {
    fputcsv($output, [
        $row['full_name'],
        $row['employee_id'],
        ucwords(str_replace('_', ' ', $row['role'])),
        $row['date'],
        $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-',
        $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-',
        $row['status'],
        $row['check_in_latitude'],
        $row['check_in_longitude']
    ]);
}

fclose($output);
exit();
?>
