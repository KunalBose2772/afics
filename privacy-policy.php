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
    <title>Privacy Policy - <?= htmlspecialchars($settings['site_name']) ?></title>
    <meta name="description" content="Privacy Policy for <?= htmlspecialchars($settings['site_name']) ?>">
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
                                Privacy Policy
                            </li>
                        </ol>
                    </nav>

                    <div class="d-flex align-items-center justify-content-center" style="margin-bottom: 20px;"
                        data-aos="fade-up" data-aos-duration="700">
                        <div
                            style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <i class="bi bi-shield-lock-fill" style="font-size: 2rem; color: #ffffff;"></i>
                        </div>
                        <div>
                            <span class="text-uppercase fw-semibold text-white"
                                style="letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem); opacity: 0.9;">LEGAL
                                INFORMATION</span>
                        </div>
                    </div>

                    <h1 class="text-white fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; font-family: var(--font-heading);"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
                        Privacy Policy
                    </h1>

                    <p class="text-white lead"
                        style="margin-bottom: 0; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95; max-width: 800px; margin-left: auto; margin-right: auto; line-height: 1.6;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                        Your privacy is important to us. Learn how we collect, use, and protect your information.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Content Section -->
    <section class="privacy-content" style="padding: 80px 0; background: #ffffff;">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <div data-aos="fade-up" data-aos-duration="700">
                        <div class="content-wrapper"
                            style="background: #ffffff; padding: 50px; border-radius: 16px; box-shadow: 0 4px 30px rgba(0,0,0,0.08);">

                            <div style="color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.8;">
                                <?php
                                $privacy_content = $settings['legal_privacy_content'] ?? '';
                                if (!empty($privacy_content)) {
                                    echo nl2br(htmlspecialchars($privacy_content));
                                } else {
                                    // Default privacy policy content
                                    ?>
                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">1. Information We
                                        Collect</h3>
                                    <p>We collect information that you provide directly to us, including but not limited
                                        to:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>Name and contact information</li>
                                        <li>Email address and phone number</li>
                                        <li>Service inquiries and requests</li>
                                        <li>Payment and billing information</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">2. How We Use Your
                                        Information</h3>
                                    <p>We use the information we collect to:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>Provide, maintain, and improve our services</li>
                                        <li>Process transactions and send related information</li>
                                        <li>Send you technical notices and support messages</li>
                                        <li>Respond to your comments and questions</li>
                                        <li>Communicate with you about products, services, and events</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">3. Information
                                        Sharing</h3>
                                    <p>We do not share your personal information with third parties except:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>With your consent</li>
                                        <li>To comply with legal obligations</li>
                                        <li>To protect our rights and prevent fraud</li>
                                        <li>With service providers who assist in our operations</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">4. Data Security</h3>
                                    <p>We implement appropriate technical and organizational measures to protect your
                                        personal information against unauthorized access, alteration, disclosure, or
                                        destruction.</p>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">5. Your Rights</h3>
                                    <p>You have the right to:</p>
                                    <ul style="margin-bottom: 30px;">
                                        <li>Access your personal information</li>
                                        <li>Correct inaccurate data</li>
                                        <li>Request deletion of your data</li>
                                        <li>Object to processing of your data</li>
                                        <li>Request data portability</li>
                                    </ul>

                                    <h3 class="fw-bold" style="margin-bottom: 20px; color: #1a1a1a;">6. Contact Us</h3>
                                    <p>If you have any questions about this Privacy Policy, please contact us at:</p>
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