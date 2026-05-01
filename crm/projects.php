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
            if ($stmt->execute($params)) {
                // --- Notification for FO-Closed ---
                if ($new_status == 'FO-Closed') {
                    require_once '../includes/functions.php';
                    $settings = get_settings($pdo);
                    
                    $claim_no = $current_project['manual_claim_number'] ?: $current_project['claim_number'];
                    $patient = $current_project['title'];

                    // 1. Notify Admin
                    $admin_email = $settings['contact_email'] ?? 'support@documantraa.in';
                    $subject = "Case Status Updated to FO-Closed: $claim_no";
                    $body = "Dear Admin,\n\nThe case for $patient (Claim # $claim_no) has been marked as 'FO-Closed' and is awaiting medical review.\n\nRegards,\nAFICS DOCUMANTRAA";
                    if(function_exists('queue_email')) queue_email($pdo, $admin_email, $subject, $body, $_SESSION['user_id'] ?? null);

                    // 2. Notify Assigned Doctor
                    if (!empty($current_project['assigned_doctor_id'])) {
                        $doc_stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE id = ?");
                        $doc_stmt->execute([$current_project['assigned_doctor_id']]);
                        $doctor = $doc_stmt->fetch();
                        
                        if ($doctor && !empty($doctor['email'])) {
                            $doc_subject = "Case Ready for Medical Review: $claim_no";
                            $doc_body = "Dear Dr. {$doctor['full_name']},\n\n";
                            $doc_body .= "Greetings from AFICS Investigation Agency!\n\n";
                            $doc_body .= "The case for Patient $patient (Claim #$claim_no) has been marked as 'FO-Closed' by the Field Officer.\n";
                            $doc_body .= "This case is now ready for your medical review and final reporting.\n\n";
                            $doc_body .= "Please log in to the portal to proceed: https://documantraa.in/crm/\n\n";
                            $doc_body .= "Wishing you all the best.\n\nRegards,\nAFICS DOCUMANTRAA";
                            
                            queue_email($pdo, $doctor['email'], $doc_subject, $doc_body, $_SESSION['user_id'] ?? null);
                        }
                    }
                }
                
                header('Location: projects.php?success=1');
                exit;
            }
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
        $ta = $_POST['ta_amount'] ?? 0;
        $tat_ded = $_POST['tat_deduction'] ?? 0;
        $other_ded = $_POST['other_deduction'] ?? 0;
        $notes = $_POST['payment_notes'] ?? '';
        
        $fine_amt = $_POST['fine_amount'] ?? 0;
        
        if (empty($utr)) throw new Exception("UTR Number is required to release payment.");

        $stmt = $pdo->prepare("UPDATE projects SET 
                                payment_status = 'Paid', 
                                payment_utr = ?, 
                                ta_amount = ?,
                                tat_deduction = ?,
                                other_deduction = ?,
                                fine_amount = ?,
                                payment_notes = ?,
                                payment_confirmed_at = NOW(), 
                                payment_confirmed_by = ? 
                              WHERE id = ?");
        $stmt->execute([$utr, $ta, $tat_ded, $other_ded, $fine_amt, $notes, $_SESSION['user_id'], $pid]);
        
        // --- Notification Logic ---
        require_once '../includes/functions.php';
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
            $gross = ($p['price_hospital'] + $p['price_patient'] + $p['price_other']);
            $deductions = ($p['fine_amount'] + $p['tat_deduction'] + $p['other_deduction']);
            $total = ($gross + $p['ta_amount']) - $deductions;
            
            $claim_no = $p['manual_claim_number'] ?: $p['claim_number'];
            $patient = $p['title'];
            $utr_text = "UTR/URI #: $utr";
            $paid_date = date('d-m-Y');
            
            // --- Notification for Admin (US MAIL) ---
            $settings = get_settings($pdo);
            $admin_name = $_SESSION['full_name'] ?? 'Admin';
            $admin_email = 'fopayment@documantraa.in'; // Routing payment alerts to specific department
            $admin_subject = "Fee Payment Release Notification: $claim_no";
            $admin_body = "The payment of ₹" . number_format($total, 2) . " has been released for Claim # $claim_no ($patient) with UTR/URI $utr.\n\nRegards,\nAFICS Accounts";
            if(function_exists('queue_email')) queue_email($pdo, $admin_email, $admin_subject, $admin_body, $_SESSION['user_id']);

            // --- Notification for Team Manager ---
            if (!empty($p['tm_email'])) {
                $tm_subject = "Fees Payment Alert: Claim of $patient with Claim No $claim_no Transferred on $paid_date";
                
                $tm_body = "Dear {$p['tm_name']},\n\n";
                $tm_body .= "Greetings AFICS INVESTIGATION AGENCY !!!\n\n";
                $tm_body .= "This is to inform you that the payment for verification of $patient (Claim #$claim_no) has been released.\n";
                $tm_body .= "This notification is a courtesy copy for your records.\n\n";
                $tm_body .= "Details of Payment:\n";
                $tm_body .= "Claim Type: " . strtoupper($p['scope']) . "\n";
                $tm_body .= "Approved Amount: ₹" . number_format($total, 2) . "\n";
                $tm_body .= "Approved By: $admin_name\n";
                $tm_body .= "Released to: " . ($p['fo_name'] ?: 'Assigned FOs') . "\n";
                $tm_body .= "Transferred Amount: ₹" . number_format($total, 2) . "\n";
                $tm_body .= "Transaction UTR: $utr\n";
                $tm_body .= "Transferred On: $paid_date\n\n";
                $tm_body .= "Regards,\nAFICS Accounts";
                
                if (function_exists('queue_email')) queue_email($pdo, $p['tm_email'], $tm_subject, $tm_body, $_SESSION['user_id']);
                if (!empty($p['tm_phone']) && function_exists('send_whatsapp_notification')) {
                    send_whatsapp_notification($p['tm_phone'], "Fee Transfer Alert: $claim_no\nBeneficiary: " . ($p['fo_name'] ?: 'FO') . "\nAmount: ₹".number_format($total, 2)."\nUTR: $utr");
                }
            }

            // --- Notification for Assigned FOs ---
            $p_fo = $pdo->prepare("SELECT DISTINCT u.email, u.phone, u.full_name, u.bank_name, u.account_number 
                                  FROM projects p 
                                  JOIN users u ON (u.id = p.assigned_to OR u.id = p.pt_fo_id OR u.id = p.hp_fo_id OR u.id = p.other_fo_id)
                                  WHERE p.id = ?");
            $p_fo->execute([$pid]);
            $assigned_users = $p_fo->fetchAll();
            $company_bank = $settings['company_bank'] ?? 'HDFC Bank';
            
            foreach ($assigned_users as $u) {
                if (!empty($u['email']) && function_exists('queue_email')) {
                    $fo_subject = "Fees for Claim of $patient with Claim No $claim_no Transferred on $paid_date";
                    
                    $full_name_up = strtoupper($u['full_name']);
                    $patient_up = strtoupper($patient);
                    $scope_up = strtoupper($p['scope']);
                    $admin_up = strtoupper($admin_name);
                    $total_fmt = number_format($total, 2);
                    $support_email = $settings['contact_email'] ?? 'support@documantraa.in';

                    $fo_body = "
                    <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                        <h2 style='color: #4a148c; margin-bottom: 5px;'>Dear $full_name_up,</h2>
                        <p style='margin-top: 0;'>Greetings AFICS INVESTIGATION AGENCY !!!</p>
                        
                        <p>This is to inform you that your payment for verification of <b>$patient_up</b> is transferred on $paid_date.<br>
                        Details of Payment Transferred to you below:</p>
                        
                        <p style='color: #0d47a1; font-weight: bold; margin-bottom: 10px;'>Claim Type : $scope_up</p>
                        
                        <table style='width: 100%; max-width: 550px; border-collapse: collapse; border: 1px solid #e0e0e0; font-size: 0.95rem;'>
                            <tr><td style='width: 220px; padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Gross Fee Amount</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: ₹" . number_format($gross, 2) . "</td></tr>";

                    if ($p['ta_amount'] > 0) {
                        $fo_body .= "<tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>TA / Allowance</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: + ₹" . number_format($p['ta_amount'], 2) . "</td></tr>";
                    }
                    if ($deductions > 0) {
                        $fo_body .= "<tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Total Deductions</td><td style='padding: 10px; border: 1px solid #e0e0e0; color: #d32f2f;'>: - ₹" . number_format($deductions, 2) . "</td></tr>";
                    }

                    $fo_body .= "
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #f3f9fe; font-weight: bold;'>Net Transferred</td><td style='padding: 10px; border: 1px solid #e0e0e0; font-weight: bold;'>: ₹$total_fmt</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Transaction Type</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: NEFT / Digital</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Beneficiary A/c No</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: " . ($u['account_number'] ?: 'N/A') . "</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Beneficiary Bank Name</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: " . strtoupper($u['bank_name'] ?: 'N/A') . "</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Transferred On</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: $paid_date</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>UTR NO.</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: $utr</td></tr>
                            <tr><td style='padding: 10px; border: 1px solid #e0e0e0; background-color: #fcfcfc;'>Internal Remarks</td><td style='padding: 10px; border: 1px solid #e0e0e0;'>: " . ($p['payment_notes'] ?: 'Success') . "</td></tr>
                        </table>
                        
                        <hr style='border: 0; border-top: 1px solid #ccc; margin: 25px 0;'>
                        
                        <p style='font-size: 0.95rem; color: #555;'>
                            If you do not receive payment within 48 hours of this email, kindly send an email to 
                            <a href='mailto:$support_email' style='color: #0d47a1;'>$support_email</a> or call office.
                        </p>
                    </div>";
                    
                    queue_email($pdo, $u['email'], $fo_subject, $fo_body, $_SESSION['user_id']);
                }
                
                if (!empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                    send_whatsapp_notification($u['phone'], "Fee Transfer Alert: $claim_no\nAmount: ₹".number_format($total, 2)."\nUTR: $utr\nDetails sent to email.");
                }
            }
        }
        
        header('Location: projects.php?success=payment_released');
        exit;
    } catch (Exception $e) { $error_message = $e->getMessage(); }
}

// Handle New Claim
if (isset($_POST['create_project'])) {
    $errors = [];
    
    // Required fields check
    $req_fields = [
        'title'              => 'Patient Name',
        'claim_number'       => 'AFICS ID',
        'manual_claim_number'=> 'Claim Number',
        'client_id'          => 'Client',
        'scope'              => 'Part/Scope',
        'tat_deadline'       => 'TAT Deadline'
    ];
    $errors = validate_required($req_fields, $_POST);

    // Sanitize and validate inputs
    $title = sanitize_input($_POST['title']); 
    $claim_number = sanitize_input($_POST['claim_number']); // AFICS ID (auto-generated)
    $manual_claim_number = sanitize_input($_POST['manual_claim_number'] ?? ''); // manually entered claim number
    $client_id = intval($_POST['client_id']);
    $assigned_to = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
    $team_manager_id = !empty($_POST['team_manager_id']) ? intval($_POST['team_manager_id']) : null;
    $manager_id = !empty($_POST['manager_id']) ? intval($_POST['manager_id']) : null;
    $pt_fo_id = !empty($_POST['pt_fo_id']) ? intval($_POST['pt_fo_id']) : null;
    $hp_fo_id = !empty($_POST['hp_fo_id']) ? intval($_POST['hp_fo_id']) : null;
    $other_fo_id = !empty($_POST['other_fo_id']) ? intval($_POST['other_fo_id']) : null;
    $assigned_doctor_id_create = !empty($_POST['assigned_doctor_id']) ? intval($_POST['assigned_doctor_id']) : null;
    $tat_deadline = $_POST['tat_deadline'];
    $description = sanitize_input($_POST['description'] ?? '');
    $scope = sanitize_input($_POST['scope']);
    $hospital_name = sanitize_input($_POST['hospital_name'] ?? '');
    $hospital_address = sanitize_input($_POST['hospital_address'] ?? '');
    $doa = !empty($_POST['doa']) ? $_POST['doa'] : null;
    $dod = !empty($_POST['dod']) ? $_POST['dod'] : null;
    $uhid = sanitize_input($_POST['uhid'] ?? '');
    $diagnosis = sanitize_input($_POST['diagnosis'] ?? '');
    $price_hospital = floatval($_POST['price_hospital'] ?? 0);
    $price_patient = floatval($_POST['price_patient'] ?? 0);
    $price_other = floatval($_POST['price_other'] ?? 0);
    $case_points = floatval($_POST['case_points'] ?? 1.0);
    $tm_points = floatval($_POST['tm_points'] ?? 0);
    $dr_points = floatval($_POST['dr_points'] ?? 0);
    $mngr_points = floatval($_POST['mngr_points'] ?? 0);
    $pt_fo_points = floatval($_POST['pt_fo_points'] ?? 0);
    $hp_fo_points = floatval($_POST['hp_fo_points'] ?? 0);
    $other_fo_points = floatval($_POST['other_fo_points'] ?? 0);
    $claim_type = sanitize_input($_POST['claim_type'] ?? 'REIMBURSEMENT');
    $main_complaints = sanitize_input($_POST['main_complaints'] ?? '');

    if (empty($errors)) {

    try {
        $stmt = $pdo->prepare("INSERT INTO projects (title, claim_number, manual_claim_number, client_id, assigned_to, team_manager_id, manager_id, pt_fo_id, hp_fo_id, other_fo_id, assigned_doctor_id, tat_deadline, description, main_complaints, scope, claim_type, hospital_name, hospital_address, doa, dod, uhid, diagnosis, price_hospital, price_patient, price_other, case_points, tm_points, dr_points, mngr_points, pt_fo_points, hp_fo_points, other_fo_points, status, mrd_status, allocation_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', CURDATE())");
        $stmt->execute([$title, $claim_number, $manual_claim_number, $client_id, $assigned_to, $team_manager_id, $manager_id, $pt_fo_id, $hp_fo_id, $other_fo_id, $assigned_doctor_id_create, $tat_deadline, $description, $main_complaints, $scope, $claim_type, $hospital_name, $hospital_address, $doa, $dod, $uhid, $diagnosis, $price_hospital, $price_patient, $price_other, $case_points, $tm_points, $dr_points, $mngr_points, $pt_fo_points, $hp_fo_points, $other_fo_points]);
        $new_project_id = $pdo->lastInsertId();

        // --- Handle File Uploads (Attachment Option) ---
        if (!empty($_FILES['claim_documents']['name'][0])) {
            $uploaded_files = $_FILES['claim_documents'];
            $upload_dir = '../uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($uploaded_files['name'] as $i => $name) {
                if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    
                    if (in_array($ext, $allowed)) {
                        $new_name = time() . '_' . rand(100, 999) . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $name);
                        $target = $upload_dir . $new_name;
                        
                        if (move_uploaded_file($uploaded_files['tmp_name'][$i], $target)) {
                            $doc_stmt = $pdo->prepare("INSERT INTO project_documents (project_id, uploaded_by, file_name, file_path, category, document_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                            $doc_stmt->execute([$new_project_id, $_SESSION['user_id'], $name, 'uploads/documents/' . $new_name, 'Allocation', 'Claim Documents / Paper']);
                        }
                    }
                }
            }
        }

        // --- Notification Logic ---
        try {
            set_time_limit(300); // Give it 5 minutes for all emails
            require_once '../includes/functions.php'; 
            
            $all_recipients = [];
            
            // Reconnect helper
            $reconnect = function() use ($pdo) {
                try { @$pdo->query("SELECT 1"); } catch (Exception $e) { require '../config/db.php'; }
            };
            
            $reconnect();
            
            // Helper to add unique recipients
            $add_to_list = function($uid) use (&$all_recipients, $pdo, $reconnect) {
                if (empty($uid)) return;
                $reconnect();
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

            // Reconnect if connection lost
            try { $pdo->query("SELECT 1"); } catch (PDOException $e) { require '../config/db.php'; }

            // Fetch Names for Body
            $pt_fo_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($pt_fo_id ?: 0))->fetchColumn() ?: 'N/A';
            $hp_fo_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($hp_fo_id ?: 0))->fetchColumn() ?: 'N/A';
            $manager_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($manager_id ?: 0))->fetchColumn() ?: 'N/A';
            
            $subject = "New Claim Assigned in DOCUMANTRAA - Claim #: " . ($manual_claim_number ?: $claim_number);

            foreach ($all_recipients as $u) {
                // Email Body
                $body = "Dear {$u['full_name']},\n\n";
                $body .= "Greetings from AFICS Investigation Agency!\n\n";
                $body .= "A new claim has been assigned to you in DOCUMANTRAA. Please find the details below:\n\n";
                $body .= "Claim Number: " . ($manual_claim_number ?: 'N/A') . "\n\n";
                $body .= "Patient Name: $title\n\n";
                $body .= "Phone Number: " . ($_POST['patient_phone'] ?? 'N/A') . "\n\n";
                $body .= "Hospital Name: " . ($hospital_name ?: 'N/A') . "\n\n";
                $body .= "Hospital City: " . ($hospital_address ?: 'N/A') . "\n\n";
                $body .= "PT Fo Name : $pt_fo_name\n\n";
                $body .= "HP Fo Name : $hp_fo_name\n\n";
                $body .= "Manager: $manager_name\n\n";
                $body .= "Trigger: " . ($diagnosis ?: 'Standard') . "\n\n";
                $body .= "Category: " . str_ireplace(' Part', '', $scope) . "\n\n";
                $body .= "Kindly review the case and proceed with the necessary action at the earliest.\n\n";
                $body .= "Wishing you all the best.\n\n";
                $body .= "Regards,\nAFICS HO Team";

                if(function_exists('queue_email')) {
                    // Always send allocation emails immediately to staff
                    queue_email($pdo, $u['email'], $subject, $body, $_SESSION['user_id'], [], true);
                }

                $is_primary_for_wa = ($u['id'] == $assigned_to || $u['id'] == $pt_fo_id);
                if ($is_primary_for_wa && !empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                    $wa_msg = "New Claim Assigned!\nClaim: $claim_number\nPatient: $title\nHospital: " . ($hospital_name ?? 'N/A') . "\nCheck portal for details.";
                    send_whatsapp_notification($u['phone'], $wa_msg);
                }
            }
            
            // Notify Admin of the new allocation (Background Queue)
            if (function_exists('queue_email')) {
                $settings = get_settings($pdo);
                $admin_email = 'allocation@documantraa.in'; // Routing allocation alerts to specific department
                $admin_subject = "Case Allocation Alert: $claim_number";
                $admin_body = "The case # $claim_number has been successfully created and allocated to assigned staff.\n\nRegards,\nDocumantraa CMS";
                queue_email($pdo, $admin_email, $admin_subject, $admin_body, $_SESSION['user_id'] ?? null, [], false);
            }

            // Notify Doctor if assigned at creation time
            if (!empty($assigned_doctor_id_create)) {
                $reconnect();
                $doctor = $pdo->query("SELECT full_name, email, phone FROM users WHERE id = $assigned_doctor_id_create")->fetch();
                if ($doctor) {
                    $subject_doc = "New Case Assigned for Medical Review: " . ($manual_claim_number ?: $claim_number);
                    $body_doc = "Dear {$doctor['full_name']},\n\n";
                    $body_doc .= "Greetings from AFICS Investigation Agency!\n\n";
                    $body_doc .= "You have been assigned as the Concerned Doctor for a new case in DOCUMANTRAA. Please find the details below:\n\n";
                    $body_doc .= "Claim Number: " . ($manual_claim_number ?: 'N/A') . "\n\n";
                    $body_doc .= "Patient Name: $title\n\n";
                    $body_doc .= "Hospital Name: " . ($hospital_name ?: 'N/A') . "\n\n";
                    $body_doc .= "Kindly review the case and submit your medical opinion reports through the portal.\n\n";
                    $body_doc .= "Wishing you all the best.\n\n";
                    $body_doc .= "Regards,\nAFICS HO Team";
                    if(function_exists('queue_email')) queue_email($pdo, $doctor['email'], $subject_doc, $body_doc, $_SESSION['user_id']);
                }
            }
        } catch (Exception $e) {
            // Silently log notification errors to prevent breaking the flow
            error_log("Notification Error: " . $e->getMessage());
        }
        // --- End Notification Logic ---
        
        header('Location: projects.php?success=created');
        exit;
    } catch (PDOException $e) {
        $error_message = "Database Error: " . $e->getMessage();
    }
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
        
        // Fetch current project data to use as fallback for blank fields
        $stmt_old = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt_old->execute([$pid]);
        $old = $stmt_old->fetch();
        if (!$old) throw new Exception("Project not found.");

        $title               = !empty($_POST['title']) ? sanitize_input($_POST['title']) : $old['title'];
        $claim_number        = !empty($_POST['claim_number']) ? sanitize_input($_POST['claim_number']) : $old['claim_number'];
        $manual_claim_number = !empty($_POST['manual_claim_number']) ? sanitize_input($_POST['manual_claim_number']) : $old['manual_claim_number'];
        $scope               = !empty($_POST['scope']) ? sanitize_input($_POST['scope']) : $old['scope'];
        $claim_type          = !empty($_POST['claim_type']) ? sanitize_input($_POST['claim_type']) : $old['claim_type'];
        
        $assigned_to         = isset($_POST['assigned_to']) && $_POST['assigned_to'] !== '' ? intval($_POST['assigned_to']) : $old['assigned_to'];
        $team_manager_id     = isset($_POST['team_manager_id']) && $_POST['team_manager_id'] !== '' ? intval($_POST['team_manager_id']) : $old['team_manager_id'];
        $manager_id          = isset($_POST['manager_id']) && $_POST['manager_id'] !== '' ? intval($_POST['manager_id']) : $old['manager_id'];
        $pt_fo_id            = isset($_POST['pt_fo_id']) && $_POST['pt_fo_id'] !== '' ? intval($_POST['pt_fo_id']) : $old['pt_fo_id'];
        $hp_fo_id            = isset($_POST['hp_fo_id']) && $_POST['hp_fo_id'] !== '' ? intval($_POST['hp_fo_id']) : $old['hp_fo_id'];
        $other_fo_id         = isset($_POST['other_fo_id']) && $_POST['other_fo_id'] !== '' ? intval($_POST['other_fo_id']) : $old['other_fo_id'];
        $assigned_doctor_id  = isset($_POST['assigned_doctor_id']) && $_POST['assigned_doctor_id'] !== '' ? intval($_POST['assigned_doctor_id']) : $old['assigned_doctor_id'];

        $tat_deadline        = !empty($_POST['tat_deadline']) ? $_POST['tat_deadline'] : $old['tat_deadline'];
        $description         = isset($_POST['description']) && $_POST['description'] !== '' ? sanitize_input($_POST['description']) : $old['description'];
        $main_complaints     = isset($_POST['main_complaints']) && $_POST['main_complaints'] !== '' ? sanitize_input($_POST['main_complaints']) : $old['main_complaints'];
        $hospital_name       = isset($_POST['hospital_name']) && $_POST['hospital_name'] !== '' ? sanitize_input($_POST['hospital_name']) : $old['hospital_name'];
        $hospital_address    = isset($_POST['hospital_address']) && $_POST['hospital_address'] !== '' ? sanitize_input($_POST['hospital_address']) : $old['hospital_address'];
        $closure_conclusion  = isset($_POST['closure_conclusion']) && $_POST['closure_conclusion'] !== '' ? sanitize_input($_POST['closure_conclusion']) : $old['closure_conclusion'];

        $case_points         = isset($_POST['case_points']) && $_POST['case_points'] !== '' ? floatval($_POST['case_points']) : $old['case_points'];
        $tm_points           = isset($_POST['tm_points']) && $_POST['tm_points'] !== '' ? floatval($_POST['tm_points']) : $old['tm_points'];
        $dr_points           = isset($_POST['dr_points']) && $_POST['dr_points'] !== '' ? floatval($_POST['dr_points']) : $old['dr_points'];
        $mngr_points         = isset($_POST['mngr_points']) && $_POST['mngr_points'] !== '' ? floatval($_POST['mngr_points']) : $old['mngr_points'];
        $pt_fo_points        = isset($_POST['pt_fo_points']) && $_POST['pt_fo_points'] !== '' ? floatval($_POST['pt_fo_points']) : $old['pt_fo_points'];
        $hp_fo_points        = isset($_POST['hp_fo_points']) && $_POST['hp_fo_points'] !== '' ? floatval($_POST['hp_fo_points']) : $old['hp_fo_points'];
        $other_fo_points     = isset($_POST['other_fo_points']) && $_POST['other_fo_points'] !== '' ? floatval($_POST['other_fo_points']) : $old['other_fo_points'];

        $price_hospital      = isset($_POST['price_hospital']) && $_POST['price_hospital'] !== '' ? floatval($_POST['price_hospital']) : $old['price_hospital'];
        $price_patient       = isset($_POST['price_patient']) && $_POST['price_patient'] !== '' ? floatval($_POST['price_patient']) : $old['price_patient'];
        $price_other         = isset($_POST['price_other']) && $_POST['price_other'] !== '' ? floatval($_POST['price_other']) : $old['price_other'];

        $sql = "UPDATE projects SET title=?, claim_number=?, manual_claim_number=?, scope=?, claim_type=?, assigned_to=?, team_manager_id=?, manager_id=?, pt_fo_id=?, hp_fo_id=?, other_fo_id=?, tat_deadline=?, description=?, main_complaints=?, hospital_name=?, hospital_address=?, case_points=?, tm_points=?, dr_points=?, mngr_points=?, pt_fo_points=?, hp_fo_points=?, other_fo_points=?, assigned_doctor_id=?, closure_conclusion=?, price_hospital=?, price_patient=?, price_other=? WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$title, $claim_number, $manual_claim_number, $scope, $claim_type, $assigned_to, $team_manager_id, $manager_id, $pt_fo_id, $hp_fo_id, $other_fo_id, $tat_deadline, $description, $main_complaints, $hospital_name, $hospital_address, $case_points, $tm_points, $dr_points, $mngr_points, $pt_fo_points, $hp_fo_points, $other_fo_points, $assigned_doctor_id, $closure_conclusion, $price_hospital, $price_patient, $price_other, $pid]);
        
        // --- Handle Additional File Uploads (Attachment Option) ---
        if (!empty($_FILES['claim_documents']['name'][0])) {
            $uploaded_files = $_FILES['claim_documents'];
            $upload_dir = '../uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

            foreach ($uploaded_files['name'] as $i => $name) {
                if ($uploaded_files['error'][$i] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    
                    if (in_array($ext, $allowed)) {
                        $new_name = time() . '_' . rand(100, 999) . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", $name);
                        $target = $upload_dir . $new_name;
                        
                        if (move_uploaded_file($uploaded_files['tmp_name'][$i], $target)) {
                            $doc_stmt = $pdo->prepare("INSERT INTO project_documents (project_id, uploaded_by, file_name, file_path, category, document_type, uploaded_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                            $doc_stmt->execute([$pid, $_SESSION['user_id'], $name, 'uploads/documents/' . $new_name, 'Allocation', 'Added Claim Paper']);
                        }
                    }
                }
            }
        }

        // --- Notification Logic (Update/Reassignment) ---
        try {
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
            $add_to_list($assigned_doctor_id);

            $subject = "Update/Reassignment in DOCUMANTRAA - Claim #: " . ($manual_claim_number ?: $claim_number);

            // Reconnect if connection lost
            try { $pdo->query("SELECT 1"); } catch (PDOException $e) { require '../config/db.php'; }

            // Fetch Names for Body
            $pt_fo_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($pt_fo_id ?: 0))->fetchColumn() ?: 'N/A';
            $hp_fo_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($hp_fo_id ?: 0))->fetchColumn() ?: 'N/A';
            $manager_name = $pdo->query("SELECT full_name FROM users WHERE id = " . ($manager_id ?: 0))->fetchColumn() ?: 'N/A';

            foreach ($all_recipients as $u) {
                $body = "Dear {$u['full_name']},\n\n";
                $body .= "Greetings from AFICS Investigation Agency!\n\n";
                $body .= "A claim you are assigned to has been updated/reassigned in DOCUMANTRAA. Please find the details below:\n\n";
                $body .= "Claim Number: " . ($manual_claim_number ?: 'N/A') . "\n\n";
                $body .= "Patient Name: $title\n\n";
                $body .= "Phone Number: " . ($_POST['patient_phone'] ?? 'N/A') . "\n\n";
                $body .= "Hospital Name: " . ($hospital_name ?: 'N/A') . "\n\n";
                $body .= "Hospital City: " . ($hospital_address ?: 'N/A') . "\n\n";
                $body .= "PT Fo Name : $pt_fo_name\n\n";
                $body .= "HP Fo Name : $hp_fo_name\n\n";
                $body .= "Manager: $manager_name\n\n";
                $body .= "Trigger: " . ($diagnosis ?: 'Standard') . "\n\n";
                $body .= "Category: " . str_ireplace(' Part', '', $scope) . "\n\n";
                $body .= "Kindly review the case and proceed with the necessary action at the earliest.\n\n";
                $body .= "Wishing you all the best.\n\n";
                $body .= "Regards,\nAFICS HO Team";

                if(function_exists('queue_email')) queue_email($pdo, $u['email'], $subject, $body, $_SESSION['user_id']);
                if (!empty($u['phone']) && function_exists('send_whatsapp_notification')) {
                    send_whatsapp_notification($u['phone'], "Claim $claim_number Updated!\nPatient: $title\nHospital: " . ($hospital_name ?? 'N/A') . "\nPlease check portal.");
                }
            }
            
            // Notify Admin of the update
            if (function_exists('queue_email')) {
                $settings = get_settings($pdo);
                $admin_email = $settings['contact_email'] ?? 'support@documantraa.in';
                $admin_subject = "Case Update Alert: $claim_number";
                $admin_body = "The case # $claim_number has been updated/reassigned on the portal.\n\nRegards,\nDocumantraa CMS";
                queue_email($pdo, $admin_email, $admin_subject, $admin_body, $_SESSION['user_id'] ?? null);
            }
        } catch (Exception $e) {
            error_log("Update Notification Error: " . $e->getMessage());
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
    $search_sql = " AND (
        p.title LIKE $term
        OR p.claim_number LIKE $term
        OR p.manual_claim_number LIKE $term
        OR p.description LIKE $term
        OR c.company_name LIKE $term
        OR u.full_name LIKE $term
    )";
}

// Fetch Data
$where = "";
$is_full_access = in_array($_SESSION['role'], ['admin', 'super_admin', 'hr', 'hr_manager', 'hod']);
$is_ho_staff = in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'team_manager', 'fo_manager', 'hod']);
$is_reports_access = in_array($_SESSION['role'], ['admin', 'super_admin', 'hr', 'hr_manager', 'hod']);
$is_doctor = in_array($_SESSION['role'], ['doctor', 'incharge']);

if (!$is_full_access) {
    // Restricted access: only see cases where user is assigned in ANY role
    $uid = $_SESSION['user_id'];
    $where .= " AND (p.assigned_to = $uid OR p.pt_fo_id = $uid OR p.hp_fo_id = $uid OR p.other_fo_id = $uid OR p.team_manager_id = $uid OR p.assigned_doctor_id = $uid OR p.manager_id = $uid)";
    
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
$payment_ready_projects = [];
$schema_warning = false;

// Re-establish DB connection if it timed out during long email process
try {
    $pdo->query("SELECT 1");
} catch (PDOException $e) {
    // If "gone away" or connection lost, re-include db config to get fresh $pdo
    require '../config/db.php';
}

try {
    $all_stmt = $pdo->query("SELECT p.*, c.company_name, 
            u.full_name as officer_name, 
            tm.full_name as tm_name, 
            mngr.full_name as mngr_name, 
            ptfo.full_name as pt_fo_name, 
            hpfo.full_name as hp_fo_name, 
            otherfo.full_name as other_fo_name,
            (SELECT COUNT(*) FROM project_documents WHERE project_id = p.id) as doc_count
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
    elseif ($p['status'] == 'Completed') {
        $completed_projects[] = $p;
        if ($p['payment_status'] == 'Unpaid') $payment_ready_projects[] = $p;
    }
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
            <?= render_form_errors($errors ?? []) ?>
            <?php if ($error_message): ?>
            <div class="alert alert-danger mb-4"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <!-- Search & Filter -->
            <div class="app-card mb-4">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="text" name="search" class="input-v2" style="max-width: 300px;" placeholder="Search by claim no / AFICS ID / patient..." value="<?= htmlspecialchars($search) ?>">
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
                <?php if($is_ho_staff): ?>
                <li class="nav-item">
                    <button class="nav-link text-success fw-bold" id="payment-tab" data-bs-toggle="tab" data-bs-target="#payment-ready" type="button"><i class="bi bi-cash-stack me-1"></i> Payment Desk (<?= count($payment_ready_projects) ?>)</button>
                </li>
                <?php endif; ?>
                <?php if($is_reports_access): ?>
                <li class="nav-item">
                    <button class="nav-link text-primary fw-bold" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-section" type="button"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Reports & Exports</button>
                </li>
                <?php endif; ?>
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
                            <?= $is_ho_staff ? 'FO Bucket' : 'My Active Claims' ?> (<?= $is_ho_staff ? count($progress_projects) : (count($pending_projects) + count($progress_projects)) ?>)
                        </div>
                        <?php if($is_ho_staff || $is_doctor): ?>
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
                        <h6 class="fw-bold mb-3 text-muted px-2 small text-uppercase" style="letter-spacing: 1px;"><?= $is_ho_staff ? 'FO Bucket (In-Progress)' : 'My Active Claims' ?></h6>
                        <div class="row g-4">
                            <?php if(!$is_ho_staff): ?>
                            <?php foreach ($pending_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                            <?php endif; ?>
                            <?php foreach ($progress_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                            <?php if($is_ho_staff && empty($progress_projects)) echo '<div class="col-12 text-center text-muted p-3 small">No claims in FO Bucket.</div>'; ?>
                            <?php if(!$is_ho_staff && empty($pending_projects) && empty($progress_projects)) echo '<div class="col-12 text-center text-muted p-3 small">No active claims found.</div>'; ?>
                        </div>
                    </div>

                    <?php if($is_ho_staff || $is_doctor): ?>
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

                <!-- Payment Ready Tab -->
                <?php if($is_ho_staff): ?>
                <div class="tab-pane fade" id="payment-ready">
                    <div class="row g-4">
                        <div class="col-12"><div class="alert alert-success border-0 small"><i class="bi bi-info-circle me-1"></i> Cases listed here are marked as <b>Completed</b> but are awaiting fee release.</div></div>
                        <?php foreach ($payment_ready_projects as $project) { include 'includes/project_card_v2_inline.php'; } ?>
                        <?php if(empty($payment_ready_projects)) echo '<div class="col-12 text-center text-muted p-5">No cases awaiting payment.</div>'; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($is_reports_access): ?>
                <!-- Reports & Exports Tab -->
                <div class="tab-pane fade" id="reports-section">
                    <div class="row g-4">
                        <div class="col-md-6 col-lg-4">
                            <div class="app-card border-top border-primary" style="border-width: 4px !important;">
                                <h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill me-2 text-primary"></i> FO Pending Lists</h6>
                                <p class="small text-muted mb-4">Download a monthly Excel report of all projects assigned to a specific Field Officer.</p>
                                <form action="exports.php" method="GET">
                                    <input type="hidden" name="type" value="fo">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Select Field Officer</label>
                                        <select name="id" class="form-select input-v2" required>
                                            <?php foreach($officers as $o): ?>
                                            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold text-muted mb-1">Select Month</label>
                                        <input type="month" name="month" class="form-control input-v2" value="<?= date('Y-m') ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 shadow-sm">
                                        <i class="bi bi-download me-2"></i> Download FO Excel
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="app-card border-top border-success" style="border-width: 4px !important;">
                                <h6 class="fw-bold mb-3"><i class="bi bi-building me-2 text-success"></i> Client Monthly Data</h6>
                                <p class="small text-muted mb-4">Generate comprehensive monthly claim reports for individual Insurance Companies or TPA clients.</p>
                                <form action="exports.php" method="GET">
                                    <input type="hidden" name="type" value="client">
                                    <div class="mb-3">
                                        <label class="small fw-bold text-muted mb-1">Select Client</label>
                                        <select name="id" class="form-select input-v2" required>
                                            <?php foreach($clients as $c): ?>
                                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-4">
                                        <label class="small fw-bold text-muted mb-1">Select Month</label>
                                        <input type="month" name="month" class="form-control input-v2" value="<?= date('Y-m') ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-success text-white w-100 py-3 fw-bold rounded-3 shadow-sm" style="background:#2e7d32 !important; border:none;">
                                        <i class="bi bi-file-earmark-spreadsheet me-2"></i> Download Client Excel
                                    </button>
                                </form>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-4">
                            <div class="app-card border-top border-danger" style="border-width: 4px !important;">
                                <h6 class="fw-bold mb-3"><i class="bi bi-lightning-charge-fill me-2 text-danger"></i> Automation Tools</h6>
                                <p class="small text-muted mb-4">Trigger system-wide automation tasks manually. These normally run on a schedule.</p>
                                <div class="d-grid gap-3">
                                    <button type="button" onclick="triggerDailyAlert()" class="btn btn-white-v2 border w-100 py-3 text-start">
                                        <i class="bi bi-envelope-at me-2 text-danger"></i> Send Daily Pending List to FOs
                                    </button>
                                    <a href="notification_logs.php" class="btn btn-white-v2 border w-100 py-3 text-start">
                                        <i class="bi bi-bug me-2 text-primary"></i> View Email Delivery Logs (Debug)
                                    </a>
                                    <div id="alert-status" class="alert alert-info py-2 small d-none" style="font-size: 0.75rem;"></div>
                                </div>
                                <script>
                                function triggerDailyAlert() {
                                    const btn = event.currentTarget;
                                    const status = document.getElementById('alert-status');
                                    btn.disabled = true;
                                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Sending...';
                                    
                                    fetch('daily_pending_alert.php')
                                        .then(res => res.text())
                                        .then(data => {
                                            status.classList.remove('d-none');
                                            status.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Notification job completed successfully.';
                                            btn.innerHTML = '<i class="bi bi-envelope-at me-2 text-danger"></i> Send Daily Pending List to FOs';
                                            btn.disabled = false;
                                        });
                                }
                                </script>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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

            // Auto-activate tab from hash
            window.addEventListener('load', function() {
                if(window.location.hash === '#payment-ready') {
                    const tabEl = document.querySelector('#payment-tab');
                    if(tabEl) bootstrap.Tab.getOrCreateInstance(tabEl).show();
                }
            });
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
                    <?= render_form_errors($errors ?? []) ?>
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="create_project" value="1">
                        <div class="row g-4">
                            <!-- Section: Primary Information -->
                            <div class="col-12 border-bottom pb-2 mb-2">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Patient & Case Identity</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Reference ID <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" name="claim_number" id="auto_claim_number" class="input-v2 form-control" placeholder="Generating..." pattern="[A-Z0-9-]+" title="Please enter an alphanumeric Reference ID" required>
                                    <button class="btn btn-outline-secondary" type="button" onclick="generateClaimNumber()"><i class="bi bi-arrow-clockwise"></i></button>
                                </div>
                                <div class="invalid-feedback">Valid alphanumeric Reference ID required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Insurer Claim Number <span class="text-danger">*</span></label>
                                <input type="text" name="manual_claim_number" id="manual_claim_number" class="input-v2" placeholder="Enter insurer claim number" required>
                                <div class="invalid-feedback">Claim Number is required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Patient Name <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="input-v2" placeholder="Full legal name" pattern="[A-Za-z\s.]{2,}" title="Name should only contain letters" required>
                                <div class="invalid-feedback">Please enter a valid name (Letters only).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Patient Phone</label>
                                <input type="text" name="patient_phone" class="input-v2" placeholder="10 Digit Number" pattern="[0-9]{10}" title="Must be exactly 10 digits" maxlength="10">
                                <div class="invalid-feedback">Please enter a valid 10-digit number.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Scope of Work</label>
                                <select name="scope" class="input-v2 form-select" required>
                                    <option value="Full Investigation">Full Investigation</option>
                                    <option value="Hospital Part">Hospital Part</option>
                                    <option value="Patient Part">Patient Part</option>
                                    <option value="Low Cost">Low Cost</option>
                                    <option value="Other Part">Other Part</option>
                                </select>
                            </div>

                            <!-- Section: Timeline & Client -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Timeline & Client Assignment</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">TAT Deadline <span class="text-danger">*</span></label>
                                <input type="date" name="tat_deadline" class="input-v2" min="<?= date('Y-m-d') ?>" required>
                                <div class="invalid-feedback">Please set a TAT deadline (Today or Future).</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Client / Insurance Agency</label>
                                <select name="client_id" class="input-v2 form-select" required>
                                    <option value="">Select Client...</option>
                                    <?php foreach($clients as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a client.</div>
                            </div>

                            <!-- Section: Hospital Details -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Hospital Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Name</label>
                                <input type="text" name="hospital_name" class="input-v2" placeholder="Enter hospital name">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital Address / City</label>
                                <input type="text" name="hospital_address" class="input-v2" placeholder="Location">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">DOA (Adm.)</label>
                                <input type="date" name="doa" class="input-v2">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">DOD (Disch.)</label>
                                <input type="date" name="dod" class="input-v2">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">UHID / IP No.</label>
                                <input type="text" name="uhid" class="input-v2" placeholder="Hospital ID">
                            </div>

                            <!-- Section: Medical & Financial -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Clinical & Estimates</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Claim Category</label>
                                <div class="input-group">
                                    <select name="claim_type" id="add_claim_type_select" class="input-v2 form-select" style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                        <option value="REIMBURSEMENT">REIMBURSEMENT</option>
                                        <option value="CASHLESS">CASHLESS</option>
                                        <option value="LOW COST">LOW COST</option>
                                        <option value="PA DISABILITY">PA DISABILITY</option>
                                        <option value="PA DEATH">PA DEATH</option>
                                        <option value="CRITICAL ILLNESS">CRITICAL ILLNESS</option>
                                        <option value="TRAVEL CLAIM">TRAVEL CLAIM</option>
                                        <option value="SPOT VERIFICATION">SPOT VERIFICATION</option>
                                        <option value="OTHER">OTHERS (+)</option>
                                    </select>
                                    <input type="text" id="add_claim_type_other" class="form-control input-v2 d-none" placeholder="Enter Type..." style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Case Trigger / Diagnosis</label>
                                <input type="text" name="diagnosis" class="input-v2" placeholder="e.g. Fever, Trauma, etc.">
                            </div>
                            <div class="col-md-12">
                                <label class="stat-label mb-1">Main Complaints on Admission</label>
                                <textarea name="main_complaints" class="input-v2" rows="2" placeholder="Primary medical complaints..."></textarea>
                            </div>
                             <div class="col-md-3">
                                <label class="stat-label mb-1">Hosp. Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_hospital" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Pt. Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_patient" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Other Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_other" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Main FO Points</label>
                                <input type="number" step="0.1" name="case_points" class="input-v2" value="1.0">
                            </div>

                            <!-- Points Allocation Section -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Performance Points Allocation</h6>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">TM Points</label>
                                <input type="number" step="0.1" name="tm_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Doctor Points</label>
                                <input type="number" step="0.1" name="dr_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Manager Points</label>
                                <input type="number" step="0.1" name="mngr_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">PT Part Points</label>
                                <input type="number" step="0.1" name="pt_fo_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">HP Part Points</label>
                                <input type="number" step="0.1" name="hp_fo_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Other Part Points</label>
                                <input type="number" step="0.1" name="other_fo_points" class="input-v2" value="0.0">
                            </div>

                            <!-- Section: Staff Assignment -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Resource Allocation</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1"><i class="bi bi-person-badge me-1 text-danger"></i>Concerned Doctor</label>
                                <select name="assigned_doctor_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name'] . ' - ' . ($d['employee_id'] ?: ('ID ' . $d['id']))) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                <label class="stat-label mb-1">Admin / Manager</label>
                                <select name="manager_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($managers as $m): ?>
                                    <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['full_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Main FO</label>
                                <select name="assigned_to" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital part FO</label>
                                <select name="hp_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Patient Part FO</label>
                                <select name="pt_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="stat-label mb-1"><i class="bi bi-paperclip me-1"></i> Claim Documents / Attachments</label>
                                <input type="file" name="claim_documents[]" multiple class="input-v2" accept="image/*,application/pdf,.doc,.docx">
                                <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">PDF or Images (Allocation papers, triggers, etc.)</small>
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Administrative Notes / Description</label>
                                <textarea name="description" class="input-v2" rows="2" placeholder="Any specific instructions..."></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top">
                            <button type="submit" id="createClaimBtn" class="btn-v2 btn-primary-v2 w-100 py-3 shadow-sm">
                                <span id="createClaimBtnText"><i class="bi bi-plus-circle me-1"></i> Finalize &amp; Create Claim</span>
                                <span id="createClaimBtnLoading" style="display:none;"><span class="spinner-border spinner-border-sm me-2" role="status"></span> Processing... Please wait</span>
                            </button>
                        </div>
                    </form>
                    <script>
                        function generateClaimNumber() {
                            const date = new Date();
                            const dateStr = date.getFullYear().toString().substr(-2) + 
                                          (date.getMonth() + 1).toString().padStart(2, '0') + 
                                          date.getDate().toString().padStart(2, '0');
                            const rand = Math.floor(1000 + Math.random() * 9000);
                            const claimNo = 'DOC' + dateStr + rand;
                            document.getElementById('auto_claim_number').value = claimNo;
                        }

                        // Generate when modal opens
                        const addModal = document.getElementById('addProjectModal');
                        if(addModal) {
                            addModal.addEventListener('show.bs.modal', function() {
                                if(!document.getElementById('auto_claim_number').value) {
                                    generateClaimNumber();
                                }
                            });
                        }

                        document.getElementById('add_claim_type_select').addEventListener('change', function() {
                            const other = document.getElementById('add_claim_type_other');
                            if(this.value === 'OTHER') {
                                other.classList.remove('d-none');
                                other.setAttribute('name', 'claim_type');
                                this.removeAttribute('name');
                            } else {
                                other.classList.add('d-none');
                                other.removeAttribute('name');
                                this.setAttribute('name', 'claim_type');
                            }
                        });

                        // Loading state on form submit
                        document.getElementById('createClaimBtn').closest('form').addEventListener('submit', function() {
                            const btn = document.getElementById('createClaimBtn');
                            const btnText = document.getElementById('createClaimBtnText');
                            const btnLoading = document.getElementById('createClaimBtnLoading');
                            btn.disabled = true;
                            btnText.style.display = 'none';
                            btnLoading.style.display = 'inline-block';
                        });
                    </script>
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
                    <?= render_form_errors($errors ?? []) ?>
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="update_project_details" value="1">
                        <input type="hidden" name="project_id" id="edit_project_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Reference ID</label>
                                <input type="text" name="claim_number" id="edit_claim_number" class="input-v2" required>
                                <div class="invalid-feedback">Required.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Insurer Claim Number</label>
                                <input type="text" name="manual_claim_number" id="edit_manual_claim_number" class="input-v2" placeholder="Insurer claim number">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Patient Name</label>
                                <input type="text" name="title" id="edit_title" class="input-v2" required>
                                <div class="invalid-feedback">Required.</div>
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
                                <label class="stat-label mb-1">Claim Type</label>
                                <div class="input-group">
                                    <select name="claim_type" id="edit_claim_type" class="input-v2 form-select">
                                        <option value="REIMBURSEMENT">REIMBURSEMENT</option>
                                        <option value="CASHLESS">CASHLESS</option>
                                        <option value="LOW COST">LOW COST</option>
                                        <option value="PA DISABILITY">PA DISABILITY</option>
                                        <option value="PA DEATH">PA DEATH</option>
                                        <option value="CRITICAL ILLNESS">CRITICAL ILLNESS</option>
                                        <option value="TRAVEL CLAIM">TRAVEL CLAIM</option>
                                        <option value="SPOT VERIFICATION">SPOT VERIFICATION</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-primary" onclick="addOtherClaimType()"><i class="bi bi-plus-lg"></i></button>
                                </div>
                                <script>
                                    function addOtherClaimType() {
                                        const val = prompt("Enter Custom Claim Type:");
                                        if(val) {
                                            const sel = document.getElementById('edit_claim_type');
                                            const opt = document.createElement('option');
                                            opt.value = val;
                                            opt.text = val;
                                            opt.selected = true;
                                            sel.add(opt);
                                        }
                                    }
                                </script>
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
                                <label class="stat-label mb-1">Main FO</label>
                                <select name="assigned_to" id="edit_assigned_to" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Hospital part FO</label>
                                <select name="hp_fo_id" id="edit_hp_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">Patient Part FO</label>
                                <select name="pt_fo_id" id="edit_pt_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1">FO <small class="text-muted">(Other Part)</small></label>
                                <select name="other_fo_id" id="edit_other_fo_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($officers as $o): ?>
                                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['full_name'] . ' - ' . ($o['employee_id'] ?: ('ID ' . $o['id'])) . ' - ' . ($o['email'] ?: 'No Email')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Description / Internal Notes</label>
                                <textarea name="description" id="edit_description" class="input-v2" rows="2"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Main Complaints on Admission</label>
                                <textarea name="main_complaints" id="edit_main_complaints" class="input-v2" rows="2"></textarea>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Hosp. Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_hospital" id="edit_price_hospital" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Pt. Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_patient" id="edit_price_patient" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="stat-label mb-1">Other Price</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light pe-1 small text-muted">&#8377;</span>
                                    <input type="number" step="0.01" min="0" name="price_other" id="edit_price_other" class="form-control input-v2 border-start-0 ps-1" placeholder="0.00">
                                </div>
                            </div>
                             <div class="col-md-3">
                                <label class="stat-label mb-1">Main FO Points</label>
                                <input type="number" step="0.1" name="case_points" id="edit_case_points" class="input-v2" required>
                            </div>

                            <!-- Points Allocation Section -->
                            <div class="col-12 border-bottom pb-2 mb-2 mt-4">
                                <h6 class="text-primary fw-bold mb-0" style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em;">Performance Points Allocation</h6>
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">TM Points</label>
                                <input type="number" step="0.1" name="tm_points" id="edit_tm_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Doctor Points</label>
                                <input type="number" step="0.1" name="dr_points" id="edit_dr_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Manager Points</label>
                                <input type="number" step="0.1" name="mngr_points" id="edit_mngr_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">PT Part Points</label>
                                <input type="number" step="0.1" name="pt_fo_points" id="edit_pt_fo_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">HP Part Points</label>
                                <input type="number" step="0.1" name="hp_fo_points" id="edit_hp_fo_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-4">
                                <label class="stat-label mb-1">Other Part Points</label>
                                <input type="number" step="0.1" name="other_fo_points" id="edit_other_fo_points" class="input-v2" value="0.0">
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1 text-primary">Closure Conclusion</label>
                                <select name="closure_conclusion" id="edit_closure_conclusion" class="input-v2 form-select border-primary shadow-sm">
                                    <option value="">-- No Conclusion --</option>
                                    <option value="GENUINE">GENUINE</option>
                                    <option value="SUSPICIOUS">SUSPICIOUS</option>
                                    <option value="INADMISSIBLE">INADMISSIBLE</option>
                                    <option value="QUERY">QUERY</option>
                                </select>
                            </div>
                            <div class="col-md-12">
                                <label class="stat-label mb-1"><i class="bi bi-paperclip me-1"></i> Claim Documents / Attachments</label>
                                <input type="file" name="claim_documents[]" multiple class="input-v2" accept="image/*,application/pdf,.doc,.docx">
                                <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">PDF, Images, or Word docs. (Allocation papers, triggers, etc.)</small>
                            </div>
                            <div class="col-md-6">
                                <label class="stat-label mb-1"><i class="bi bi-person-badge me-1 text-danger"></i>Concerned Doctor</label>
                                <select name="assigned_doctor_id" id="edit_assigned_doctor_id" class="input-v2 form-select">
                                    <option value="">-- Unassigned --</option>
                                    <?php foreach($doctors as $d): ?>
                                    <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['full_name'] . ' - ' . ($d['employee_id'] ?: ('ID ' . $d['id']))) ?></option>
                                    <?php endforeach; ?>
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
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label class="stat-label mb-1">TA / Allowance (₹)</label>
                                <input type="number" step="0.01" name="ta_amount" id="pay_ta_amount" class="form-control input-v2" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1">TAT Deduction (₹)</label>
                                <input type="number" step="0.01" name="tat_deduction" id="pay_tat_deduction" class="form-control input-v2" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1">Fine Amount (₹)</label>
                                <input type="number" step="0.01" name="fine_amount" id="pay_fine_amount" class="form-control input-v2 border-danger" value="0.00">
                            </div>
                            <div class="col-6">
                                <label class="stat-label mb-1">Other Deduction (₹)</label>
                                <input type="number" step="0.01" name="other_deduction" id="pay_other_deduction" class="form-control input-v2" value="0.00">
                            </div>
                            <div class="col-12">
                                <label class="stat-label mb-1">Transaction UTR <span class="text-danger">*</span></label>
                                <input type="text" name="payment_utr" class="form-control input-v2 border-success" placeholder="Enter Transaction ID / UTR" required>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="stat-label mb-1">Internal Payment Notes</label>
                            <textarea name="payment_notes" class="form-control input-v2" rows="2" placeholder="Bank details, reasons for deductions..."></textarea>
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
            document.getElementById('edit_manual_claim_number').value = button.getAttribute('data-manual-claim') || '';
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
            document.getElementById('edit_tm_points').value = button.getAttribute('data-tm-points') || 0;
            document.getElementById('edit_dr_points').value = button.getAttribute('data-dr-points') || 0;
            document.getElementById('edit_mngr_points').value = button.getAttribute('data-mngr-points') || 0;
            document.getElementById('edit_pt_fo_points').value = button.getAttribute('data-pt-fo-points') || 0;
            document.getElementById('edit_hp_fo_points').value = button.getAttribute('data-hp-fo-points') || 0;
            document.getElementById('edit_other_fo_points').value = button.getAttribute('data-other-fo-points') || 0;
            document.getElementById('edit_description').value = button.getAttribute('data-desc');
            document.getElementById('edit_main_complaints').value = button.getAttribute('data-complaints');
            document.getElementById('edit_patient_phone').value = button.getAttribute('data-phone');
            document.getElementById('edit_closure_conclusion').value = button.getAttribute('data-conclusion');
            document.getElementById('edit_assigned_doctor_id').value = button.getAttribute('data-doctor') || '';
            document.getElementById('edit_price_hospital').value = button.getAttribute('data-price-hosp') || 0;
            document.getElementById('edit_price_patient').value = button.getAttribute('data-price-pt') || 0;
            document.getElementById('edit_price_other').value = button.getAttribute('data-price-other') || 0;
            
            const ct = button.getAttribute('data-ctype');
            const selCt = document.getElementById('edit_claim_type');
            let exists = false;
            for(let i=0; i<selCt.options.length; i++){
                if(selCt.options[i].value === ct){ exists = true; break; }
            }
            if(!exists && ct){
                const opt = document.createElement('option');
                opt.value = ct; opt.text = ct;
                selCt.add(opt);
            }
            selCt.value = ct || 'REIMBURSEMENT';
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
            document.getElementById('pay_ta_amount').value = button.getAttribute('data-ta') || '0.00';
            document.getElementById('pay_tat_deduction').value = button.getAttribute('data-tat') || '0.00';
            document.getElementById('pay_fine_amount').value = button.getAttribute('data-fine') || '0.00';
            document.getElementById('pay_other_deduction').value = button.getAttribute('data-other') || '0.00';
        });
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
</body>
</html>
