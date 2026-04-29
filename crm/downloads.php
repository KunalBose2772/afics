<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Define the 5 official forms
$forms = [
    [
        'label' => 'Cashless Form',
        'file' => 'Cashless_Form.pdf',
        'icon' => 'bi-credit-card',
        'color' => '#10b981',
        'desc' => 'Standard cashless authorization request form.'
    ],
    [
        'label' => 'Admission Confirmation',
        'file' => 'Admission_Confirmation_Form.pdf',
        'icon' => 'bi-file-earmark-check',
        'color' => '#3b82f6',
        'desc' => 'Official admission confirmation for field operations.'
    ],
    [
        'label' => 'Reimbursement Form',
        'file' => 'Reimbursement_Form.pdf',
        'icon' => 'bi-cash-coin',
        'color' => '#f59e0b',
        'desc' => 'Standard claim form for medical reimbursements.'
    ],
    [
        'label' => 'FO Checklist',
        'file' => 'FO_Checklist.pdf',
        'icon' => 'bi-list-check',
        'color' => '#8b5cf6',
        'desc' => 'Field Officer operational checklist and verification.'
    ],
    [
        'label' => 'TDQ Form',
        'file' => 'TDQ_Form.pdf',
        'icon' => 'bi-file-earmark-text',
        'color' => '#ef4444',
        'desc' => 'Technical Data Questionnaire for medical reports.'
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
        :root {
            --primary-gradient: linear-gradient(135deg, #3D0C60 0%, #6B21A8 100%);
            --glass-bg: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        .form-card {
            background: #fff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 24px;
            height: 100%;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border-color: #cbd5e1;
        }

        .icon-box {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .download-btn {
            width: 100%;
            padding: 12px;
            border-radius: 12px;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.2s ease;
            background: #f8fafc;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            margin-top: 20px;
        }

        .download-btn:hover {
            background: #3D0C60;
            color: #fff;
            border-color: #3D0C60;
            box-shadow: 0 4px 12px rgba(61, 12, 96, 0.2);
        }

        .page-header {
            padding: 40px 0;
            margin-bottom: 20px;
        }

        .header-title {
            font-family: 'Lexend', sans-serif;
            font-weight: 800;
            font-size: 2.5rem;
            color: #1e293b;
            letter-spacing: -0.03em;
            margin-bottom: 8px;
        }

        .header-subtitle {
            font-size: 1.1rem;
            color: #64748b;
            max-width: 600px;
        }

        .form-label {
            font-family: 'Lexend', sans-serif;
            font-weight: 700;
            font-size: 1.2rem;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .form-desc {
            font-size: 0.9rem;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .header-title { font-size: 2rem; }
            .page-header { padding: 30px 0; }
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
        <div class="app-container">
            <header class="page-header">
                <h1 class="header-title">Standard Operating Forms</h1>
                <p class="header-subtitle">Download the 5 official AFICS DOCUMANTRAA forms required for field operations and verification.</p>
            </header>

            <div class="row g-4">
                <?php foreach ($forms as $form): ?>
                <div class="col-lg-4 col-md-6">
                    <div class="form-card">
                        <div>
                            <div class="icon-box" style="background: <?= $form['color'] ?>15; color: <?= $form['color'] ?>;">
                                <i class="bi <?= $form['icon'] ?>"></i>
                            </div>
                            <h5 class="form-label"><?= $form['label'] ?></h5>
                            <p class="form-desc"><?= $form['desc'] ?></p>
                        </div>
                        
                        <a href="../assets/forms/<?= rawurlencode($form['file']) ?>" download class="download-btn">
                            Download PDF <i class="bi bi-download"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-5 d-flex justify-content-center">
                <div class="info-banner" style="background: #f1f5f9; color: #64748b; padding: 14px 28px; border-radius: 100px; font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 12px; border: 1px solid #e2e8f0;">
                    <i class="bi bi-info-circle-fill opacity-50"></i>
                    <span>These are official document templates. Please ensure you use the latest version.</span>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


