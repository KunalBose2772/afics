<?php
require_once 'app_init.php';
require_once 'auth.php';

// Security Check: Only HO staff can access the Report Builder
$curr_role = $_SESSION['role'] ?? '';
$is_ho_staff = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'doctor']);
if (!$is_ho_staff) {
    echo "<div style='font-family:sans-serif; padding:50px; text-align:center;'><h2>Access Denied</h2><p>Only HO staff can access the Investigation Report Builder.</p><a href='projects.php'>Return to Dashboard</a></div>";
    exit;
}

$pid = $_GET['id'] ?? 0;
if ($pid == 0) {
    header('Location: projects.php');
    exit;
}

// Fetch Project Details
$stmt = $pdo->prepare("SELECT p.*, c.company_name, u.full_name as officer_name, tm.full_name as tm_name
                       FROM projects p 
                       JOIN clients c ON p.client_id = c.id 
                       LEFT JOIN users u ON p.assigned_to = u.id 
                       LEFT JOIN users tm ON p.team_manager_id = tm.id
                       WHERE p.id = ?");
$stmt->execute([$pid]);
$project = $stmt->fetch();

if (!$project) {
    echo "<h1>Claim not found</h1><a href='projects.php'>Back</a>";
    exit;
}

// Fetch Photos for Appendix
$docStmt = $pdo->prepare("SELECT * FROM project_documents WHERE project_id = ? AND (file_name LIKE '%.jpg' OR file_name LIKE '%.png' OR file_name LIKE '%.jpeg') ORDER BY uploaded_at ASC");
$docStmt->execute([$pid]);
$photos = $docStmt->fetchAll();

// Dates
$doa = !empty($project['doa']) ? date('d/m/Y', strtotime($project['doa'])) : 'N/A';
$dod = !empty($project['dod']) ? date('d/m/Y', strtotime($project['dod'])) : 'N/A';
$alloc = !empty($project['allocation_date']) ? date('d/m/Y', strtotime($project['allocation_date'])) : date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Premium Investigation Report - <?= htmlspecialchars($project['claim_number']) ?></title>
    
    <!-- Professional Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lexend:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    
    <style>
        :root { --report-blue: #1e40af; --report-grey: #475569; --report-border: #cbd5e1; }
        body { background: #e2e8f0; font-family: 'Inter', sans-serif; color: #1f2937; }
        
        /* GUI Controls */
        .controls-sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 380px; background: #fff; padding: 30px; overflow-y: auto; box-shadow: 4px 0 30px rgba(0,0,0,0.1); z-index: 1000; }
        .main-viewer { margin-left: 380px; padding: 40px; }
        
        /* Premium Paper Style */
        .page { background: #fff; width: 210mm; min-height: 297mm; padding: 20mm; margin: 0 auto 30px auto; position: relative; box-shadow: 0 10px 40px rgba(0,0,0,0.1); border-radius: 4px; overflow: hidden; }
        .page::before { content: ""; position: absolute; top: 0; left: 0; width: 100%; height: 8px; background: var(--report-blue); }
        
        /* Typography */
        h1, h2, h3, h4 { font-family: 'Lexend', sans-serif; font-weight: 800; text-transform: uppercase; letter-spacing: -0.02em; }
        .report-header { margin-bottom: 30px; }
        .report-logo { max-height: 70px; width: auto; object-fit: contain; }
        .report-subtitle { font-size: 8pt; color: #64748b; font-weight: 700; border-top: 1px solid #e2e8f0; margin-top: 5px; padding-top: 2px; }
        
        .section-tag { background: #f1f5f9; border-left: 5px solid var(--report-blue); padding: 6px 12px; font-weight: 700; color: #1e293b; font-size: 11pt; text-transform: uppercase; margin: 25px 0 15px 0; display: block; }
        
        /* Layout Tables */
        .data-grid { width: 100%; border-collapse: collapse; border: 1.5px solid var(--report-border); }
        .data-grid th { background: #f8fafc; border: 1px solid var(--report-border); padding: 8px 12px; font-size: 9pt; color: #64748b; font-weight: 700; text-align: left; text-transform: uppercase; }
        .data-grid td { border: 1px solid var(--report-border); padding: 8px 12px; font-size: 10.5pt; font-weight: 500; }
        
        /* Summary Box */
        .decision-badge { border: 4px solid #000; padding: 20px; text-align: center; margin-top: 40px; background: #fafafa; }
        .decision-badge h2 { font-size: 24pt; margin: 0; }
        
        /* Photos Grid */
        .photo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 20px; }
        .photo-box { border: 1px solid #ddd; padding: 5px; background: #fff; }
        .photo-box img { width: 100%; height: 260px; object-fit: cover; }
        .photo-caption { font-size: 8pt; color: #666; margin-top: 5px; text-align: center; }

        .report-seal { width: 100px; height: 100px; border: 3px solid #8b5cf6; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-size: 8pt; font-weight: 800; transform: rotate(-15deg); text-align: center; padding: 5px; }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .main-viewer { margin: 0; padding: 0; }
            .page { width: 100%; height: auto; padding: 15mm; margin: 0; box-shadow: none; }
            .page-break { page-break-before: always; }
        }
        
        @media (max-width: 991px) {
            .controls-sidebar { width: 100%; position: relative; height: auto; }
            .main-viewer { margin-left: 0; padding: 10px; }
            .page { width: 100%; height: auto; padding: 10mm; }
        }
    </style>
</head>
<body>

    <!-- Professional Controls Panel -->
    <div class="controls-sidebar no-print">
        <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
            <a href="project_details.php?id=<?= $pid ?>" class="btn btn-outline-secondary btn-sm rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
            <h5 class="mb-0 fw-bold">Report Customizer</h5>
        </div>
        
        <form id="editorForm" oninput="syncReport()">
            <div class="mb-4">
                <label class="small fw-bold text-muted mb-2">1. CORE CLAIM DETAILS</label>
                <div class="mb-2"><input type="text" id="in_policy" class="form-control form-control-sm" placeholder="Policy Number"></div>
                <div class="mb-2"><input type="text" id="in_doctor" class="form-control form-control-sm" placeholder="Treating Doctor Name"></div>
                <div class="mb-2">
                    <select id="in_type" class="form-select form-select-sm">
                        <option value="Reimbursement" selected>Reimbursement Claim</option>
                        <option value="Cashless">Cashless Claim</option>
                        <option value="Cashless (Non-Network)">Cashless (Non-Network)</option>
                    </select>
                </div>
                <div class="mb-2"><input type="text" id="in_trigger" class="form-control form-control-sm" value="High claimed amount / Diagnosis verification"></div>
            </div>

            <div class="mb-4">
                <label class="small fw-bold text-muted mb-2">2. VISITATION CHECKLIST</label>
                <div id="check_rows_input">
                    <div class="row g-1 mb-1">
                        <div class="col-6"><input type="text" value="Indoor Register" class="form-control form-control-sm"></div>
                        <div class="col-6"><input type="text" value="Verified" class="form-control form-control-sm"></div>
                    </div>
                    <div class="row g-1 mb-1">
                        <div class="col-6"><input type="text" value="Indoor Case Papers" class="form-control form-control-sm"></div>
                        <div class="col-6"><input type="text" value="Collected & Provided" class="form-control form-control-sm"></div>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <label class="small fw-bold text-muted mb-2">3. FINDINGS & COMMENTS</label>
                <textarea id="in_hosp_visit" class="form-control form-control-sm mb-2" rows="4" placeholder="Hospital Visit Findings...">Physical visit was initiated at <?= htmlspecialchars($project['hospital_name'] ?? 'Hospital') ?>. ICPs are collected and verified. Client is advised to review based on surgical necessity.</textarea>
                
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="in_pt_not_assigned" checked>
                    <label class="form-check-label small" for="in_pt_not_assigned">Mark Patient Part "Not Assigned"</label>
                </div>
                <textarea id="in_pt_visit" class="form-control form-control-sm mb-2" rows="2" placeholder="Patient Visit Comment..."></textarea>
            </div>

            <div class="mb-4">
                <label class="small fw-bold text-muted mb-2">4. FINAL VERDICT</label>
                <textarea id="in_conclusion" class="form-control form-control-sm mb-2" rows="3">Based on medical records and on-site verification, the claim is found to be consistent with the diagnosed ailment.</textarea>
                <select id="in_decision" class="form-select fw-bold">
                    <option value="GENUINE">GENUINE</option>
                    <option value="FRAUD">FRAUD / SUSPICIOUS</option>
                    <option value="INADMISSIBLE">INADMISSIBLE</option>
                </select>
            </div>

            <div class="p-3 bg-light rounded text-center border">
                <button type="button" onclick="window.print()" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-printer me-2"></i> DOWNLOAD FINAL PDF</button>
                <small class="text-muted mt-2 d-block" style="font-size: 0.7rem;">Optimized for A4 Printing & Microsoft Edge/Chrome</small>
            </div>
        </form>
    </div>

    <!-- Premium Report Viewer -->
    <div class="main-viewer">
        <div class="page" id="report_content">
            <!-- Header -->
            <div class="report-header d-flex justify-content-between align-items-center">
                <div class="text-start">
                    <img src="../assets/images/auth_logo1.png" class="report-logo" alt="Logo">
                    <div class="report-subtitle">CONFIDENTIAL INVESTIGATION SERVICES</div>
                </div>
                <div class="text-end">
                    <div class="report-header-text">Closure Report</div>
                    <div class="small fw-bold text-muted"><?= date('F d, Y') ?> &middot; Claim #<?= htmlspecialchars($project['claim_number']) ?></div>
                </div>
            </div>

            <!-- Basic Details Grid -->
            <span class="section-tag">Case Identification</span>
            <table class="data-grid">
                <tr>
                    <th width="20%">Insurance Co.</th>
                    <td width="30%"><?= htmlspecialchars($project['company_name']) ?></td>
                    <th width="20%">Insured Name</th>
                    <td width="30%"><?= htmlspecialchars($project['title']) ?></td>
                </tr>
                <tr>
                    <th>Claim Type</th>
                    <td id="d_type">Reimbursement</td>
                    <th>Hospital Name</th>
                    <td><?= htmlspecialchars($project['hospital_name'] ?? 'N/A') ?></td>
                </tr>
                <tr>
                    <th>Policy No.</th>
                    <td id="d_policy">-</td>
                    <th>Treating Dr.</th>
                    <td id="d_doctor">-</td>
                </tr>
                <tr>
                    <th>DOA & DOD</th>
                    <td><?= $doa ?> to <?= $dod ?></td>
                    <th>Allocation Date</th>
                    <td><?= $alloc ?></td>
                </tr>
                <tr>
                    <th>Diagnosis</th>
                    <td colspan="3" id="d_diagnosis" class="fw-bold"><?= htmlspecialchars($project['diagnosis'] ?? '-') ?></td>
                </tr>
                <tr>
                    <th>Trigger</th>
                    <td colspan="3" id="d_trigger">-</td>
                </tr>
            </table>

            <!-- Visitation Checklist -->
            <span class="section-tag">Investigation Points & Visitation Inputs</span>
            <table class="data-grid text-center">
                <thead>
                    <tr>
                        <th>Observation Point</th>
                        <th>Verification Status</th>
                    </tr>
                </thead>
                <tbody id="d_checklist">
                    <!-- JS Populated -->
                </tbody>
            </table>

            <!-- Visit Comments -->
            <span class="section-tag">Comments of Hospital Visit</span>
            <div id="d_hosp_comments" class="small" style="text-align: justify; line-height: 1.6;"></div>

            <span class="section-tag">Patient Visit Findings</span>
            <div id="d_pt_comments" class="small fst-italic text-muted"></div>

            <span class="section-tag">Conclusion & Inference</span>
            <div id="d_conclusion" class="small" style="text-align: justify; line-height: 1.6;"></div>

            <!-- Decision Box -->
            <div class="decision-badge" id="d_verdict_box">
                <div class="small fw-bold text-muted mb-1">Final Recommendation</div>
                <h2 id="d_decision_text">GENUINE</h2>
            </div>

            <!-- Report Footer -->
            <div style="display: flex; justify-content: space-between; margin-top: 50px; position: relative;">
                <div class="text-center" style="width: 150px; border-top: 1px solid #000; padding-top: 8px; font-size: 8pt;">Field Staff Signature</div>
                <div class="text-center" style="width: 180px; border-top: 1px solid #000; padding-top: 8px; font-size: 8pt; position: relative;">
                    Authorized Signatory
                    <!-- Official Company Seal -->
                    <img src="../assets/images/auth_seal.png" style="position: absolute; top: -100px; right: -20px; width: 140px; height: auto; opacity: 0.85; pointer-events: none; transform: rotate(-10deg);">
                </div>
            </div>
            
            <div class="mt-4 pt-4 border-top text-center" style="font-size: 7.5pt; color: #94a3b8; line-height: 1.4;">
                This Investigation Report is being issued strictly for internal insurance consideration without prejudice.<br>
                Issued by **Documantraa Investigation Unit** on <?= date('d/m/Y H:i') ?>.
            </div>
        </div>

        <!-- Photo Appendix Page -->
        <?php if(!empty($photos)): ?>
        <div class="page page-break">
            <div class="report-header-text mb-4" style="font-size: 18pt;">Evidence Appendix</div>
            <p class="text-muted small mb-4">The following photographs were captured during the physical visitation at the location.</p>
            
            <div class="photo-grid">
                <?php foreach($photos as $index => $photo): ?>
                <div class="photo-box" style="position: relative;">
                    <img src="../<?= htmlspecialchars($photo['file_path']) ?>" alt="Evidence">
                    <?php if($index === 0): ?>
                        <img src="../assets/images/auth_seal.png" style="position: absolute; top: 10px; right: 10px; width: 50px; height: auto; opacity: 0.7; pointer-events: none; transform: rotate(-15deg);">
                    <?php endif; ?>
                    <div class="photo-caption">Fig <?= $index+1 ?>: <?= htmlspecialchars($photo['file_name']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-5 p-3 bg-light text-center small text-muted border border-dashed" style="border-radius: 8px;">
                GPS Coordinates and Timestamps are embedded in digital originals for authenticity.
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function syncReport() {
            // Basic Fields
            document.getElementById('d_policy').innerText = document.getElementById('in_policy').value || '-';
            document.getElementById('d_doctor').innerText = document.getElementById('in_doctor').value || '-';
            document.getElementById('d_type').innerText = document.getElementById('in_type').value;
            document.getElementById('d_trigger').innerText = document.getElementById('in_trigger').value;
            
            // Comments
            document.getElementById('d_hosp_comments').innerHTML = document.getElementById('in_hosp_visit').value.replace(/\n/g, '<br>');
            document.getElementById('d_conclusion').innerHTML = document.getElementById('in_conclusion').value.replace(/\n/g, '<br>');

            // Pt Assignment Logic
            const ptNotAssigned = document.getElementById('in_pt_not_assigned').checked;
            const ptCustom = document.getElementById('in_pt_visit').value;
            const ptDisplay = document.getElementById('d_pt_comments');
            if(ptNotAssigned) {
                ptDisplay.innerHTML = "Patient part is not assigned to us as per company guidelines for this specific claim scope. Direct patient interaction was not conducted.";
            } else {
                ptDisplay.innerHTML = ptCustom || "Direct patient verification conducted. Findings consistent with documentation.";
            }

            // Verdict
            const verdict = document.getElementById('in_decision').value;
            const vText = document.getElementById('d_decision_text');
            vText.innerText = verdict;
            vText.className = (verdict === 'GENUINE') ? 'text-success' : 'text-danger';

            // Checklist Table Sync
            const checkInputs = document.getElementById('check_rows_input').querySelectorAll('.row');
            const checkTable = document.getElementById('d_checklist');
            checkTable.innerHTML = '';
            checkInputs.forEach(row => {
                const vals = row.querySelectorAll('input');
                const tr = document.createElement('tr');
                tr.innerHTML = `<td>${vals[0].value}</td><td><span class="badge bg-secondary opacity-75">${vals[1].value}</span></td>`;
                checkTable.appendChild(tr);
            });
        }

        // Run initially
        window.onload = syncReport;
    </script>
</body>
</html>
