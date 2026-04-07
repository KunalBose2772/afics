<?php
$current_page = basename($_SERVER['PHP_SELF']);
$base_path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
$base_path = $base_path ? $base_path . '/' : '/';
?>
<!-- Ultra Professional Navbar -->
<header class="fixed-top" id="main-header"
    style="z-index: 1029; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
    <nav class="navbar navbar-expand-lg py-2">
        <div class="container">
            <!-- Logo -->
            <a class="navbar-brand" href="<?= htmlspecialchars($base_path) ?>index" style="margin-right: 2rem;">
                <?php if (!empty($settings['site_logo'])):
                    // Make logo path absolute if it's relative
                    $logo_path = $settings['site_logo'];
                    if (strpos($logo_path, 'http') !== 0 && strpos($logo_path, '/') !== 0) {
                        // Get the base path (e.g., /doc/)
                        // Base path logic moved to top of file
                        $logo_path = $base_path . $logo_path;
                    }
                    ?>
                    <img src="<?= htmlspecialchars($logo_path) ?>" alt="<?= htmlspecialchars($settings['site_name']) ?>"
                        style="max-height: 45px; transition: transform 0.3s ease;"
                        onmouseover="this.style.transform='scale(1.03)';" onmouseout="this.style.transform='scale(1)';">
                <?php else: ?>
                    <div class="d-flex align-items-center">
                        <div
                            style="width: 38px; height: 38px; background: linear-gradient(135deg, #dc3545, #ff6b35); border-radius: 6px; display: flex; align-items: center; justify-content: center; margin-right: 10px;">
                            <i class="bi bi-shield-check text-white" style="font-size: 1.1rem;"></i>
                        </div>
                        <div>
                            <h2 class="m-0 lh-1 fw-bold"
                                style="font-family: var(--font-heading); letter-spacing: 0.5px; color: #1a1a1a; font-size: 1.3rem;">
                                <?= htmlspecialchars($settings['site_name'] ?? 'DOCUMANTRAA') ?>
                            </h2>
                            <small class="text-muted"
                                style="letter-spacing: 1.5px; font-size: 0.6rem; font-weight: 500;">SECURITY &
                                INVESTIGATION</small>
                        </div>
                    </div>
                <?php endif; ?>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0 p-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarNav" style="background: transparent;">
                <span class="navbar-toggler-icon" style="width: 24px; height: 24px;"></span>
            </button>

            <!-- Navigation -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'index.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($base_path) ?>index">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page == 'about.php' ? 'active' : '' ?>" href="<?= htmlspecialchars($base_path) ?>about">About
                            Us</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $current_page == 'services.php' ? 'active' : '' ?>"
                            href="#" data-bs-toggle="dropdown">Services</a>
                        <ul class="dropdown-menu border-0 shadow-lg"
                            style="border-radius: 10px; padding: 0.8rem; margin-top: 0.5rem; min-width: 240px;">
                            <?php
                            if (!isset($services)) {
                                $services = get_services($pdo);
                            }
                            $displayedServices = array_slice($services, 0, 6);
                            foreach ($displayedServices as $service):
                                $service_slug = $service['slug'] ?? 'service-' . $service['id'];
                                ?>
                                <li>
                                    <a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>service/<?= htmlspecialchars($service_slug) ?>">
                                        <i class="<?= htmlspecialchars($service['icon_class']) ?> me-2"
                                            style="color: #dc3545; font-size: 0.9rem;"></i>
                                        <?= htmlspecialchars($service['title']) ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <li>
                                <hr class="dropdown-divider my-2">
                            </li>
                            <li>
                                <a class="dropdown-item fw-semibold" href="<?= htmlspecialchars($base_path) ?>services" style="color: #dc3545;">
                                    <i class="bi bi-arrow-right-circle me-2"></i>
                                    View All Services
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= htmlspecialchars($base_path) ?>index#faq">FAQ's</a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Login</a>
                        <ul class="dropdown-menu border-0 shadow-lg"
                            style="border-radius: 10px; padding: 0.8rem; margin-top: 0.5rem; min-width: 180px;">
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>crm/login?role=admin"><i
                                        class="bi bi-shield-lock-fill me-2" style="color: #dc3545;"></i>Admin</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>crm/login?role=hr"><i class="bi bi-people-fill me-2"
                                        style="color: #dc3545;"></i>HR</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>crm/login?role=office_staff"><i
                                        class="bi bi-building-fill me-2" style="color: #dc3545;"></i>Staff</a></li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>crm/login?role=employee"><i
                                        class="bi bi-person-badge-fill me-2" style="color: #dc3545;"></i>Employee</a>
                            </li>
                            <li><a class="dropdown-item" href="<?= htmlspecialchars($base_path) ?>crm/login?role=freelancer"><i
                                        class="bi bi-briefcase-fill me-2" style="color: #dc3545;"></i>Freelancer</a>
                            </li>
                        </ul>
                    </li>
                </ul>

                <!-- CTA Button -->
                <div class="d-flex align-items-center">
                    <a href="<?= htmlspecialchars($base_path) ?>index#contact" class="btn px-4 py-2 fw-semibold"
                        style="background: linear-gradient(135deg, #dc3545, #ff6b35); color: #ffffff; border: none; border-radius: 50px; font-size: 0.8rem; letter-spacing: 0.5px; box-shadow: 0 3px 12px rgba(220, 53, 69, 0.3); transition: all 0.3s ease;"
                        onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 5px 18px rgba(220, 53, 69, 0.4)';"
                        onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 3px 12px rgba(220, 53, 69, 0.3)';">
                        <i class="bi bi-telephone-fill me-2"></i>Contact Us
                    </a>
                </div>
            </div>
        </div>
    </nav>
</header>

<style>
    /* Professional Navigation */
    .nav-link {
        position: relative;
        font-weight: 500;
        font-size: 0.9rem;
        color: #2d2d2d !important;
        padding: 0.5rem 1rem !important;
        transition: color 0.3s ease;
    }

    .nav-link::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 0;
        height: 2px;
        background: linear-gradient(90deg, #dc3545, #ff6b35);
        transition: width 0.3s ease;
    }

    .nav-link:hover::after,
    .nav-link.active::after {
        width: 50%;
    }

    .nav-link:hover,
    .nav-link.active {
        color: #dc3545 !important;
    }

    /* Dropdown Styling */
    .dropdown-item {
        padding: 0.5rem 1rem;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.3s ease;
        color: #2d2d2d;
    }

    .dropdown-item:hover {
        background: linear-gradient(135deg, rgba(220, 53, 69, 0.08), rgba(255, 107, 53, 0.08));
        color: #dc3545 !important;
        transform: translateX(4px);
    }

    /* Mobile Responsive */
    @media (max-width: 991px) {
        .navbar-collapse {
            background: #ffffff;
            padding: 1.2rem;
            border-radius: 10px;
            margin-top: 0.8rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .nav-link::after {
            display: none;
        }

        .navbar-nav {
            margin-bottom: 1rem !important;
        }
    }

    /* Sticky Header */
    #main-header {
        transition: all 0.3s ease;
    }

    #main-header.scrolled {
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
    }
</style>

<script>
    // Scroll effect
    window.addEventListener('scroll', function () {
        const header = document.getElementById('main-header');
        if (window.scrollY > 30) {
            header.classList.add('scrolled');
        } else {
            header.classList.remove('scrolled');
        }
    });

    // Adjust body padding
    document.addEventListener("DOMContentLoaded", function () {
        var header = document.getElementById('main-header');
        var adjustPadding = function () {
            if (header) {
                document.body.style.paddingTop = header.offsetHeight + 'px';
            }
        };
        adjustPadding();
        window.addEventListener('resize', adjustPadding);
        setTimeout(adjustPadding, 300);

        // Dropdown hover functionality (desktop only) with delay
        if (window.innerWidth > 991) {
            const dropdowns = document.querySelectorAll('.nav-item.dropdown');
            let closeTimeout;

            dropdowns.forEach(dropdown => {
                dropdown.addEventListener('mouseenter', function () {
                    // Clear any pending close timeout
                    if (closeTimeout) {
                        clearTimeout(closeTimeout);
                        closeTimeout = null;
                    }

                    const dropdownMenu = this.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.classList.add('show');
                        this.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'true');
                    }
                });

                dropdown.addEventListener('mouseleave', function () {
                    const dropdownMenu = this.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        // Add 300ms delay before closing
                        closeTimeout = setTimeout(() => {
                            dropdownMenu.classList.remove('show');
                            this.querySelector('.dropdown-toggle').setAttribute('aria-expanded', 'false');
                        }, 300);
                    }
                });
            });
        }
    });
</script>