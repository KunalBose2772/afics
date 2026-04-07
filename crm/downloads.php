<?php
require_once 'app_init.php';

// Auth Check (Redundant as sidebar handles visibility, but good for direct access prevention)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define forms list (Mirrors legacy structure)
$forms = [
    'Cashless' => [
        'icon' => 'bi-credit-card',
        'color_class' => 'text-success', // V2 colors
        'bg_class' => 'bg-success-subtle',
        'files' => [
            'Cashless Form' => 'cashless_form.pdf',
            'Admission Confirmation Form' => 'admission_confirmation.pdf'
        ]
    ],
    'Reimbursement' => [
        'icon' => 'bi-cash-coin',
        'color_class' => 'text-primary',
        'bg_class' => 'bg-primary-subtle',
        'files' => [
            'Reimbursement Form' => 'reimbursement_form.pdf',
            'Doctor Form' => 'doctor_form.pdf',
            'Low Cost Form' => 'low_cost_form.pdf'
        ]
    ],
    'Medical Reports' => [
        'icon' => 'bi-file-medical',
        'color_class' => 'text-danger',
        'bg_class' => 'bg-danger-subtle',
        'files' => [
            'Pathology Form' => 'pathology_form.pdf',
            'Pathologist Statement' => 'pathologist_statement.pdf',
            'Radiologist Statement' => 'radiologist_statement.pdf'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Downloads & Forms - Documantraa</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
    <style>
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
        }
        .file-item:last-child {
            border-bottom: none;
        }
        .file-item:hover {
            background-color: var(--surface-hover);
        }
        .cat-card-header {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
    </style>
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

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Downloads & Forms</h1>
                    <p class="text-muted mb-0 small">Access and download standard operational forms.</p>
                </div>
            </div>
        </header>

        <div class="app-container">
            <div class="row g-4">
                <?php foreach ($forms as $category => $data): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="app-card p-0 h-100">
                        <div class="cat-card-header <?= $data['bg_class'] ?>">
                            <i class="bi <?= $data['icon'] ?> fs-5 <?= $data['color_class'] ?>"></i>
                            <h5 class="mb-0 fw-bold fs-6 text-dark"><?= $category ?></h5>
                        </div>
                        <div class="d-flex flex-column">
                            <?php foreach ($data['files'] as $label => $filename): ?>
                            <div class="file-item">
                                <div class="d-flex align-items-center gap-3 overflow-hidden">
                                    <div class="bg-light rounded p-2 text-secondary flex-shrink-0">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </div>
                                    <span class="text-truncate text-main fw-medium small"><?= $label ?></span>
                                </div>
                                <a href="../assets/forms/<?= $filename ?>" class="btn-v2 btn-white-v2" style="padding: 6px 12px; font-size: 0.8rem;" download>
                                    Download <i class="bi bi-download ms-1"></i>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-4 text-center">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded-pill bg-light text-muted small">
                    <i class="bi bi-info-circle-fill"></i>
                    <span>If a form download fails, please contact Admin to upload the PDF template.</span>
                </div>
            </div>

        </div>
    </div>

    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2"></i>
            <span>Home</span>
        </a>
        <a href="projects.php" class="bottom-nav-item">
            <i class="bi bi-folder"></i>
            <span>Claims</span>
        </a>
        <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="downloads.php" class="bottom-nav-item active">
            <i class="bi bi-cloud-download-fill"></i>
            <span>Forms</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
