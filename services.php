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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- SEO Meta Tags -->
    <title>Services - <?= htmlspecialchars($settings['site_name']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($settings['services_page_subtitle'] ?? 'Professional investigation and security solutions including field investigation, fraud detection, claim verification, surveillance, and comprehensive background checks.') ?>">
    <meta name="keywords" content="investigation services, fraud detection, claim verification, field investigation, surveillance services, background checks, insurance investigation, security solutions">
    <meta name="author" content="<?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://documantraa.in/services">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://documantraa.in/services">
    <meta property="og:title" content="Services - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($settings['services_page_subtitle'] ?? 'Professional investigation and security solutions tailored to your needs') ?>">
    <meta property="og:image" content="https://documantraa.in/assets/images/services-og.jpg">
    <meta property="og:site_name" content="<?= htmlspecialchars($settings['site_name']) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://documantraa.in/services">
    <meta name="twitter:title" content="Services - <?= htmlspecialchars($settings['site_name']) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($settings['services_page_subtitle'] ?? 'Professional investigation and security solutions') ?>">
    <meta name="twitter:image" content="https://documantraa.in/assets/images/services-og.jpg">
    
    <!-- Structured Data (JSON-LD) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Service",
        "serviceType": "Investigation Services",
        "provider": {
            "@type": "Organization",
            "name": "<?= htmlspecialchars($settings['site_name']) ?>",
            "url": "https://documantraa.in"
        },
        "areaServed": {
            "@type": "Country",
            "name": "India"
        },
        "hasOfferCatalog": {
            "@type": "OfferCatalog",
            "name": "Investigation Services",
            "itemListElement": [
                <?php foreach ($services as $index => $service): ?>
                {
                    "@type": "Offer",
                    "itemOffered": {
                        "@type": "Service",
                        "name": "<?= htmlspecialchars($service['title']) ?>",
                        "description": "<?= htmlspecialchars(substr($service['description'], 0, 150)) ?>"
                    }
                }<?= $index < count($services) - 1 ? ',' : '' ?>
                <?php endforeach; ?>
            ]
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

    <!-- Services Hero Section -->
    <section class="services-hero position-relative d-flex align-items-center"
        style="min-height: 50vh; background: linear-gradient(135deg, #dc3545 0%, #dc3545 100%);">
        <div class="container position-relative" style="z-index: 2;">
            <div class="row justify-content-center">
                <div class="col-lg-10 text-center text-white">
                    <div class="d-flex align-items-center justify-content-center" style="margin-bottom: 20px;"
                        data-aos="fade-down" data-aos-duration="700">
                        <div style="width: 50px; height: 2px; background: #ffffff; margin-right: 12px;"></div>
                        <span class="text-uppercase fw-semibold"
                            style="letter-spacing: 2.5px; font-size: clamp(0.7rem, 1.5vw, 0.8rem);">WHAT WE OFFER</span>
                        <div style="width: 50px; height: 2px; background: #ffffff; margin-left: 12px;"></div>
                    </div>
                    <h1 class="fw-bold"
                        style="margin-bottom: 20px; font-size: clamp(2.5rem, 5vw, 4rem); line-height: 1.1; font-family: var(--font-heading);"
                        data-aos="fade-up" data-aos-duration="700">
                        <?= htmlspecialchars($settings['services_page_title'] ?? 'Our Services') ?>
                    </h1>
                    <p class="lead"
                        style="margin-bottom: 0; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95; max-width: 700px; margin-left: auto; margin-right: auto;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">
                        <?= htmlspecialchars($settings['services_page_subtitle'] ?? 'Professional investigation and security solutions tailored to your needs') ?>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Grid -->
    <section class="services-grid" style="padding: 60px 0; background: #f8f9fa;">
        <div class="container">
            <div class="row g-4">
                <?php foreach ($services as $index => $service):
                    $service_slug = $service['slug'] ?? 'service-' . $service['id'];
                    ?>
                    <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-duration="700"
                        data-aos-delay="<?= $index * 100 ?>">
                        <a href="service/<?= htmlspecialchars($service_slug) ?>" class="text-decoration-none">
                            <div class="service-card position-relative h-100"
                                style="min-height: 400px; overflow: hidden; border-radius: 16px; background: #ffffff; box-shadow: 0 4px 20px rgba(0,0,0,0.08); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);">
                                <!-- Service Image -->
                                <?php if (!empty($service['image_path'])): ?>
                                    <div class="service-image" style="height: 220px; overflow: hidden; position: relative;">
                                        <img src="<?= htmlspecialchars($service['image_path']) ?>"
                                            alt="<?= htmlspecialchars($service['title']) ?>"
                                            class="w-100 h-100 object-fit-cover" style="transition: transform 0.6s ease;">
                                        <div class="position-absolute top-0 start-0 w-100 h-100"
                                            style="background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.3) 100%);">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="service-image d-flex align-items-center justify-content-center"
                                        style="height: 220px; background: linear-gradient(135deg, rgba(220, 53, 69, 0.1), rgba(255, 107, 53, 0.1));">
                                        <i class="<?= htmlspecialchars($service['icon_class']) ?>"
                                            style="font-size: 4rem; color: #dc3545; opacity: 0.3;"></i>
                                    </div>
                                <?php endif; ?>

                                <!-- Service Content -->
                                <div class="p-4">
                                    <!-- Icon -->
                                    <div class="d-flex align-items-center justify-content-center"
                                        style="width: 60px; height: 60px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 12px; margin-bottom: 20px; margin-top: -50px; position: relative; box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);">
                                        <i class="<?= htmlspecialchars($service['icon_class']) ?>"
                                            style="font-size: 1.8rem; color: #ffffff;"></i>
                                    </div>

                                    <!-- Title -->
                                    <h4 class="fw-bold"
                                        style="margin-bottom: 15px; color: #1a1a1a; font-size: clamp(1.2rem, 2.5vw, 1.4rem);">
                                        <?= htmlspecialchars($service['title']) ?>
                                    </h4>

                                    <!-- Description -->
                                    <p
                                        style="margin-bottom: 20px; color: #6a6a6a; font-size: clamp(0.85rem, 1.8vw, 0.95rem); line-height: 1.6;">
                                        <?= substr(htmlspecialchars($service['description']), 0, 120) ?>...
                                    </p>

                                    <!-- Learn More Link -->
                                    <div class="d-flex align-items-center fw-semibold"
                                        style="color: #dc3545; font-size: clamp(0.85rem, 1.8vw, 0.95rem);">
                                        <span>Learn More</span>
                                        <i class="bi bi-arrow-right ms-2" style="transition: transform 0.3s;"></i>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="services-cta" style="padding: 60px 0; background: linear-gradient(135deg, #dc3545, #ff6b35);">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center text-white">
                    <h2 class="fw-bold" style="margin-bottom: 20px; font-size: clamp(1.8rem, 4vw, 3rem);"
                        data-aos="fade-up" data-aos-duration="700">Need a Custom Solution?</h2>
                    <p class="lead" style="margin-bottom: 30px; font-size: clamp(1rem, 2vw, 1.2rem); opacity: 0.95;"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="100">Our team of experts is ready to
                        discuss your specific requirements and provide tailored solutions.</p>
                    <a href="index#contact" class="btn btn-lg px-5 py-3 fw-bold text-uppercase"
                        style="background: #ffffff; color: #dc3545; border: none; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);"
                        onmouseover="this.style.transform='translateY(-3px) scale(1.05)'; this.style.boxShadow='0 10px 30px rgba(0, 0, 0, 0.4)';"
                        onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.3)';"
                        data-aos="fade-up" data-aos-duration="700" data-aos-delay="200">
                        <i class="bi bi-envelope-fill me-2"></i> CONTACT US NOW
                    </a>
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
            box-shadow: 0 12px 40px rgba(220, 53, 69, 0.2);
        }

        .service-card:hover .service-image img {
            transform: scale(1.1);
        }

        .service-card:hover .bi-arrow-right {
            transform: translateX(5px);
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