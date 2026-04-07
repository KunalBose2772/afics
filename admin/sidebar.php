<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- Mobile Hamburger Button -->
<button class="btn d-lg-none hamburger-btn" id="sidebarToggle" style="position: fixed; top: 20px; left: 20px; z-index: 1100; border-radius: 10px; padding: 10px 15px; background: transparent; border: 2px solid rgba(0, 0, 0, 0.1); color: #333;">
    <i class="bi bi-list fs-4"></i>
</button>

<!-- Mobile Logo Top Center -->
<div class="d-lg-none d-flex align-items-center justify-content-center" style="position: fixed; top: 0; left: 0; width: 100%; height: 104px; z-index: 1090; background: #000000; border-bottom: 1px solid rgba(255,255,255,0.1);">
    <img src="../assets/images/CRMlogo.png" alt="Documantraa" style="height: 65px; filter: brightness(0) invert(1);">
</div>

<!-- Overlay for mobile -->
<div class="sidebar-overlay d-lg-none" id="sidebarOverlay"></div>

<!-- Sidebar -->
<div class="sidebar p-4 d-flex flex-column" id="sidebar" style="width: 280px; position: fixed; height: 100vh; overflow-y: auto; z-index: 1050;">
    <!-- Close button for mobile -->
    <button class="btn btn-link text-white d-lg-none mb-3 align-self-end" id="sidebarClose" style="font-size: 1.5rem;">
        <i class="bi bi-x-lg"></i>
    </button>
    
<?php
require_once __DIR__ . '/../includes/functions.php';
if (!isset($settings)) {
    $settings = get_settings($pdo);
}
?>
    <div class="mb-4 text-center py-4" style="margin: -1.5rem -1.5rem 2rem -1.5rem; border-bottom: 1px solid rgba(255,255,255,0.1); background: transparent;">
         <img src="../assets/images/CRMlogo.png" alt="Documantraa" class="img-fluid" style="max-height: 70px; filter: brightness(0) invert(1);">
    </div>
    
    <nav class="nav flex-column gap-2">
        <a href="dashboard" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-speedometer2 me-3"></i> Dashboard</a>
        <a href="settings" class="nav-link <?= $current_page == 'settings.php' ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-gear me-3"></i> Global Settings</a>
        <a href="crm_appearance" class="nav-link <?= $current_page == 'crm_appearance.php' ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-palette me-3"></i> CRM Appearance</a>
        <a href="services" class="nav-link <?= ($current_page == 'services.php' || $current_page == 'service_form.php') ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-list-check me-3"></i> Service Manager</a>
        <a href="faqs" class="nav-link <?= ($current_page == 'faqs.php' || $current_page == 'faq_form.php') ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-question-circle me-3"></i> FAQ</a>
        <a href="email_templates" class="nav-link <?= $current_page == 'email_templates.php' ? 'active' : '' ?> d-flex align-items-center"><i class="bi bi-envelope-paper me-3"></i> Email Templates</a>
    </nav>

    <div class="mt-auto pt-4 border-top border-secondary">
        <a href="../index" target="_blank" class="nav-link d-flex align-items-center"><i class="bi bi-box-arrow-up-right me-3"></i> View Site</a>
        <a href="logout" class="nav-link d-flex align-items-center text-danger"><i class="bi bi-box-arrow-right me-3"></i> Logout</a>
    </div>
</div>

<style>
/* Mobile sidebar styles */
@media (max-width: 991.98px) {
    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease-in-out;
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .sidebar-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(4px);
        z-index: 1040;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    }
    
    .sidebar-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    .hamburger-btn {
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
        top: 20px !important; /* Keep position */
    }
    
    .hamburger-btn:hover {
        background: rgba(255, 255, 255, 0.1) !important;
        border-color: rgba(255, 255, 255, 0.5) !important;
        transform: scale(1.05);
    }
    
    /* Remove sidebar margin from content on mobile */
    .flex-grow-1[style*="margin-left"] {
        margin-left: 0 !important;
        padding-top: 114px !important; /* Push content down for header (104px) + margin (10px) */
    }
}

/* Desktop - hide hamburger and overlay */
@media (min-width: 992px) {
    .hamburger-btn,
    .sidebar-overlay {
        display: none !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    // Open sidebar
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.add('show');
            sidebarOverlay.classList.add('show');
        });
    }
    
    // Close sidebar
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
    }
    
    if (sidebarClose) {
        sidebarClose.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // Close sidebar when clicking on a nav link (mobile)
    const navLinks = sidebar.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });
});
</script>
