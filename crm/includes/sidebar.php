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
                }, null, { enableHighAccuracy: true });
            }
            updateLocation();
            setInterval(updateLocation, 5 * 60 * 1000);
        }
    })();

    // Universal Download Feedback System
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
            toast.style.opacity = '1';
            toast.innerHTML = `<i class="bi bi-cloud-arrow-down-fill me-2 text-primary"></i> <span>${message}</span>`;
            container.appendChild(toast);
            
            // Auto-remove after 3 seconds and show completion
            setTimeout(() => {
                toast.innerHTML = `<i class="bi bi-check-circle-fill me-2 text-success"></i> <span>Download completed</span>`;
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    toast.style.transition = 'all 0.4s ease';
                    setTimeout(() => toast.remove(), 400);
                }, 2000);
            }, 3000);
        }

        // Global click listener for downloads
        document.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href') || '';
            const isDownload = link.hasAttribute('download') || 
                               href.includes('letter.php') || 
                               href.includes('report.php') || 
                               href.includes('export') || 
                               href.includes('download') ||
                               href.includes('pdf');

            if (isDownload) {
                showToast('Starting download...');
            }
        }, true);
    })();
</script>
