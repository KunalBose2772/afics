<?php
require_once 'app_init.php';
require_once 'auth.php';

// Security: Only allow Ho staff/admins
if (!in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'manager', 'hod', 'coordinator', 'incharge'])) {
    die("Access Denied.");
}

$page_title = "Notification Delivery Logs";

// Fetch last 100 emails
$stmt = $pdo->query("SELECT q.*, u.full_name as sender_name 
                    FROM email_queue q 
                    LEFT JOIN users u ON q.user_id = u.id 
                    ORDER BY q.created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();

// Statistics
$stats = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM email_queue WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> - AFICS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4a148c;
            --success: #2e7d32;
            --warning: #f57c00;
            --danger: #d32f2f;
            --bg: #f8f9fa;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: #333;
        }
        .app-card {
            background: #fff;
            border-radius: 1rem;
            border: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 2rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-sent { background: #e8f5e9; color: var(--success); }
        .status-pending { background: #fff3e0; color: var(--warning); }
        .status-failed { background: #ffebee; color: var(--danger); }
        
        .stat-card {
            padding: 1.5rem;
            border-radius: 1rem;
            color: #fff;
            position: relative;
            overflow: hidden;
        }
        .stat-card i {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 4rem;
            opacity: 0.15;
        }
        .table th {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #666;
            background: #fdfdfd;
            border-top: none;
        }
        .email-body-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-truncate: ellipsis;
            font-size: 0.85rem;
            color: #666;
        }
    </style>
</head>
<body>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-envelope-check-fill me-2 text-primary"></i>Email Delivery Debugger</h4>
            <p class="text-muted small mb-0">Monitor system notifications and diagnostic logs in real-time.</p>
        </div>
        <div class="d-flex gap-2">
            <?php
            if (isset($_POST['send_test'])) {
                $test_to = $_SESSION['email'] ?? 'support@documantraa.in';
                $sent = queue_email($pdo, $test_to, "Test Email: Delivery Check", "This is a test email to verify that the AFICS notification system is working correctly.\n\nTimestamp: " . date('Y-m-d H:i:s'), $_SESSION['user_id']);
                if ($sent) {
                    echo '<div class="alert alert-success py-1 px-3 mb-0 small rounded-pill">Test Sent!</div>';
                } else {
                    echo '<div class="alert alert-danger py-1 px-3 mb-0 small rounded-pill">Test Failed!</div>';
                }
            }
            ?>
            <form method="POST" class="m-0">
                <button type="submit" name="send_test" class="btn btn-warning btn-sm rounded-pill px-3">
                    <i class="bi bi-send-fill me-1"></i> Send Test Email
                </button>
            </form>
            <button onclick="location.reload()" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh Logs
            </button>
            <a href="projects.php" class="btn btn-primary btn-sm rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #4a148c 0%, #7b1fa2 100%);">
                <small class="d-block opacity-75">Weekly Total</small>
                <h3 class="fw-bold mb-0"><?= $stats['total'] ?? 0 ?></h3>
                <i class="bi bi-envelope"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #2e7d32 0%, #43a047 100%);">
                <small class="d-block opacity-75">Delivered</small>
                <h3 class="fw-bold mb-0"><?= $stats['sent'] ?? 0 ?></h3>
                <i class="bi bi-check-all"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #f57c00 0%, #fb8c00 100%);">
                <small class="d-block opacity-75">Pending</small>
                <h3 class="fw-bold mb-0"><?= $stats['pending'] ?? 0 ?></h3>
                <i class="bi bi-clock-history"></i>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card shadow-sm" style="background: linear-gradient(135deg, #d32f2f 0%, #e53935 100%);">
                <small class="d-block opacity-75">Failed</small>
                <h3 class="fw-bold mb-0"><?= $stats['failed'] ?? 0 ?></h3>
                <i class="bi bi-exclamation-octagon"></i>
            </div>
        </div>
    </div>

    <!-- Logs Card -->
    <div class="app-card shadow-sm">
        <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold">Recent Notifications (Last 100)</h6>
            <span class="badge bg-white text-dark border fw-normal">PHP Mailer (Standard)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">Timestamp</th>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($logs)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">No email logs found.</td></tr>
                    <?php endif; ?>
                    <?php foreach($logs as $l): ?>
                    <tr>
                        <td class="ps-4">
                            <div class="fw-medium" style="font-size:0.85rem;"><?= date('d M, H:i', strtotime($l['created_at'])) ?></div>
                            <small class="text-muted" style="font-size:0.7rem;"><?= date('Y', strtotime($l['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="fw-bold" style="font-size:0.85rem;"><?= htmlspecialchars($l['to_email']) ?></div>
                            <small class="text-muted" style="font-size:0.7rem;">From: <?= htmlspecialchars($l['sender_name'] ?? 'System') ?></small>
                        </td>
                        <td>
                            <div class="text-truncate" style="max-width:250px; font-size:0.85rem;"><?= htmlspecialchars($l['subject']) ?></div>
                        </td>
                        <td>
                            <span class="status-badge status-<?= $l['status'] ?>">
                                <?= $l['status'] ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-light btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#viewEmailModal" 
                                    data-body="<?= htmlspecialchars($l['body']) ?>" 
                                    data-error="<?= htmlspecialchars($l['error_message'] ?? 'None') ?>">
                                Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- View Email Modal -->
<div class="modal fade" id="viewEmailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="border-radius:1rem; border:none;">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Email Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="small fw-bold text-muted mb-2 text-uppercase">Message Content</label>
                    <div id="modal_body" class="p-3 border rounded bg-light" style="font-size: 0.9rem; max-height: 400px; overflow-y: auto;"></div>
                </div>
                <div>
                    <label class="small fw-bold text-danger mb-2 text-uppercase">Diagnostic / Error Log</label>
                    <div id="modal_error" class="p-3 border rounded bg-danger-subtle text-danger" style="font-size: 0.85rem; font-family: monospace;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    const viewModal = document.getElementById('viewEmailModal');
    if(viewModal) {
        viewModal.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            document.getElementById('modal_body').innerHTML = btn.getAttribute('data-body').replace(/\n/g, '<br>');
            document.getElementById('modal_error').innerText = btn.getAttribute('data-error');
        });
    }
</script>

</body>
</html>
