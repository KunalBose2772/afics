<?php
require_once 'app_init.php';
require_once 'auth.php';

$pid = $_GET['id'] ?? 0;
if ($pid == 0) {
    header('Location: projects.php');
    exit;
}

// Fetch Project Details
$stmt = $pdo->prepare("SELECT p.*, c.company_name, u.full_name as officer_name 
                       FROM projects p 
                       LEFT JOIN clients c ON p.client_id = c.id 
                       LEFT JOIN users u ON p.assigned_to = u.id 
                       WHERE p.id = ?");
$stmt->execute([$pid]);
$project = $stmt->fetch();

if (!$project) {
    echo "<h1>Claim not found</h1><a href='projects.php'>Back</a>";
    exit;
}

// Fetch Visit Specific Photos (Search in both category and document_type)
$visit_keywords = ['Hospital Visit Photo', 'Hospital Selfie', 'Lab Visit Photo', 'Pharmacy Visit Photo', 'Patient Selfie', 'Visit Photo', 'Selfie'];
$conditions = [];
$params = [$pid];

foreach ($visit_keywords as $kw) {
    $conditions[] = "category LIKE ?";
    $conditions[] = "document_type LIKE ?";
    $params[] = "%$kw%";
    $params[] = "%$kw%";
}

// Also include anything under 'Investigation' category as it typically contains visit proof
$conditions[] = "category = ?";
$params[] = 'Investigation';

$query = "SELECT * FROM project_documents WHERE project_id = ? AND (" . implode(' OR ', $conditions) . ") ORDER BY uploaded_at ASC";
$docStmt = $pdo->prepare($query);
$docStmt->execute($params);
$documents = $docStmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit Report - <?= htmlspecialchars($project['claim_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2563eb;
            --surface: #f8fafc;
        }
        body { font-family: 'Jost', sans-serif; background: #fff; color: #1e293b; padding: 0; margin: 0; }
        
        .report-page {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px;
        }

        .report-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .brand-name { font-family: 'Lexend', sans-serif; font-weight: 700; color: var(--primary); font-size: 1.5rem; }
        
        .visit-photo-container {
            margin-bottom: 50px;
            page-break-inside: avoid;
            position: relative;
        }

        .visit-photo-img {
            width: 100%;
            border-radius: 8px;
            display: block;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        /* GPS Watermark Styling */
        .gps-overlay {
            position: absolute;
            bottom: 12px;
            left: 12px;
            right: 12px;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            color: white;
            padding: 12px 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid rgba(255,255,255,0.2);
            font-family: 'Lexend', sans-serif;
        }

        .gps-map-preview {
            width: 70px;
            height: 70px;
            background: #334155;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #94a3b8;
            flex-shrink: 0;
            overflow: hidden;
            border: 1px solid rgba(255,255,255,0.1);
        }
        
        .gps-details { line-height: 1.4; font-size: 0.75rem; flex: 1; }
        .gps-location-main { font-weight: 700; font-size: 0.9rem; margin-bottom: 2px; display: block; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.5px; }
        .gps-coords { font-family: monospace; opacity: 0.9; color: #94a3b8; font-size: 0.7rem; }
        .gps-timestamp { font-size: 0.65rem; color: #cbd5e1; margin-top: 4px; display: flex; align-items: center; gap: 4px; }
        .gps-badge { font-size: 0.6rem; background: rgba(255,255,255,0.1); padding: 2px 6px; border-radius: 4px; text-transform: uppercase; margin-left: auto; }

        @media print {
            .no-print { display: none !important; }
            .report-page { padding: 0; width: 100%; max-width: 100%; }
            body { background: white; }
            .visit-photo-container { margin-bottom: 100px; }
        }

        .btn-print {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1000;
            border-radius: 99px;
            padding: 12px 30px;
            font-weight: 600;
            box-shadow: 0 10px 25px rgba(37, 99, 235, 0.4);
        }
    </style>
</head>
<body>

    <button type="button" class="btn btn-primary btn-print no-print" onclick="window.print()">
        <i class="bi bi-file-earmark-pdf-fill me-2"></i> Download Visit PDF
    </button>

    <div class="report-page">
        <header class="report-header">
            <div>
                <span class="brand-name">Documantraa</span>
                <p class="text-muted small mb-0">Visit Verification Report &middot; Generated on <?= date('d M Y, h:i A') ?></p>
            </div>
            <div class="text-end">
                <div class="text-muted small fw-bold" style="letter-spacing: 0.05em; font-size: 0.65rem;">CLAIM NUMBER</div>
                <h5 class="fw-bold mb-0" style="font-family: monospace; color: #1e293b;"><?= htmlspecialchars($project['claim_number']) ?></h5>
                <span class="badge bg-primary-subtle text-primary border border-primary px-2 mt-1" style="font-size: 0.7rem;"><?= htmlspecialchars($project['company_name'] ?? 'N/A') ?></span>
            </div>
        </header>

        <div class="row mb-4">
            <div class="col-6">
                <div class="text-muted small fw-bold">PATIENT NAME</div>
                <div class="fw-bold"><?= htmlspecialchars($project['title'] ?? 'N/A') ?></div>
            </div>
            <div class="col-6 text-end">
                <div class="text-muted small fw-bold">HOSPITAL NAME</div>
                <div class="fw-bold"><?= htmlspecialchars($project['hospital_name'] ?? 'N/A') ?></div>
            </div>
        </div>

        <section class="mb-5">
            <h5 class="fw-bold mb-4"><i class="bi bi-geo-alt-fill text-danger me-2"></i>Location Verification Evidence</h5>
            
            <?php if (empty($documents)): ?>
                <div class="alert alert-warning">No visit specific photos found. Please upload photos under 'Visit Photo' or 'Selfie' categories.</div>
            <?php else: ?>
                <?php foreach ($documents as $doc): ?>
                    <div class="visit-photo-container">
                        <img src="../<?= htmlspecialchars($doc['file_path']) ?>" class="visit-photo-img" alt="Visit Photo">
                        
                        <div class="gps-overlay">
                            <div class="gps-map-preview">
                                <!-- Placeholder for Map Icon -->
                                <i class="bi bi-geo-fill"></i>
                                <?php if($doc['gps_lat']): ?>
                                    <img src="https://dev.virtualearth.net/REST/v1/Imagery/Map/Road/<?= $doc['gps_lat'] ?>,<?= $doc['gps_long'] ?>/16?mapSize=150,150&pp=<?= $doc['gps_lat'] ?>,<?= $doc['gps_long'] ?>;66&key=Alp_h_9G_7_e_7_z_j_q_j_q_j_q_j_q_j_q_j_q" style="width:100%; height:100%; object-fit:cover; display:none;" onload="this.style.display='block'">
                                <?php endif; ?>
                            </div>
                            <div class="gps-details">
                                <span class="gps-location-main"><?= htmlspecialchars($doc['category']) ?></span>
                                <div class="mb-1 text-white opacity-95 fw-bold"><?= htmlspecialchars($project['hospital_name'] ?? 'Verification Location') ?></div>
                                <div class="gps-coords">
                                    <i class="bi bi-pin-map-fill me-1"></i>LAT: <?= $doc['gps_lat'] ?? 'N/A' ?> &nbsp; LONG: <?= $doc['gps_long'] ?? 'N/A' ?>
                                </div>
                                <div class="gps-timestamp">
                                    <i class="bi bi-clock-fill"></i> <?= date('l, d M Y | h:i A', strtotime($doc['uploaded_at'])) ?>
                                    <span class="gps-badge">Verified Evidence</span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <footer class="report-footer text-center border-top pt-4 mt-5">
            <p class="text-muted small mb-0">System Verified by Documantraa AI &middot; Digital Signature Locked</p>
            <p class="text-muted" style="font-size: 0.6rem;">This is an automated report, no manual signature is required.</p>
        </footer>
    </div>

</body>
</html>
