<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

$data = get_about_page_data($pdo);
$settings = $data['settings'];
$services = $data['services'];
$featured_services = array_slice($data['services'], 0, 3);
$team = $data['team'];
$faqs = $data['faqs'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>About Us - <?= htmlspecialchars($settings['site_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($settings['about_main_text'] ?? 'Learn about AFICS Investigation Agency - Leading investigation experts with 25+ years of excellence in fraud detection, claim verification, and field investigation services.') ?>">
    <meta name="keywords" content="about AFICS, investigation agency, fraud detection experts, claim verification, field investigation, insurance investigation services">
    <meta name="author" content="<?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://documantraa.in/about">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://documantraa.in/about">
    <meta property="og:title" content="About Us - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($settings['about_main_text'] ?? 'Leading investigation experts with 25+ years of excellence.') ?>">
    <meta property="og:image" content="https://documantraa.in/<?= htmlspecialchars($settings['about_hero_bg_image'] ?? 'assets/images/about-hero.jpg') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($settings['site_name']) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://documantraa.in/about">
    <meta name="twitter:title" content="About Us - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($settings['about_main_text'] ?? 'Leading investigation experts with 25+ years of excellence.') ?>">
    <meta name="twitter:image" content="https://documantraa.in/<?= htmlspecialchars($settings['about_hero_bg_image'] ?? 'assets/images/about-hero.jpg') ?>">
    
    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AboutPage",
        "name": "About <?= htmlspecialchars($settings['site_name']) ?>",
        "description": "<?= htmlspecialchars($settings['about_main_text'] ?? 'Leading investigation experts') ?>",
        "url": "https://documantraa.in/about",
        "mainEntity": {
            "@type": "Organization",
            "name": "<?= htmlspecialchars($settings['site_name']) ?>",
            "foundingDate": "<?= date('Y') - intval($settings['about_exp_years'] ?? 25) ?>",
            "description": "<?= htmlspecialchars($settings['about_main_text'] ?? '') ?>"
        }
    }
    </script>
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
    <link rel="manifest" href="assets/favicon/site.webmanifest">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php render_dynamic_css($settings); ?>
</head>

<body>

    <?php include 'includes/top-bar.php'; ?>
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section - Professional Design -->
    <section class="about-hero position-relative d-flex align-items-center"
        style="min-height: 70vh; background-image: url('<?= htmlspecialchars($settings['about_hero_bg_image'] ?? 'assets/images/about-hero.jpg') ?>'); background-size: cover; background-position: center; background-attachment: fixed;">
        <div class="position-absolute w-100 h-100"
            style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.85), rgba(255, 107, 53, 0.85)); top:0; left:0;">
        </div>

        <div class="container position-relative" style="z-index: 2;">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center text-white">
                    <div class="d-flex align-items-center justify-content-center" style="margin-bottom: 20px;"
                        data-aos="fade-down" data-aos-duration="700">
                        <div style="width: 50px; height: 2px; background: #ffffff; margin-right: 12px;"></div>
                        <span class="text-uppercase fw-semibold"
                            style="letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem);">ABOUT US</span>
                        <div style="width: 50px; height: 2px; background: #ffffff; margin-left: 12px;"></div>
                    </div>
                    <h1 class="fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; font-family: var(--font-heading);"
                        data-aos="fade-up" data-aos-duration="700">
                        <?= htmlspecialchars($settings['about_hero_headline'] ?? 'Strategic Claim Intelligence') ?>
                    </h1>
                    <p class="lead"
                        style="margin-bottom: 30px; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95; max-width: 800px; margin-left: auto; margin-right: auto;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
                        <?= htmlspecialchars($settings['about_hero_subtext'] ?? 'Bridging the gap between physical truth and digital forensics.') ?>
                    </p>
                    <a href="#about-content" class="btn btn-lg px-5 py-3 fw-bold text-uppercase"
                        style="background: #ffffff; color: #dc3545; border: none; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);"
                        onmouseover="this.style.transform='translateY(-3px) scale(1.05)'; this.style.boxShadow='0 10px 30px rgba(0, 0, 0, 0.4)';"
                        onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.3)';"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                        <i class="bi bi-arrow-down-circle me-2"></i> DISCOVER MORE
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Highlights - Glassmorphism Cards -->
    <section class="service-highlights" style="margin-top: -80px; position: relative; z-index: 3; padding: 20px 0;">
        <div class="container">
            <div class="row g-4">
                <?php foreach ($featured_services as $index => $service):
                    $service_slug = $service['slug'] ?? 'service-' . $service['id'];
                    ?>
                    <div class="col-md-4" data-aos="fade-up" data-aos-duration="700" data-aos-delay="<?= $index * 100 ?>">
                        <div class="position-relative service-card h-100"
                            style="min-height: 320px; overflow: hidden; border-radius: 16px; background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); box-shadow: 0 8px 32px rgba(0,0,0,0.1); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                            <?php if (!empty($service['image_path'])): ?>
                                <div style="height: 180px; overflow: hidden;">
                                    <img src="<?= htmlspecialchars($service['image_path']) ?>"
                                        alt="<?= htmlspecialchars($service['title']) ?>" class="w-100 h-100 object-fit-cover"
                                        style="transition: transform 0.6s ease;">
                                </div>
                            <?php endif; ?>

                            <div class="p-4">
                                <div class="d-flex align-items-center" style="margin-bottom: 15px;">
                                    <div
                                        style="width: 40px; height: 3px; background: linear-gradient(90deg, #dc3545, #ff6b35); margin-right: 10px;">
                                    </div>
                                    <span class="text-uppercase fw-semibold"
                                        style="color: #dc3545; font-size: clamp(0.7rem, 1.5vw, 0.75rem); letter-spacing: 1px;">SERVICE</span>
                                </div>
                                <h4 class="fw-bold"
                                    style="margin-bottom: 15px; color: #1a1a1a; font-size: clamp(1.2rem, 2.5vw, 1.5rem);">
                                    <?= htmlspecialchars($service['title']) ?>
                                </h4>
                                <p
                                    style="margin-bottom: 15px; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                    <?= substr(htmlspecialchars($service['description']), 0, 100) ?>...
                                </p>
                                <a href="service/<?= htmlspecialchars($service_slug) ?>"
                                    class="text-decoration-none fw-semibold d-inline-flex align-items-center"
                                    style="color: #dc3545; font-size: clamp(0.8rem, 1.8vw, 0.9rem); transition: all 0.3s;"
                                    onmouseover="this.style.paddingLeft='5px';" onmouseout="this.style.paddingLeft='0';">
                                    Learn More <i class="bi bi-arrow-right ms-2"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Content Section -->
    <section id="about-content" class="experience-section" style="padding: 60px 0; background: #ffffff;">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 text-center" data-aos="fade-right" data-aos-duration="700">
                    <div class="position-relative d-inline-block">
                        <div class="display-1 fw-bold experience-number"
                            style="font-size: clamp(5rem, 10vw, 8rem); background: linear-gradient(135deg, #dc3545, #ff6b35); -webkit-background-clip: text; -webkit-text-fill-color: transparent; line-height: 1;">
                            <?= htmlspecialchars($settings['about_exp_years'] ?? '25') ?>+
                        </div>
                        <div class="h4 text-uppercase fw-bold"
                            style="color: #1a1a1a; margin-top: 10px; font-size: clamp(1.2rem, 2.5vw, 1.5rem);">Years of
                            Excellence</div>
                    </div>
                </div>
                <div class="col-lg-7" data-aos="fade-left" data-aos-duration="700">
                    <div class="d-flex align-items-center" style="margin-bottom: 20px;">
                        <div style="width: 50px; height: 2px; background: #dc3545; margin-right: 12px;"></div>
                        <span class="text-uppercase fw-semibold"
                            style="color: #dc3545; letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem);">ABOUT
                            DOCUMANTRAA</span>
                    </div>
                    <h2 class="fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(1.8rem, 4vw, 3.2rem); color: #1a1a1a; line-height: 1.2;">
                        <?= htmlspecialchars($settings['about_main_heading'] ?? 'Leading Investigation Experts') ?>
                    </h2>
                    <p
                        style="margin-bottom: 30px; color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.7;">
                        <?= htmlspecialchars($settings['about_main_text'] ?? '') ?>
                    </p>

                    <div class="row g-4" style="margin-bottom: 30px;">
                        <div class="col-md-6">
                            <div
                                style="padding: 20px; background: rgba(220, 53, 69, 0.05); border-radius: 12px; border-left: 4px solid #dc3545;">
                                <h5 class="fw-bold"
                                    style="margin-bottom: 12px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.2rem);"><i
                                        class="bi bi-eye-fill me-2"
                                        style="color: #dc3545;"></i><?= htmlspecialchars($settings['about_vision_title'] ?? 'Our Vision') ?>
                                </h5>
                                <p
                                    style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                    <?= htmlspecialchars($settings['about_vision_text'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div
                                style="padding: 20px; background: rgba(255, 107, 53, 0.05); border-radius: 12px; border-left: 4px solid #ff6b35;">
                                <h5 class="fw-bold"
                                    style="margin-bottom: 12px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.2rem);"><i
                                        class="bi bi-bullseye me-2"
                                        style="color: #ff6b35;"></i><?= htmlspecialchars($settings['about_mission_title'] ?? 'Our Mission') ?>
                                </h5>
                                <p
                                    style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                    <?= htmlspecialchars($settings['about_mission_text'] ?? '') ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <a href="<?= htmlspecialchars($settings['about_discover_link'] ?? 'services') ?>"
                        class="btn btn-lg px-5 py-3 fw-bold text-uppercase"
                        style="background: linear-gradient(135deg, #dc3545, #ff6b35); color: #ffffff; border: none; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; box-shadow: 0 6px 20px rgba(220, 53, 69, 0.35); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);"
                        onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 10px 30px rgba(220, 53, 69, 0.45)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 20px rgba(220, 53, 69, 0.35)';">
                        <i class="bi bi-arrow-right-circle me-2"></i> DISCOVER MORE
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Skills/Expertise Section -->
    <section class="skills-section" style="padding: 60px 0; background: #f8f9fa;">
        <div class="container">
            <div class="row g-5">
                <div class="col-lg-4" data-aos="fade-right" data-aos-duration="700">
                    <div class="d-flex align-items-center" style="margin-bottom: 20px;">
                        <div style="width: 50px; height: 2px; background: #dc3545; margin-right: 12px;"></div>
                        <span class="text-uppercase fw-semibold"
                            style="color: #dc3545; letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem);">EXPERTISE</span>
                    </div>
                    <h2 class="fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(1.8rem, 4vw, 3.2rem); color: #1a1a1a;">
                        <?= htmlspecialchars($settings['about_skills_title'] ?? 'Our Expertise') ?>
                    </h2>
                    <p style="color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.7;">
                        <?= htmlspecialchars($settings['about_skills_text'] ?? '') ?>
                    </p>
                </div>
                <div class="col-lg-8" data-aos="fade-left" data-aos-duration="700">
                    <?php for ($i = 1; $i <= 4; $i++):
                        $label = $settings["skill_{$i}_label"] ?? '';
                        $percent = $settings["skill_{$i}_percent"] ?? 0;
                        if ($label):
                            ?>
                            <div style="margin-bottom: 25px;">
                                <div class="d-flex justify-content-between align-items-center" style="margin-bottom: 10px;">
                                    <span class="fw-bold"
                                        style="color: #1a1a1a; font-size: clamp(0.95rem, 2vw, 1.05rem);"><?= htmlspecialchars($label) ?></span>
                                    <span class="fw-bold"
                                        style="color: #dc3545; font-size: clamp(0.95rem, 2vw, 1.05rem);"><?= $percent ?>%</span>
                                </div>
                                <div class="progress"
                                    style="height: 12px; background: rgba(220, 53, 69, 0.1); border-radius: 10px; overflow: hidden;">
                                    <div class="progress-bar" role="progressbar"
                                        style="width: <?= $percent ?>%; background: linear-gradient(90deg, #dc3545, #ff6b35); transition: width 1.5s ease;"
                                        aria-valuenow="<?= $percent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endif; endfor; ?>
                </div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 12px 48px rgba(220, 53, 69, 0.2);
        }

        .service-card:hover img {
            transform: scale(1.1);
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