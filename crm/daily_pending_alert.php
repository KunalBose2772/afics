<?php
require_once 'app_init.php';
require_once '../includes/functions.php';

// This script can be run via CLI/Cron
// Usage: php daily_pending_alert.php

echo "Starting Daily Pending Alert Service...\n";
set_time_limit(300);

// ─── 1. Field Officers ─────────────────────────────────────────────────────
$fos = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('field_officer', 'fo_manager', 'fo', 'investigator', 'field_agent')")->fetchAll();

foreach ($fos as $fo) {
    $uid = $fo['id'];
    
    $stmt = $pdo->prepare("SELECT p.*, c.company_name 
                           FROM projects p 
                           LEFT JOIN clients c ON p.client_id = c.id
                           WHERE (p.assigned_to = ? OR p.pt_fo_id = ? OR p.hp_fo_id = ? OR p.other_fo_id = ?) 
                           AND p.status IN ('Pending', 'In-Progress', 'Hold')
                           ORDER BY p.tat_deadline ASC");
    $stmt->execute([$uid, $uid, $uid, $uid]);
    $pending = $stmt->fetchAll();
    
    if (count($pending) > 0) {
        echo "Sending mail to {$fo['full_name']} ({$fo['email']})...\n";
        
        $subject = "Daily Alert: You have " . count($pending) . " Pending Cases - " . date('d-m-Y');
        
        $body = "
        <div style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #d32f2f;'>AFICS DOCUMANTRAA - Daily Pending Cases Alert</h2>
            <p>Dear <b>{$fo['full_name']}</b>,</p>
            <p>This is an automated reminder of your currently active/pending cases in the portal. Please ensure timely field visits and document uploads.</p>
            
            <table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; font-size: 0.9rem;'>
                <tr style='background: #f5f5f5;'>
                    <th>SL. No</th>
                    <th>CLAIM Number</th>
                    <th>Current TAT</th>
                    <th>Patient Name</th>
                    <th>Hospital Name</th>
                    <th>TAT DEADLINE</th>
                    <th>Status</th>
                </tr>";
                
        $i = 1;
        foreach ($pending as $p) {
            $created_at = new DateTime($p['created_at']);
            $today = new DateTime();
            $current_tat = $created_at->diff($today)->days . ' Days';
            $deadline = date('d M Y', strtotime($p['tat_deadline']));
            $status = ($p['status'] == 'FO-Closed') ? 'FO Closer' : $p['status'];
            $color = ($p['status'] == 'Hold') ? '#ff9800' : '#1e88e5';
            $claim_no = $p['manual_claim_number'] ?: '-';
            
            $body .= "
                <tr>
                    <td>" . $i++ . "</td>
                    <td><b>$claim_no</b></td>
                    <td>$current_tat</td>
                    <td>{$p['title']}</td>
                    <td>" . ($p['hospital_name'] ?? 'N/A') . "</td>
                    <td style='color: #d32f2f; font-weight: bold;'>$deadline</td>
                    <td style='color: $color; font-weight: bold;'>$status</td>
                </tr>";
        }
        
        $body .= "
            </table>
            <p style='margin-top: 20px;'>
                <a href='https://documantraa.in/crm/' style='background: #1976d2; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Open Portal Dashboard</a>
            </p>
            <hr style='border: 0; border-top: 1px solid #eee; margin-top: 30px;'>
            <p style='font-size: 0.8rem; color: #777;'>Sent by AFICS DOCUMANTRAA Automation Unit.</p>
        </div>";
        
        queue_email($pdo, $fo['email'], $subject, $body, 1);
    }
}

// ─── 2. Doctors — send pending cases assigned to them ──────────────────────
echo "Sending doctor alerts...\n";

$doctors = $pdo->query("SELECT id, full_name, email FROM users WHERE role IN ('doctor', 'incharge')")->fetchAll();

foreach ($doctors as $doc) {
    $uid = $doc['id'];
    
    $stmt = $pdo->prepare("SELECT p.*, c.company_name 
                           FROM projects p 
                           LEFT JOIN clients c ON p.client_id = c.id
                           WHERE p.assigned_doctor_id = ?
                           AND p.status IN ('Pending', 'In-Progress', 'FO-Closed', 'Hold')
                           ORDER BY p.tat_deadline ASC");
    $stmt->execute([$uid]);
    $pending = $stmt->fetchAll();
    
    if (count($pending) > 0) {
        echo "Sending doctor alert to {$doc['full_name']} ({$doc['email']})...\n";
        
        $subject = "Daily Alert: " . count($pending) . " Pending Cases Assigned to You - " . date('d-m-Y');
        
        $body = "
        <div style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #3D0C60;'>AFICS DOCUMANTRAA - Daily Pending Cases — Doctor Review</h2>
            <p>Dear Dr. <b>{$doc['full_name']}</b>,</p>
            <p>This is your daily summary of cases assigned to you for medical review and reporting. Please complete your reports at the earliest.</p>
            
            <table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; font-size: 0.9rem;'>
                <tr style='background: #f3f0fa;'>
                    <th>SL. No</th>
                    <th>CLAIM Number</th>
                    <th>Current TAT</th>
                    <th>Patient Name</th>
                    <th>Hospital Name</th>
                    <th>TAT DEADLINE</th>
                    <th>Status</th>
                </tr>";
        
        $i = 1;
        foreach ($pending as $p) {
            $created_at = new DateTime($p['created_at']);
            $today = new DateTime();
            $current_tat = $created_at->diff($today)->days . ' Days';
            $deadline = date('d M Y', strtotime($p['tat_deadline']));
            $status = ($p['status'] == 'FO-Closed') ? 'FO Closer' : $p['status'];
            $color = ($p['status'] == 'FO-Closed') ? '#0288d1' : (($p['status'] == 'Hold') ? '#ff9800' : '#1e88e5');
            $claim_no = $p['manual_claim_number'] ?: '-';
            
            $body .= "
                <tr>
                    <td>" . $i++ . "</td>
                    <td><b>$claim_no</b></td>
                    <td>$current_tat</td>
                    <td>{$p['title']}</td>
                    <td>" . ($p['hospital_name'] ?? 'N/A') . "</td>
                    <td style='color: #d32f2f; font-weight: bold;'>$deadline</td>
                    <td style='color: $color; font-weight: bold;'>$status</td>
                </tr>";
        }
        
        $body .= "
            </table>
            <p style='margin-top: 20px;'>
                <a href='https://documantraa.in/crm/' style='background: #3D0C60; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Open Portal &amp; Submit Reports</a>
            </p>
            <hr style='border: 0; border-top: 1px solid #eee; margin-top: 30px;'>
            <p style='font-size: 0.8rem; color: #777;'>Sent by AFICS DOCUMANTRAA Automation Unit.</p>
        </div>";
        
        queue_email($pdo, $doc['email'], $subject, $body, 1);
    }
}

echo "Finished Process.\n";
