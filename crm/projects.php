<?php
require_once 'app_init.php';
require_once 'auth.php';

// Migration: Ensure payment_utr exists
try {
    $pdo->query("SELECT payment_utr FROM projects LIMIT 1");
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Unknown column') !== false) {
        $pdo->exec("ALTER TABLE projects ADD COLUMN payment_utr VARCHAR(255) AFTER payment_status");
    }
}

require_permission('projects');

// Helper function for status update logic
function is_disabled($current, $target, $is_admin) {
    if ($is_admin) return '';
    if ($current == 'Completed') return 'disabled';
    
    // Define logic: e.g. Pending can go anywhere. In-Progress cannot go back to Pending?
    // Matches logic in status update handler
    $forbidden = [
        'In-Progress' => ['Pending'],
        'Hold' => ['Pending'],
        'Completed' => ['Pending', 'In-Progress', 'Hold']
    ];
    if (in_array($target, $forbidden[$current] ?? [])) return 'disabled';
    return '';
}

$error_message = '';

// Handle Status Update
if (isset($_POST['update_status'])) {
    try {
        $project_id = $_POST['project_id'];
        $new_status = $_POST['status'];

        // Fetch current status and details
        $current_project = $pdo->query("SELECT * FROM projects WHERE id = $project_id")->fetch();
        if (!$current_project) throw new Exception("Project not found.");
        
        $current_status = $current_project['status'];
        $claim_number = $current_project['claim_number'];

        // Role-based Status Logic
        $user_role = $_SESSION['role'];
        $allowed = true;

        if ($user_role != 'admin' && $user_role != 'super_admin') {
            $forbidden_transitions = [
                'Pending' => [], 
                'In-Progress' => ['Pending'], 
                'Hold' => ['Pending'],
                'FO-Closed' => ['Pending'],
                'Completed' => ['Pending', 'In-Progress', 'Hold', 'FO-Closed']
            ];

            if (in_array($new_status, $forbidden_transitions[$current_status] ?? [])) {
                $allowed = false;
                $error_message = "You cannot revert the status to $new_status.";
            }
        }

        // Evidence Check
        if ($new_status == 'Completed' && $allowed) {
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM field_visits WHERE claim_number = ? AND status = 'Approved'");
            $stmt_check->execute([$claim_number]);
            $has_evidence = $stmt_check->fetchColumn() > 0;

            if (!$has_evidence && $user_role != 'admin' && $user_role != 'super_admin') {
                $allowed = false;
                $error_message = "Cannot complete claim. No approved field visit evidence found for Claim #$claim_number.";
            }
        }

        if ($allowed) {
            $timer_sql = "";
            $params = [$new_status, $project_id];
            
            if ($new_status == 'In-Progress' && empty($current_project['started_at'])) {
                $timer_sql = ", started_at = NOW()";
            }

            // Auto-calculate fine if moving to FO-Closed or Completed and not confirmed yet
            $fine_sql = "";
            if (($new_status == 'FO-Closed' || $new_status == 'Completed') && $current_project['is_fine_confirmed'] == 0) {
                 $calculated_fine = calculate_project_fine($current_project);
                 if ($calculated_fine > 0) {
                     $fine_sql = ", fine_amount = $calculated_fine";
                 }
            }

            $sql = "UPDATE projects SET status = ? $timer_sql $fine_sql WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            if (!$stmt->execute($params)) {
               $err = $stmt->errorInfo();
               throw new Exception("Update failed: " . $err[2]);
            }

            header('Location: projects.php?success=1');
            exit;
        }
    } catch (Exception $e) {
        $error_message = "Error Updating Status: " . $e->getMessage();
    }
}

// Handle Fine Confirmation
if (isset($_POST['confirm_fine'])) {
    try {
        $pid = $_POST['project_id'];
        $amt = $_POST['fine_amount'];
        $remark = $_POST['fine_remark'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE projects SET fine_amount = ?, fine_remark = ?, is_fine_confirmed = 1 WHERE id = ?");
        $stmt->execute([$amt, $remark, $pid]);
        
        header('Location: projects.php?success=fine_confirmed');
        exit;
    } catch (Exception $e) {
        $error_message = "Fine Error: " . $e->getMessage();
    }
}

// Handle Hard Copy Received
if (isset($_POST['mark_hard_copy'])) {
    try {
        $pid = $_POST['project_id'];
        $stmt = $pdo->prepare("UPDATE projects SET is_hard_copy_received = 1, hard_copy_received_at = NOW() WHERE id = ?");
        $stmt->execute([$pid]);
        header('Location: projects.php?success=hard_copy_received');
        exit;
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

// Handle Hard Copy Override
if (isset($_POST['override_hard_copy'])) {
    try {
        $pid = $_POST['project_id'];
        $stmt = $pdo->prepare("UPDATE projects SET is_hard_copy_overridden = 1, hard_copy_overridden_by = ? WHERE id = ?");
        $stmt->execute([$_SESSION['user_id'], $pid]);
        header('Location: projects.php?success=hard_copy_overridden');
        exit;
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

// Handle Mark Paid
if (isset($_POST['mark_paid'])) {
    try {
        $pid = $_POST['project_id'];
        $utr = $_POST['payment_utr'] ?? '';
        
        if (empty($utr)) throw new Exception("UTR Number is required to release payment.");

        $stmt = $pdo->prepare("UPDATE projects SET payment_status = 'Paid', payment_utr = ?, payment_confirmed_at = NOW(), payment_confirmed_by = ? WHERE id = ?");
        $stmt->execute([$utr, $_SESSION['user_id'], $pid]);
        
        // --- Notification Logic ---
        $p = $pdo->prepare("SELECT p.*, tm.email as tm_email, tm.full_name as tm_name, tm.phone as tm_phone,
                                 fo.full_name as fo_name, fo.phone as fo_phone,
                                 pfo.phone as pt_fo_phone, hfo.phone as hp_fo_phone
                          FROM projects p 
                          LEFT JOIN users tm ON p.team_manager_id = tm.id 
                          LEFT JOIN users fo ON p.assigned_to = fo.id
                          LEFT JOIN users pfo ON p.pt_fo_id = pfo.id
                          LEFT JOIN users hfo ON p.hp_fo_id = hfo.id
                          WHERE p.id = ?");
        $p->execute([$pid]);
        $p = $p->fetch();
        
        if ($p) {
            $total = ($p['price_hospital'] + $p['price_patient'] + $p['price_other']) - $p['fine_amount'];
            $claim_no = $p['claim_number'];
            $patient = $p['title'];
            $utr_text = "UTR #: $utr";
            $paid_date = date('d-m-Y');
            
            // --- Notification for Team Manager ---
            if (!empty($p['tm_email'])) {
                $tm_subject = "Fees for Claim of $patient with Claim No $claim_no Transferred on $paid_date";
                $tm_body = "Dear {$p['tm_name']},\n\nGreetings !!!\n\nThis is to inform you that the payment for verification of $patient has been transferred on $paid_date.\n\nDetails of Payment Transferred to you below:\n\n";
                $tm_body .= "Claim No: $claim_no\n";
                $tm_body .= "Approved Amount: ₹".number_format($total, 2)."\n";
                $tm_body .= "Approved By: " . ($_SESSION['user_name'] ?? 'Admin') . "\n";
                $tm_body .= "Transferred Amount: ₹".number_format($total, 2)."\n";
                $tm_body .= "Transaction UTR: $utr\n\n";
                $tm_body .= "Regards,\nAccounts Team";
                
                if (function_exists('queue_email')) queue_email($pdo, $p['tm_email'], $tm_subject, $tm_body);
                if (!empty($p['tm_phone']) && function_exists('send_whatsapp_notification')) {
                    send_whatsapp_notification($p['tm_phone'], "Payment Released (₹".number_format($total, 2).") for Case #$claim_no ($patient). $utr_text. Check portal.");
                }
            }

            // --- Notification for Assigned FOs ---
            $p_fo = $pdo->prepare("SELECT u.email, u.phone, u.full_name FROM projects p 
                                  LEFT JOIN users u ON (u.id = p.assigned_to OR u.id = p.pt_fo_id OR u.id = p.hp_fo_id OR u.id = p.other_fo_id)
                                  WHERE p.id = ?");
            $p_fo->execute([$pid]);
            $assigned_users = $p_fo->fetchAll();
            
            foreach ($assigned_users as $u) {
                if (!empty($u['email']) && function_exists('queue_email')) {
                    $fo_subject = "Fees for Claim of $patient with Claim No $claim_no Transferred on $paid_date";
                    $fo_body = "Dear {$u['full_name']},\n\nGreetings !!!\n\nThis is to inform you that your payment for verification of $patient is transferred on $paid_date.\n\nDetails of Payment Transferred to you below:\n\n";
                    $fo_body .= "Claim No: $claim_no\n";
                    $fo_body .= "Approved Amount: ₹".number_format($total, 2)."\n";
                    $fo_body .= "Approved By: " . ($_SESSION['user_name'] ?? 'Admin') . "\n";
                    $fo_body .= "Transferred Amount: ₹".number_format($total, 2)."\n";
                    $fo_body .= "Transaction UTR: $utr\n\n";
                    $fo_body .= "Regards,\nAccounts Team";
                    
                    queue_email($pdo, $u['email'], $fo_subject, $fo_body);
                }
                if (!empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                    $msg = "Payment Released for Case #$claim_no ($patient). $utr_text. Check portal for details.";
                    send_whatsapp_notification($u['phone'], $msg);
                }
            }
        }
        
        header('Location: projects.php?success=payment_released');
        exit;
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

// Handle New Claim
if (isset($_POST['create_project'])) {
    // ... (Same logic as legacy but redirected to V2)
    // Extracting vars
    $title = $_POST['title']; 
    $claim_number = $_POST['claim_number'];
    $client_id = $_POST['client_id'];
    $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
    $team_manager_id = !empty($_POST['team_manager_id']) ? $_POST['team_manager_id'] : null;
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
    $pt_fo_id = !empty($_POST['pt_fo_id']) ? $_POST['pt_fo_id'] : null;
    $hp_fo_id = !empty($_POST['hp_fo_id']) ? $_POST['hp_fo_id'] : null;
    $other_fo_id = !empty($_POST['other_fo_id']) ? $_POST['other_fo_id'] : null;
    $tat_deadline = $_POST['tat_deadline'];
    $description = $_POST['description'];
    $scope = $_POST['scope'];
    $hospital_name = $_POST['hospital_name'] ?? null;
    $hospital_address = $_POST['hospital_address'] ?? null;
    $doa = !empty($_POST['doa']) ? $_POST['doa'] : null;
    $dod = !empty($_POST['dod']) ? $_POST['dod'] : null;
    $uhid = $_POST['uhid'] ?? null;
    $diagnosis = $_POST['diagnosis'] ?? null;
    $price_hospital = !empty($_POST['price_hospital']) ? $_POST['price_hospital'] : 0;
    $price_patient = !empty($_POST['price_patient']) ? $_POST['price_patient'] : 0;
    $price_other = !empty($_POST['price_other']) ? $_POST['price_other'] : 0;
    $case_points = !empty($_POST['case_points']) ? $_POST['case_points'] : 1.0;

    try {
        $stmt = $pdo->prepare("INSERT INTO projects (title, claim_number, client_id, assigned_to, team_manager_id, manager_id, pt_fo_id, hp_fo_id, other_fo_id, tat_deadline, description, scope, hospital_name, hospital_address, doa, dod, uhid, diagnosis, price_hospital, price_patient, price_other, case_points, status, mrd_status, allocation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', CURDATE())");
        $stmt->execute([$title, $claim_number, $client_id, $assigned_to, $team_manager_id, $manager_id, $pt_fo_id, $hp_fo_id, $other_fo_id, $tat_deadline, $description, $scope, $hospital_name, $hospital_address, $doa, $dod, $uhid, $diagnosis, $price_hospital, $price_patient, $price_other, $case_points]);
                // --- Notification Logic ---
        require_once '../includes/functions.php'; 
        
        $all_recipients = [];
        
        // Helper to add unique recipients
        $add_to_list = function($uid) use (&$all_recipients, $pdo) {
            if (empty($uid)) return;
            $u = $pdo->query("SELECT id, full_name, email, phone FROM users WHERE id = $uid")->fetch();
            if ($u) $all_recipients[$u['id']] = $u;
        };

        // Collect all assigned users
        $add_to_list($assigned_to);
        $add_to_list($team_manager_id);
        $add_to_list($manager_id);
        $add_to_list($pt_fo_id);
        $add_to_list($hp_fo_id);
        $add_to_list($other_fo_id);

        $client_name = $pdo->query("SELECT company_name FROM clients WHERE id = $client_id")->fetchColumn();
        $subject = "New Claim Assigned in OIMS - Claim #: $claim_number";

        foreach ($all_recipients as $u) {
            // Email Body
            $body = "Dear {$u['full_name']},\n\n";
            $body .= "Greetings of the Day!!\n";
            $body .= "New claim Assigned in OIMS with following details:\n\n";
            $body .= "Claim No: - $claim_number\n";
            $body .= "Patient Name: - $title\n";
            $body .= "Hospital Name: - " . ($hospital_name ?? 'N/A') . "\n";
            $body .= "Hospital City: - " . ($hospital_address ?? 'N/A') . "\n";
            $body .= "Trigger: - " . ($diagnosis ?? 'Standard') . "\n";
            $body .= "Part: - $scope\n\n";
            $body .= "Wish you best of luck\n";
            $body .= "Regards,\nAllocation Team";

            // Send Email
            if(function_exists('queue_email')) {
                queue_email($pdo, $u['email'], $subject, $body, $_SESSION['user_id']);
            }
            
            // Send WhatsApp
            if (!empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                $wa_msg = "New Claim Assigned!\nClaim: $claim_number\nPatient: $title\nHospital: " . ($hospital_name ?? 'N/A') . "\nCheck portal for details.";
                send_whatsapp_notification($u['phone'], $wa_msg);
            }
        }

        // Notify Doctor separately if exists (using the POST variable if set, otherwise logic might skip)
        $doc_id = $_POST['assigned_doctor_id'] ?? null;
        if (!empty($doc_id)) {
            $doctor = $pdo->query("SELECT full_name, email, phone FROM users WHERE id = $doc_id")->fetch();
            if ($doctor) {
                $subject_doc = "New Case Assigned for Review: $claim_number";
                $body_doc = "Dear {$doctor['full_name']},\n\nYou have been assigned as Incharge/Doctor for a new claim.\nClaim #: $claim_number\nPatient: $title\n\nPlease check the portal for details.\n\nRegards,\nAllocation Team";
                if(function_exists('queue_email')) queue_email($pdo, $doctor['email'], $subject_doc, $body_doc, $_SESSION['user_id']);
            }
        }
        // --- End Notification Logic ---
        
        header('Location: projects.php?success=created');
        exit;
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
}

// Handle Update
if (isset($_POST['update_project_details'])) {
    if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'hr', 'hr_manager'])) {
        header('Location: projects.php?error=unauthorized');
        exit;
    }
    try {
        $pid = $_POST['project_id'];
        $title = $_POST['title'];
        $claim_number = $_POST['claim_number'];
        $scope = $_POST['scope'];
        $assigned_to = !empty($_POST['assigned_to']) ? $_POST['assigned_to'] : null;
        $team_manager_id = !empty($_POST['team_manager_id']) ? $_POST['team_manager_id'] : null;
        $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $pt_fo_id = !empty($_POST['pt_fo_id']) ? $_POST['pt_fo_id'] : null;
        $hp_fo_id = !empty($_POST['hp_fo_id']) ? $_POST['hp_fo_id'] : null;
        $other_fo_id = !empty($_POST['other_fo_id']) ? $_POST['other_fo_id'] : null;
        $tat_deadline = $_POST['tat_deadline'];
        $description = $_POST['description'];
        $hospital_name = $_POST['hospital_name'] ?? null;
        $hospital_address = $_POST['hospital_address'] ?? null;
        $case_points = $_POST['case_points'] ?? 0;
        
        $sql = "UPDATE projects SET title=?, claim_number=?, scope=?, assigned_to=?, team_manager_id=?, manager_id=?, pt_fo_id=?, hp_fo_id=?, other_fo_id=?, tat_deadline=?, description=?, hospital_name=?, hospital_address=?, case_points=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $claim_number, $scope, $assigned_to, $team_manager_id, $manager_id, $pt_fo_id, $hp_fo_id, $other_fo_id, $tat_deadline, $description, $hospital_name, $hospital_address, $case_points, $pid]);
        
        // --- Notification Logic (Update/Reassignment) ---
        require_once '../includes/functions.php'; 
        
        $all_recipients = [];
        $add_to_list = function($uid) use (&$all_recipients, $pdo) {
            if (empty($uid)) return;
            $u = $pdo->query("SELECT id, full_name, email, phone FROM users WHERE id = $uid")->fetch();
            if ($u) $all_recipients[$u['id']] = $u;
        };

        $add_to_list($assigned_to);
        $add_to_list($team_manager_id);
        $add_to_list($manager_id);
        $add_to_list($pt_fo_id);
        $add_to_list($hp_fo_id);
        $add_to_list($other_fo_id);

        $subject = "Update/Reassignment: OIMS Claim #: $claim_number";

        foreach ($all_recipients as $u) {
            $body = "Dear {$u['full_name']},\n\n";
            $body .= "A claim you are assigned to has been updated/reassigned:\n\n";
            $body .= "Claim No: - $claim_number\n";
            $body .= "Patient Name: - $title\n";
            $body .= "Hospital: - " . ($hospital_name ?? 'N/A') . "\n";
            $body .= "Hospital Address: - " . ($hospital_address ?? 'N/A') . "\n";
            $body .= "Part: - $scope\n\n";
            $body .= "Please log in to the portal for updated details.\n";
            $body .= "Regards,\nAllocation Team";

            if(function_exists('queue_email')) queue_email($pdo, $u['email'], $subject, $body, $_SESSION['user_id']);
            if (!empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                send_whatsapp_notification($u['phone'], "Claim $claim_number Updated!\nPatient: $title\nHospital: " . ($hospital_name ?? 'N/A') . "\nPlease check portal.");
            }
        }
        // --- End Notification Logic ---

        header('Location: projects.php?success=updated');
        exit;
    } catch (Exception $e) {
        $error_message = "Update Failed: " . $e->getMessage();
    }
}

// Search Logic
$search = trim($_GET['search'] ?? '');
$filter = trim($_GET['filter'] ?? '');
$is_breach = ($filter == 'breach');

$search_sql = "";
if (!empty($search)) {
    $term = $pdo->quote("%$search%");
    $search_sql = " AND (p.title LIKE $term OR p.claim_number LIKE $term OR p.description LIKE $term OR u.full_name LIKE $term)";
}

// Fetch Data
$where = "";
$is_ho_staff = in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'doctor']);
if (!$is_ho_staff) {
    // FOs only see cases assigned to them that are NOT closed/completed
    $where .= " AND (p.assigned_to = {$_SESSION['user_id']} OR p.pt_fo_id = {$_SESSION['user_id']} OR p.hp_fo_id = {$_SESSION['user_id']} OR p.other_fo_id = {$_SESSION['user_id']})";
    $where .= " AND p.status IN ('Pending', 'In-Progress', 'Hold')";
}

// Breach Filter Logic
if ($is_breach) {
    $today = date('Y-m-d');
    $where .= " AND tat_deadline < '$today'";
}

$pending_projects = [];
$progress_projects = [];
$closer_projects = [];
$completed_projects = [];
$schema_warning = false;

try {
    $all_stmt = $pdo->query("SELECT p.*, c.company_name, 
            u.full_name as officer_name, 
            tm.full_name as tm_name, 
            mngr.full_name as mngr_name, 
            ptfo.full_name as pt_fo_name, 
            hpfo.full_name as hp_fo_name, 
            otherfo.full_name as other_fo_name 
            FROM projects p 
            JOIN clients c ON p.client_id = c.id 
            LEFT JOIN users u ON p.assigned_to = u.id 
            LEFT JOIN users tm ON p.team_manager_id = tm.id
            LEFT JOIN users mngr ON p.manager_id = mngr.id
            LEFT JOIN users ptfo ON p.pt_fo_id = ptfo.id
            LEFT JOIN users hpfo ON p.hp_fo_id = hpfo.id
            LEFT JOIN users otherfo ON p.other_fo_id = otherfo.id
            WHERE 1=1 $where $search_sql 
            ORDER BY p.id DESC");
    $all_projects = $all_stmt->fetchAll();
} catch (PDOException $e) {
    if ($e->getCode() == '42S22' || strpos($e->getMessage(), 'Unknown column') !== false) {
        $schema_warning = true;
        $all_stmt = $pdo->query("SELECT p.*, c.company_name, u.full_name as officer_name 
                FROM projects p 
                JOIN clients c ON p.client_id = c.id 
                LEFT JOIN users u ON p.assigned_to = u.id 
                WHERE 1=1 $where $search_sql 
                ORDER BY p.id DESC");
        $all_projects = $all_stmt->fetchAll();
    } else {
        throw $e;
    }
}

foreach ($all_projects as $p) {
    if ($p['status'] == 'Pending') $pending_projects[] = $p;
    elseif ($p['status'] == 'In-Progress' || $p['status'] == 'Hold') $progress_projects[] = $p;
    elseif ($p['status'] == 'FO-Closed') $closer_projects[] = $p;
    elseif ($p['status'] == 'Completed') $completed_projects[] = $p;
}

// Fetch Users for Assignment
$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];
$officer_where = "";
if ($role == 'team_manager' || $role == 'fo_manager') {
    $officer_where = " AND (reporting_to = $user_id OR id = $user_id)";
}
$officers = $pdo->query("SELECT * FROM users WHERE role IN ('investigator', 'field_agent', 'fo', 'field_officer') $officer_where")->fetchAll();

// Fetch Team Managers and Managers (for Admins)
$tms = $pdo->query("SELECT * FROM users WHERE role IN ('team_manager', 'fo_manager', 'admin')")->fetchAll();
$mngrs = $pdo->query("SELECT * FROM users WHERE role IN ('manager', 'hod', 'admin')")->fetchAll();
// Clients
$clients = $pdo->query("SELECT * FROM clients")->fetchAll();
$doctors = $pdo->query("SELECT * FROM users WHERE role IN ('incharge', 'doctor')")->fetchAll();
$managers = $pdo->query("SELECT * FROM users WHERE role IN ('manager', 'admin', 'super_admin')")->fetchAll();
$team_managers = $pdo->query("SELECT * FROM users WHERE role IN ('team_manager', 'admin', 'super_admin')")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= $is_breach ? 'TAT Breaches' : 'Claims Management' ?> - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .bucket-tabs {
            display: flex !important;
            gap: 10px !important;
            overflow-x: auto !important;
            padding: 5px 5px 15px 5px !important;
            margin-bottom: 20px !important;
            scrollbar-width: none;
            -webkit-overflow-scrolling: touch;
        }
        .bucket-tabs::-webkit-scrollbar { display: none; }

        .bucket-pill {
            white-space: nowrap !important;
            padding: 10px 22px !important;
            border: 1.5px solid var(--border) !important;
            border-radius: 99px !important;
            color: var(--text-secondary) !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            background: white !important;
            cursor: pointer !important;
            transition: all 0.25s ease !important;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05) !important;
        }

        .bucket-pill.active {
            background: #2563eb !important;
            color: white !important;
            border-color: #2563eb !important;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3) !important;
            transform: translateY(-1px) !important;
        }
    </style>
</head>
<body>
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 32px;">
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);"><?= $is_breach ? 'TAT Breaches' : 'Claims Management' ?></h1>
                    <p class="text-muted mb-0 small">
                        <?= $is_breach ? 'Active claims exceeding TAT deadline' : 'Track insurance investigations and reports' ?>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($is_ho_staff): ?>
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                        <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline ms-1">New Claim</span>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if ($error_message): ?>
            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- Search & Filter -->
            <div class="app-card mb-4">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" name="search" class="input-v2" style="max-width: 300px;" placeholder="Search claims..." value="<?= htmlspecialchars($search) ?>">
                    <?php if($is_breach): ?>
                        <input type="hidden" name="filter" value="breach">
                    <?php endif; ?>
                    <button type="submit" class="btn-v2 btn-primary-v2">Search</button>
                    
                    <div class="ms-auto d-flex gap-2">
                         <?php if ($is_breach): ?>
                            <a href="projects.php" class="btn-v2 btn-white-v2 text-danger"><i class="bi bi-x-circle"></i> Clear Filter</a>
                        <?php else: ?>
                            <a href="projects.php?filter=breach" class="btn-v2 btn-white-v2 text-danger" title="Show Overdue"><i class="bi bi-exclamation-triangle"></i> Breaches</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <ul class="nav nav-tabs mb-4" id="claimTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button">Active Claims (<?= count($pending_projects) + count($progress_projects) + count($closer_projects) ?>)</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed" type="button">Closed Cases (<?= count($completed_projects) ?>)</button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Active Claims Tab -->
                <div class="tab-pane fade show active" id="active">
                    <!-- Sub-Bucket Pills -->
                    <div class="bucket-tabs mb-4 px-1" id="subBuckets">
                        <div class="bucket-pill active" id="pill-all" onclick="showAllBuckets()">
                            All Active
                        </div>
                        <?php if($is_ho_staff): ?>
                        <div class="bucket-pill" id="pill-pending" onclick="filterBuckets('pending-section', 'pill-pending')">
                            Pending Assign (<?= count($pending_projects) ?>)
                        </div>
                        <?php endif; ?>
                        <div class="bucket-pill" id="pill-progress" onclick="filterBuckets('progress-section', 'pill-progress')">
                            <?= $is_ho_staff ? 'FO Bucket' : 'Pending Cases' ?> (<?= count($progress_projects) ?>)
                        </div>
                        <?php if($is_ho_staff): ?>
                        <div class="bucket-pill" id="pill-closer" onclick="filterBuckets('closer-section', 'pill-closer')">
                            Closer Option (<?= count($closer_projects) ?>)
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if($is_ho_staff): ?>
                    <div id="pending-section" class="bucket-section mb-5">
                        <h6 class="fw-bold mb-3 text-muted px-2 small text-uppercase" style="letter-spacing: 1px;">Pending Allocation</h6>
                        <div class="row g-4">
                            <?php foreach ($pending_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                            <?php if(empty($pending_projects)) echo '<div class="col-12 text-center text-muted p-3 small">No pending allocations.</div>'; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="progress-section" class="bucket-section mb-5">
                        <h6 class="fw-bold mb-3 text-muted px-2 small text-uppercase" style="letter-spacing: 1px;">FO Bucket (In-Progress)</h6>
                        <div class="row g-4">
                            <?php foreach ($progress_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                            <?php if(empty($progress_projects)) echo '<div class="col-12 text-center text-muted p-3 small">No claims in FO Bucket.</div>'; ?>
                        </div>
                    </div>

                    <?php if($is_ho_staff): ?>
                    <div id="closer-section" class="bucket-section mb-5">
                        <h6 class="fw-bold mb-3 text-muted px-2 small text-uppercase" style="letter-spacing: 1px;">Closer Option (Awaiting Review)</h6>
                        <div class="row g-4">
                            <?php foreach ($closer_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                            <?php if(empty($closer_projects)) echo '<div class="col-12 text-center text-muted p-3 small">No claims awaiting closer review.</div>'; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Projects Tab -->
                <div class="tab-pane fade" id="closed">
                    <div class="row g-4">
                        <?php foreach ($completed_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                        <?php if(empty($completed_projects)) echo '<div class="col-12 text-center text-muted p-5">No completed claims found.</div>'; ?>
                    </div>
                </div>
            </div>

            <script>
            function filterBuckets(id, pillId) {
                document.querySelectorAll('.bucket-section').forEach(el => {
                    el.style.display = 'none';
                });
                document.getElementById(id).style.display = 'block';
                
                // Update pill state
                document.querySelectorAll('.bucket-pill').forEach(pill => pill.classList.remove('active'));
                document.getElementById(pillId).classList.add('active');
            }
            
            function showAllBuckets() {
                document.querySelectorAll('.bucket-section').forEach(el => {
                    el.style.display = 'block';
                });
                document.querySelectorAll('.bucket-pill').forEach(pill => pill.classList.remove('active'));
                document.getElementById('pill-all').classList.add('active');
            }
            </script>
        </div>
    </div>

    <!-- Add Project Modal -->
    <div class="modal fade" id="addProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2">Create New Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="create_project" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Claim Number <span class="text-danger">*</span></label>
                                <input type="text" name="claim_number" class="input-v2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Patient Name <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="input-v2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Patient Phone</label>
                                <input type="text" name="patient_phone" class="input-v2" placeholder="e.g. +91 9999999999">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Scope</label>
                                <select name="scope" class="input-v2 form-select" required>
                                    <option value="Full Investigation">Full Investigation</option>
                                    <option value="Hospital Part">Hospital Part</option>
                                    <option value="Patient Part">Patient Part</option>
                                    <option value="Low Cost">Low Cost</option>
                                    <option value="Other Part">Other Part</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">TAT Deadline</label>
                                <input type="date" name="tat_deadline" class="input-v2" required>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Client</label>
                                <select name="client_id" class="input-v2 form-select" required>
                                    <?php foreach($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Name</label>
                                <input type="text" name="hospital_name" class="input-v2">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Address</label>
                                <input type="text" name="hospital_address" class="input-v2">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">DOA</label>
                                <input type="date" name="doa" class="input-v2">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">DOD</label>
                                <input type="date" name="dod" class="input-v2">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">UHID</label>
                                <input type="text" name="uhid" class="input-v2">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Case Trigger / Diagnosis</label>
                                <input type="text" name="diagnosis" class="input-v2" placeholder="e.g. Low Cost, Trauma, etc.">
                            </div>
                             <div class="col-md-4">
                                <label class="stat-label mb-1">Hosp. Price</label>
                                <input type="number" step="0.01" name="price_hospital" class="input-v2" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Pt. Price</label>
                                <input type="number" step="0.01" name="price_patient" class="input-v2" placeholder="0.00">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Other Price</label>
                                <input type="number" step="0.01" name="price_other" class="input-v2" placeholder="0.00">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Team Manager</label>
                                <select name="team_manager_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($team_managers as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Manager</label>
                                <select name="manager_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Main FO <small class="text-muted">(Full)</small></label>
                                <select name="assigned_to" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(PT Part)</small></label>
                                <select name="pt_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(HP Part)</small></label>
                                <select name="hp_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(Other Part)</small></label>
                                <select name="other_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Case Points (Trigger)</label>
                                <select name="case_points" class="input-v2 form-select" required>
                                    <option value="0">0 Points</option>
                                    <option value="0.5">Low Cost (0.5)</option>
                                    <option value="1.0" selected>Standard / Full Case (1.0)</option>
                                    <option value="1.5">Complex Case (1.5)</option>
                                    <option value="2.0">Other / Priority (2.0)</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Description</label>
                                <textarea name="description" class="input-v2" rows="3"></textarea>
                            </div>
                            <div class="col-12 text-end mt-3">
                                <button type="submit" class="btn-v2 btn-primary-v2">Create Claim</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Project Modal -->
    <div class="modal fade" id="editProjectModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2">Edit Claim</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST">
                        <input type="hidden" name="update_project_details" value="1">
                        <input type="hidden" name="project_id" id="edit_project_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Claim Number</label>
                                <input type="text" name="claim_number" id="edit_claim_number" class="input-v2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Patient Name</label>
                                <input type="text" name="title" id="edit_title" class="input-v2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Patient Phone</label>
                                <input type="text" name="patient_phone" id="edit_patient_phone" class="input-v2">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Scope</label>
                                <select name="scope" id="edit_scope" class="input-v2 form-select">
                                    <option value="Full Investigation">Full Investigation</option>
                                    <option value="Hospital Part">Hospital Part</option>
                                    <option value="Patient Part">Patient Part</option>
                                    <option value="Low Cost">Low Cost</option>
                                    <option value="Other Part">Other Part</option>
                                </select>
                            </div>
                             <div class="col-md-6">
                                <label class="stat-label mb-1">TAT Deadline</label>
                                <input type="date" name="tat_deadline" id="edit_tat_deadline" class="input-v2">
                            </div>
                             <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Name</label>
                                <input type="text" name="hospital_name" id="edit_hospital_name" class="input-v2">
                            </div>
                             <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Address</label>
                                <input type="text" name="hospital_address" id="edit_hospital_address" class="input-v2">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Team Manager</label>
                                <select name="team_manager_id" id="edit_team_manager_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($team_managers as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Manager</label>
                                <select name="manager_id" id="edit_manager_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Main FO <small class="text-muted">(Full)</small></label>
                                <select name="assigned_to" id="edit_assigned_to" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(PT Part)</small></label>
                                <select name="pt_fo_id" id="edit_pt_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(HP Part)</small></label>
                                <select name="hp_fo_id" id="edit_hp_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(Other Part)</small></label>
                                <select name="other_fo_id" id="edit_other_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Description</label>
                                <textarea name="description" id="edit_description" class="input-v2" rows="3"></textarea>
                            </div>
                             <div class="col-md-12">
                                <label class="stat-label mb-1">Case Points (Trigger)</label>
                                <select name="case_points" id="edit_case_points" class="input-v2 form-select" required>
                                    <option value="0">0 Points</option>
                                    <option value="0.5">Low Cost (0.5)</option>
                                    <option value="1.0">Standard / Full Case (1.0)</option>
                                    <option value="1.5">Complex Case (1.5)</option>
                                    <option value="2.0">Other / Priority (2.0)</option>
                                </select>
                            </div>
                            <div class="col-12 mt-4 text-end">
                                <button type="submit" class="btn-v2 btn-primary-v2">Update Details</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
        <a href="attendance.php" class="bottom-nav-item">
            <i class="bi bi-calendar-check"></i>
            <span>Attend</span>
        </a>
        <?php if($is_ho_staff): ?>
         <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main" data-bs-toggle="modal" data-bs-target="#addProjectModal">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <?php endif; ?>
        <a href="projects.php" class="bottom-nav-item active">
            <i class="bi bi-folder-fill"></i>
            <span>Claims</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <!-- Fine Confirmation Modal -->
    <div class="modal fade" id="confirmFineModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 1rem;">
                <div class="modal-header border-0 bg-danger-subtle text-danger">
                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i> Confirm TAT Fine</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">The system calculated a fine based on the 5-day TAT policy. You can confirm or edit this amount before finalizing.</p>
                    <form method="POST">
                        <input type="hidden" name="confirm_fine" value="1">
                        <input type="hidden" name="project_id" id="fine_project_id">
                        <div class="mb-3">
                            <label class="stat-label mb-1">Fine Amount (₹)</label>
                            <input type="number" step="0.01" name="fine_amount" id="fine_amount_input" class="form-control input-v2 border-danger" required>
                        </div>
                        <div class="mb-4">
                            <label class="stat-label mb-1">Remarks / Reason</label>
                            <textarea name="fine_remark" class="form-control input-v2" rows="2" placeholder="Explain fine adjustment..."></textarea>
                        </div>
                        <button type="submit" class="btn-v2 btn-danger w-100 py-3 fw-bold">Confirm & Save Fine</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </script>
    
    <!-- Release Payment Modal -->
    <div class="modal fade" id="releasePaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0" style="border-radius: 1rem;">
                <div class="modal-header border-0 bg-success-subtle text-success">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i> Release Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">You are marking this case as <b>Paid</b>. This will notify the field staff and team manager.</p>
                    <form method="POST">
                        <input type="hidden" name="mark_paid" value="1">
                        <input type="hidden" name="project_id" id="pay_project_id">
                        <div class="mb-4">
                            <label class="stat-label mb-1">Transaction UTR Number / Reference <span class="text-danger">*</span></label>
                            <input type="text" name="payment_utr" class="form-control input-v2 border-success" placeholder="e.g. 3085XXXXX or UPI Ref" required>
                            <small class="text-muted" style="font-size: 0.65rem;">This UTR will be visible to the Field Officer.</small>
                        </div>
                        <button type="submit" class="btn-v2 btn-success w-100 py-3 fw-bold">Confirm & Release Payment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Existing Modals JS ...
    var editModal = document.getElementById('editProjectModal');
    if(editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('edit_project_id').value = button.getAttribute('data-id');
            document.getElementById('edit_title').value = button.getAttribute('data-title');
            document.getElementById('edit_claim_number').value = button.getAttribute('data-claim');
            document.getElementById('edit_scope').value = button.getAttribute('data-scope');
            document.getElementById('edit_tat_deadline').value = button.getAttribute('data-dead');
            document.getElementById('edit_hospital_name').value = button.getAttribute('data-hospital');
            document.getElementById('edit_hospital_address').value = button.getAttribute('data-address');
            document.getElementById('edit_assigned_to').value = button.getAttribute('data-assign');
            document.getElementById('edit_team_manager_id').value = button.getAttribute('data-tm');
            document.getElementById('edit_manager_id').value = button.getAttribute('data-mngr');
            document.getElementById('edit_pt_fo_id').value = button.getAttribute('data-ptfo');
            document.getElementById('edit_hp_fo_id').value = button.getAttribute('data-hpfo');
            document.getElementById('edit_other_fo_id').value = button.getAttribute('data-otherfo');
            document.getElementById('edit_case_points').value = button.getAttribute('data-points');
            document.getElementById('edit_description').value = button.getAttribute('data-desc');
            document.getElementById('edit_patient_phone').value = button.getAttribute('data-phone');
        });
    }

    var fineModal = document.getElementById('confirmFineModal');
    if(fineModal) {
        fineModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('fine_project_id').value = button.getAttribute('data-id');
            document.getElementById('fine_amount_input').value = button.getAttribute('data-fine');
        });
    }

    var payModal = document.getElementById('releasePaymentModal');
    if(payModal) {
        payModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            document.getElementById('pay_project_id').value = button.getAttribute('data-id');
        });
    }
    </script>
</body>
</html>
