<?php
require_once 'app_init.php';
require_once 'auth.php';

$doc_id = intval($_GET['id'] ?? 0);
if (!$doc_id) {
    die("Invalid Document ID");
}

// Fetch Document Details
$stmt = $pdo->prepare("SELECT pd.*, p.claim_number, p.title as project_title FROM project_documents pd JOIN projects p ON pd.project_id = p.id WHERE pd.id = ?");
$stmt->execute([$doc_id]);
$doc = $stmt->fetch();

if (!$doc) {
    die("Document not found");
}

$file_path = '../' . $doc['file_path'];
if (!file_exists($file_path)) {
    die("File does not exist on server: " . htmlspecialchars($doc['file_path']));
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$is_image = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
$is_pdf = ($ext === 'pdf');

// Security: Check if user has access to this project (reuse logic from project_documents.php if needed)
// For now, auth.php ensures they are logged in. More granular checks could be added.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($doc['document_type']) ?> - <?= htmlspecialchars($doc['claim_number']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #7c3aed;
            --primary-dark: #6d28d9;
            --bg: #0f172a;
        }
        body {
            font-family: 'Jost', sans-serif;
            background-color: var(--bg);
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .viewer-header {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            color: white;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .viewer-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding: 20px;
            position: relative;
            background: radial-gradient(circle at center, #1e293b 0%, #0f172a 100%);
        }
        .viewer-content img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border-radius: 8px;
            transition: transform 0.3s ease;
        }
        .viewer-content iframe, .viewer-content embed {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            border-radius: 8px;
        }
        .floating-download-btn {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #7c3aed, #9333ea);
            color: white;
            padding: 14px 28px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(124, 58, 237, 0.4);
            z-index: 2000;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            border: 2px solid rgba(255,255,255,0.2);
            font-size: 1.1rem;
            white-space: nowrap;
        }
        .floating-download-btn:hover {
            transform: translateX(-50%) translateY(-5px) scale(1.05);
            box-shadow: 0 15px 35px rgba(124, 58, 237, 0.6);
            color: white;
            background: linear-gradient(135deg, #6d28d9, #7e22ce);
        }
        .floating-download-btn:active {
            transform: translateX(-50%) scale(0.95);
        }
        .back-btn {
            color: #cbd5e1;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .back-btn:hover {
            color: white;
        }
        .doc-info {
            text-align: center;
            flex: 1;
        }
        .doc-info h1 {
            font-size: 1rem;
            margin: 0;
            font-weight: 700;
            letter-spacing: 0.02em;
        }
        .doc-info p {
            font-size: 0.75rem;
            margin: 0;
            color: #94a3b8;
        }

        /* Mobile specific adjustments */
        @media (max-width: 768px) {
            .viewer-content {
                padding: 10px;
            }
            .floating-download-btn {
                bottom: 20px;
                padding: 12px 24px;
                font-size: 1rem;
            }
            .doc-info {
                display: none; /* Hide middle info on very small screens to save space */
            }
        }
    </style>
</head>
<body>

    <div class="viewer-header">
        <a href="javascript:history.back()" class="back-btn">
            <i class="bi bi-chevron-left"></i> Back
        </a>
        <div class="doc-info">
            <h1><?= htmlspecialchars($doc['document_type']) ?></h1>
            <p>Claim #<?= htmlspecialchars($doc['claim_number']) ?> &middot; <?= htmlspecialchars($doc['project_title']) ?></p>
        </div>
        <div style="width: 60px;"></div> <!-- Spacer to balance header -->
    </div>

    <div class="viewer-content">
        <?php if ($is_image): ?>
            <img src="<?= $file_path ?>" alt="Document Image">
        <?php elseif ($is_pdf): ?>
            <iframe src="<?= $file_path ?>#toolbar=0" type="application/pdf"></iframe>
        <?php else: ?>
            <div class="text-white text-center">
                <i class="bi bi-file-earmark-text fs-1 mb-3 d-block"></i>
                <p>This file type (<?= strtoupper($ext) ?>) cannot be previewed in the browser.</p>
                <p class="small text-muted">Please use the download button below.</p>
            </div>
        <?php endif; ?>
    </div>

    <a href="download_file.php?id=<?= $doc_id ?>" target="_blank" class="floating-download-btn">
        <i class="bi bi-cloud-arrow-down-fill fs-4"></i>
        DOWNLOAD NOW
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
