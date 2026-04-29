<?php
require_once 'app_init.php';
require_once 'auth.php';

// Security: Only admins/managers can export
if (!in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'coordinator', 'hod', 'hr', 'hr_manager', 'doctor'])) {
    die("Access Denied");
}

$type = $_GET['type'] ?? ''; // 'client' or 'fo'
$id = $_GET['id'] ?? 0;
$month = $_GET['month'] ?? date('Y-m');

if (empty($type) || empty($id)) {
    die("Missing parameters");
}

$filename = "Export_{$type}_{$id}_{$month}.csv";
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"$filename\"");

$where = "";
$params = [];

if ($type == 'client') {
    $where = "WHERE p.client_id = ? AND p.created_at LIKE ?";
    $params = [$id, "$month%"];
    $title = "Client Wise Report - " . $pdo->query("SELECT company_name FROM clients WHERE id=$id")->fetchColumn();
} elseif ($type == 'fo') {
    $where = "WHERE (p.assigned_to = ? OR p.pt_fo_id = ? OR p.hp_fo_id = ? OR p.other_fo_id = ?) AND p.created_at LIKE ?";
    $params = [$id, $id, $id, $id, "$month%"];
    $title = "FO Wise Report - " . $pdo->query("SELECT full_name FROM users WHERE id=$id")->fetchColumn();
}

$sql = "SELECT p.*, c.company_name, u.full_name as officer_name 
        FROM projects p 
        JOIN clients c ON p.client_id = c.id 
        LEFT JOIN users u ON p.assigned_to = u.id 
        $where 
        ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$output = fopen('php://output', 'w');

// Title Row
fputcsv($output, ["$title ($month)"]);
fputcsv($output, []);

// Headers
fputcsv($output, ['Claim #', 'Insurer Claim #', 'Patient Name', 'Client', 'Hospital', 'Scope', 'Claim Type', 'Status', 'Created At', 'TAT Deadline', 'Field Officer', 'Gross Fee', 'TA Amount', 'Fine/Ded.', 'Net Paid', 'UTR #', 'Payment Status']);

// Data Rows
foreach ($rows as $r) {
    $gross = ($r['price_hospital'] + $r['price_patient'] + $r['price_other']);
    $deductions = ($r['fine_amount'] + $r['tat_deduction'] + $r['other_deduction']);
    $net = ($r['payment_status'] == 'Paid') ? (($gross + $r['ta_amount']) - $deductions) : 0;

    fputcsv($output, [
        $r['claim_number'],
        $r['manual_claim_number'] ?: '-',
        $r['title'],
        $r['company_name'],
        $r['hospital_name'],
        $r['scope'],
        $r['claim_type'],
        $r['status'],
        date('d-m-Y', strtotime($r['created_at'])),
        date('d-m-Y', strtotime($r['tat_deadline'])),
        $r['officer_name'],
        number_format($gross, 2),
        number_format($r['ta_amount'] ?? 0, 2),
        number_format($deductions, 2),
        number_format($net, 2),
        $r['payment_utr'] ?: '-',
        $r['payment_status']
    ]);
}

fclose($output);
exit();
?>
