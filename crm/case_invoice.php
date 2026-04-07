<?php
require_once 'app_init.php';
require_once 'auth.php';

$id = $_GET['id'] ?? 0;
if ($id == 0) exit("Invalid Invoice");

// Fetch Project, Client and Team Manager info
$stmt = $pdo->prepare("SELECT p.*, c.company_name, u.full_name as tm_name, u.email as tm_email 
                       FROM projects p 
                       JOIN clients c ON p.client_id = c.id 
                       LEFT JOIN users u ON p.team_manager_id = u.id 
                       WHERE p.id = ?");
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p) exit("Case not found");

// Check view permissions
$user_role = $_SESSION['role'];
$is_power_user = in_array($user_role, ['admin', 'super_admin', 'hod', 'manager']);
if(!$is_power_user && $p['team_manager_id'] != $_SESSION['user_id']) {
    exit("Permission Denied");
}

$gross = $p['price_hospital'] + $p['price_patient'] + $p['price_other'];
$net = $gross - $p['fine_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $p['claim_number'] ?> - Documantraa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .invoice-box { max-width: 800px; margin: 50px auto; background: #fff; padding: 50px; border-radius: 1rem; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
        .invoice-header { border-bottom: 2px solid #f0f0f0; margin-bottom: 30px; padding-bottom: 30px; }
        .table { margin-top: 30px; }
        .table thead th { background: #f8f9fa; border: none; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; }
        .total-section { margin-top: 30px; border-top: 2px solid #f0f0f0; padding-top: 20px; }
        @media print {
            body { background: white; margin: 0; }
            .invoice-box { box-shadow: none; margin: 0; padding: 20px; width: 100%; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="no-print mt-4 d-flex justify-content-between">
        <a href="my_earnings.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to Earnings</a>
        <button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button>
    </div>

    <div class="invoice-box">
        <div class="invoice-header d-flex justify-content-between align-items-end">
            <div>
                <h2 class="fw-bold text-primary mb-1">INVOICE</h2>
                <p class="text-muted small mb-0">Invoice #DM-INV-<?= $p['id'] ?></p>
                <p class="text-muted small">Date: <?= date('d M Y') ?></p>
            </div>
            <div class="text-end">
                <h5 class="fw-bold mb-1">Documantraa Investigation</h5>
                <p class="text-muted small mb-0">Confidential Claims Settlement</p>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-6">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Billed To (Freelancer)</h6>
                <div class="fw-bold"><?= htmlspecialchars($p['tm_name'] ?? 'N/A') ?></div>
                <div class="small text-muted"><?= htmlspecialchars($p['tm_email'] ?? '') ?></div>
                <div class="small text-muted">Role: Team Manager</div>
            </div>
            <div class="col-6 text-end">
                <h6 class="text-muted small text-uppercase fw-bold mb-3">Case Information</h6>
                <div><strong>Claim #:</strong> <?= htmlspecialchars($p['claim_number']) ?></div>
                <div><strong>Project:</strong> <?= htmlspecialchars($p['title']) ?></div>
                <div><strong>Client:</strong> <?= htmlspecialchars($p['company_name']) ?></div>
            </div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if($p['price_hospital'] > 0): ?>
                <tr>
                    <td>Investigation Fee - Hospital Part</td>
                    <td class="text-end">₹<?= number_format($p['price_hospital'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if($p['price_patient'] > 0): ?>
                <tr>
                    <td>Investigation Fee - Patient Part</td>
                    <td class="text-end">₹<?= number_format($p['price_patient'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if($p['price_other'] > 0): ?>
                <tr>
                    <td>Investigation Fee - Other Work</td>
                    <td class="text-end">₹<?= number_format($p['price_other'], 2) ?></td>
                </tr>
                <?php endif; ?>
                
                <?php if($p['fine_amount'] > 0): ?>
                <tr class="text-danger">
                    <td><strong>TAT Breach Penalty (Fine Applied)</strong></td>
                    <td class="text-end">-₹<?= number_format($p['fine_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-section d-flex justify-content-end">
            <div class="text-end" style="min-width: 200px;">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Gross Total:</span>
                    <span class="fw-bold">₹<?= number_format($gross, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                    <span class="text-muted">Penalty:</span>
                    <span class="text-danger">₹<?= number_format($p['fine_amount'], 2) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <h5 class="fw-bold">Net Payable:</h5>
                    <h5 class="fw-bold text-primary">₹<?= number_format($net, 2) ?></h5>
                </div>
                <div class="mt-4">
                    <span class="badge bg-<?= ($p['payment_status'] == 'Paid' ? 'success' : 'warning') ?> p-2 px-3 fw-bold shadow-sm" style="font-size: 0.85rem;">
                        STATUS: <?= strtoupper($p['payment_status']) ?>
                    </span>
                    <?php if($p['payment_status'] == 'Paid' && !empty($p['payment_utr'])): ?>
                        <div class="mt-2 small fw-bold text-muted" style="font-size: 0.7rem; letter-spacing: 0.5px;">UTR: <?= htmlspecialchars($p['payment_utr']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="mt-5 pt-5 text-center text-muted small border-top">
            <p>This is a system-generated invoice for professional investigation fees. Under contractual agreement between Freelancer and Documantraa.</p>
        </div>
    </div>
</div>

</body>
</html>
