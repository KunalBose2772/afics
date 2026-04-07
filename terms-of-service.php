<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$settings = get_settings($pdo);
$services = get_services($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/favicon/favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/site.webmanifest">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - <?= htmlspecialchars($settings['site_name']) ?></title>
    <meta name="description" content="Terms of Service for <?= htmlspecialchars($settings['site_name']) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php render_dynamic_css($settings); ?>
</head>

<body>

    <?php include __DIR__ . '/includes/top-bar.php'; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="page-hero position-relative d-flex align-items-center"
        style="min-height: 50vh; background: linear-gradient(135deg, rgba(220, 53, 69, 0.95), rgba(139, 0, 0, 0.95)), url('assets/images/hero-bg.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
        <div class="container position-relative" style="z-index: 2;">
            <div class="row">
                <div class="col-lg-10 mx-auto text-center">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" style="margin-bottom: 20px;" data-aos="fade-down"
                        data-aos-duration="700">
                        <ol class="breadcrumb justify-content-center"
                            style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 50px; display: inline-flex; margin-bottom: 0;">
                            <li class="breadcrumb-item"><a href="/index"
                                    style="color: #ffffff; text-decoration: none; font-size: clamp(0.8rem, 1.6vw, 0.9rem); transition: opacity 0.3s;"
                                    onmouseover="this.style.opacity='0.8';"
                                    onmouseout="this.style.opacity='1';">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page"
                                style="font-size: clamp(0.8rem, 1.6vw, 0.9rem);">
                                Terms of Service
                            </li>
                        </ol>
                    </nav>

                    <div class="d-flex align-items-center justify-content-center" style="margin-bottom: 20px;"
                        data-aos="fade-up" data-aos-duration="700">
                        <div
                            style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <i class="bi bi-file-text-fill" style="font-size: 2rem; color: #ffffff;"></i>
                        </div>
                        <div>
                            <span class="text-uppercase fw-semibold text-white"
                                style="letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem); opacity: 0.9;">LEGAL
                                AGREEMENT</span>
                        </div>
                    </div>

                    <h1 class="text-white fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; font-family: var(--font-heading);"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
                        Terms of Service
                    </h1>

                    <p class="text-white lead"
                        style="margin-bottom: 0; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95; max-width: 800px; margin-left: auto; margin-right: auto; line-height: 1.6;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                        Please read these terms carefully before using our services.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Section -->
    <section class="terms-content" style="padding: 80px 0; background: #ffffff;">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div data-aos="fade-up" data-aos-duration="700">
                        <div class="content-wrapper"
                            style="background: #ffffff; padding: 50px; border-radius: 16px; box-shadow: 0 4px 30px rgba(0,0,0,0.08);">

                            <div style="color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.8;">
                                <?php
                                $terms_content = $settings['legal_terms_content'] ?? '';
                                if (!empty($terms_content)) {
                                    echo nl2br(htmlspecialchars($terms_content));
                                } else {
                                    // Default terms of service content
                                    ?>
                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">1. Acceptance of
                                        Terms</h3>
                                    <p>By accessing and using the services provided by
                                        <?= htmlspecialchars($settings['site_name']) ?>,
                                        you accept and agree to be bound by the terms and provision of this agreement.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">2. Use of Services
                                    </h3>
                                    <p>You agree to use our services only for lawful purposes and in accordance with these
                                        Terms. You agree not to:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>Use our services in any way that violates any applicable law or regulation</li>
                                        <li>Engage in any conduct that restricts or inhibits anyone's use of the services
                                        </li>
                                        <li>Attempt to interfere with the proper working of the services</li>
                                        <li>Use any automated system to access the services</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">3. Service Delivery
                                    </h3>
                                    <p>We strive to provide high-quality investigation and security services. However, we
                                        cannot guarantee specific outcomes or results. All services are provided on an
                                        "as-is" basis.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">4. Confidentiality
                                    </h3>
                                    <p>We maintain strict confidentiality regarding all client information and case details.
                                        Similarly, clients agree to maintain confidentiality regarding our methods,
                                        processes, and proprietary information.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">5. Payment Terms</h3>
                                    <p>Payment terms are as follows:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>Services must be paid according to the agreed payment schedule</li>
                                        <li>Late payments may result in service suspension</li>
                                        <li>Refunds are subject to our refund policy</li>
                                        <li>All fees are non-transferable</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">6. Limitation of
                                        Liability</h3>
                                    <p>To the maximum extent permitted by law,
                                        <?= htmlspecialchars($settings['site_name']) ?>
                                        shall not be liable for any indirect, incidental, special, consequential, or
                                        punitive damages resulting from your use of our services.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">7. Termination</h3>
                                    <p>We reserve the right to terminate or suspend access to our services immediately,
                                        without prior notice, for any reason, including breach of these Terms.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">8. Governing Law</h3>
                                    <p>These Terms shall be governed by and construed in accordance with the laws of India,
                                        without regard to its conflict of law provisions.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">9. Changes to Terms
                                    </h3>
                                    <p>We reserve the right to modify these terms at any time. We will notify users of any
                                        material changes via email or through our website.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">10. Contact
                                        Information</h3>
                                    <p>For any questions regarding these Terms of Service, please contact us at:</p>
                                    <p style="margin-bottom: 0;">
                                        <strong>Email:</strong>
                                        <?= htmlspecialchars($settings['contact_email'] ?? 'info@example.com') ?><br>
                                        <strong>Phone:</strong>
                                        <?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?>
                                    </p>
                                <?php } ?>
                            </div>

                            <div style="margin-top: 40px; padding-top: 30px; border-top: 2px solid #f0f0f0;">
                                <p style="color: #6a6a6a; font-size: 0.9rem; margin-bottom: 0;">
                                    <strong>Last Updated:</strong> <?= date('F d, Y') ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        .breadcrumb-item+.breadcrumb-item::before {
            color: rgba(255, 255, 255, 0.7);
        }
    </style>

    <script>
        AOS.init({
            duration: 700,
            easing: 'ease-in-out',
            once: true,
            mirror: false,
            offset: 100
        });
    </script>
</body>

</html>