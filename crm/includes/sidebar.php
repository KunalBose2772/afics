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
        'visible' => (function_exists('has_permission') && has_permission('projects')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'investigator', 'field_agent']))
    ],
    [
        'url' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager'])) ? 'field_visits_admin.php' : 'field_visits.php',
        'icon' => 'bi-geo-alt-fill',
        'label' => 'Field Visits',
        'visible' => true
    ],
    [
        'url' => 'mrd_payments.php',
        'icon' => 'bi-wallet2',
        'label' => 'MRD Payments',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'manager', 'investigator', 'field_agent']))
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
        'visible' => !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])
    ],
    [
        'url' => 'users.php',
        'icon' => 'bi-people-fill',
        'label' => 'Users',
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin', 'hr', 'website_manager']))
    ],
    [
        'url' => 'clients.php',
        'icon' => 'bi-buildings-fill',
        'label' => 'Clients',
        'visible' => (function_exists('has_permission') && has_permission('clients')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin', 'website_manager']))
    ],
    [
        'url' => 'inquiries.php',
        'icon' => 'bi-chat-dots-fill',
        'label' => 'Inquiries',
        'visible' => (function_exists('has_permission') && has_permission('inquiries')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']))
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
        'visible' => (isset($_SESSION['role']) && in_array($_SESSION['role'], ['super_admin', 'admin', 'manager']))
    ],
    // Payroll & Registry
    [
        'url' => 'payroll.php',
        'icon' => 'bi-credit-card-fill',
        'label' => 'Payroll',
        'visible' => (function_exists('has_permission') && has_permission('payroll')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']))
    ],
    [
        'url' => 'salary_admin.php',
        'icon' => 'bi-cash-stack',
        'label' => 'Salary Registry',
        'visible' => (function_exists('has_permission') && has_permission('payroll')) || (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'super_admin']))
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

<!-- Desktop Sidebar (Hidden on Mobile via CSS usually, but here we output it and let layout handle) -->
<!-- Note: The parent layout expects this to be visible on desktop. -->
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
            console.log('GPS Tracking initialized for FO');
            
            function updateLocation() {
                // If on localhost/non-https, this might fail unless permitted
                if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
                    console.warn('GPS requires HTTPS');
                    return;
                }

                navigator.geolocation.getCurrentPosition(position => {
                    const data = new FormData();
                    data.append('lat', position.coords.latitude);
                    data.append('lon', position.coords.longitude);
                    data.append('acc', position.coords.accuracy);
                    
                    fetch('ajax/update_location.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(r => r.json())
                    .then(res => {
                        if(res.success) console.log('Location updated');
                    })
                    .catch(e => console.error('Location sync error', e));
                }, err => {
                    console.warn('Geolocation error: ' + err.message);
                }, { enableHighAccuracy: true });
            }

            // Initial update
            updateLocation();
            
            // Periodical update (Every 5 minutes)
            setInterval(updateLocation, 5 * 60 * 1000);
        }
    })();
</script>
