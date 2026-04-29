<?php
require_once 'app_init.php';
require_once 'auth.php';

$pid = intval($_GET['id'] ?? 0);
if (!$pid) { echo "Invalid ID"; exit; }

$stmt = $pdo->prepare("SELECT p.*, c.company_name as client_name, u.full_name as officer_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    WHERE p.id = ?");
$stmt->execute([$pid]);
$project = $stmt->fetch();
if (!$project) { echo "<h1>Not found</h1>"; exit; }

// Security Check: Only allow HO staff, managers, doctors and incharge to view the report
$curr_role = $_SESSION['role'] ?? '';
$is_authorized = in_array($curr_role, ['admin', 'super_admin', 'manager', 'hod', 'doctor', 'incharge', 'team_manager']);
if (!$is_authorized) {
    die("Access Denied: You do not have permission to view this report.");
}

$s          = get_settings($pdo);
$tagline    = $s['site_tagline']    ?? 'Claim & Risk Management Services';
$c_phone    = $s['contact_phone']   ?? '';
$c_email    = $s['contact_email']   ?? '';
$c_addr     = $s['contact_address'] ?? '';

$fmt_date   = fn($f) => !empty($project[$f]) ? date('d/m/Y', strtotime($project[$f])) : 'N/A';
$fmt_dt     = fn($f) => !empty($project[$f]) ? strtoupper(date('d/m/Y \a\t h:i A', strtotime($project[$f]))) : 'N/A';

$doa          = $fmt_dt('doa');
$dod          = $fmt_dt('dod');
$assign_date  = $fmt_date('allocation_date') === 'N/A' ? date('d/m/Y') : $fmt_date('allocation_date');
$report_date  = date('d/m/Y');

// Safe field accessor – uppercase display
$v = function(string $key, string $fb = 'N/A') use ($project): string {
    $val = trim((string)($project[$key] ?? ''));
    return htmlspecialchars($val !== '' ? strtoupper($val) : strtoupper($fb), ENT_QUOTES);
};
// Safe field – preserve case (narrative text)
$n = function(string $key, string $fb = '') use ($project): string {
    $val = trim((string)($project[$key] ?? ''));
    return nl2br(htmlspecialchars($val !== '' ? $val : $fb, ENT_QUOTES));
};

// Base64 helper for rendering images inside MS Word export
function img_base64($path) {
    $real_path = __DIR__ . '/../' . ltrim($path, './');
    if (!file_exists($real_path)) return '';
    $ext = pathinfo($real_path, PATHINFO_EXTENSION);
    $data = file_get_contents($real_path);
    return 'data:image/' . $ext . ';base64,' . base64_encode($data);
}

if (isset($_GET['export']) && $_GET['export'] === 'doc') {
    header('Content-Type: application/vnd.ms-word');
    header('Content-Disposition: attachment; filename="IR_' . $project['claim_number'] . '.doc"');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Investigation Report – <?= htmlspecialchars($project['claim_number'] ?? '') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ── Reset & base ─────────────────────────────────── */
*{box-sizing:border-box;margin:0;padding:0}
@page{size:A4;margin:12mm 14mm}
body{font-family:'Jost',Arial,sans-serif;font-size:9.2pt;color:#111;background:#d8dce8;line-height:1.5}
a{color:inherit;text-decoration:none}

/* ── Page wrapper ─────────────────────────────────── */
.page{width:210mm;min-height:297mm;background:#fff;margin:8mm auto;padding:11mm 13mm;
      box-shadow:0 8px 36px rgba(0,0,0,.16);position:relative}

/* ── Header ───────────────────────────────────────── */
.hdr{display:flex;justify-content:space-between;align-items:center;
     padding-bottom:12px;border-bottom:2pt solid #0f172a;margin-bottom:15px}
.hdr-logo{max-height:60px;max-width:200px}
.hdr-right{text-align:right}
.hdr-right h1{font-size:13pt;font-weight:900;color:#0f172a;text-transform:uppercase;
              letter-spacing:.08em;line-height:1.1;margin-bottom:3px}
.hdr-tagline{font-size:7.5pt;color:#334155;font-weight:600;letter-spacing:.04em}
.hdr-conf{font-size:6.5pt;color:#64748b;font-weight:700;letter-spacing:.08em;text-transform:uppercase;margin-top:2px}

/* ── Section header ───────────────────────────────── */
.sec{background:#f1f5f9;color:#1e293b;
     font-weight:800;font-size:8.5pt;text-transform:uppercase;letter-spacing:.06em;
     padding:7px 10px;margin-top:12px;margin-bottom:0;
     border-left:4pt solid #3D0C60;border-bottom:1pt solid #cbd5e1}

/* ── Tables ───────────────────────────────────────── */
table{width:100%;border-collapse:collapse;margin:0}
td{border:1pt solid #e2e8f0;padding:5px 9px;font-size:8.5pt;vertical-align:top;word-break:break-word}
.lbl{width:40%;background:#f8fafc;font-weight:600;color:#475569}
.val{width:60%;font-weight:700;color:#0f172a;text-transform:uppercase}
/* narrative value – preserves case, justify, line-height */
.val-text{width:60%;font-weight:500;color:#1e293b;text-transform:none;text-align:justify;line-height:1.65;font-size:8.5pt}
.val-text ol{margin:4px 0 4px 18px}

/* ── Observation box ──────────────────────────────── */
.obs-wrap{border:1pt solid #e2e8f0;padding:11px 14px;margin-top:0}
.obs-wrap p{font-size:8.5pt;color:#1e293b;text-align:justify;margin-bottom:7px;line-height:1.65}
.obs-wrap ol{font-size:8.5pt;color:#1e293b;margin:5px 0 7px 20px;line-height:1.65}
.obs-sub{text-align:left;font-weight:800;text-transform:uppercase;
         font-size:8.5pt;letter-spacing:.05em;color:#334155;margin:10px 0 6px;
         border-bottom:1pt dashed #cbd5e1;padding-bottom:3px}
.obs-sub:first-child{margin-top:0}

/* ── Conclusion block ─────────────────────────────── */
.conc{border:1.5pt solid #cbd5e1;background:#f8fafc;padding:12px 14px;margin-top:12px;font-size:8.5pt;color:#1e293b;line-height:1.65}
.conc-title{font-weight:800;font-size:9pt;color:#0f172a;text-align:center;text-transform:uppercase;
            letter-spacing:.03em;margin-bottom:7px}
.conc p{text-align:justify;margin-bottom:6px}
.disc{font-size:7.5pt;color:#64748b;text-align:justify;margin-top:8px;border-top:1pt solid #e2e8f0;padding-top:6px}

/* ── Recommendation banner ────────────────────────── */
.reco{margin-top:12px;background:#f1f5f9;color:#0f172a;text-align:center;
      font-weight:900;font-size:10.5pt;letter-spacing:.1em;text-transform:uppercase;padding:9px 14px;
      border:1.5pt solid #94a3b8;border-radius:4px}

/* ── Footer / signature ───────────────────────────── */
.footer-wrap{margin-top:16px;position:relative}
.footer-wm{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);
           width:260px;opacity:.05;z-index:0;pointer-events:none}
.ftbl{width:100%;border-collapse:collapse;position:relative;z-index:1}
.ftbl th{background:#f1f5f9;color:#0f172a;font-size:8.5pt;font-weight:800;padding:8px 10px;
         text-align:left;letter-spacing:.05em;border-bottom:1pt solid #cbd5e1;border-top:2pt solid #3D0C60}
.ftbl td{border:1pt solid #e2e8f0;padding:8px 10px;font-size:8.5pt;background:rgba(255,255,255,.9)}
.flbl{display:block;font-size:6pt;font-weight:800;color:#64748b;text-transform:uppercase;
      letter-spacing:.06em;margin-bottom:2px}
.fval{font-weight:700;color:#111;font-size:8.5pt}
.sig-area{display:flex;align-items:center;gap:10px;margin-top:5px}
.sig-name{font-family:'Georgia',serif;font-style:italic;font-weight:600;font-size:9pt;color:#111}

/* ── No-print action bar ──────────────────────────── */
.no-print{position:fixed;top:14px;right:14px;z-index:9999;display:flex;gap:8px}
.btn{padding:9px 18px;border:none;border-radius:7px;cursor:pointer;font-family:'Jost',sans-serif;
     font-weight:700;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;
     gap:7px;box-shadow:0 4px 12px rgba(0,0,0,.18);transition:transform .15s}
.btn:hover{transform:translateY(-1px)}
.btn-p{background:#3D0C60;color:#fff}
.btn-d{background:#1d4ed8;color:#fff}
.btn-b{background:#fff;color:#374151;border:1px solid #d1d5db}

@media print{
  body{background:#fff}
  .page{margin:0;box-shadow:none;width:100%;padding:8mm 10mm}
  .no-print{display:none}
  .sec{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .reco{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  .ftbl th{-webkit-print-color-adjust:exact;print-color-adjust:exact}
}
</style>
</head>
<body>

<?php if (!isset($_GET['export'])): ?>
<div class="no-print">
  <button class="btn btn-p" onclick="window.print()">
    <svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
      <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
      <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a.5.5 0 0 0 0 1h6a.5.5 0 0 0 0-1H5zm6 1H5a.5.5 0 0 1 0-1h6a.5.5 0 0 1 0 1zM11 14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-2h6v2z"/>
    </svg>Print Report
  </button>
  <a class="btn btn-d" href="investigation_report.php?id=<?= $pid ?>&export=doc">
    <svg width="15" height="15" fill="currentColor" viewBox="0 0 16 16">
      <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
    </svg>Download DOC
  </a>
  <a class="btn btn-b" href="projects.php">&#8592; Back</a>
</div>
<?php endif; ?>

<div class="page">

<!-- ═══ HEADER ═══ -->
<div class="hdr">
  <img class="hdr-logo" src="<?= img_base64('assets/images/documantraa_logo.png') ?>" alt="Documantraa">
  <div class="hdr-right">
    <h1>Investigation Report</h1>
    <div class="hdr-tagline" style="text-transform:uppercase;letter-spacing:0.1em;font-size:7pt;color:#64748b;font-weight:700">CLAIM NUMBER</div>
    <div class="hdr-conf" style="font-size:10.5pt;color:#0f172a;font-weight:800;letter-spacing:0.05em;margin-top:2px;font-family:monospace"><?= htmlspecialchars($project['claim_number'] ?? 'N/A') ?></div>
  </div>
</div>

<!-- ═══ S1: INSURED SUMMARY ═══ -->
<div class="sec">Insured &amp; Related Details</div>
<table>
  <tr><td class="lbl">Name of the Insured</td>                 <td class="val"><?= $v('title') ?></td></tr>
  <tr><td class="lbl">Type of Claim</td>                       <td class="val"><?= $v('claim_type') ?></td></tr>
  <tr><td class="lbl">Hospital Name</td>                       <td class="val"><?= $v('hospital_name') ?></td></tr>
  <tr><td class="lbl">Claim Number</td>                        <td class="val"><?= $v('claim_number') ?></td></tr>
  <tr><td class="lbl">Claimed Amount</td>                      <td class="val">Rs. <?= number_format((float)($project['claim_amount']??0),0) ?>/-</td></tr>
  <tr><td class="lbl">Location</td>                            <td class="val"><?= $v('hospital_address') ?></td></tr>
  <tr><td class="lbl">Date of Investigation Assigned</td>      <td class="val"><?= $assign_date ?></td></tr>
  <tr><td class="lbl">Date of Report Sent</td>                 <td class="val"><?= $report_date ?></td></tr>
  <tr><td class="lbl">Triggers</td>                            <td class="val"><?= $v('diagnosis','CLAIM GENUINITY') ?></td></tr>
</table>

<!-- ═══ S2: INSURED DETAILED ═══ -->
<div class="sec">Insured &amp; Related Details – In Depth</div>
<table>
  <tr><td class="lbl">Name of the Insured</td>                                           <td class="val"><?= $v('title') ?></td></tr>
  <tr><td class="lbl">Name of the Patient (Insured)</td>                                 <td class="val"><?= $v('title') ?></td></tr>
  <tr><td class="lbl">Relation with Insured</td>                                         <td class="val"><?= $v('patient_relation') ?></td></tr>
  <tr><td class="lbl">Occupation / Corporate of the Insured</td>                         <td class="val"><?= $v('insured_occupation') ?></td></tr>
  <tr><td class="lbl">Insured's Residence Distance from Hospital</td>                    <td class="val"><?= $v('residence_distance') ?></td></tr>
  <tr><td class="lbl">Reason for Selecting this Particular Hospital</td>                 <td class="val"><?= $v('hospital_selection_reason') ?></td></tr>
  <tr><td class="lbl">Other Good Hospitals Between Residence &amp; Admitted Hospital</td><td class="val"><?= $v('other_hospitals_nearby') ?></td></tr>
  <tr><td class="lbl">Details of Family Physician / First Consulting Doctor</td>         <td class="val"><?= $v('family_physician') ?></td></tr>
  <tr><td class="lbl">Other Policies with US &amp; Other Insurer (apart from existing)</td><td class="val"><?= $v('other_policies') ?></td></tr>
  <tr><td class="lbl">Details of Earlier Claims (Us &amp; Other Insurers)</td>          <td class="val"><?= $v('earlier_claims') ?></td></tr>
</table>

<!-- ═══ S3: HOSPITAL & DOCTOR ═══ -->
<div class="sec">Hospital &amp; Doctor Related</div>
<table>
  <tr><td class="lbl">No. of In-Patient Beds in Hospital</td>              <td class="val"><?= $v('hospital_beds') ?></td></tr>
  <tr><td class="lbl">Hospital Registration Number</td>                    <td class="val"><?= $v('hospital_reg_no') ?></td></tr>
  <tr><td class="lbl">Operation Theater Attached</td>                      <td class="val"><?= $v('hospital_ot') ?></td></tr>
  <tr><td class="lbl">Pathology, Medical Store &amp; Basic Facilities</td> <td class="val"><?= $v('hospital_facilities') ?></td></tr>
  <tr><td class="lbl">Qualification of the Doctor</td>                     <td class="val"><?= $v('doctor_qualification') ?></td></tr>
  <tr><td class="lbl">Name of Treating Doctor</td>                         <td class="val"><?= $v('treating_doctor') ?></td></tr>
  <tr><td class="lbl">Different Room Rent Tariff Options Available</td>    <td class="val"><?= $v('room_rent_tariff') ?></td></tr>
</table>

<!-- ═══ S4: DISEASE RELATED ═══ -->
<div class="sec">For Disease Related Claims</div>
<table>
  <tr><td class="lbl">Main Complaints on Admission</td>                               <td class="val"><?= $v('main_complaints') ?></td></tr>
  <tr><td class="lbl">Date of Hospitalization</td>                                    <td class="val"><?= $doa ?></td></tr>
  <tr><td class="lbl">Date of Discharge</td>                                          <td class="val"><?= $dod ?></td></tr>
  <tr><td class="lbl">Diagnosis</td>                                                  <td class="val"><?= $v('diagnosis') ?></td></tr>
  <tr><td class="lbl">Scar Mark Verification (if Surgery)</td>                        <td class="val"><?= $v('scar_mark_verification') ?></td></tr>
  <tr><td class="lbl">Line of Treatment in Order &amp; Correlating with Diagnosis</td><td class="val"><?= $v('treatment_line') ?></td></tr>
  <tr><td class="lbl">Name of Surgeon &amp; Anesthetist</td>                         <td class="val"><?= $v('surgeon_name') ?></td></tr>
  <tr><td class="lbl">Stay Justified / Prolonged – Reason if Prolonged</td>          <td class="val"><?= $v('stay_justified') ?></td></tr>
  <tr><td class="lbl">Previous History of Similar Complaints</td>                     <td class="val"><?= $v('previous_history') ?></td></tr>
  <tr><td class="lbl">Diagnostic Tests in Line with Diagnosis</td>                    <td class="val"><?= $v('diagnostics_in_line') ?></td></tr>
</table>

<!-- ═══ S5: ACCIDENT RELATED ═══ -->
<div class="sec">Accident Related Claims</div>
<table>
  <tr><td class="lbl">Exact Date &amp; Time of Accident / Injury</td>       <td class="val"><?= $v('accident_datetime') ?></td></tr>
  <tr><td class="lbl">Narration of How the Incident / Injury Happened</td>  <td class="val"><?= $v('accident_narration') ?></td></tr>
  <tr><td class="lbl">PA Policy with Us or Any Other Insurer</td>           <td class="val"><?= $v('pa_policy') ?></td></tr>
  <tr><td class="lbl">Scar Mark Verification</td>                           <td class="val"><?= $v('scar_mark_verification') ?></td></tr>
  <tr><td class="lbl">Any Alcoholism Factor Noted</td>                      <td class="val"><?= $v('alcoholism_noted') ?></td></tr>
  <tr><td class="lbl">Accident / Assault / Suicidal Attempt</td>            <td class="val"><?= $v('accident_type') ?></td></tr>
</table>

<!-- ═══ S6: VERIFICATION – HOSPITAL ═══ -->
<div class="sec">Verification from Hospital</div>
<table>
  <tr><td class="lbl">Indoor Register Verified for Insured's Entry</td><td class="val"><?= $v('indoor_register_verified') ?></td></tr>
  <tr><td class="lbl">Any Overwriting in Dates</td>                    <td class="val"><?= $v('overwritten_dates') ?></td></tr>
  <tr><td class="lbl">IPD Papers Written in Single Stretch</td>        <td class="val"><?= $v('ipd_single_stretch') ?></td></tr>
</table>

<!-- ═══ S7: VERIFICATION – MEDICAL STORE ═══ -->
<div class="sec">Verification from Medical Store</div>
<table>
  <tr><td class="lbl">Medicines in IPD Papers Match Medical Bills</td><td class="val"><?= $v('medicine_matches_bills') ?></td></tr>
  <tr><td class="lbl">Any Inflation of Bills</td>                     <td class="val"><?= $v('bill_inflation') ?></td></tr>
</table>

<!-- ═══ S8: VERIFICATION – LAB ═══ -->
<div class="sec">Verification from Lab</div>
<table>
  <tr><td class="lbl">Lab Register Verified for Insured Entry</td><td class="val"><?= $v('lab_register_verified') ?></td></tr>
  <tr><td class="lbl">Any Inflation of Bills</td>                  <td class="val"><?= $v('lab_bill_inflation') ?></td></tr>
</table>

<!-- ═══ S9: OBSERVATION ═══ -->
<div class="sec">Observation of the Claims</div>
<?php
  $icp = trim($project['icp_observation']    ?? '');
  $pat = trim($project['patient_observation'] ?? '');
  $doc = trim($project['doctor_observation']  ?? '');
  $adm = trim($project['admission_genuinely']  ?? '');
  $ppa = trim($project['patient_paid_amount']  ?? '');
  $amc = trim($project['amount_confirmation']  ?? '');
?>
<table>
  <tr>
    <td class="lbl" style="width:22%;font-weight:700;vertical-align:top">Visit to Hospital</td>
    <td class="val-text"><?= $icp ? nl2br(htmlspecialchars($icp, ENT_QUOTES)) : 'As per ICP the insured was admitted in hospital. Field observation details to be updated after visit.' ?></td>
  </tr>
  <tr>
    <td class="lbl" style="width:22%;font-weight:700;vertical-align:top">Patient Part</td>
    <td class="val-text"><?= $pat ? nl2br(htmlspecialchars($pat, ENT_QUOTES)) : 'Na' ?></td>
  </tr>
  <tr>
    <td class="lbl" style="width:22%;font-weight:700;vertical-align:top">Treating Doctor Part</td>
    <td class="val-text"><?= $doc ? nl2br(htmlspecialchars($doc, ENT_QUOTES)) : 'Na' ?></td>
  </tr>
  <?php if ($adm || $ppa || $amc): ?>
  <tr>
    <td class="lbl" style="width:22%;font-weight:700;vertical-align:top">Summary</td>
    <td class="val-text">
      <ol style="margin:2px 0 2px 18px">
        <?php if ($adm): ?><li>Admission Genuinely :&#8211; <strong><?= htmlspecialchars(strtoupper($adm)) ?></strong></li><?php endif; ?>
        <?php if ($ppa): ?><li>Patient Paid Amount :&#8211; <strong><?= htmlspecialchars($ppa) ?></strong></li><?php endif; ?>
        <?php if ($amc): ?><li>Amount Confirmation :&#8211; <?= htmlspecialchars($amc) ?></li><?php endif; ?>
      </ol>
    </td>
  </tr>
  <?php endif; ?>
</table>

<!-- ═══ S10: INVESTIGATOR COMMENTS ═══ -->
<div class="sec">Investigator Comments &amp; Advice</div>
<?php
  $inv = trim($project['investigator_comments'] ?? '');
  if (!$inv) $inv = "Based on comprehensive on-site verification, the claim appears genuine. The hospital facilities and doctor qualifications have been verified. All medicines billed are correlating with the treatment plan. No evidence of bill inflation or stay prolongation was observed.\n\nICP verified and found in order.\n\nMedical and lab reports are verified.";
?>
<table>
  <tr>
    <td class="val-text" colspan="2" style="padding:10px 14px;text-align:justify"><?= nl2br(htmlspecialchars($inv, ENT_QUOTES)) ?></td>
  </tr>
</table>

<!-- ═══ CONCLUSION ═══ -->
<div class="conc">
  <p><strong>Conclusion:</strong><br><br><?= nl2br(htmlspecialchars(trim($project['closure_conclusion'] ?? '') ?: 'On the basis of above findings, insurer may decide the fate of the claim as per the terms and conditions of the policy issued.', ENT_QUOTES)) ?></p>

  <div class="disc">
    <strong>Disclaimer:</strong> This Investigation Report is issued without prejudice. It is strictly confidential and is subject to the Terms &amp; Conditions of the Insurance Policy under which the subject claim is lodged.
  </div>
</div>

<!-- ═══ RECOMMENDATION BANNER ═══ -->
<div class="reco">
  Recommendation &nbsp;&#124;&nbsp;
  <?= !empty($project['admission_genuinely']) ? strtoupper(htmlspecialchars($project['admission_genuinely'])) : 'GENUINE' ?>
</div>

<!-- ═══ FOOTER / SIGNATURE ═══ -->
<div class="footer-wrap">
  <img class="footer-wm" src="<?= img_base64('assets/images/auth_seal.png') ?>" alt="">
  <table class="ftbl">
    <thead>
      <tr><th colspan="3">Report Prepared By</th></tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <span class="flbl">Name</span>
          <div class="fval"><?= htmlspecialchars($project['officer_name'] ?? 'Authorized Investigator') ?></div>
        </td>
        <td>
          <span class="flbl">Designation</span>
          <div class="fval">Investigation Officer</div>
        </td>
        <td>
          <span class="flbl">Mobile No.</span>
          <div class="fval"><?= htmlspecialchars($project['investigator_phone'] ?? $c_phone ?: '—') ?></div>
        </td>
      </tr>
      <tr>
        <td>
          <span class="flbl">Email</span>
          <div class="fval" style="text-transform:lowercase"><?= htmlspecialchars($project['investigator_email'] ?? $c_email ?: '—') ?></div>
        </td>
        <td colspan="2">
          <span class="flbl">Agency Stamp &amp; Signature</span>
          <div class="sig-area">
            <img src="<?= img_base64('assets/images/auth_seal.png') ?>" alt="Seal" style="height:40px;opacity:.85">
            <span class="sig-name"><?= htmlspecialchars($project['officer_name'] ?? 'Investigator') ?></span>
          </div>
        </td>
      </tr>
      <tr>
        <td>
          <span class="flbl">Date</span>
          <div class="fval"><?= $report_date ?></div>
        </td>
        <td>
          <span class="flbl">Place</span>
          <div class="fval"><?= htmlspecialchars(strtoupper($project['city'] ?? 'N/A')) ?></div>
        </td>
        <td>
          <span class="flbl">Report / Claim No.</span>
          <div class="fval"><?= htmlspecialchars($project['claim_number'] ?? '') ?></div>
        </td>
      </tr>
    </tbody>
  </table>
</div>

</div><!-- .page -->
</body>
</html>
