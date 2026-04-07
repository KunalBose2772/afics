<?php
require_once 'auth.php';
require_once '../config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>Dashboard - Documantraa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4" style="margin-left: 280px;">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                    <div>
                        <h2 class="mb-0 fw-bold">CMS Dashboard</h2>
                        <p class="text-secondary mb-0">Manage website content.</p>
                    </div>
                    <div class="text-end">
                        <span class="text-white-50 d-block"><?= date('l, F j, Y') ?></span>
                    </div>
                </div>
                
                <div class="row g-4">
                    <!-- Active Services -->
                    <div class="col-12 col-md-6">
                        <div class="glass-card h-100 position-relative overflow-hidden d-flex flex-column">
                            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                                <i class="bi bi-list-check display-4"></i>
                            </div>
                            <h5 class="text-uppercase text-secondary mb-3">Active Services</h5>
                            <h2 class="display-3 fw-bold mb-3 text-white">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) FROM services");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                            <a href="services" class="btn btn-outline-light rounded-pill px-4 mt-auto align-self-start">Manage Services <i class="bi bi-arrow-right ms-2"></i></a>
                        </div>
                    </div>

                    <!-- Global Settings -->
                    <div class="col-12 col-md-6">
                        <div class="glass-card h-100 position-relative overflow-hidden d-flex flex-column">
                            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                                <i class="bi bi-gear display-4"></i>
                            </div>
                            <h5 class="text-uppercase text-secondary mb-3">Global Settings</h5>
                            <h2 class="display-3 fw-bold mb-3 text-white">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_group != 'CRM Appearance'");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                            <a href="settings" class="btn btn-outline-light rounded-pill px-4 mt-auto align-self-start">Configuration <i class="bi bi-arrow-right ms-2"></i></a>
                        </div>
                    </div>

                    <!-- CRM Settings -->
                    <div class="col-12 col-md-6">
                        <div class="glass-card h-100 position-relative overflow-hidden d-flex flex-column">
                            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                                <i class="bi bi-palette display-4"></i>
                            </div>
                            <h5 class="text-uppercase text-secondary mb-3">CRM Appearance</h5>
                            <h2 class="display-3 fw-bold mb-3 text-white">
                                <span class="fs-4">Theme & Style</span>
                            </h2>
                            <a href="crm_appearance" class="btn btn-outline-light rounded-pill px-4 mt-auto align-self-start">Customize <i class="bi bi-arrow-right ms-2"></i></a>
                        </div>
                    </div>

                    <!-- FAQ's -->
                    <div class="col-12 col-md-6">
                        <div class="glass-card h-100 position-relative overflow-hidden d-flex flex-column">
                            <div class="position-absolute top-0 end-0 p-3 opacity-25">
                                <i class="bi bi-question-circle display-4"></i>
                            </div>
                            <h5 class="text-uppercase text-secondary mb-3">FAQ's</h5>
                            <h2 class="display-3 fw-bold mb-3 text-white">
                                <?php 
                                $stmt = $pdo->query("SELECT COUNT(*) FROM faqs");
                                echo $stmt->fetchColumn();
                                ?>
                            </h2>
                            <a href="faqs" class="btn btn-outline-light rounded-pill px-4 mt-auto align-self-start">Manage FAQs <i class="bi bi-arrow-right ms-2"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
