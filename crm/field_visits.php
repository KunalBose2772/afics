<?php
require_once 'app_init.php';
// Only include auth if not already included to avoid re-declare errors
if (!function_exists('has_permission')) {
    require_once 'auth.php';
}

// Basic Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];

// --- HANDLERS ---

// Handle image/PDF upload for completed visits or manual uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_evidence'])) {
    $visit_id = $_POST['visit_id'];

    // Check if visit belongs to user
    $check = $pdo->prepare("SELECT id FROM field_visits WHERE id = ? AND user_id = ?");
    $check->execute([$visit_id, $user_id]);

    if ($check->rowCount() > 0) {
        if (isset($_FILES['visit_image']) && $_FILES['visit_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/field_visits/'; // Adjusted for V2
            if (!file_exists($upload_dir))
                mkdir($upload_dir, 0777, true);

            $extension = strtolower(pathinfo($_FILES['visit_image']['name'], PATHINFO_EXTENSION));
            
            // Allow images and PDFs
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
            if (!in_array($extension, $allowed_extensions)) {
                header('Location: field_visits.php?error=' . urlencode('Invalid file type. Only images and PDF allowed.'));
                exit;
            }
            
            // Check file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['visit_image']['size'] > $max_size) {
                header('Location: field_visits.php?error=' . urlencode('File size exceeds 10MB limit.'));
                exit;
            }
            
            $filename = 'visit_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['visit_image']['tmp_name'], $upload_path)) {
                $stmt = $pdo->prepare("UPDATE field_visits SET image_path = ? WHERE id = ?");
                $stmt->execute([$filename, $visit_id]);
                header('Location: field_visits.php?success=evidence_uploaded');
                exit;
            } else {
                header('Location: field_visits.php?error=' . urlencode('Upload failed during move.'));
                exit;
            }
        } else {
             header('Location: field_visits.php?error=' . urlencode('File upload error code: ' . $_FILES['visit_image']['error']));
             exit;
        }
    }
    header('Location: field_visits.php?error=' . urlencode('Visit verification failed or invalid ID'));
    exit;
}

// Check for Active Journey
$active_journey = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM field_visits WHERE user_id = ? AND start_time IS NOT NULL AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
    if ($stmt) {
        $stmt->execute([$user_id]);
        $active_journey = $stmt->fetch();
    }
} catch (PDOException $e) { /* Fail silently */ }

// Stats
$visit_stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $stats = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
        FROM field_visits WHERE user_id = ?");
    if ($stats) {
        $stats->execute([$user_id]);
        $result = $stats->fetch();
        if ($result) $visit_stats = $result;
    }
} catch (PDOException $e) { }

// Fetch Visits
$my_visits = [];
$search = trim($_GET['search'] ?? '');
$search_sql = "";
$params = [$user_id];
if (!empty($search)) {
    $search_sql = " AND (location_name LIKE ? OR purpose LIKE ? OR claim_number LIKE ?)";
    $term = "%$search%";
    $params[] = $term; $params[] = $term; $params[] = $term;
}

try {
    $query = "SELECT * FROM field_visits WHERE user_id = ? $search_sql ORDER BY visit_date DESC, visit_time DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $my_visits = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Query Failed: " . $e->getMessage();
    // Maintain empty array to not break HTML below
    $my_visits = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Field Visits - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 32px;">
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <!-- Sidebar (Mobile & Desktop) -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <h1 style="font-size: 1.75rem; color: var(--text-main);">Field Visits</h1>
                <?php if (!$active_journey): ?>
                <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#startJourneyModal">
                    <i class="bi bi-geo-alt-fill"></i> Start New Journey
                </button>
                <?php endif; ?>
            </div>
        </header>

        <div class="app-container">
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-check-circle me-2"></i> Action completed successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i> <?= htmlspecialchars($_GET['error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Active Journey Card -->
            <?php if ($active_journey): ?>
            <div class="app-card" style="border-left: 5px solid var(--primary); background: linear-gradient(to right, #eff6ff, white);">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
                    <div class="text-center text-md-start">
                        <span class="badge badge-v2 badge-process animate-pulse mb-2">LIVE TRACKING</span>
                        <h3 class="mb-1" style="font-family: 'Lexend', sans-serif;">Journey: <?= htmlspecialchars($active_journey['claim_number'] ?? 'N/A') ?></h3>
                        <p class="text-muted mb-0">
                            <i class="bi bi-geo-alt me-1"></i> <?= htmlspecialchars($active_journey['location_name']) ?> <span class="mx-2">|</span>
                            <i class="bi bi-clock me-1"></i> Started: <?= date('h:i A', strtotime($active_journey['start_time'])) ?>
                        </p>
                    </div>
                    <button class="btn-v2 btn-primary-v2" onclick="endJourney(<?= $active_journey['id'] ?>)" style="background: var(--warning-text); border-color: var(--warning-text);">
                        <i class="bi bi-flag-fill"></i> End Journey
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Total Visits</div>
                        <div class="stat-value text-primary"><?= $visit_stats['total'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Pending</div>
                        <div class="stat-value text-warning"><?= $visit_stats['pending'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Approved</div>
                        <div class="stat-value text-success"><?= $visit_stats['approved'] ?? 0 ?></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="app-card h-100 d-flex flex-column justify-content-center" style="background: #ffffff; border: 1px solid var(--border); box-shadow: var(--shadow-sm); border-radius: 12px; padding: 20px;">
                        <div class="card-title-v2 mb-2">Rejected</div>
                        <div class="stat-value text-danger"><?= $visit_stats['rejected'] ?? 0 ?></div>
                    </div>
                </div>
            </div>

            <!-- Visits List -->
            <div class="app-card">
                <div class="card-header-v2">
                    <h3 class="card-title-v2 m-0">Visit History</h3>
                     <form method="GET" class="d-flex gap-2" style="width: 100%; max-width: 300px;">
                        <input type="text" name="search" class="input-v2 py-1" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" style="font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid var(--border);">
                                <th class="py-3 text-secondary fw-normal">Date</th>
                                <th class="py-3 text-secondary fw-normal">Details</th>
                                <th class="py-3 text-secondary fw-normal">Times</th>
                                <th class="py-3 text-secondary fw-normal">TA</th>
                                <th class="py-3 text-secondary fw-normal">Status</th>
                                <th class="py-3 text-secondary fw-normal text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_visits as $visit): ?>
                            <tr>
                                <td class="py-3 align-middle">
                                    <div class="fw-bold"><?= date('d M', strtotime($visit['visit_date'])) ?></div>
                                    <small class="text-muted"><?= date('H:i', strtotime($visit['visit_time'])) ?></small>
                                </td>
                                <td class="py-3 align-middle">
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($visit['claim_number']) ?></div>
                                    <div class="small"><?= htmlspecialchars($visit['location_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($visit['visit_type']) ?></small>
                                </td>
                                <td class="py-3 align-middle small text-muted">
                                    <div>Start: <?= $visit['start_time'] ? date('H:i', strtotime($visit['start_time'])) : '-' ?></div>
                                    <div>End: <?= $visit['end_time'] ? date('H:i', strtotime($visit['end_time'])) : '-' ?></div>
                                </td>
                                <td class="py-3 align-middle">
                                    <?php if ($visit['travel_allowance'] > 0): ?>
                                    <span class="badge badge-v2 badge-success">₹<?= $visit['travel_allowance'] ?></span>
                                    <?php else: echo '-'; endif; ?>
                                </td>
                                <td class="py-3 align-middle">
                                    <span class="badge badge-v2 <?= ($visit['status']=='Approved')?'badge-success':( ($visit['status']=='Rejected')?'badge-pending':'badge-process' ) ?>">
                                        <?= $visit['status'] ?>
                                    </span>
                                </td>
                                <td class="py-3 align-middle text-end">
                                    <?php if (empty($visit['image_path'])): ?>
                                        <button class="btn-v2 btn-white-v2" style="padding: 4px 10px; font-size: 0.8rem;" onclick="uploadEvidence(<?= $visit['id'] ?>)">
                                            <i class="bi bi-camera"></i> Upload
                                        </button>
                                    <?php else: ?>
                                        <a href="../uploads/field_visits/<?= $visit['image_path'] ?>" target="_blank" class="btn-v2 btn-white-v2" style="padding: 4px 10px; font-size: 0.8rem;">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Start Journey Modal -->
    <div class="modal fade" id="startJourneyModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Start Field Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="startJourneyForm">
                        <input type="hidden" name="action" value="start_visit">
                        <input type="hidden" name="latitude" id="start_lat">
                        <input type="hidden" name="longitude" id="start_long">

                        <div class="mb-3">
                            <label class="stat-label mb-1 d-block">Claim Number</label>
                            <input type="text" name="claim_number" class="input-v2" required placeholder="e.g. 12345">
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1 d-block">Visit Type</label>
                            <select name="visit_type" class="input-v2 form-select" required>
                                <option value="Hospital Part">Hospital Part</option>
                                <option value="Patient Part">Patient Part</option>
                                <option value="Other Part">Other Part</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1 d-block">Location Name</label>
                            <input type="text" name="location_name" class="input-v2" required>
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1 d-block">Purpose</label>
                            <input type="text" name="purpose" class="input-v2" required>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="button" onclick="submitStartJourney()" class="btn-v2 btn-primary-v2">
                                Start Journey
                            </button>
                        </div>
                        <p class="text-center text-muted small mt-2 mb-0">GPS Location will be captured.</p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Evidence Modal -->
    <div class="modal fade" id="uploadEvidenceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold">Upload Evidence</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="upload_evidence" value="1">
                        <input type="hidden" name="visit_id" id="upload_visit_id">
                        
                        <div class="mb-4">
                            <label class="stat-label mb-1 d-block">Select File</label>
                            <input type="file" name="visit_image" class="form-control" accept="image/*,.pdf" required>
                            <small class="text-muted">Images or PDF (Max 10MB)</small>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn-v2 btn-primary-v2">Upload Now</button>
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
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
         <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="field_visits.php" class="bottom-nav-item active">
            <i class="bi bi-geo-alt"></i>
            <span>Visits</span>
        </a>
        <a href="#" class="bottom-nav-item">
            <i class="bi bi-credit-card"></i>
            <span>Pay</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getLocation(callback) {
            if (navigator.geolocation) {
                const highAccuracyOptions = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };
                const lowAccuracyOptions = { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 };

                navigator.geolocation.getCurrentPosition(callback, function (error) {
                    console.warn('GPS High Accuracy failed, retrying network location...', error.message);
                    // Fallback to low accuracy
                    navigator.geolocation.getCurrentPosition(callback, function (error2) {
                        alert('Error getting location: ' + error2.message);
                    }, lowAccuracyOptions);
                }, highAccuracyOptions);
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        function submitStartJourney() {
            const btn = document.querySelector('#startJourneyForm button');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Locating...';

            getLocation(function (position) {
                document.getElementById('start_lat').value = position.coords.latitude;
                document.getElementById('start_long').value = position.coords.longitude;

                const formData = new FormData(document.getElementById('startJourneyForm'));

                fetch('ajax/handle_field_visit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    alert('Request failed');
                    console.error(err);
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        }

        function endJourney(visitId) {
            if (!confirm('Are you sure you have reached the destination?')) return;
            
            getLocation(function (position) {
                const formData = new FormData();
                formData.append('action', 'end_visit');
                formData.append('visit_id', visitId);
                formData.append('latitude', position.coords.latitude);
                formData.append('longitude', position.coords.longitude);

                fetch('ajax/handle_field_visit.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(`Journey Ended! Distance: ${data.distance}. TA: ₹${data.ta_amount}`);
                        uploadEvidence(visitId);
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            });
        }

        function uploadEvidence(visitId) {
            document.getElementById('upload_visit_id').value = visitId;
            const modal = new bootstrap.Modal(document.getElementById('uploadEvidenceModal'));
            modal.show();
        }
    </script>
</body>
</html>
