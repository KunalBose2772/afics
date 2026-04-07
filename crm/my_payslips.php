<?php
require_once 'app_init.php';

// Auth Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's payroll records
$stmt = $pdo->prepare("SELECT * FROM payroll WHERE user_id = ? ORDER BY year DESC, month DESC LIMIT 24");
$stmt->execute([$user_id]);
$my_payslips = $stmt->fetchAll();

// Get latest payslip
$latest = !empty($my_payslips) ? $my_payslips[0] : null;

// Calculate stats
$total_earned = 0;
$total_deductions = 0;
foreach ($my_payslips as $slip) {
    $total_earned += $slip['net_salary'];
    $total_deductions += $slip['deductions'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Payslips - Documantraa</title>
    
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

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content-wrapper">
        <header class="app-header-section">
            <div class="header-inner">
                <div>
                    <h1 style="font-size: 1.75rem; color: var(--text-main);">My Payslips</h1>
                    <p class="text-muted mb-0 small">View and download your salary payslips.</p>
                </div>
                <?php if (!empty($my_payslips)): ?>
                <div class="d-flex gap-2">
                    <?php if ($latest): ?>
                    <a href="../crm/download_payslip.php?id=<?= $latest['id'] ?>" class="btn-v2 btn-white-v2" target="_blank">
                        <i class="bi bi-file-earmark-text me-1"></i> Latest
                    </a>
                    <?php endif; ?>
                    <button onclick="downloadAllPayslips()" id="bulkBtn" class="btn-v2 btn-primary-v2">
                        <i class="bi bi-archive me-1"></i> Download All
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="app-container">
            <?php if (!empty($my_payslips)): ?>
            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="app-card text-center">
                        <div class="fs-4 fw-bold text-success">₹<?= number_format($total_earned, 2) ?></div>
                        <small class="text-muted">Total Earned</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card text-center">
                        <div class="fs-4 fw-bold text-danger">₹<?= number_format($total_deductions, 2) ?></div>
                        <small class="text-muted">Total Deductions</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="app-card text-center">
                        <div class="fs-4 fw-bold text-primary"><?= count($my_payslips) ?></div>
                        <small class="text-muted">Payslips Generated</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($my_payslips)): ?>
            <div class="app-card text-center py-5">
                <div class="bg-light rounded-circle d-inline-flex p-4 mb-3 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                </div>
                <h5 class="text-muted">No Payslips Available</h5>
                <p class="text-muted">Your payslips will appear here once they are generated.</p>
            </div>
            <?php else: ?>
            <!-- Payslips List -->
            <div class="app-card">
                <div class="card-header-v2">
                    <span class="card-title-v2"><i class="bi bi-file-earmark-text me-2"></i>Payslip History</span>
                </div>

                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pay Period</th>
                                <th>Basic Salary</th>
                                <th>Incentives</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_payslips as $payslip): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= date('F Y', mktime(0, 0, 0, $payslip['month'], 1, $payslip['year'])) ?></div>
                                    <small class="text-muted">Payslip #<?= str_pad($payslip['id'], 5, '0', STR_PAD_LEFT) ?></small>
                                </td>
                                <td>₹<?= number_format($payslip['basic_salary'], 2) ?></td>
                                <td class="text-success">+₹<?= number_format($payslip['incentives'], 2) ?></td>
                                <td class="text-danger">-₹<?= number_format($payslip['deductions'], 2) ?></td>
                                <td>
                                    <div class="fw-bold text-success">₹<?= number_format($payslip['net_salary'], 2) ?></div>
                                </td>
                                <td>
                                    <span class="badge badge-v2 <?= $payslip['status'] === 'Paid' ? 'badge-success' : 'badge-pending' ?>">
                                        <?= $payslip['status'] ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button onclick="downloadSinglePayslip(<?= $payslip['id'] ?>, this)" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-download me-1"></i> Download
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="d-md-none">
                    <?php foreach ($my_payslips as $payslip): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <div class="fw-bold"><?= date('F Y', mktime(0, 0, 0, $payslip['month'], 1, $payslip['year'])) ?></div>
                                <small class="text-muted">#<?= str_pad($payslip['id'], 5, '0', STR_PAD_LEFT) ?></small>
                            </div>
                            <span class="badge badge-v2 <?= $payslip['status'] === 'Paid' ? 'badge-success' : 'badge-pending' ?>">
                                <?= $payslip['status'] ?>
                            </span>
                        </div>
                        <div class="row g-2 mb-3 small">
                            <div class="col-6">
                                <div class="text-muted">Basic</div>
                                <div class="fw-semibold">₹<?= number_format($payslip['basic_salary'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Incentives</div>
                                <div class="fw-semibold text-success">+₹<?= number_format($payslip['incentives'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Deductions</div>
                                <div class="fw-semibold text-danger">-₹<?= number_format($payslip['deductions'], 2) ?></div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted">Net Salary</div>
                                <div class="fw-bold text-success fs-5">₹<?= number_format($payslip['net_salary'], 2) ?></div>
                            </div>
                        </div>
                        <button onclick="downloadSinglePayslip(<?= $payslip['id'] ?>, this)" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-download me-1"></i> Download Payslip
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/FileSaver.js/2.0.5/FileSaver.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    
    <script>
    async function downloadAllPayslips() {
        const btn = document.getElementById('bulkBtn');
        await generatePDFsFromURL('../crm/get_payslips_batch.php?t=' + new Date().getTime(), btn, true);
    }
    
    async function downloadSinglePayslip(id, btn) {
        await generatePDFsFromURL('../crm/get_single_payslip.php?id=' + id + '&t=' + new Date().getTime(), btn, false);
    }

    // Unified PDF Generator Function
    async function generatePDFsFromURL(url, btnElement, isZip) {
        const originalText = btnElement.innerHTML;
        
        try {
            btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
            btnElement.classList.add('disabled');
            
            // Fetch Data
            const response = await fetch(url);
            if (!response.ok) throw new Error("Failed to fetch data");
            
            const data = await response.json();
            if (!data || data.length === 0) {
                alert("No payslip data found.");
                return;
            }

            // Create Overlay
            const overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100vw;height:100vh;background:#333;z-index:999999;display:flex;justify-content:center;align-items:center;overflow:auto;';
            document.body.appendChild(overlay);

            // Container for rendering
            const container = document.createElement('div');
            container.style.cssText = 'background:#fff;width:700px;padding:20px;box-shadow:0 0 20px rgba(0,0,0,0.5);';
            overlay.appendChild(container);

            const zip = isZip ? new JSZip() : null;
            const folder = isZip ? zip.folder("Payslips") : null;

            for (let i = 0; i < data.length; i++) {
                const item = data[i];
                if (isZip) btnElement.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> ${i+1}/${data.length}`;
                
                // Inject and Render
                container.innerHTML = item.html_content;
                await new Promise(resolve => setTimeout(resolve, 200));

                const opt = {
                    margin: 10,
                    filename: item.filename,
                    image: { type: 'jpeg', quality: 0.98 },
                    html2canvas: { scale: 1.5, useCORS: true, logging: false, scrollX: 0, scrollY: 0 },
                    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                };
                
                if (!isZip) {
                    await html2pdf().set(opt).from(container).save();
                } else {
                    const pdfBlob = await html2pdf().set(opt).from(container).output('blob');
                    folder.file(item.filename, pdfBlob);
                }
            }
            
            document.body.removeChild(overlay);
            
            if (isZip) {
                btnElement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Zipping...';
                const zipContent = await zip.generateAsync({type: "blob"});
                saveAs(zipContent, "Payslips_Bundle_" + new Date().toISOString().slice(0,10) + ".zip");
            }

        } catch (error) {
            console.error(error);
            alert("Error: " + error.message);
            const overlay = document.querySelector('div[style*="z-index:999999"]');
            if(overlay) document.body.removeChild(overlay);
        } finally {
            btnElement.innerHTML = originalText;
            btnElement.classList.remove('disabled');
        }
    }
    </script>
</body>
</html>
