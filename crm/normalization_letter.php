<?php
require_once 'app_init.php';
require_once 'auth.php';

// Validations
if (!isset($_GET['id'])) {
    die("Invalid Request. Claim ID is missing.");
}

$project_id = $_GET['id'];

// Fetch Data
$stmt = $pdo->prepare("SELECT p.*, c.company_name, 
                      u.full_name as officer_name, u.employee_id as officer_code,
                      pt.full_name as pt_fo_name, pt.employee_id as pt_fo_code,
                      hp.full_name as hp_fo_name, hp.employee_id as hp_fo_code,
                      ot.full_name as ot_fo_name, ot.employee_id as ot_fo_code,
                      d.full_name as doctor_name 
                      FROM projects p 
                      JOIN clients c ON p.client_id = c.id 
                      LEFT JOIN users u ON p.assigned_to = u.id 
                      LEFT JOIN users pt ON p.pt_fo_id = pt.id
                      LEFT JOIN users hp ON p.hp_fo_id = hp.id
                      LEFT JOIN users ot ON p.other_fo_id = ot.id
                      LEFT JOIN users d ON p.assigned_doctor_id = d.id
                      WHERE p.id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    die("Claim not found.");
}

// Access Control
$curr_role = $_SESSION['role'] ?? '';
$is_ho_staff = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'team_manager', 'fo_manager']);
$is_assigned = (
    $project['assigned_to'] == $_SESSION['user_id'] || 
    $project['pt_fo_id'] == $_SESSION['user_id'] || 
    $project['hp_fo_id'] == $_SESSION['user_id'] || 
    $project['other_fo_id'] == $_SESSION['user_id'] ||
    $project['assigned_doctor_id'] == $_SESSION['user_id']
);

if (!$is_ho_staff && !$is_assigned) {
    die("Access Denied: You are not assigned to this case. The assigned FO can download the authorization letter.");
}

// Format Data
$patient_name = $project['title'] ?? 'Unknown';
$hospital_name = $project['hospital_name'] ?? 'The Medical Director';
$hospital_address = nl2br(htmlspecialchars($project['hospital_address'] ?? ''));
$insurance_co = $project['company_name'];
$claim_no = $project['claim_number'];
$doa = $project['doa'] ? date('d-m-Y', strtotime($project['doa'])) : '-';
$uhid = $project['uhid'] ?? '-';

// Determine which FO name to show
$curr_user_id = $_SESSION['user_id'] ?? 0;
$officer_name = 'Authorized Officer';
$officer_code = 'N/A';

if ($project['assigned_to'] == $curr_user_id) {
    $officer_name = $project['officer_name'];
    $officer_code = $project['officer_code'];
} elseif ($project['pt_fo_id'] == $curr_user_id) {
    $officer_name = $project['pt_fo_name'];
    $officer_code = $project['pt_fo_code'];
} elseif ($project['hp_fo_id'] == $curr_user_id) {
    $officer_name = $project['hp_fo_name'];
    $officer_code = $project['hp_fo_code'];
} elseif ($project['other_fo_id'] == $curr_user_id) {
    $officer_name = $project['ot_fo_name'];
    $officer_code = $project['ot_fo_code'];
} else {
    // If HO staff downloads, use primary FO if exists, else first available
    $officer_name = $project['officer_name'] ?: ($project['pt_fo_name'] ?: ($project['hp_fo_name'] ?: ($project['ot_fo_name'] ?: 'Authorized Officer')));
    $officer_code = $project['officer_code'] ?: ($project['pt_fo_code'] ?: ($project['hp_fo_code'] ?: ($project['ot_fo_code'] ?: 'N/A')));
}

// Paths
$logo_path = "../assets/images/documantraa_logo.png";
$seal_path = "../assets/images/auth_seal.png";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Normalization Letter - <?= htmlspecialchars($claim_no) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        .letter-sheet {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            color: #000;
            font-family: 'Times New Roman', Times, serif;
        }

        .letter-content { font-size: 11pt; line-height: 1.5; }

        @media print {
            @page { margin: 0; size: A4; }
            body { background: white; }
            .letter-sheet { margin: 0; box-shadow: none; width: 100%; border: none; }
            .no-print { display: none !important; }
        }

        .header-section { border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: flex-end; }
        .header-logo { max-height: 60px; filter: grayscale(100%); }
        .info-table { width: 100%; margin-bottom: 30px; border-collapse: collapse; }
        .info-table td { padding: 6px 0; border-bottom: 1px dashed #eee; }
        .info-label { width: 160px; font-weight: bold; }
        .seal-img { max-height: 90px; }
    </style>
</head>
<body>

    <div class="no-print">
        <?php include 'includes/sidebar.php'; ?>
    </div>

    <div class="main-content-wrapper">
        <div class="app-container">
            <div class="d-flex justify-content-end gap-2 mb-4 no-print">
                <button onclick="window.print()" class="btn-v2 btn-white-v2 border"><i class="bi bi-printer me-2"></i> Print</button>
                <button onclick="downloadPDF()" class="btn-v2 btn-primary-v2"><i class="bi bi-download me-2"></i> Download PDF</button>
            </div>

            <div class="d-flex justify-content-center bg-secondary-subtle py-5 rounded-3">
                <div class="letter-sheet" id="pdfContent">
                    <div class="header-section">
                        <div>
                            <h4 style="margin: 0; font-weight: bold; text-transform: uppercase;">AFICS Investigation Agency</h4>
                            <div style="font-size: 9pt; margin-top: 5px;">
                                India Head Office: Calicut, Kerala<br>
                                Verification & Investigation Services<br>
                                Email: support@documantraa.in
                            </div>
                        </div>
                        <div class="text-end">
                            <img src="<?= $logo_path ?>" class="header-logo" alt="Logo">
                            <div style="font-size: 10pt; font-weight: bold; margin-top: 5px;">Ref: AFICS/NORM/<?= $project_id ?></div>
                        </div>
                    </div>

                    <div class="letter-content">
                        <div class="text-end" style="margin-bottom: 20px;"><strong>Date:</strong> <?= date('d-m-Y') ?></div>

                        <div style="margin-bottom: 30px;">
                            <strong>To,</strong><br>
                            <strong>The Medical Superintendent / In-Charge,</strong><br>
                            <strong><?= htmlspecialchars($hospital_name) ?></strong><br>
                            <?= $hospital_address ?>
                        </div>

                        <div style="margin-bottom: 30px; text-align: center; text-decoration: underline; font-weight: bold; text-transform: uppercase;">
                            Sub: Normalization & Identity Verification for Claim # <?= htmlspecialchars($claim_no) ?>
                        </div>

                        <p>Respected Sir/Madam,</p>
                        
                        <p>We are writing to perform a normalization check for the ongoing claim investigation of <strong>Mr./Ms. <?= htmlspecialchars($patient_name) ?></strong>, who is/was reportedly admitted to your esteemed hospital.</p>

                        <table class="info-table">
                            <tr><td class="info-label">Patient Name</td><td>: <?= htmlspecialchars($patient_name) ?></td></tr>
                            <tr><td class="info-label">Claim Number</td><td>: <?= htmlspecialchars($claim_no) ?></td></tr>
                            <tr><td class="info-label">IP / UHID No.</td><td>: <?= htmlspecialchars($uhid) ?></td></tr>
                            <tr><td class="info-label">Admission Date</td><td>: <?= $doa ?></td></tr>
                            <tr><td class="info-label">Assigned Officer</td><td>: <strong><?= htmlspecialchars(strtoupper($officer_name)) ?></strong> (ID: <?= htmlspecialchars($officer_code) ?>)</td></tr>
                        </table>

                        <p>In accordance with industry standard normalization procedures, we kindly request the hospital to verify that the patient profile and clinical data align with the records submitted for insurance processing.</p>

                        <p>Please provide confirmation regarding the following:</p>
                        <ul>
                            <li>Identity of the patient matches the ID proof provided during admission.</li>
                            <li>The clinical condition documented in the ICP (Indoor Case Papers) matches the primary diagnosis.</li>
                            <li>Treatment protocols are consistent with hospital standards.</li>
                        </ul>

                        <p>Your timely response will ensure that the investigation remains localized and standard-compliant, preventing any delays in the final claim decision.</p>

                        <div style="margin-top: 60px;">
                            <p>Yours Sincerely,</p>
                            <img src="<?= $seal_path ?>" class="seal-img" alt="Seal">
                            <div style="font-weight: bold; margin-top: 5px;">
                                Authorized Signatory<br>
                                <small>AFICS Investigation Agency</small>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('pdfContent');
            const opt = {
                margin:       10,
                filename:     'Normalization_Letter_<?= $claim_no ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
