<?php
require_once 'app_init.php';
require_once 'auth.php';

$pid = $_GET['id'] ?? 0;
if ($pid == 0) {
    header('Location: projects.php');
    exit;
}

// Fetch Project Details
$stmt = $pdo->prepare("SELECT id, title, claim_number, status, scope, assigned_to, team_manager_id, manager_id, pt_fo_id, hp_fo_id, other_fo_id, assigned_doctor_id FROM projects WHERE id = ?");
$stmt->execute([$pid]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Access Control for assigned staff
$curr_role = $_SESSION['role'] ?? '';
$is_ho_staff = in_array($curr_role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager', 'hod', 'team_manager', 'fo_manager']);
$is_assigned_user = in_array($_SESSION['user_id'], array_filter([
    $project['assigned_to'] ?? null,
    $project['team_manager_id'] ?? null,
    $project['manager_id'] ?? null,
    $project['pt_fo_id'] ?? null,
    $project['hp_fo_id'] ?? null,
    $project['other_fo_id'] ?? null,
    $project['assigned_doctor_id'] ?? null
]));

if (!$is_ho_staff && !$is_assigned_user) {
    header("Location: projects.php?error=access_denied");
    exit;
}

$error_message = '';
$success_message = '';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $category = $_POST['category'] ?? 'General';
    $document_type = $_POST['document_type'] ?? '';
    $custom_label = $_POST['custom_label'] ?? '';
    
    if($document_type === 'Other' && !empty($custom_label)) {
        $document_type = $custom_label;
    } elseif(!empty($custom_label)) {
        $document_type .= " (" . $custom_label . ")";
    }
    $file = $_FILES['document'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $upload_dir = '../uploads/documents/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_name = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($file['name']));
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                $lat = $_POST['lat'] ?? null;
                $lng = $_POST['lng'] ?? null;
                $stmt = $pdo->prepare("INSERT INTO project_documents (project_id, uploaded_by, file_name, file_path, category, document_type, gps_lat, gps_long) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$pid, $_SESSION['user_id'], $file['name'], 'uploads/documents/' . $file_name, $category, $document_type, $lat, $lng])) {
                    $success_message = 'Document uploaded successfully.';
                    
                    // --- Routing Doc upload alert to Insurance ---
                    require_once '../includes/functions.php';
                    $admin_email = 'allocation@documantraa.in';
                    $subject = "Doc Uploaded: " . $project['claim_number'];
                    $body = "A new document ($document_type) has been uploaded for Claim # " . $project['claim_number'] . ".\n\nRegards,\nDocumantraa CMS";
                    if (function_exists('queue_email')) queue_email($pdo, $admin_email, $subject, $body, $_SESSION['user_id']);

                } else {
                    $error_message = 'Failed to save to database.';
                }
            } else {
                $error_message = 'Failed to move uploaded file.';
            }
        } else {
            $error_message = 'Invalid file type.';
        }
    } else {
        $error_message = 'Error uploading file.';
    }
}

// Handle FO Closer Action
if (isset($_POST['fo_closer'])) {
    $scope = $project['scope'] ?? '';
    $validation_error = "";
    
    // Check for Hospital Part requirement (Skip for HO Staff/Admins)
    if (!$is_ho_staff && (strpos($scope, 'Hospital') !== false || $scope == 'Full Investigation')) {
        $h_check = $pdo->prepare("SELECT COUNT(*) FROM project_documents WHERE project_id = ? AND category = 'Hospital Part'");
        $h_check->execute([$pid]);
        if ($h_check->fetchColumn() == 0) {
            $validation_error .= "Hospital Part evidence is missing. ";
        }
    }
    
    // Check for Patient Part requirement (Skip for HO Staff/Admins)
    if (!$is_ho_staff && (strpos($scope, 'Patient') !== false || $scope == 'Full Investigation')) {
        $p_check = $pdo->prepare("SELECT COUNT(*) FROM project_documents WHERE project_id = ? AND category = 'Patient Part'");
        $p_check->execute([$pid]);
        if ($p_check->fetchColumn() == 0) {
            $validation_error .= "Patient Part evidence is missing. ";
        }
    }

    if (!empty($validation_error)) {
        $error_message = "<b>Cannot Close:</b> " . $validation_error . " Please upload required documents for all assigned parts.";
    } else {
        $stmt = $pdo->prepare("UPDATE projects SET status = 'FO-Closed' WHERE id = ?");
        if ($stmt->execute([$pid])) {
            $success_message = 'Case marked as FO Closer successfully!';
            $project['status'] = 'FO-Closed'; 

            // --- Notification Logic ---
            require_once '../includes/functions.php';
            $admin_email = 'allocation@documantraa.in'; // Routing Closer Alert to Insurance
            
            $subject = "FO Closure Alert: " . $project['claim_number'];
            $body = "Dear Admin,\n\n"
                   . "The case with Claim # " . $project['claim_number'] . " (" . $project['title'] . ") has been marked as 'FO-Closed' by " . ($_SESSION['full_name'] ?? 'an investigator') . ".\n\n"
                   . "Please review the uploaded documents and proceed with the next steps.\n\n"
                   . "Regards,\nDocumantraa CMS";
            
            if (function_exists('queue_email')) {
                queue_email($pdo, $admin_email, $subject, $body, $_SESSION['user_id'] ?? null);
                // Also notify Team Manager if exists
                $tm_stmt = $pdo->prepare("SELECT u.email FROM projects p JOIN users u ON p.team_manager_id = u.id WHERE p.id = ?");
                $tm_stmt->execute([$pid]);
                $tm_email = $tm_stmt->fetchColumn();
                if ($tm_email) {
                    queue_email($pdo, $tm_email, $subject, $body, $_SESSION['user_id'] ?? null);
                }
            }
        } else {
            $error_message = 'Failed to mark case as FO Closer.';
        }
    }
}

// Handle Delete (Simple version)
if (isset($_POST['delete_doc_id']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin')) {
    $doc_id = $_POST['delete_doc_id'];
    $stmt = $pdo->prepare("SELECT file_path FROM project_documents WHERE id = ?");
    $stmt->execute([$doc_id]);
    $doc = $stmt->fetch();
    
    if ($doc) {
        @unlink('../' . $doc['file_path']);
        $pdo->prepare("DELETE FROM project_documents WHERE id = ?")->execute([$doc_id]);
        $success_message = 'Document deleted.';
    }
}

// Fetch Documents
$stmt = $pdo->prepare("SELECT pd.*, u.full_name as uploader_name FROM project_documents pd JOIN users u ON pd.uploaded_by = u.id WHERE pd.project_id = ? ORDER BY pd.uploaded_at DESC");
$stmt->execute([$pid]);
$documents = $stmt->fetchAll();

// Fetch counts for validation feedback
$stmt_counts = $pdo->prepare("SELECT category, COUNT(*) as cnt FROM project_documents WHERE project_id = ? GROUP BY category");
$stmt_counts->execute([$pid]);
$counts_map = $stmt_counts->fetchAll(PDO::FETCH_KEY_PAIR);

$h_req = (strpos($project['scope'], 'Hospital') !== false || $project['scope'] == 'Full Investigation');
$p_req = (strpos($project['scope'], 'Patient') !== false || $project['scope'] == 'Full Investigation');
$h_count = $counts_map['Hospital Part'] ?? 0;
$p_count = $counts_map['Patient Part'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - <?= htmlspecialchars($project['claim_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <a href="project_details.php?id=<?= $pid ?>" class="text-main"><i class="bi bi-arrow-left fs-4"></i></a>
            <span class="fw-bold ms-2">Documents</span>
        </div>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section d-none d-lg-flex">
            <div class="header-inner">
                <div class="d-flex align-items-center gap-3">
                    <a href="project_details.php?id=<?= $pid ?>" class="btn-white-v2 rounded-circle d-flex align-items-center justify-content-center shadow-sm" style="width: 42px; height: 42px; min-width: 42px; padding: 0;">
                        <i class="bi bi-arrow-left text-dark"></i>
                    </a>
                    <div>
                        <h1 class="mb-0" style="font-size: 1.5rem;">Project Documents</h1>
                        <p class="text-muted mb-0 small">Claim #<?= htmlspecialchars($project['claim_number']) ?> &middot; <?= htmlspecialchars($project['title']) ?></p>
                    </div>
                </div>
                <?php if ($is_ho_staff || $curr_role == 'doctor'): ?>
                <div class="d-flex gap-2">
                     <a href="visit_report.php?id=<?= $pid ?>" target="_blank" class="btn-v2 btn-white-v2 border py-2 px-3 shadow-sm">
                        <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> Visit Photo Report (PDF)
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="app-container">
            <?php if ($error_message): ?>
            <div class="alert alert-danger shadow-sm border-0"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            <?php if ($success_message): ?>
            <div class="alert alert-success shadow-sm border-0"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="app-card">
                        <h6 class="card-title-v2 mb-3"><i class="bi bi-cloud-arrow-up me-2"></i>Upload File</h6>
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="stat-label mb-1">Select Part</label>
                                <select name="category" id="main_group" class="form-select input-v2" required>
                                    <option value="">-- Select --</option>
                                    <option value="General">GENERAL</option>
                                    <?php if ($is_ho_staff || $curr_role == 'doctor'): ?>
                                    <option value="Investigation">INVESTIGATION</option>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $is_hp_fo = (($project['hp_fo_id'] ?? 0) == $_SESSION['user_id']);
                                    $is_pt_fo = (($project['pt_fo_id'] ?? 0) == $_SESSION['user_id']);
                                    $is_other_fo = (($project['other_fo_id'] ?? 0) == $_SESSION['user_id']);
                                    $is_legacy_main = (($project['assigned_to'] ?? 0) == $_SESSION['user_id']);
                                    
                                    $show_h = $is_ho_staff || $is_hp_fo || $is_legacy_main;
                                    $show_p = $is_ho_staff || $is_pt_fo || $is_legacy_main;
                                    $show_o = $is_ho_staff || $is_other_fo || $is_legacy_main;
                                    ?>
                                    
                                    <?php if ($show_h): ?>
                                    <option value="Hospital Part">HOSPITAL PART</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($show_p): ?>
                                    <option value="Patient Part">PATIENT PART</option>
                                    <?php endif; ?>
                                    
                                    <?php if ($show_o): ?>
                                    <option value="Other Part">OTHER PART</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3" id="sub_category_container" style="display: none;">
                                <label class="stat-label mb-1">Document Type</label>
                                <select name="document_type" id="sub_category" class="form-select input-v2" required>
                                    <option value="">-- Select Specific Document --</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="stat-label mb-1">Custom Label / Remark</label>
                                <input type="text" name="custom_label" id="doc_type_input" class="form-control input-v2" placeholder="e.g. Adhaar Card, ICP papers...">
                            </div>
                            <div class="mb-3">
                                <label class="stat-label mb-1">Select File</label>
                                <input type="file" name="document" id="document_file" class="form-control input-v2" accept="image/*,application/pdf,.doc,.docx" required>
                                <small class="text-muted" style="font-size: 0.65rem;">GPS Coordinates will be tagged automatically.</small>
                            </div>
                            <input type="hidden" name="lat" id="gps_lat">
                            <input type="hidden" name="lng" id="gps_lng">
                            <button type="submit" class="btn-v2 btn-primary-v2 w-100" id="uploadBtn">Upload Document</button>
                        </form>
                    </div>

                    <?php if ($project['status'] != 'FO-Closed' && $project['status'] != 'Completed'): ?>
                    <div class="app-card border-0 shadow-sm mt-3" style="background: linear-gradient(135deg, #2563eb, #1e40af); color: white;">
                        <div class="p-4 text-center">
                            <i class="bi bi-file-earmark-check fs-1 mb-2 d-block"></i>
                            <h6 class="fw-bold mb-1">Finalize Visit?</h6>
                            <p class="small opacity-75 mb-4">Once all photos are uploaded, mark as FO Closer.</p>
                            
                            <form method="POST" onsubmit="return confirm('Done with all uploads? This will mark the case as FO Closer.');">
                                <div class="mb-3 text-start bg-white bg-opacity-10 p-2 rounded">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span>Hospital Evidence</span>
                                        <span class="badge <?= ($h_count > 0) ? 'bg-success' : ($h_req ? 'bg-danger' : 'bg-secondary') ?>"><?= $h_count ?> uploads</span>
                                    </div>
                                    <div class="d-flex justify-content-between small">
                                        <span>Patient Evidence</span>
                                        <span class="badge <?= ($p_count > 0) ? 'bg-success' : ($p_req ? 'bg-danger' : 'bg-secondary') ?>"><?= $p_count ?> uploads</span>
                                    </div>
                                </div>
                                <input type="hidden" name="fo_closer" value="1">
                                <button type="submit" class="btn btn-light w-100 py-3 fw-bold text-primary shadow" style="background-color: #ffffff !important; color: #1e40af !important; border:none; border-radius: 12px;">
                                    <i class="bi bi-check-circle-fill me-2"></i> Mark as FO Closer
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="app-card border-0 shadow-sm mt-3 bg-success-subtle text-success">
                         <div class="p-4 text-center">
                            <i class="bi bi-check-circle-fill fs-2 mb-2 d-block text-success"></i> 
                            <span class="fw-bold">FO Closer Processed</span>
                            <p class="small text-muted mb-0 mt-1">Visit is closed for further investigation.</p>
                         </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-8">
                    <div class="app-card min-vh-50">
                        <h6 class="card-title-v2 mb-4"><i class="bi bi-files me-2"></i>Uploaded Documents</h6>
                        
                        <?php if (empty($documents)): ?>
                            <div class="text-center p-5 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">No documents uploaded yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($documents as $doc): ?>
                                    <div class="list-group-item px-0 py-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center gap-3 w-75">
                                            <div class="bg-light p-2 rounded text-primary">
                                                <i class="bi <?= (strpos($doc['file_name'], '.pdf') !== false) ? 'bi-file-earmark-pdf-fill text-danger' : 'bi-image text-primary' ?> fs-4"></i>
                                            </div>
                                            <div class="text-truncate">
                                                <h6 class="mb-0 fw-bold text-main" style="font-size: 0.95rem;">
                                                    <a href="../<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-decoration-none">
                                                        <?= htmlspecialchars($doc['document_type']) ?>
                                                    </a>
                                                </h6>
                                                <small class="text-muted d-block text-truncate">
                                                    <span class="badge bg-secondary-subtle text-secondary me-2"><?= htmlspecialchars($doc['category']) ?></span>
                                                    Uploaded on <?= date('d M Y h:i A', strtotime($doc['uploaded_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <a href="../<?= htmlspecialchars($doc['file_path']) ?>" download="<?= htmlspecialchars($doc['file_name']) ?>" class="btn btn-link text-primary p-0" title="Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
                                            <form method="POST" onsubmit="return confirm('Delete this document?');">
                                                <input type="hidden" name="delete_doc_id" value="<?= $doc['id'] ?>">
                                                <button type="submit" class="btn btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="project_details.php?id=<?= $pid ?>" class="bottom-nav-item">
            <i class="bi bi-arrow-left"></i>
            <span>Back</span>
        </a>
        <a href="#" class="bottom-nav-item active">
            <i class="bi bi-cloud-upload"></i>
            <span>Docs</span>
        </a>
        <div style="position: relative; top: -20px;">
            <a href="field_visits.php?search=<?= urlencode($project['claim_number']) ?>" class="bottom-nav-icon-main">
                <i class="bi bi-geo-alt-fill"></i>
            </a>
        </div>
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const mainGroup = document.getElementById('main_group');
        const subCategoryContainer = document.getElementById('sub_category_container');
        const subCategory = document.getElementById('sub_category');
        const docTypeInput = document.getElementById('doc_type_input');
        const fileInput = document.getElementById('document_file');
        
        const subOptions = {
            'General': [
                { val: 'General', label: 'General Document' },
                { val: 'Other', label: 'Other Document' }
            ],
            'Investigation': [
                { val: 'Investigation', label: 'Investigation Report' },
                { val: 'Pharmacy Visit Photo', label: 'Pharmacy Visit Photo' }
            ],
            'Hospital Part': [
                { val: 'Hospital Visit Photo', label: 'Hospital Visit Evidence' },
                { val: 'Hospital Selfie', label: 'Hospital Selfie' },
                { val: 'Hospital Reg Certificate', label: 'Registration Certificate' },
                { val: 'Hospital Tariff', label: 'Hospital Tariff' },
                { val: 'Case Sheet', label: 'Case Sheet' },
                { val: 'OPD-IPD Entry', label: 'OPD / IPD ENTRY' },
                { val: 'Radiology-Pathology', label: 'RADIOLOGY / PATHOLOGY' },
                { val: 'Lab Reports', label: 'Lab Reports' },
                { val: 'TDQ-TDC', label: 'TDQ / TDC' },
                { val: 'Admission Confirmation', label: 'Admission Confirmation Form' }
            ],
            'Patient Part': [
                { val: 'Patient Visit Photo', label: 'Patient Visit Evidence' },
                { val: 'Patient Selfie', label: 'Patient Selfie' },
                { val: 'IVR Form', label: 'IVR Form' },
                { val: 'Discharge Summary', label: 'DISCHARGE SUMMARY' },
                { val: 'Bills', label: 'BILLS (Invoices)' },
                { val: 'Timeline', label: 'Timeline' },
                { val: 'FCP-History', label: 'FCP & Past History' },
                { val: 'Payment Records', label: 'Payment Records' }
            ],
            'Other Part': [
                { val: 'Aadhar', label: 'Aadhar Card' },
                { val: 'Pan', label: 'PAN Card' },
                { val: 'Bank Passbook', label: 'Bank Passbook' }
            ]
        };

        mainGroup.addEventListener('change', function() {
            const group = this.value;
            subCategory.innerHTML = '<option value="">-- Select Specific Document --</option>';
            
            if (group && subOptions[group]) {
                subOptions[group].forEach(opt => {
                    const el = document.createElement('option');
                    el.value = opt.val;
                    el.textContent = opt.label;
                    subCategory.appendChild(el);
                });
                subCategoryContainer.style.display = 'block';
            } else {
                subCategoryContainer.style.display = 'none';
            }
        });

        subCategory.addEventListener('change', function() {
            const val = this.value;
            docTypeInput.value = this.options[this.selectedIndex].text;
            
            if (val.includes('Selfie') || val.includes('Visit Photo')) {
                fileInput.setAttribute('capture', 'user');
                fileInput.setAttribute('accept', 'image/*');
                captureGPS();
            } else {
                fileInput.removeAttribute('capture');
                fileInput.setAttribute('accept', 'image/*,application/pdf');
            }
        });

        function captureGPS() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    document.getElementById('gps_lat').value = position.coords.latitude;
                    document.getElementById('gps_lng').value = position.coords.longitude;
                }, err => {
                    console.warn('GPS error:', err.message);
                }, { enableHighAccuracy: true });
            }
        }
        captureGPS();
    </script>
</body>
</html>
