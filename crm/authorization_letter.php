<?php
require_once 'app_init.php';
require_once 'auth.php';

// Validations
if (!isset($_GET['id'])) {
    die("Invalid Request. Claim ID is missing.");
}

$project_id = $_GET['id'];
$root_path = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
if($root_path == '\doc\crm') $root_path = '/doc/crm'; 
else $root_path = $root_path ? $root_path : '';


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
$is_ho_staff = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'doctor', 'team_manager', 'fo_manager']);
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
$claim_no = !empty($project['manual_claim_number']) ? $project['manual_claim_number'] : ($project['claim_number'] ?? 'N/A');
$doa = $project['doa'] ? date('d-m-Y', strtotime($project['doa'])) : '-';
$dod = $project['dod'] ? date('d-m-Y', strtotime($project['dod'])) : '-';
$uhid = $project['uhid'] ?? '-';
$diagnosis = $project['diagnosis'] ?? '-';
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

// Base64 helper for rendering images inside MS Word export
function img_base64($path) {
    $real_path = __DIR__ . '/../' . ltrim($path, './');
    if (!file_exists($real_path)) return '';
    $ext = pathinfo($real_path, PATHINFO_EXTENSION);
    $data = file_get_contents($real_path);
    return 'data:image/' . $ext . ';base64,' . base64_encode($data);
}

// Handle Word Export
if (isset($_GET['export']) && $_GET['export'] === 'doc') {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=\"Auth_Letter_{$claim_no}.doc\"");
    header("Pragma: no-cache");
    header("Expires: 0");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auth Letter - <?= htmlspecialchars($claim_no) ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    
    <!-- PDF Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

    <style>
        /* Print & PDF Styles */
        body { 
            background: var(--bg-body); 
            /* Font is managed by app.css (Jost) for UI, Times for letter only */
        }
        
        /* The Actual Letter Sheet */
        .letter-sheet {
            width: 210mm;
            min-height: 297mm;
            background: white;
            margin: 0 auto;
            padding: 20mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            position: relative;
            color: #000;
            font-family: 'Times New Roman', Times, serif; /* Specific font for the letter content */
        }

        .letter-content {
            font-size: 11pt;
            line-height: 1.4;
        }

        @media print {
            @page { margin: 0; size: A4 portrait; }
            body { background: white; margin: 0; padding: 0; }
            .letter-sheet { margin: 0; box-shadow: none; border: none; width: 100%; height: auto; }
            .mobile-top-bar, .sidebar-v2, .app-header-section, .ui-controls-bar { display: none !important; }
            .main-content-wrapper { margin: 0 !important; padding: 0 !important; }
            .app-container { padding: 0 !important; max-width: none !important; }
        }

        /* Letter Components */
        .header-section {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        
        .header-logo { max-height: 60px; filter: grayscale(100%); }
        
        .info-table { width: 100%; margin-bottom: 20px; border-collapse: collapse; }
        .info-table td { padding: 4px 0; vertical-align: top; }
        .info-label { width: 140px; font-weight: bold; }
        
        .hospital-box { 
            border: 1px solid #000; 
            padding: 15px; 
            margin-top: 25px; 
            background: #fff;
        }
        
        .footer-law {
            font-size: 8pt;
            color: #555;
            text-align: center;
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }

        .seal-img {
            max-height: 80px;
            opacity: 0.8;
            margin-top: -10px;
        }
    </style>
</head>
<body>

<?php if (!isset($_GET['export'])): ?>
    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <a href="projects.php" class="text-main"><i class="bi bi-arrow-left fs-4"></i></a>
            <span class="fw-bold ms-2">Authorization Letter</span>
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
<?php endif; ?>

    <div class="main-content-wrapper" style="<?= isset($_GET['export']) ? 'margin-left: 0; padding: 0;' : '' ?>">
<?php if (!isset($_GET['export'])): ?>
        <!-- App Header (Desktop) -->
        <header class="app-header-section d-none d-lg-block">
            <div class="header-inner">
                <div class="d-flex align-items-center gap-3">
                    <a href="projects.php" class="btn-white-v2 rounded-circle" style="width: 40px; height: 40px; padding: 0;"><i class="bi bi-arrow-left"></i></a>
                    <div>
                        <h1 style="font-size: 1.5rem; color: var(--text-main);">Authorization Letter</h1>
                        <p class="text-muted mb-0 small">Claim #: <?= htmlspecialchars($claim_no) ?></p>
                    </div>
                </div>
            </div>
        </header>
<?php endif; ?>

        <div class="app-container">
<?php if (!isset($_GET['export'])): ?>
            <!-- Controls Bar -->
            <div class="ui-controls-bar d-flex justify-content-end gap-2 mb-4 no-print">
                <button onclick="window.print()" class="btn-v2 btn-white-v2">
                    <i class="bi bi-printer"></i> Print
                </button>
                <a href="authorization_letter.php?id=<?= $project_id ?>&export=doc" class="btn-v2 btn-white-v2">
                    <i class="bi bi-file-earmark-word"></i> Word
                </a>
                <button onclick="downloadPDF()" class="btn-v2 btn-primary-v2">
                    <i class="bi bi-download"></i> Download PDF
                </button>
            </div>
<?php endif; ?>

            <!-- Letter Sheet Wrapper -->
            <div class="d-flex justify-content-center bg-secondary-subtle py-5 rounded-3">
                <div class="letter-sheet" id="pdfContent">
                    
                    <!-- Header -->
                    <div class="header-section">
                        <div>
                            <h4 style="margin: 0; font-weight: bold; text-transform: uppercase; font-family: 'Arial', sans-serif;">AFICS Investigation Agency</h4>
                            <div style="font-size: 9pt; margin-top: 5px;">
                                South Zone Head Office, Calicut, Kerala<br>
                                Reg No: AFICS/KL/2023/8892<br>
                                Email: support@documantraa.in | Web: www.documantraa.in | www.afics.in
                            </div>
                        </div>
                        <div class="text-end">
                            <img src="<?= img_base64('assets/images/documantraa_logo.png') ?>" class="header-logo" alt="Logo" width="180">
                            <div style="font-size: 10pt; font-weight: bold; margin-top: 5px;">Date: <?= date('d/m/Y') ?></div>
                        </div>
                    </div>

                    <div class="letter-content">
                        <!-- To Address -->
                        <div style="margin-bottom: 20px;">
                            <strong>To,</strong><br>
                            <strong>The Medical Superintendent / Medical Director,</strong><br>
                            <strong><?= htmlspecialchars($hospital_name) ?></strong><br>
                            <?= $hospital_address ?>
                        </div>

                        <!-- Subject -->
                        <div style="margin-bottom: 20px; font-weight: bold; text-decoration: underline;">
                            Sub: Authorization for Verification of Medical Records & Documents
                        </div>

                        <!-- Patient Details -->
                        <table class="info-table">
                            <tr><td class="info-label">Claim Number</td><td>: <strong><?= htmlspecialchars($claim_no) ?></strong></td></tr>
                            <tr><td class="info-label">Patient Name</td><td>: <?= htmlspecialchars($patient_name) ?></td></tr>
                            <tr><td class="info-label">DOA</td><td>: <?= $doa ?></td></tr>
                            <tr><td class="info-label">UHID / IP No</td><td>: <?= htmlspecialchars($uhid) ?></td></tr>
                            <tr><td class="info-label">Assigned Officer</td><td>: <strong><?= htmlspecialchars(strtoupper($officer_name)) ?></strong> (ID: <?= htmlspecialchars($officer_code) ?>)</td></tr>
                        </table>

                        <!-- Body -->
                        <p>Dear Sir/Madam,</p>
                        
                        <p class="text-justify">
                            We, <strong>AFICS Investigation Agency</strong>, have been authorized by <strong><?= htmlspecialchars($insurance_co) ?></strong> to verify the hospitalization details of the above-mentioned patient.
                        </p>

                        <p class="text-justify">
                            We have assigned our Field Officer, <strong>Mr./Ms. <?= htmlspecialchars(strtoupper($officer_name)) ?></strong> (ID: <?= htmlspecialchars($officer_code) ?>), to visit your hospital for the verification of documents and to collect necessary medical records required for claim processing.
                        </p>

                        <p>We kindly request you to provide the following documents/information:</p>
                        <ol style="margin-bottom: 20px; padding-left: 20px;">
                            <li>Attested copies of ICP (Indoor Case Papers).</li>
                            <li>Final Bill with breakup & Discharge Summary.</li>
                            <li>Diagnostic Reports (Lab, X-Ray, etc.).</li>
                            <li>Any other relevant documents.</li>
                        </ol>

                        <p>Your cooperation in this regard will help in the speedy settlement of the patient's insurance claim.</p>

                        <!-- Signatures -->
                        <div style="margin-top: 40px; display: flex; justify-content: space-between; align-items: flex-end;">
                            <div>
                                <img src="<?= img_base64('assets/images/auth_seal.png') ?>" class="seal-img" alt="Seal" width="100" height="100">
                                <div style="border-top: 1px solid #000; width: 200px; padding-top: 5px; font-weight: bold;">
                                     Authorized Signatory<br>
                                     <small style="font-weight: normal;">AFICS Investigation Agency</small>
                                </div>
                            </div>
                        </div>

                        <!-- Hospital Acknowledgement -->


                        <!-- Disclaimer -->
                        <div class="footer-law">
                            This document is legally privileged and confidential...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadPDF() {
            const element = document.getElementById('pdfContent');
            const opt = {
                margin:       0,
                filename:     'Auth_Letter_<?= $claim_no ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, useCORS: true },
                jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };
            html2pdf().set(opt).from(element).save();
        }
    </script>
</body>
</html>
