<!-- CTA Section - Professional Design -->
<section class="cta-section position-relative"
    style="padding: 60px 0; background: linear-gradient(135deg, #dc3545 0%, #dc3545 100%); overflow: hidden;">
    <!-- Pattern Overlay -->
    <div class="position-absolute w-100 h-100" style="top: 0; left: 0; opacity: 0.1; background-image: 
        linear-gradient(rgba(255, 255, 255, 0.3) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255, 255, 255, 0.3) 1px, transparent 1px);
        background-size: 50px 50px;"></div>

    <div class="container position-relative" style="z-index: 2;">
        <div class="row align-items-center g-4">
            <div class="col-lg-8 text-center text-lg-start">
                <div class="d-flex align-items-center justify-content-center justify-content-lg-start"
                    style="margin-bottom: 15px;">
                    <div
                        style="width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; backdrop-filter: blur(10px);">
                        <i class="bi bi-telephone-fill"
                            style="font-size: clamp(1.5rem, 3vw, 1.8rem); color: #ffffff;"></i>
                    </div>
                    <div>
                        <div class="text-uppercase fw-semibold text-white"
                            style="opacity: 0.9; font-size: clamp(0.75rem, 1.5vw, 0.85rem); letter-spacing: 2px; margin-bottom: 5px;">
                            <?= htmlspecialchars($settings['footer_cta_subtext'] ?? 'On-Call Service 24/7') ?>
                        </div>
                        <h3 class="text-white fw-bold mb-0"
                            style="font-size: clamp(1.5rem, 3vw, 2.2rem); font-family: var(--font-heading);">
                            <?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?>
                        </h3>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end">
                <a href="<?= htmlspecialchars($settings['footer_cta_btn_link'] ?? 'index#contact') ?>"
                    class="btn btn-lg px-5 py-3 fw-bold text-uppercase"
                    style="background: #ffffff; color: #dc3545; border: none; border-radius: 50px; font-size: clamp(0.8rem, 1.8vw, 0.9rem); letter-spacing: 1px; box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2); transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);"
                    onmouseover="this.style.transform='translateY(-3px) scale(1.05)'; this.style.boxShadow='0 10px 30px rgba(0, 0, 0, 0.3)';"
                    onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 6px 20px rgba(0, 0, 0, 0.2)';">
                    <i class="bi bi-arrow-right-circle-fill me-2"></i>
                    <?= htmlspecialchars($settings['footer_cta_btn_text'] ?? 'Get Started') ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Main Footer - Professional Design -->
<footer class="footer-custom"
    style="background: #0a0a0a; color: rgba(255,255,255,0.7); padding: 60px 0 0; border-top: 1px solid rgba(255,255,255,0.05);">
    <div class="container">
        <div class="row g-4">
            <!-- Column 1: Brand & Description -->
            <div class="col-lg-4 mb-4">
                <a class="navbar-brand d-block" href="index" style="margin-bottom: 20px;">
                    <?php
                    $footer_logo = !empty($settings['footer_logo']) ? $settings['footer_logo'] : ($settings['site_logo'] ?? '');
                    if (!empty($footer_logo)):
                        ?>
                        <img src="<?= htmlspecialchars($footer_logo) ?>"
                            alt="<?= htmlspecialchars($settings['site_name']) ?>" style="max-height: 50px;">
                    <?php else: ?>
                        <div class="d-flex align-items-center">
                            <div
                                style="width: 40px; height: 40px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 8px; display: flex; align-items: center; justify-content: center; margin-right: 12px;">
                                <i class="bi bi-shield-fill-check" style="font-size: 1.2rem; color: #ffffff;"></i>
                            </div>
                            <span
                                style="font-family: var(--font-heading); font-size: clamp(1.3rem, 2.5vw, 1.6rem); color: #ffffff; font-weight: 700;"><?= htmlspecialchars($settings['site_name']) ?></span>
                        </div>
                    <?php endif; ?>
                </a>
                <p
                    style="font-size: clamp(0.9rem, 1.8vw, 1rem); line-height: 1.7; color: rgba(255,255,255,0.6); margin-bottom: 20px;">
                    <?= htmlspecialchars($settings['footer_about_text'] ?? 'Empowering physicians with advanced multi-modal tools to improve treatment selection and patient outcomes.') ?>
                </p>

                <h6 class="text-uppercase fw-semibold text-white"
                    style="margin-bottom: 15px; font-size: clamp(0.8rem, 1.5vw, 0.9rem); letter-spacing: 1px;">
                    FOLLOW US
                </h6>
                <div class="d-flex gap-3">
                    <?php if (!empty($settings['social_facebook'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_facebook']) ?>" class="social-link"
                            style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.7); transition: all 0.3s;"><i
                                class="bi bi-facebook"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_twitter'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_twitter']) ?>" class="social-link"
                            style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.7); transition: all 0.3s;"><i
                                class="bi bi-twitter"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_instagram'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_instagram']) ?>" class="social-link"
                            style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.7); transition: all 0.3s;"><i
                                class="bi bi-instagram"></i></a>
                    <?php endif; ?>
                    <?php if (!empty($settings['social_linkedin'])): ?>
                        <a href="<?= htmlspecialchars($settings['social_linkedin']) ?>" class="social-link"
                            style="width: 40px; height: 40px; background: rgba(255,255,255,0.05); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: rgba(255,255,255,0.7); transition: all 0.3s;"><i
                                class="bi bi-linkedin"></i></a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Column 2: Quick Links -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="fw-bold text-white" style="margin-bottom: 20px; font-size: clamp(1rem, 2vw, 1.1rem);">
                    QUICK LINKS
                </h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="index" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Home</a>
                    </li>
                    <li><a href="about" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">About
                            Us</a></li>
                    <li><a href="services" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Services</a>
                    </li>
                    <li><a href="index#faq" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">FAQ's</a>
                    </li>
                    <li><a href="index#contact" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Contact</a>
                    </li>
                </ul>
            </div>

            <!-- Column 3: Login Links -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="fw-bold text-white" style="margin-bottom: 20px; font-size: clamp(1rem, 2vw, 1.1rem);">LOGIN
                </h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="crm/login?role=admin" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Admin</a>
                    </li>
                    <li><a href="crm/login?role=hr" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">HR</a>
                    </li>
                    <li><a href="crm/login?role=office_staff" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Staff</a>
                    </li>
                    <li><a href="crm/login?role=employee" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Employee</a>
                    </li>
                    <li><a href="crm/login?role=freelancer" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Freelancer</a>
                    </li>
                </ul>
            </div>

            <!-- Column 4: Legal -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="fw-bold text-white" style="margin-bottom: 20px; font-size: clamp(1rem, 2vw, 1.1rem);">
                    LEGAL
                </h5>
                <ul class="list-unstyled d-flex flex-column gap-2">
                    <li><a href="/privacy-policy" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Privacy
                            Policy</a></li>
                    <li><a href="/terms-of-service" class="footer-link"
                            style="color: rgba(255,255,255,0.6); text-decoration: none; font-size: clamp(0.85rem, 1.8vw, 0.95rem); transition: all 0.3s; display: inline-block;">Terms
                            of Service</a></li>
                </ul>
            </div>

            <!-- Column 5: Contact Us -->
            <div class="col-lg-2 col-md-3 col-6 mb-4">
                <h5 class="fw-bold text-white" style="margin-bottom: 20px; font-size: clamp(1rem, 2vw, 1.1rem);">
                    CONTACT
                </h5>
                <ul class="list-unstyled d-flex flex-column gap-3">
                    <li class="d-flex align-items-start">
                        <i class="bi bi-geo-alt-fill me-2 mt-1"
                            style="color: #dc3545; font-size: 1.1rem; flex-shrink: 0;"></i>
                        <span
                            style="font-size: clamp(0.8rem, 1.6vw, 0.85rem); color: rgba(255,255,255,0.6); line-height: 1.5;"><?= htmlspecialchars($settings['contact_address'] ?? 'AFICS SOUTH ZONE HEAD OFFICE, FEROKE (PO) 673631, KOZHIKODE KERALA') ?></span>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-envelope-fill me-2"
                            style="color: #dc3545; font-size: 1.1rem; flex-shrink: 0;"></i>
                        <a href="mailto:<?= htmlspecialchars($settings['contact_email'] ?? '') ?>" class="footer-link"
                            style="font-size: clamp(0.8rem, 1.6vw, 0.85rem); color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.3s;"><?= htmlspecialchars($settings['contact_email'] ?? '') ?></a>
                    </li>
                    <li class="d-flex align-items-center">
                        <i class="bi bi-telephone-fill me-2"
                            style="color: #dc3545; font-size: 1.1rem; flex-shrink: 0;"></i>
                        <a href="tel:<?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?>" class="footer-link"
                            style="font-size: clamp(0.8rem, 1.6vw, 0.85rem); color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.3s;"><?= htmlspecialchars($settings['contact_phone'] ?? '+91 755 883 4483') ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="footer-bottom"
        style="background: #000000; padding: 25px 0; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.05);">
        <div class="container">
            <div class="row align-items-center g-3">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0" style="font-size: clamp(0.8rem, 1.5vw, 0.85rem); color: rgba(255,255,255,0.5);">
                        &copy; <?= date('Y') ?> <?= htmlspecialchars($settings['site_name']) ?>. All Rights Reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0" style="font-size: clamp(0.8rem, 1.5vw, 0.85rem); color: rgba(255,255,255,0.5);">
                        Powered by <a href="https://globalwebify.com/" target="_blank" class="footer-link"
                            style="color: #dc3545; text-decoration: none; font-weight: 600; transition: all 0.3s; white-space: nowrap; display: inline-block;">Global
                            Webify</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<style>
    .footer-link:hover {
        color: #dc3545 !important;
        padding-left: 5px;
        background: rgba(220, 53, 69, 0.1);
        backdrop-filter: blur(10px);
        padding: 4px 8px;
        border-radius: 6px;
        margin-left: -8px;
    }

    .social-link:hover {
        background: linear-gradient(135deg, #dc3545, #ff6b35) !important;
        color: #ffffff !important;
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
    }
</style>


<!-- Floating Back to Top Button -->
<button id="backToTop" class="back-to-top-btn" onclick="window.scrollTo({top: 0, behavior: 'smooth'});"
    title="Back to Top">
    <i class="bi bi-chevron-up"></i>
</button>

<style>
    .back-to-top-btn {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #dc3545, #ff6b35);
        color: white;
        border: none;
        border-radius: 12px;
        font-size: 1.5rem;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 9999;
        box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .back-to-top-btn.show {
        opacity: 1;
        visibility: visible;
    }

    .back-to-top-btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(220, 53, 69, 0.6);
    }

    .back-to-top-btn:active {
        transform: translateY(-2px);
    }
</style>

<script>
    // Show/hide back to top button on scroll
    window.addEventListener('scroll', function () {
        const backToTop = document.getElementById('backToTop');
        if (window.pageYOffset > 300) {
            backToTop.classList.add('show');
        } else {
            backToTop.classList.remove('show');
        }
    });
</script>

<style>
    .transition-hover:hover {
        color: var(--primary) !important;
        padding-left: 5px;
        transition: all 0.3s;
    }
</style>

<!-- AOS Animation Library -->
<?php if (($settings['enable_animations'] ?? '0') == '1'): ?>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>     AOS.init({ duration: <?= intval($settings['animation_duration'] ?? 1000) ?>, offset: <?= intval($settings['animation_offset'] ?? 100) ?>, easing: '<?= htmlspecialchars($settings['animation_easing'] ?? 'ease-in-out') ?>', once: true });
    </script>
<?php endif; ?>

<!-- Legal Modal -->
<div class="modal fade" id="legalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 rounded-0 overflow-hidden" style="background-color: var(--bg-color);">

            <!-- Modal Header Image -->
            <div class="position-relative" style="height: 250px;">
                <img id="legalModalImage" src="" alt="Legal" class="w-100 h-100 object-fit-cover d-none">

                <div id="legalModalImagePlaceholder"
                    class="w-100 h-100 bg-dark d-flex align-items-center justify-content-center">
                    <i class="bi bi-file-text text-white-50 fs-1"></i>
                </div>

                <div class="position-absolute bottom-0 start-0 w-100 p-4"
                    style="background: linear-gradient(to top, rgba(0,0,0,0.95), rgba(0,0,0,0.7), transparent);">
                    <h3 class="text-white mb-0" id="legalModalLabel" style="font-family: var(--font-heading);"></h3>
                </div>
                <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3"
                    data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Modal Body -->
            <div class="modal-body p-5" style="background-color: var(--bg-color); color: var(--text-color);">
                <div id="legalModalContent" style="font-size: 1.15rem; line-height: 1.8;"></div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-0 p-4" style="background-color: var(--bg-color);">
                <button type="button" class="btn btn-secondary w-100 py-3 text-uppercase fw-bold"
                    data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Prepare Legal Data from PHP
    var legalData = <?php echo json_encode([
        'privacy' => [
            'title' => $settings['footer_legal_privacy_text'] ?? 'Privacy Policy',
            'content' => $settings['legal_privacy_content'] ?? 'Privacy Policy content not yet updated.',
            'image' => $settings['legal_privacy_image'] ?? ''
        ],
        'terms' => [
            'title' => $settings['footer_legal_terms_text'] ?? 'Terms of Services',
            'content' => $settings['legal_terms_content'] ?? 'Terms of Service content not yet updated.',
            'image' => $settings['legal_terms_image'] ?? ''
        ]
    ]); ?>;

    function openLegalModal(type) {
        var data = legalData[type];
        if (data) {
            // Set Title
            document.getElementById('legalModalLabel').innerText = data.title;

            // Set Content (allow HTML)
            document.getElementById('legalModalContent').innerHTML = data.content;

            // Set Image
            var img = document.getElementById('legalModalImage');
            var placeholder = document.getElementById('legalModalImagePlaceholder');

            if (data.image && data.image.trim() !== '') {
                img.src = data.image;
                img.classList.remove('d-none');
                placeholder.classList.add('d-none');
            } else {
                img.classList.add('d-none');
                placeholder.classList.remove('d-none');
            }

            // Show Modal
            var legalModal = new bootstrap.Modal(document.getElementById('legalModal'));
            legalModal.show();
        }
    }
</script>