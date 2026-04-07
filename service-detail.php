<?php
require_once 'config/db.php';
require_once 'includes/functions.php';

// Define base path for relative links
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_path = $base_path ? $base_path . '/' : '/';

// Get service slug from URL
$slug = isset($_GET['slug']) ? $_GET['slug'] : '';

if (empty($slug)) {
    header('Location: ' . $base_path . 'services');
    exit;
}

// Get service details by slug
$stmt = $pdo->prepare("SELECT * FROM services WHERE slug = ?");
$stmt->execute([$slug]);
$service = $stmt->fetch();

// If slug doesn't exist, try by ID for backward compatibility
if (!$service && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([intval($_GET['id'])]);
    $service = $stmt->fetch();
}

if (!$service) {
    header('Location: ' . $base_path . 'services');
    exit;
}

// Get all services for navbar and sidebar
$settings = get_settings($pdo);
$services = get_services($pdo);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title><?= htmlspecialchars($service['title']) ?> - <?= htmlspecialchars($settings['site_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars(substr($service['description'], 0, 160)) ?>">
    <meta name="keywords" content="<?= htmlspecialchars($service['title']) ?>, investigation services, <?= htmlspecialchars($settings['site_name']) ?>, fraud detection, claim verification">
    <meta name="author" content="<?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://documantraa.in/service/<?= htmlspecialchars($service['slug']) ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="article">
    <meta property="og:url" content="https://documantraa.in/service/<?= htmlspecialchars($service['slug']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($service['title']) ?> - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars(substr($service['description'], 0, 160)) ?>">
    <meta property="og:image" content="https://documantraa.in/<?= htmlspecialchars($service['image_path'] ?? 'assets/images/service-default.jpg') ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($settings['site_name']) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://documantraa.in/service/<?= htmlspecialchars($service['slug']) ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($service['title']) ?> - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars(substr($service['description'], 0, 160)) ?>">
    <meta name="twitter:image" content="https://documantraa.in/<?= htmlspecialchars($service['image_path'] ?? 'assets/images/service-default.jpg') ?>">
    
    <!-- Structured Data (JSON-LD) - Service Schema -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "serviceType": "<?= htmlspecialchars($service['title']) ?>",
        "name": "<?= htmlspecialchars($service['title']) ?>",
        "description": "<?= htmlspecialchars($service['description']) ?>",
        "provider": {
            "@type": "Organization",
            "name": "<?= htmlspecialchars($settings['site_name']) ?>",
            "url": "https://documantraa.in"
        },
        "areaServed": {
            "@type": "Country",
            "name": "India"
        },
        "url": "https://documantraa.in/service/<?= htmlspecialchars($service['slug']) ?>"
    }
    </script>
    
    <!-- Breadcrumb Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [{
            "@type": "ListItem",
            "position": 1,
            "name": "Home",
            "item": "https://documantraa.in/"
        },{
            "@type": "ListItem",
            "position": 2,
            "name": "Services",
            "item": "https://documantraa.in/services"
        },{
            "@type": "ListItem",
            "position": 3,
            "name": "<?= htmlspecialchars($service['title']) ?>",
            "item": "https://documantraa.in/service/<?= htmlspecialchars($service['slug']) ?>"
        }]
    }
    </script>
    
    <!-- Favicon -->
    <link rel="apple-touch-icon" sizes="180x180" href="<?= $base_path ?>assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="<?= $base_path ?>assets/favicon/site.webmanifest">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $base_path ?>assets/css/style.css">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <?php render_dynamic_css($settings); ?>
</head>

<body>

    <?php include __DIR__ . '/includes/top-bar.php'; ?>
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- Service Hero Section -->
    <section class="service-detail-hero position-relative d-flex align-items-center"
        style="min-height: 60vh; background-image: url('<?= $base_path . htmlspecialchars($service['image_path'] ?? 'assets/images/service-default.jpg') ?>'); background-size: cover; background-position: center; background-attachment: fixed;">
        <div class="position-absolute w-100 h-100"
            style="background: linear-gradient(135deg, rgba(220, 53, 69, 0.9), rgba(139, 0, 0, 0.9)); top:0; left:0;">
        </div>

        <div class="container position-relative" style="z-index: 2; padding-top: 20px; padding-bottom: 20px;">
            <div class="row">
                <div class="col-lg-10">
                    <!-- Breadcrumb -->
                    <nav aria-label="breadcrumb" style="margin-bottom: 20px;" data-aos="fade-down"
                        data-aos-duration="700">
                        <ol class="breadcrumb"
                            style="background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); padding: 12px 20px; border-radius: 50px; display: inline-flex; margin-bottom: 0;">
                            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_path) ?>index"
                                    style="color: #ffffff; text-decoration: none; font-size: clamp(0.8rem, 1.6vw, 0.9rem); transition: opacity 0.3s;"
                                    onmouseover="this.style.opacity='0.8';"
                                    onmouseout="this.style.opacity='1';">Home</a></li>
                            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($base_path) ?>services"
                                    style="color: #ffffff; text-decoration: none; font-size: clamp(0.8rem, 1.6vw, 0.9rem); transition: opacity 0.3s;"
                                    onmouseover="this.style.opacity='0.8';"
                                    onmouseout="this.style.opacity='1';">Services</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page"
                                style="font-size: clamp(0.8rem, 1.6vw, 0.9rem);">
                                <?= htmlspecialchars($service['title']) ?>
                            </li>
                        </ol>
                    </nav>

                    <div class="d-flex align-items-center" style="margin-bottom: 20px;" data-aos="fade-up"
                        data-aos-duration="700">
                        <div
                            style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin-right: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <i class="<?= htmlspecialchars($service['icon_class']) ?>"
                                style="font-size: 2rem; color: #ffffff;"></i>
                        </div>
                        <div>
                            <span class="text-uppercase fw-semibold text-white"
                                style="letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem); opacity: 0.9;">SERVICE
                                DETAIL</span>
                        </div>
                    </div>

                    <h1 class="text-white fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; font-family: var(--font-heading);"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
                        <?= htmlspecialchars($service['title']) ?>
                    </h1>

                    <p class="text-white lead"
                        style="margin-bottom: 30px; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95; max-width: 800px; line-height: 1.6;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                        <?= htmlspecialchars(substr($service['description'], 0, 200)) ?>...
                    </p>

                    <a href="#service-content" class="btn btn-lg px-5 py-3 fw-bold text-uppercase"
                        style="background: #ffffff; color: #dc3545; border: none; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);"
                        onmouseover="this.style.transform='translateY(-3px) scale(1.05)'; this.style.boxShadow='0 10px 30px rgba(0, 0, 0, 0.4)';"
                        onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.3)';"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="300">
                        <i class="bi bi-arrow-down-circle me-2"></i> READ MORE
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Service Content -->
    <section id="service-content" class="service-content" style="padding: 60px 0; background: #ffffff;">
        <div class="container">
            <div class="row g-5">
                <!-- Main Content -->
                <div class="col-lg-8">
                    <div data-aos="fade-up" data-aos-duration="700">
                        <div class="d-flex align-items-center" style="margin-bottom: 20px;">
                            <div style="width: 50px; height: 2px; background: #dc3545; margin-right: 12px;"></div>
                            <span class="text-uppercase fw-semibold"
                                style="color: #dc3545; letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem);">OVERVIEW</span>
                        </div>

                        <h2 class="fw-bold"
                            style="margin-bottom: 20px; font-size: clamp(1.8rem, 4vw, 2.5rem); color: #1a1a1a;">About
                            This Service</h2>

                        <div
                            style="color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.8; margin-bottom: 30px;">
                            <?= nl2br(htmlspecialchars($service['description'])) ?>
                        </div>

                        <?php if (!empty($service['long_description'])): ?>
                            <div
                                style="padding: 30px; background: rgba(220, 53, 69, 0.03); border-radius: 16px; border-left: 4px solid #dc3545; margin-bottom: 30px;">
                                <h3 class="fw-bold"
                                    style="margin-bottom: 15px; font-size: clamp(1.3rem, 3vw, 1.8rem); color: #1a1a1a;">
                                    Detailed Information</h3>
                                <div style="color: #4a4a4a; font-size: clamp(0.95rem, 2vw, 1.05rem); line-height: 1.8;">
                                    <?= nl2br(htmlspecialchars($service['long_description'])) ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Key Benefits -->
                        <div style="margin-top: 40px;">
                            <h3 class="fw-bold"
                                style="margin-bottom: 25px; font-size: clamp(1.5rem, 3vw, 2rem); color: #1a1a1a;">Why
                                Choose This Service?</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start"
                                        style="padding: 20px; background: #ffffff; border-radius: 12px; border: 1px solid rgba(220, 53, 69, 0.1); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                        <div
                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 15px;">
                                            <i class="bi bi-check-lg"
                                                style="color: #ffffff; font-size: 1.2rem; font-weight: bold;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold"
                                                style="margin-bottom: 8px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.1rem);">
                                                Professional Expertise</h5>
                                            <p
                                                style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                                Experienced team with proven track record</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start"
                                        style="padding: 20px; background: #ffffff; border-radius: 12px; border: 1px solid rgba(220, 53, 69, 0.1); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                        <div
                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 15px;">
                                            <i class="bi bi-shield-check"
                                                style="color: #ffffff; font-size: 1.2rem;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold"
                                                style="margin-bottom: 8px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.1rem);">
                                                Confidential & Secure</h5>
                                            <p
                                                style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                                Complete privacy and data protection</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start"
                                        style="padding: 20px; background: #ffffff; border-radius: 12px; border: 1px solid rgba(220, 53, 69, 0.1); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                        <div
                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 15px;">
                                            <i class="bi bi-clock-history"
                                                style="color: #ffffff; font-size: 1.2rem;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold"
                                                style="margin-bottom: 8px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.1rem);">
                                                Quick Turnaround</h5>
                                            <p
                                                style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                                Fast and efficient service delivery</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-start"
                                        style="padding: 20px; background: #ffffff; border-radius: 12px; border: 1px solid rgba(220, 53, 69, 0.1); box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
                                        <div
                                            style="width: 40px; height: 40px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-right: 15px;">
                                            <i class="bi bi-graph-up-arrow"
                                                style="color: #ffffff; font-size: 1.2rem;"></i>
                                        </div>
                                        <div>
                                            <h5 class="fw-bold"
                                                style="margin-bottom: 8px; color: #1a1a1a; font-size: clamp(1rem, 2vw, 1.1rem);">
                                                Proven Results</h5>
                                            <p
                                                style="margin-bottom: 0; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                                High success rate and client satisfaction</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Contact Card -->
                    <div style="padding: 30px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 16px; margin-bottom: 30px; box-shadow: 0 8px 30px rgba(220, 53, 69, 0.3);"
                        data-aos="fade-left" data-aos-duration="700">
                        <div class="text-center" style="margin-bottom: 20px;">
                            <div
                                style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                <i class="bi bi-headset" style="font-size: 1.8rem; color: #ffffff;"></i>
                            </div>
                        </div>
                        <h4 class="text-white fw-bold text-center"
                            style="margin-bottom: 15px; font-size: clamp(1.2rem, 2.5vw, 1.5rem);">Need This Service?
                        </h4>
                        <p class="text-white text-center"
                            style="margin-bottom: 25px; opacity: 0.95; font-size: clamp(0.9rem, 1.8vw, 1rem); line-height: 1.6;">
                            Get in touch with our experts for a free consultation.</p>
                        <a href="<?= htmlspecialchars($base_path) ?>index#contact" class="btn btn-lg w-100 fw-bold text-uppercase"
                            style="background: #ffffff; color: #dc3545; border: none; border-radius: 50px; padding: 15px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.2);"
                            onmouseover="this.style.transform='translateY(-3px)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.3)';"
                            onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.2)';">
                            <i class="bi bi-envelope-fill me-2"></i> CONTACT US
                        </a>
                    </div>

                    <!-- Other Services -->
                    <div style="padding: 30px; background: #f8f9fa; border-radius: 16px;" data-aos="fade-left"
                        data-aos-duration="700" data-aos-delay="100">
                        <h4 class="fw-bold"
                            style="margin-bottom: 25px; font-size: clamp(1.2rem, 2.5vw, 1.5rem); color: #1a1a1a;">Other
                            Services</h4>
                        <div class="d-flex flex-column gap-3">
                            <?php
                            $other_services = array_filter($services, function ($s) use ($service) {
                                return $s['id'] != $service['id'];
                            });
                            $other_services = array_slice($other_services, 0, 5);
                            foreach ($other_services as $other):
                                $other_slug = $other['slug'] ?? 'service-' . $other['id'];
                                ?>
                                <a href="<?= htmlspecialchars($base_path) ?>service/<?= htmlspecialchars($other_slug) ?>"
                                    class="text-decoration-none d-flex align-items-center p-3 service-link"
                                    style="background: #ffffff; border-radius: 12px; border: 1px solid rgba(220, 53, 69, 0.1); transition: all 0.3s;">
                                    <i class="<?= htmlspecialchars($other['icon_class']) ?> me-3"
                                        style="color: #dc3545; font-size: 1.5rem; flex-shrink: 0;"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"
                                            style="color: #1a1a1a; font-size: clamp(0.9rem, 1.8vw, 1rem);">
                                            <?= htmlspecialchars($other['title']) ?>
                                        </div>
                                    </div>
                                    <i class="bi bi-arrow-right" style="color: #dc3545; flex-shrink: 0;"></i>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <a href="<?= htmlspecialchars($base_path) ?>services" class="btn btn-outline-danger w-100 mt-3 fw-semibold"
                            style="border-radius: 50px; padding: 12px; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s;"
                            onmouseover="this.style.background='linear-gradient(135deg, #dc3545, #ff6b35)'; this.style.color='#ffffff'; this.style.borderColor='transparent';"
                            onmouseout="this.style.background='transparent'; this.style.color='#dc3545'; this.style.borderColor='#dc3545';">
                            VIEW ALL SERVICES
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

    <style>
        .service-link:hover {
            transform: translateX(5px);
            border-color: #dc3545 !important;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
        }

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