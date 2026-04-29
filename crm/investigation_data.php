<?php
require_once 'app_init.php';
require_once 'auth.php';

$pid = intval($_GET['id'] ?? 0);
if (!$pid) { header('Location: projects.php'); exit; }

// Security Check: Only allow HO staff and managers to edit report data
$curr_role = $_SESSION['role'] ?? '';
$is_authorized = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'doctor', 'team_manager', 'fo_manager']);
if (!$is_authorized) {
    die("Access Denied: You do not have permission to edit investigation data.");
}

// Handle Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ir_data'])) {
    $fields = [
        // Insured detailed
        'patient_relation','insured_occupation','residence_distance',
        'hospital_selection_reason','other_hospitals_nearby','family_physician',
        'other_policies','earlier_claims',
        // Hospital & Doctor
        'hospital_beds','hospital_reg_no','hospital_ot','hospital_facilities',
        'doctor_qualification','treating_doctor','room_rent_tariff',
        // Disease
        'main_complaints','scar_mark_verification','treatment_line',
        'surgeon_name','stay_justified','previous_history','diagnostics_in_line',
        // Accident
        'accident_datetime','accident_narration','pa_policy',
        'alcoholism_noted','accident_type',
        // Verification
        'indoor_register_verified','overwritten_dates','ipd_single_stretch',
        'medicine_matches_bills','bill_inflation',
        'lab_register_verified','lab_bill_inflation',
        // Observation
        'icp_observation','patient_observation','doctor_observation',
        // Comments
        'investigator_comments','admission_genuinely','patient_paid_amount',
        'amount_confirmation','closure_conclusion','investigator_phone','investigator_email',
        // Core
        'claim_type','diagnosis','doa','dod','hospital_name','hospital_address',
    ];

    $set_parts = [];
    $values = [];
    foreach ($fields as $f) {
        $set_parts[] = "$f = ?";
        $val = $_POST[$f] ?? '';
        // Date fields: store empty as null
        if (in_array($f, ['doa','dod']) && empty($val)) $val = null;
        $values[] = $val;
    }
    $values[] = $pid;

    try {
        $sql = "UPDATE projects SET " . implode(', ', $set_parts) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($values);
        $saved = true;
    } catch (PDOException $e) {
        $save_error = $e->getMessage();
    }
}

// Load claim
$stmt = $pdo->prepare("SELECT p.*, u.full_name as officer_name FROM projects p LEFT JOIN users u ON p.assigned_to = u.id WHERE p.id = ?");
$stmt->execute([$pid]);
$p = $stmt->fetch();
if (!$p) { echo "<h1>Claim not found</h1>"; exit; }

$v = fn($k) => htmlspecialchars($p[$k] ?? '', ENT_QUOTES);
$doa_val = !empty($p['doa']) ? date('Y-m-d\TH:i', strtotime($p['doa'])) : '';
$dod_val = !empty($p['dod']) ? date('Y-m-d\TH:i', strtotime($p['dod'])) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Investigation Data – <?= $v('claim_number') ?> – Documantraa</title>
<link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600;700&family=Lexend:wght@600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<link rel="stylesheet" href="css/app.css">
<style>
body { font-family: 'Jost', sans-serif; }
.ir-section-head {
    background: linear-gradient(90deg, #3D0C60, #6d28d9);
    color: #fff;
    font-weight: 700;
    font-size: .8rem;
    text-transform: uppercase;
    letter-spacing: .08em;
    padding: 8px 14px;
    border-radius: 6px;
    margin: 24px 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.field-group { margin-bottom: 14px; }
.field-group label { font-size: .8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; display: block; }
.field-group input, .field-group select, .field-group textarea {
    width: 100%; border: 1.5px solid #e2e8f0; border-radius: 8px;
    padding: 9px 12px; font-family: 'Jost', sans-serif; font-size: .9rem; color: #111;
    background: #fafafa; transition: border-color .2s;
}
.field-group input:focus, .field-group select:focus, .field-group textarea:focus {
    outline: none; border-color: #7c3aed; background: #fff; box-shadow: 0 0 0 3px rgba(124,58,237,.08);
}
.field-group textarea { resize: vertical; min-height: 80px; }
.yn-group { display: flex; gap: 8px; flex-wrap: wrap; }
.yn-btn { padding: 6px 18px; border: 1.5px solid #e2e8f0; border-radius: 20px; font-size: .85rem; font-weight: 600; cursor: pointer; transition: all .2s; background: #fff; color: #475569; }
.yn-btn.active-yes { background: #dcfce7; border-color: #16a34a; color: #16a34a; }
.yn-btn.active-no  { background: #fee2e2; border-color: #dc2626; color: #dc2626; }
.yn-btn.active-na  { background: #f1f5f9; border-color: #94a3b8; color: #475569; }
.sticky-save { position: fixed; bottom: 20px; right: 20px; z-index: 999; }
.claim-pill { display: inline-flex; align-items: center; gap: 6px; background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 20px; padding: 4px 12px; font-size: .8rem; font-weight: 600; color: #475569; }
</style>
</head>
<body>
<div class="mobile-top-bar d-lg-none">
    <div class="d-flex align-items-center gap-2">
        <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height:32px">
    </div>
    <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
        <i class="bi bi-list" style="font-size:1.75rem;color:var(--text-main)"></i>
    </button>
</div>

<?php include 'includes/sidebar.php'; ?>

<div class="main-content-wrapper">
    <header class="app-header-section">
        <div class="header-inner">
            <div>
                <h1 style="font-size:1.5rem;color:var(--text-main);font-family:'Lexend',sans-serif">
                    <i class="bi bi-clipboard2-pulse me-2 text-purple" style="color:#7c3aed"></i>
                    Investigation Report Data
                </h1>
                <div class="d-flex gap-2 flex-wrap mt-1">
                    <span class="claim-pill"><i class="bi bi-hash"></i><?= $v('claim_number') ?></span>
                    <span class="claim-pill"><i class="bi bi-person"></i><?= $v('title') ?></span>
                    <span class="claim-pill"><i class="bi bi-hospital"></i><?= $v('hospital_name') ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="investigation_report.php?id=<?= $pid ?>" target="_blank" class="btn-v2 btn-white-v2">
                    <i class="bi bi-eye"></i> Preview Report
                </a>
                <a href="projects.php" class="btn-v2 btn-white-v2"><i class="bi bi-arrow-left"></i> Back</a>
            </div>
        </div>
    </header>

    <div class="app-container">

        <?php if (!empty($saved)): ?>
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="bi bi-check-circle-fill me-2"></i> Investigation data saved successfully!
            <a href="investigation_report.php?id=<?= $pid ?>" target="_blank" class="ms-3 fw-bold">View Report →</a>
        </div>
        <?php endif; ?>
        <?php if (!empty($save_error)): ?>
        <div class="alert alert-danger mb-4"><?= htmlspecialchars($save_error) ?></div>
        <?php endif; ?>

        <form method="POST">
        <input type="hidden" name="save_ir_data" value="1">

        <!-- ── BASIC CLAIM INFO ── -->
        <div class="ir-section-head"><i class="bi bi-file-earmark-text"></i> Basic Claim Info</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="field-group">
                    <label>Type of Claim</label>
                    <select name="claim_type">
                        <?php foreach(['REIMBURSEMENT','CASHLESS','PERSONAL ACCIDENT','GROUP HEALTH'] as $ct): ?>
                        <option <?= strtoupper($p['claim_type']??'') === $ct ? 'selected':'' ?>><?= $ct ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Hospital Name</label>
                    <input type="text" name="hospital_name" value="<?= $v('hospital_name') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Location / Hospital Address</label>
                    <input type="text" name="hospital_address" value="<?= $v('hospital_address') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Diagnosis / Trigger</label>
                    <input type="text" name="diagnosis" value="<?= $v('diagnosis') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Date &amp; Time of Admission (DOA)</label>
                    <input type="datetime-local" name="doa" value="<?= $doa_val ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Date &amp; Time of Discharge (DOD)</label>
                    <input type="datetime-local" name="dod" value="<?= $dod_val ?>">
                </div>
            </div>
        </div>

        <!-- ── INSURED DETAILS ── -->
        <div class="ir-section-head"><i class="bi bi-person-vcard"></i> Insured &amp; Related Details</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="field-group">
                    <label>Relation with Insured</label>
                    <select name="patient_relation">
                        <?php foreach(['SELF','SPOUSE','SON','DAUGHTER','FATHER','MOTHER','OTHER'] as $r): ?>
                        <option <?= strtoupper($p['patient_relation']??'') === $r ? 'selected':'' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Occupation / Corporate of Insured</label>
                    <input type="text" name="insured_occupation" value="<?= $v('insured_occupation') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Residence Distance from Hospital</label>
                    <input type="text" name="residence_distance" placeholder="e.g. 2 KM / NA" value="<?= $v('residence_distance') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Reason for Selecting this Particular Hospital</label>
                    <input type="text" name="hospital_selection_reason" value="<?= $v('hospital_selection_reason') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Other Good Hospitals Nearby (Yes/No/NA)</label>
                    <input type="text" name="other_hospitals_nearby" value="<?= $v('other_hospitals_nearby') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Family Physician / First Consulting Doctor</label>
                    <input type="text" name="family_physician" value="<?= $v('family_physician') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Other Policies with Us &amp; Other Insurer</label>
                    <input type="text" name="other_policies" placeholder="e.g. NONE / Policy No." value="<?= $v('other_policies') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Earlier Claims from Us &amp; Other Insurers</label>
                    <input type="text" name="earlier_claims" placeholder="e.g. NO / Claim details" value="<?= $v('earlier_claims') ?>">
                </div>
            </div>
        </div>

        <!-- ── HOSPITAL & DOCTOR ── -->
        <div class="ir-section-head"><i class="bi bi-hospital"></i> Hospital &amp; Doctor Related</div>
        <div class="row g-3">
            <div class="col-md-3">
                <div class="field-group">
                    <label>No. of In-Patient Beds</label>
                    <input type="text" name="hospital_beds" placeholder="e.g. 30 BEDS" value="<?= $v('hospital_beds') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Hospital Registration Number</label>
                    <input type="text" name="hospital_reg_no" value="<?= $v('hospital_reg_no') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Operation Theater Attached (Yes/No)</label>
                    <input type="text" name="hospital_ot" value="<?= $v('hospital_ot') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Room Rent Tariff Options Available</label>
                    <input type="text" name="room_rent_tariff" placeholder="YES / NO" value="<?= $v('room_rent_tariff') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Pathology, Medical Store &amp; Basic Facilities</label>
                    <input type="text" name="hospital_facilities" value="<?= $v('hospital_facilities') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Qualification of Doctor</label>
                    <input type="text" name="doctor_qualification" placeholder="e.g. MBBS / MD" value="<?= $v('doctor_qualification') ?>">
                </div>
            </div>
            <div class="col-md-3">
                <div class="field-group">
                    <label>Name of Treating Doctor</label>
                    <input type="text" name="treating_doctor" value="<?= $v('treating_doctor') ?>">
                </div>
            </div>
        </div>

        <!-- ── DISEASE RELATED ── -->
        <div class="ir-section-head"><i class="bi bi-activity"></i> For Disease Related Claims</div>
        <div class="row g-3">
            <div class="col-12">
                <div class="field-group">
                    <label>Main Complaints on Admission</label>
                    <textarea name="main_complaints" rows="2"><?= $v('main_complaints') ?></textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Scar Mark Verification (if Surgery)</label>
                    <input type="text" name="scar_mark_verification" placeholder="NA / VERIFIED" value="<?= $v('scar_mark_verification') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Line of Treatment in Order &amp; Correlating</label>
                    <input type="text" name="treatment_line" placeholder="YES / NO" value="<?= $v('treatment_line') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Name of Surgeon &amp; Anesthetist</label>
                    <input type="text" name="surgeon_name" placeholder="NA / Dr. Name" value="<?= $v('surgeon_name') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Stay Justified / Prolonged – Reason</label>
                    <input type="text" name="stay_justified" placeholder="JUSTIFIED / reason if prolonged" value="<?= $v('stay_justified') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Previous History of Similar Complaints</label>
                    <input type="text" name="previous_history" placeholder="YES / NO / NA" value="<?= $v('previous_history') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Diagnostic Tests in Line with Diagnosis</label>
                    <input type="text" name="diagnostics_in_line" placeholder="YES / NO" value="<?= $v('diagnostics_in_line') ?>">
                </div>
            </div>
        </div>

        <!-- ── ACCIDENT RELATED ── -->
        <div class="ir-section-head"><i class="bi bi-exclamation-triangle"></i> Accident Related Claims</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="field-group">
                    <label>Exact Date &amp; Time of Accident / Injury</label>
                    <input type="text" name="accident_datetime" placeholder="NA or DD/MM/YYYY HH:MM" value="<?= $v('accident_datetime') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>PA Policy with Us or Other Insurer</label>
                    <input type="text" name="pa_policy" placeholder="YES / NO / NA" value="<?= $v('pa_policy') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Any Alcoholism Factor Noted</label>
                    <input type="text" name="alcoholism_noted" placeholder="YES / NO / NA" value="<?= $v('alcoholism_noted') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Narration of How the Incident / Injury Happened</label>
                    <textarea name="accident_narration" rows="2"><?= $v('accident_narration') ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Accident / Assault / Suicidal Attempt</label>
                    <input type="text" name="accident_type" placeholder="ACCIDENT / ASSAULT / SUICIDAL / NA" value="<?= $v('accident_type') ?>">
                </div>
            </div>
        </div>

        <!-- ── VERIFICATIONS ── -->
        <div class="ir-section-head"><i class="bi bi-patch-check"></i> Verifications (Hospital / Store / Lab)</div>
        <div class="row g-3">
            <div class="col-md-4">
                <div class="field-group">
                    <label>Indoor Register Verified for Insured Entry</label>
                    <input type="text" name="indoor_register_verified" placeholder="YES / NOT SHOWN / NO" value="<?= $v('indoor_register_verified') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Any Overwriting in Dates (Hospital)</label>
                    <input type="text" name="overwritten_dates" placeholder="YES / NO" value="<?= $v('overwritten_dates') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>IPD Papers Written in Single Stretch</label>
                    <input type="text" name="ipd_single_stretch" placeholder="YES / NO" value="<?= $v('ipd_single_stretch') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Medicines in IPD Match Medical Bills</label>
                    <input type="text" name="medicine_matches_bills" placeholder="YES / NO" value="<?= $v('medicine_matches_bills') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Any Inflation of Bills (Medical Store)</label>
                    <input type="text" name="bill_inflation" placeholder="YES / NO" value="<?= $v('bill_inflation') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Lab Register Verified for Insured Entry</label>
                    <input type="text" name="lab_register_verified" placeholder="YES / NOT SHOWN / NO" value="<?= $v('lab_register_verified') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Any Inflation of Bills (Lab)</label>
                    <input type="text" name="lab_bill_inflation" placeholder="YES / NO" value="<?= $v('lab_bill_inflation') ?>">
                </div>
            </div>
        </div>

        <!-- ── OBSERVATION ── -->
        <div class="ir-section-head"><i class="bi bi-journal-text"></i> Observation of the Claims</div>
        <div class="row g-3">
            <div class="col-12">
                <div class="field-group">
                    <label>Visit to Hospital – ICP Observation</label>
                    <textarea name="icp_observation" rows="5" placeholder="As per ICP a ... yrs old ... patient was admitted..."><?= $v('icp_observation') ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Patient Part</label>
                    <textarea name="patient_observation" rows="4" placeholder="Na / Patient statement..."><?= $v('patient_observation') ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Treating Doctor Part</label>
                    <textarea name="doctor_observation" rows="4" placeholder="As per Treating doctor statement..."><?= $v('doctor_observation') ?></textarea>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Final Recommendation Verdict</label>
                    <select name="admission_genuinely">
                        <?php foreach(['GENUINE','SUSPICIOUS','INADMISSIBLE','QUERY'] as $r): ?>
                        <option <?= strtoupper($p['admission_genuinely']??'') === $r ? 'selected':'' ?>><?= $r ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Patient Paid Amount</label>
                    <input type="text" name="patient_paid_amount" placeholder="e.g. 177600/-" value="<?= $v('patient_paid_amount') ?>">
                </div>
            </div>
            <div class="col-md-4">
                <div class="field-group">
                    <label>Amount Confirmation</label>
                    <input type="text" name="amount_confirmation" placeholder="Paid and confirmed by hospital" value="<?= $v('amount_confirmation') ?>">
                </div>
            </div>
        </div>

        <!-- ── INVESTIGATOR COMMENTS ── -->
        <div class="ir-section-head"><i class="bi bi-chat-quote"></i> Investigator Comments &amp; Advice</div>
        <div class="row g-3">
            <div class="col-12">
                <div class="field-group">
                    <label>Investigator Comments &amp; Advice</label>
                    <textarea name="investigator_comments" rows="5" placeholder="Summarize your findings..."><?= $v('investigator_comments') ?></textarea>
                </div>
            </div>
            <div class="col-12">
                <div class="field-group">
                    <label>Conclusion</label>
                    <textarea name="closure_conclusion" rows="3" placeholder="On the basis of above findings, insurer may decide..."><?= $v('closure_conclusion') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── FOOTER CONTACT DETAILS ── -->
        <div class="ir-section-head"><i class="bi bi-person-lines-fill"></i> Report Prepared By (Contact Details)</div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="field-group">
                    <label>Investigator Mobile No.</label>
                    <input type="text" name="investigator_phone" placeholder="e.g. +91 755 883 4483" value="<?= $v('investigator_phone') ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="field-group">
                    <label>Investigator Email</label>
                    <input type="email" name="investigator_email" placeholder="e.g. support@documantraa.in" value="<?= htmlspecialchars($p['investigator_email'] ?? '', ENT_QUOTES) ?>">
                </div>
            </div>
        </div>

        <div class="mt-4 d-flex gap-3 justify-content-end pb-5">
            <a href="investigation_report.php?id=<?= $pid ?>" target="_blank" class="btn-v2 btn-white-v2">
                <i class="bi bi-eye"></i> Preview Report
            </a>
            <button type="submit" class="btn-v2 btn-primary-v2" style="min-width:160px">
                <i class="bi bi-save"></i> Save &amp; Update
            </button>
        </div>

        </form>
    </div><!-- .app-container -->
</div><!-- .main-content-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
