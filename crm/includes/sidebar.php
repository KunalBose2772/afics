<?php
// sidebar.php - centralized navigation logic

// Define Navigation Items
$current_page = basename($_SERVER['PHP_SELF']);
$nav_items = [
    [
        'url' => 'dashboard.php',
        'icon' => 'bi-grid-1x2-fill',
        'label' => 'Dashboard',
        'visible' => true
    ],
    [
        'url' => 'my_profile.php',
        'icon' => 'bi-person-circle',
        'label' => 'My Profile',
        'visible' => true
    ],
    [
        'url' => 'projects.php',
        'icon' => 'bi-folder-fill',
        'label' => 'Claims',
        'visible' => (function_exists('has_permission') && has_permission('projects')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'investigator', 'field_agent', 'fo', 'field_officer', 'team_manager', 'fo_manager', 'hod', 'doctor']))
    ],
    [
        'url' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'hod'])) ? 'field_visits_admin.php' : 'field_visits.php',
        'icon' => 'bi-geo-alt-fill',
        'label' => 'Field Visits',
        'visible' => true
    ],
    [
        'url' => 'mrd_payments.php',
        'icon' => 'bi-wallet2',
        'label' => 'MRD Payments',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'investigator', 'field_agent', 'fo', 'field_officer', 'team_manager', 'fo_manager', 'hod']))
    ],
    [
        'url' => 'projects.php#payment-ready',
        'icon' => 'bi-cash-stack',
        'label' => 'Payment Desk',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'hod']))
    ],
    [
        'url' => 'my_earnings.php',
        'icon' => 'bi-currency-rupee',
        'label' => 'My Earnings',
        'visible' => true
    ],
    [
        'url' => 'my_payslips.php',
        'icon' => 'bi-file-earmark-text',
        'label' => 'My Payslips',
        'visible' => !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin', 'hod'])
    ],
    [
        'url' => 'users.php',
        'icon' => 'bi-people-fill',
        'label' => 'Users',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin', 'hr', 'website_manager', 'hod']))
    ],
    [
        'url' => 'clients.php',
        'icon' => 'bi-buildings-fill',
        'label' => 'Clients',
        'visible' => (function_exists('has_permission') && has_permission('clients')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'website_manager', 'hod']))
    ],
    [
        'url' => 'rights.php',
        'icon' => 'bi-shield-lock-fill',
        'label' => 'Rights',
        'visible' => (function_exists('has_permission') && has_permission('rights')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin']))
    ],
    [
        'url' => 'settings.php',
        'icon' => 'bi-gear-wide-connected',
        'label' => 'Settings',
        'visible' => (function_exists('has_permission') && has_permission('settings')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin']))
    ],

    [
        'url' => 'attendance.php',
        'icon' => 'bi-calendar-check',
        'label' => 'Attendance',
        'visible' => true
    ],
    [
        'url' => 'leaves.php',
        'icon' => 'bi-calendar-range',
        'label' => 'Leaves',
        'visible' => true
    ],
    [
        'url' => 'bulk_claim_import.php',
        'icon' => 'bi-file-earmark-excel-fill',
        'label' => 'Bulk Allocation',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin', 'manager', 'hod']))
    ],
    // Payroll & Registry
    [
        'url' => 'payroll.php',
        'icon' => 'bi-credit-card-fill',
        'label' => 'Payroll',
        'visible' => (function_exists('has_permission') && has_permission('payroll')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'hod']))
    ],
    [
        'url' => 'salary_admin.php',
        'icon' => 'bi-cash-stack',
        'label' => 'Salary Registry',
        'visible' => (function_exists('has_permission') && has_permission('payroll')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'hod']))
    ],
    [
        'url' => 'downloads.php',
        'icon' => 'bi-cloud-download-fill',
        'label' => 'Downloads',
        'visible' => true
    ]
];

// Function to render links
if (!function_exists('render_sidebar_items')) {
    function render_sidebar_items($items, $current_page, $is_mobile = false) {
        $mb_class = $is_mobile ? 'mb-2' : '';
        foreach ($items as $item) {
            if ($item['visible']) {
                $active = ($current_page === $item['url']) ? 'active' : '';
                echo "<a href=\"{$item['url']}\" class=\"sidebar-link {$active} {$mb_class}\">
                        <i class=\"bi {$item['icon']}\"></i> {$item['label']}
                      </a>";
            }
        }
    }
}
?>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title">
             <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 28px;">
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-0">
         <nav class="d-flex flex-column p-3">
            <?php render_sidebar_items($nav_items, $current_page, true); ?>
            <hr class="my-3" style="border-color: var(--border);">
            <a href="logout.php" class="sidebar-link" style="color: var(--danger-text);">
                <i class="bi bi-box-arrow-right"></i> Sign Out
            </a>
        </nav>
    </div>
</div>

<!-- Desktop Sidebar -->
<aside class="sidebar-v2 d-none d-lg-flex">
    <div class="sidebar-brand">
        <img src="../assets/images/documantraa_logo.png" alt="Logo" class="brand-logo-img" style="max-height: 48px; width: auto;">
    </div>
    <nav style="flex: 1;">
        <?php render_sidebar_items($nav_items, $current_page, false); ?>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php" class="sidebar-link" style="color: var(--danger-text);">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</aside>

<script>
    // Live GPS Tracking for Field Officers
    (function() {
        const role = "<?= $_SESSION['role'] ?? '' ?>";
        const isFO = ['investigator', 'field_agent', 'fo', 'field_officer', 'investigator'].includes(role);
        
        if (isFO && navigator.geolocation) {
            function updateLocation() {
                if (location.protocol !== 'https:' && location.hostname !== 'localhost') return;

                navigator.geolocation.getCurrentPosition(position => {
                    const data = new FormData();
                    data.append('lat', position.coords.latitude);
                    data.append('lon', position.coords.longitude);
                    data.append('acc', position.coords.accuracy);
                    
                    fetch('ajax/update_location.php', { method: 'POST', body: data });
                }, (err) => {
                    console.warn("Background GPS failed:", err.message);
                }, { enableHighAccuracy: false, timeout: 15000, maximumAge: 60000 });
            }
            updateLocation();
            setInterval(updateLocation, 5 * 60 * 1000);
        }
    })();

    // Universal Document Viewer System
    (function() {
        function showToast(message) {
            let container = document.getElementById('toastContainer');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toastContainer';
                container.className = 'toast-container';
                document.body.appendChild(container);
            }
            
            const toast = document.createElement('div');
            toast.className = 'toast';
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';
            toast.style.opacity = '1';
            toast.innerHTML = `<i class="bi bi-file-earmark-text-fill me-2 text-primary"></i> <span>${message}</span>`;
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.4s ease';
                setTimeout(() => toast.remove(), 400);
            }, 3000);
        }

        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href') || '';
            const isDownload = link.hasAttribute('download') || 
                               href.includes('letter.php') || 
                               href.includes('report.php') || 
                               href.includes('export') || 
                               href.includes('download') ||
                               href.includes('.pdf') ||
                               href.includes('.jpg') ||
                               href.includes('.png');

            if (isDownload && !href.startsWith('mailto:') && !href.startsWith('tel:') && href !== '#') {
                e.preventDefault();
                showToast('Opening document...');
                
                // Convert relative href to absolute URL
                const tempA = document.createElement('a');
                tempA.href = href;
                const absoluteUrl = tempA.href;

                // For Mobile Apps/WebViews: target="_blank" often fails.
                // We try to open it, if it fails or if we detect mobile, we might use location.href
                const isMobileApp = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                
                if (isMobileApp) {
                    // Direct navigation works better for triggering app-level browser or download handlers
                    window.location.href = absoluteUrl;
                } else {
                    const newWin = window.open(absoluteUrl, '_blank');
                    if (!newWin) {
                        window.location.href = absoluteUrl;
                    }
                }
            }
        }, true);
    })();

    // Global Form Submission Button Feedback (For App/WebView)
    (function() {
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const btn = form.querySelector('button[type="submit"], input[type="submit"]');
            
            if (btn && !btn.hasAttribute('data-no-loading')) {
                // Check if form contains a file input with a selected file
                const fileInputs = form.querySelectorAll('input[type="file"]');
                let isUploading = false;
                fileInputs.forEach(input => {
                    if (input.files && input.files.length > 0) isUploading = true;
                });
                
                const loadingText = isUploading ? 'Uploading...' : 'Processing...';
                const spinner = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>`;
                
                if (btn.tagName.toLowerCase() === 'input') {
                    btn.value = loadingText;
                } else {
                    // Keep the icon if it exists, replace text
                    btn.innerHTML = spinner + loadingText;
                }
                
                // Disable button slightly after to ensure form submission proceeds
                setTimeout(() => {
                    btn.disabled = true;
                    btn.style.opacity = '0.8';
                    btn.style.cursor = 'wait';
                }, 10);
                
                // Failsafe: re-enable after 15 seconds if page doesn't reload (e.g., target="_blank" or error)
                setTimeout(() => {
                    if (btn.disabled) {
                        btn.disabled = false;
                        btn.style.opacity = '1';
                        btn.style.cursor = 'pointer';
                        if (btn.tagName.toLowerCase() === 'input') {
                            btn.value = 'Submit';
                        } else {
                            btn.innerHTML = 'Action Complete';
                        }
                    }
                }, 15000);
            }
        });
    })();
</script>
