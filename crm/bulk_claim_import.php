<?php
require_once 'app_init.php';
require_once 'auth.php';

// Only Admin/Manager can access
if (!in_array($_SESSION['role'], ['super_admin', 'admin', 'manager', 'coordinator'])) {
    header('Location: dashboard.php');
    exit;
}

$success = '';
$error = '';

// Ensure upload directory exists
$upload_dir = 'uploads/client_data/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle File Upload & Import
if (isset($_POST['import_claims'])) {
    $client_id = $_POST['client_id'];
    
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $filename = $_FILES['excel_file']['name'];
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (in_array(strtolower($ext), ['csv', 'xlsx', 'xls'])) {
            $new_filename = time() . '_' . $filename;
            $target_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['excel_file']['tmp_name'], $target_path)) {
                // Log the file upload in a dedicated table if we had one, 
                // for now we just process it.
                
                // Processing logic would go here (e.g. using phpoffice/phpspreadsheet)
                // Since I cannot install new composer packages easily right now, 
                // I will implement a CSV parser for basic imports.
                
                if (strtolower($ext) == 'csv') {
                    $handle = fopen($target_path, "r");
                    $header = fgetcsv($handle); // Skip header
                    
                    $imported = 0;
                    $skipped = 0;
                    
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        // Expected Columns: Claim Number, Patient Name, Scope, Hospital Name, Hospital Address, TAT Deadline (YYYY-MM-DD), Diagnosis, UHID, Patient Phone
                        if (count($data) >= 2) {
                            $claim_number = trim($data[0]);
                            $patient_name = trim($data[1]);
                            $scope = $data[2] ?? 'Full Investigation';
                            $hospital = $data[3] ?? '';
                            $address = $data[4] ?? '';
                            $deadline = !empty($data[5]) ? $data[5] : date('Y-m-d', strtotime('+5 days'));
                            $diagnosis = $data[6] ?? '';
                            $uhid = $data[7] ?? '';
                            $phone = $data[8] ?? '';

                            // Check if claim number already exists
                            $check = $pdo->prepare("SELECT id FROM projects WHERE claim_number = ?");
                            $check->execute([$claim_number]);
                            if ($check->fetch()) {
                                $skipped++;
                                continue;
                            }

                            $stmt = $pdo->prepare("INSERT INTO projects (claim_number, title, scope, client_id, hospital_name, hospital_address, tat_deadline, diagnosis, uhid, patient_phone, status, created_at) 
                                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
                            $stmt->execute([$claim_number, $patient_name, $scope, $client_id, $hospital, $address, $deadline, $diagnosis, $uhid, $phone]);
                            $imported++;
                        }
                    }
                    fclose($handle);
                    $success = "Import complete! $imported claims added, $skipped skipped (duplicates). File stored as reference.";
                } else {
                    $success = "File '$filename' uploaded successfully to Client Data Repository. (Note: Automatic parsing for .xlsx requires PHPSpreadsheet extension, please use .csv for direct import).";
                }
            } else {
                $error = "Failed to save the file.";
            }
        } else {
            $error = "Invalid file format. Please upload .csv, .xls, or .xlsx";
        }
    } else {
        $error = "Please select a valid file to upload.";
    }
}

// Fetch Clients
$clients = $pdo->query("SELECT id, company_name FROM clients ORDER BY company_name")->fetchAll();

// Fetch Uploaded Files History
$files = [];
if (is_dir($upload_dir)) {
    $dir_files = scandir($upload_dir);
    foreach ($dir_files as $f) {
        if ($f !== '.' && $f !== '..') {
            $files[] = [
                'name' => $f,
                'path' => $upload_dir . $f,
                'time' => filemtime($upload_dir . $f)
            ];
        }
    }
    // Sort by time desc
    usort($files, function($a, $b) { return $b['time'] - $a['time']; });
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Bulk Claim Allocation - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .drop-zone {
            border: 2px dashed var(--border);
            border-radius: var(--radius-lg);
            padding: 40px;
            text-align: center;
            background: var(--surface-hover);
            transition: all 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover {
            border-color: var(--primary);
            background: #f0f7ff;
        }
        .file-card {
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            padding: 12px 16px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: white;
        }
        .file-icon {
            font-size: 1.5rem;
            color: #10b981;
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Bulk Claim Allocation</h1>
                    <p class="text-muted mb-0 small">Import claims from client Excel/CSV files (FHPL, Vidal, etc.)</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="projects.php" class="btn-v2 btn-white-v2">
                         <i class="bi bi-arrow-left"></i> View Claims
                    </a>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-center mb-4 border-0 shadow-sm">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($success) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 border-0 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div><?= htmlspecialchars($error) ?></div>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <!-- Left: Upload Section -->
                <div class="col-lg-7">
                    <div class="app-card">
                        <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold text-uppercase small text-muted">Upload New Client File</h6>
                            <a href="templates/claim_import_template.csv" class="btn btn-sm btn-link text-decoration-none">Download CSV Template</a>
                        </div>
                        <div class="p-4">
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="import_claims" value="1">
                                
                                <div class="mb-4">
                                    <label class="stat-label mb-2">Select Client</label>
                                    <select name="client_id" class="input-v2 form-select" required>
                                        <option value="">-- Choose Client (Vidal, FHPL, etc.) --</option>
                                        <?php foreach($clients as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['company_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="drop-zone mb-4" onclick="document.getElementById('fileInput').click()">
                                    <i class="bi bi-cloud-arrow-up text-primary" style="font-size: 3rem;"></i>
                                    <h5 class="mt-3 fw-bold">Drag & Drop Client Excel Here</h5>
                                    <p class="text-muted small">Supports .CSV, .XLS, .XLSX (CSV recommended for instant parsing)</p>
                                    <input type="file" name="excel_file" id="fileInput" class="d-none" accept=".csv,.xls,.xlsx" required>
                                    <div id="fileSelected" class="mt-2 fw-bold text-primary"></div>
                                </div>

                                <button type="submit" class="btn-v2 btn-primary-v2 w-100 py-3 fw-bold">
                                    <i class="bi bi-file-earmark-plus"></i> Store & Process Allocation
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Import Instructions -->
                    <div class="app-card mt-4 bg-light border-0">
                        <div class="p-3">
                            <h6 class="fw-bold small text-uppercase text-muted mb-3">CSV Format Requirements</h6>
                            <div class="table-responsive">
                                <table class="table table-sm small mb-0">
                                    <thead>
                                        <tr class="text-muted">
                                            <th>Column</th>
                                            <th>Requirement</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Claim Number</td><td><span class="badge bg-danger px-1">Required</span></td></tr>
                                        <tr><td>Patient Name</td><td><span class="badge bg-danger px-1">Required</span></td></tr>
                                        <tr><td>Scope</td><td>Full Investigation, Hospital Part, Pt Part, etc.</td></tr>
                                        <tr><td>Hospital Name</td><td>Optional</td></tr>
                                        <tr><td>TAT Deadline</td><td>YYYY-MM-DD format</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Repository History -->
                <div class="col-lg-5">
                    <div class="app-card">
                        <div class="p-3 border-bottom">
                            <h6 class="mb-0 fw-bold text-uppercase small text-muted">Client Data Repository</h6>
                        </div>
                        <div class="p-3" style="max-height: 500px; overflow-y: auto;">
                            <?php if (empty($files)): ?>
                                <div class="text-center py-5 text-muted">
                                    <i class="bi bi-folder2-open d-block fs-1 mb-2 opacity-25"></i>
                                    <p class="small">No files uploaded yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($files as $f): ?>
                                <div class="file-card">
                                    <div class="d-flex align-items-center overflow-hidden">
                                        <i class="bi bi-file-earmark-excel file-icon"></i>
                                        <div class="text-truncate">
                                            <div class="fw-bold small text-truncate" title="<?= htmlspecialchars($f['name']) ?>"><?= htmlspecialchars(substr($f['name'], 11)) ?></div>
                                            <div class="text-muted" style="font-size: 0.7rem;"><?= date('d M Y, h:i A', $f['time']) ?></div>
                                        </div>
                                    </div>
                                    <a href="<?= $f['path'] ?>" class="btn btn-sm btn-white-v2" download>
                                        <i class="bi bi-download"></i>
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
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
        <a href="projects.php" class="bottom-nav-item">
            <i class="bi bi-folder"></i>
            <span>Claims</span>
        </a>
         <div style="position: relative; top: -20px;">
            <a href="bulk_claim_import.php" class="bottom-nav-icon-main">
                <i class="bi bi-upload"></i>
            </a>
        </div>
        <a href="attendance.php" class="bottom-nav-item">
            <i class="bi bi-calendar-check"></i>
            <span>Attend</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('fileInput').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                document.getElementById('fileSelected').innerText = "Selected: " + this.files[0].name;
            }
        });
    </script>
</body>
</html>
