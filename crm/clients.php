<?php
require_once 'app_init.php';
require_once 'auth.php';

require_permission('clients');

// Handle Add Client
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $errors = [];
    
    // Validation
    $req_fields = [
        'company_name' => 'Company Name',
        'contact_person' => 'Contact Person',
        'email' => 'Email',
        'phone' => 'Phone'
    ];
    $errors = validate_required($req_fields, $_POST);
    
    $email = sanitize_input($_POST['email']);
    if (empty($errors) && !validate_email($email)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($errors)) {
        try {
            $company_name = sanitize_input($_POST['company_name']);
            $contact_person = sanitize_input($_POST['contact_person']);
            $phone = sanitize_input($_POST['phone']);
            
            $stmt = $pdo->prepare("INSERT INTO clients (company_name, contact_person, email, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$company_name, $contact_person, $email, $phone]);
            
            if(function_exists('log_action')) {
                log_action('ADD_CLIENT', "Added client: $company_name");
            }
            
            header('Location: clients.php?success=1');
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}

// Search Logic
$search = trim($_GET['search'] ?? '');
$search_sql = "";
$params = [];

if (!empty($search)) {
    $search_sql = " WHERE company_name LIKE ? OR contact_person LIKE ?";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
}

$stmt = $pdo->prepare("SELECT * FROM clients $search_sql ORDER BY id DESC");
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Stats
$total_clients = count($clients);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Client Management - Documantraa</title>
    <link href="https://fonts.googleapis.com/css2?family=Jost:wght@400;500;600&family=Lexend:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/app.css">
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-top-bar d-lg-none">
        <div class="d-flex align-items-center gap-2">
            <img src="../assets/images/documantraa_logo.png" alt="Logo" style="height: 32px;">
        </div>
        <button class="btn p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
            <i class="bi bi-list" style="font-size: 1.75rem; color: var(--text-main);"></i>
        </button>
    </div>

    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">Client Management</h1>
                    <p class="text-muted mb-0 small">Onboard and manage insurance companies</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-v2 btn-primary-v2" data-bs-toggle="modal" data-bs-target="#addClientModal">
                        <i class="bi bi-plus-lg"></i> <span class="d-none d-md-inline ms-1">Add Client</span>
                    </button>
                </div>
            </div>
        </header>

        <div class="app-container">
            <?= render_form_errors($errors ?? []) ?>
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success d-flex align-items-center mb-4" role="alert" style="background: var(--success-bg); color: var(--success-text); border: none; border-radius: var(--radius-md);">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>Client added successfully!</div>
                </div>
            <?php endif; ?>

            <!-- Search & Filters -->
            <div class="app-card mb-4">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 p-3">
                    <form method="GET" class="w-100 flex-grow-1" style="max-width: 500px;">
                        <div class="input-group">
                            <span class="input-group-text bg-transparent border-end-0" style="border-color: var(--border);">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text" name="search" class="form-control input-v2 border-start-0 ps-0" placeholder="Search clients by name or contact..." value="<?= htmlspecialchars($search) ?>">
                            <button type="submit" class="btn-v2 btn-primary-v2 ms-2">Search</button>
                        </div>
                    </form>
                    <div class="text-muted small">
                         Showing <strong><?= $total_clients ?></strong> clients
                    </div>
                </div>
            </div>

            <!-- Client Grid -->
            <div class="row g-4">
                <?php foreach ($clients as $client): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="app-card h-100">
                        <div class="d-flex align-items-start justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-3">
                                <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 48px; height: 48px; background: var(--bg-secondary); color: var(--primary);">
                                    <i class="bi bi-building fs-4"></i>
                                </div>
                                <div>
                                    <h5 class="card-title-v2 mb-0 text-truncate" style="max-width: 180px;" title="<?= htmlspecialchars($client['company_name']) ?>">
                                        <?= htmlspecialchars($client['company_name']) ?>
                                    </h5>
                                    <small class="text-muted" style="font-size: 0.75rem;">
                                        Since <?= date('M Y', strtotime($client['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                            <!-- Dropdown for actions could go here -->
                        </div>
                        
                        <div class="mb-4">
                            <div class="mb-2">
                                <small class="text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px; font-weight: 600;">Contact Person</small>
                                <div class="fw-medium text-main"><?= htmlspecialchars($client['contact_person']) ?></div>
                            </div>
                            <div class="d-flex gap-4">
                                <div>
                                    <small class="text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px; font-weight: 600;">Email</small>
                                    <div><a href="mailto:<?= htmlspecialchars($client['email']) ?>" class="text-decoration-none text-main"><i class="bi bi-envelope me-1 text-primary"></i> email</a></div>
                                </div>
                                <div>
                                    <small class="text-uppercase text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px; font-weight: 600;">Phone</small>
                                    <div><a href="tel:<?= htmlspecialchars($client['phone']) ?>" class="text-decoration-none text-main"><i class="bi bi-telephone me-1 text-success"></i> call</a></div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-3 border-top" style="border-color: var(--border) !important;">
                            <a href="../projects.php?client_id=<?= $client['id'] ?>" class="btn-v2 btn-white-v2 w-100">
                                View Projects <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($clients)): ?>
                <div class="col-12">
                     <div class="app-card p-5 text-center">
                        <i class="bi bi-building-slash fs-1 text-muted opacity-50 mb-3 d-block"></i>
                        <h5 class="text-muted">No clients found</h5>
                        <p class="text-muted small">Try adjusting your search or add a new client.</p>
                     </div>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Add Client Modal -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="border-radius: var(--radius-lg); border: none;">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title card-title-v2"><i class="bi bi-building-add me-2"></i>Add New Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="add_client" value="1">
                        
                        <div class="mb-3">
                            <label class="stat-label mb-1">Company Name</label>
                            <input type="text" name="company_name" class="input-v2 w-100" placeholder="e.g. Acme Insurance" required>
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1">Contact Person</label>
                            <input type="text" name="contact_person" class="input-v2 w-100" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1">Email</label>
                            <input type="email" name="email" class="input-v2 w-100" placeholder="e.g. john@acme.com" required>
                        </div>
                        <div class="mb-3">
                            <label class="stat-label mb-1">Phone</label>
                            <input type="text" name="phone" class="input-v2 w-100" placeholder="e.g. +91 9876543210" required>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn-v2 btn-white-v2" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-v2 btn-primary-v2">
                                Save Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mobile Bottom Nav -->
    <nav class="bottom-nav">
        <a href="dashboard.php" class="bottom-nav-item">
            <i class="bi bi-grid-1x2-fill"></i>
            <span>Home</span>
        </a>
        <a href="users.php" class="bottom-nav-item">
            <i class="bi bi-people"></i>
            <span>Users</span>
        </a>
         <div style="position: relative; top: -20px;">
            <a href="#" class="bottom-nav-icon-main" data-bs-toggle="modal" data-bs-target="#addClientModal">
                <i class="bi bi-plus-lg"></i>
            </a>
        </div>
        <a href="clients.php" class="bottom-nav-item active">
            <i class="bi bi-building"></i>
            <span>Clients</span>
        </a>
        <a href="my_profile.php" class="bottom-nav-item">
            <i class="bi bi-person"></i>
            <span>Profile</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/validation.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('prefill')) {
                const modal = new bootstrap.Modal(document.getElementById('addClientModal'));
                
                // Prefill fields logic if needed (matching legacy script)
                document.querySelector('input[name="company_name"]').value = urlParams.get('name') || '';
                document.querySelector('input[name="contact_person"]').value = urlParams.get('name') || '';
                document.querySelector('input[name="email"]').value = urlParams.get('email') || '';
                document.querySelector('input[name="phone"]').value = urlParams.get('phone') || '';
                
                modal.show();
            }
        });
    </script>
</body>
</html>
