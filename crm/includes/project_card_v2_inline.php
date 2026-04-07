<?php
// Scope Logic
$scope = $project['scope'] ?? 'Full Investigation';
$scope_badges = '';

if (!function_exists('render_scope_badge')) {
    function render_scope_badge($letter, $color_class = 'bg-danger') {
        $bg = ($color_class == 'bg-danger') ? 'linear-gradient(135deg, #dc3545 0%, #a71d2a 100%)' : 'linear-gradient(135deg, #6c757d 0%, #495057 100%)';
        return '<span class="d-inline-flex align-items-center justify-content-center rounded-circle me-1 fw-bold text-white shadow-sm" style="width: 24px; height: 24px; font-size: 0.7rem; background: '.$bg.';">' . $letter . '</span>';
    }
}

if ($scope == 'Hospital Part') {
    $scope_badges = render_scope_badge('H', 'bg-danger');
} elseif ($scope == 'Patient Part') {
    $scope_badges = render_scope_badge('P', 'bg-danger');
} elseif ($scope == 'Other Part') {
    $scope_badges = render_scope_badge('O', 'bg-secondary');
} else {
    $scope_badges = render_scope_badge('H', 'bg-danger') . render_scope_badge('P', 'bg-danger');
}

// TAT Logic
$created_date = new DateTime($project['created_at'] ?? 'now');
$now_date = new DateTime();
$days_open = $created_date->diff($now_date)->days;
$flag_class = 'text-success'; 
if ($days_open > 7) { $flag_class = 'text-danger'; }
elseif ($days_open >= 6) { $flag_class = 'text-warning'; }
elseif ($days_open > 3) { $flag_class = 'text-warning'; }

// Status Color Map
$status_colors = [
    'Pending' => 'badge-pending',
    'In-Progress' => 'badge-process',
    'Hold' => 'badge-pending',
    'FO-Closed' => 'bg-info text-white',
    'Completed' => 'badge-success'
];
$status_class = $status_colors[$project['status']] ?? 'badge-process';

// Timer Logic
$timer_html = '';
if ($project['status'] == 'In-Progress' && !empty($project['started_at'])) {
    $timer_id = 'timer_' . $project['id'];
    $timer_html = "<small class='text-warning fw-bold d-block mt-1'><i class='bi bi-stopwatch'></i> <span id='$timer_id'>...</span></small>
    <script>
        (function() {
            const start = new Date('" . date('Y-m-d H:i:s', strtotime($project['started_at'])) . "').getTime();
            function upd() {
                const diff = new Date().getTime() - start;
                if(diff<0) return;
                const d = Math.floor(diff/86400000);
                const h = Math.floor((diff%86400000)/3600000);
                const m = Math.floor((diff%3600000)/60000);
                document.getElementById('$timer_id').innerText = (d>0?d+'d ':'') + h+'h '+m+'m';
            }
            setInterval(upd, 60000); upd();
        })();
    </script>";
}

$user_role = $_SESSION['role'] ?? 'guest';
$user_id = $_SESSION['user_id'] ?? 0;
$is_power_user = in_array($user_role, ['admin', 'super_admin', 'hod', 'manager']);
$is_assigned_tm = ($user_role == 'team_manager' && ($project['team_manager_id'] ?? 0) == $user_id);
$is_assigned_fo = (
    ($project['assigned_to'] ?? 0) == $user_id || 
    ($project['pt_fo_id'] ?? 0) == $user_id || 
    ($project['hp_fo_id'] ?? 0) == $user_id || 
    ($project['other_fo_id'] ?? 0) == $user_id
);
$show_fees = $is_power_user || $is_assigned_tm || $is_assigned_fo;

// Calculate Fine
$calculated_fine = calculate_project_fine($project);
$display_fine = ($project['is_fine_confirmed'] ?? 0) ? $project['fine_amount'] : $calculated_fine;
$fine_status = ($project['is_fine_confirmed'] ?? 0) ? 'Confirmed' : 'Calculated';
?>

<div class="col-md-6 col-xl-4">
    <div class="app-card h-100 p-0 overflow-hidden d-flex flex-column">
        <!-- Header -->
        <div class="p-3 border-bottom d-flex justify-content-between align-items-start" style="background: var(--surface-hover);">
            <div class="overflow-hidden me-2">
                <a href="project_details.php?id=<?= $project['id'] ?>" class="text-decoration-none">
                    <h6 class="fw-bold mb-1 text-truncate text-main" style="font-size: 1rem;"><?= htmlspecialchars($project['title']) ?></h6>
                </a>
                <div class="d-flex align-items-center">
                    <small class="text-muted fw-bold me-2 font-monospace"><?= htmlspecialchars($project['claim_number'] ?? 'N/A') ?></small>
                    <div><?= $scope_badges ?></div>
                </div>
            </div>
            <div class="d-flex flex-column align-items-end">
                <span class="badge badge-v2 <?= $status_class ?> mb-1">
                    <?= $project['status'] == 'FO-Closed' ? 'FO Closer' : $project['status'] ?>
                </span>
                <div class="dropdown">
                    <button class="btn btn-link text-muted p-0 text-decoration-none" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item" href="project_details.php?id=<?= $project['id'] ?>"><i class="bi bi-eye me-2"></i> View Details</a></li>
                        <li><a class="dropdown-item" href="authorization_letter.php?id=<?= $project['id'] ?>" target="_blank"><i class="bi bi-file-earmark-text me-2"></i> Auth Letter</a></li>
                        <li><a class="dropdown-item" href="normalization_letter.php?id=<?= $project['id'] ?>" target="_blank"><i class="bi bi-shield-check me-2"></i> Normalization Letter</a></li>
                        <?php if($is_power_user): ?>
                            <?php if(!$project['is_hard_copy_received'] && !$project['is_hard_copy_overridden']): ?>
                                <li>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="mark_hard_copy" value="1">
                                        <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                        <button type="submit" class="dropdown-item text-primary"><i class="bi bi-check-circle me-2"></i> Hard Copy Received</button>
                                    </form>
                                </li>
                                <li>
                                    <form method="POST" class="m-0">
                                        <input type="hidden" name="override_hard_copy" value="1">
                                        <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                        <button type="submit" class="dropdown-item text-warning" onclick="return confirm('Override hard copy requirement?')"><i class="bi bi-shield-check me-2"></i> Override Hard Copy</button>
                                    </form>
                                </li>
                            <?php endif; ?>
                            <?php if($project['payment_status'] == 'Unpaid' && ($project['is_hard_copy_received'] || $project['is_hard_copy_overridden'])): ?>
                                <li>
                                    <button type="button" class="dropdown-item text-success" data-bs-toggle="modal" data-bs-target="#releasePaymentModal" data-id="<?= $project['id'] ?>">
                                        <i class="bi bi-cash-stack me-2"></i> Release Payment
                                    </button>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if(in_array($_SESSION['role'] ?? '', ['admin', 'super_admin', 'hr', 'hr_manager'])): ?>
                        <li><a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#editProjectModal" 
                               data-id="<?= $project['id'] ?>" 
                               data-title="<?= htmlspecialchars($project['title']) ?>" 
                               data-claim="<?= htmlspecialchars($project['claim_number']) ?>" 
                               data-scope="<?= htmlspecialchars($project['scope']) ?>" 
                               data-dead="<?= $project['tat_deadline'] ?>" 
                               data-hospital="<?= htmlspecialchars($project['hospital_name'] ?? '') ?>" 
                               data-address="<?= htmlspecialchars($project['hospital_address'] ?? '') ?>" 
                               data-assign="<?= $project['assigned_to'] ?? '' ?>" 
                               data-tm="<?= $project['team_manager_id'] ?? '' ?>" 
                               data-mngr="<?= $project['manager_id'] ?? '' ?>" 
                               data-ptfo="<?= $project['pt_fo_id'] ?? '' ?>" 
                               data-hpfo="<?= $project['hp_fo_id'] ?? '' ?>" 
                               data-otherfo="<?= $project['other_fo_id'] ?? '' ?>" 
                               data-points="<?= $project['case_points'] ?? '0' ?>" 
                               data-phone="<?= htmlspecialchars($project['patient_phone'] ?? '') ?>" 
                               data-desc="<?= htmlspecialchars($project['description']) ?>"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Body -->
        <div class="p-3 flex-grow-1 d-flex flex-column gap-3">
            <div class="d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center text-truncate" style="max-width: 60%;">
                    <i class="bi bi-hospital me-2 text-primary"></i>
                    <span class="small text-muted text-truncate" title="<?= htmlspecialchars($project['hospital_name'] ?? 'N/A') ?>">
                        <?= htmlspecialchars($project['hospital_name'] ?? 'No Hospital') ?>
                    </span>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge bg-primary-subtle text-primary border border-primary px-2" style="font-size: 0.7rem;"><?= (float)$project['case_points'] ?> pts</span>
                    <div class="small <?= $flag_class ?>">
                        <i class="bi bi-flag-fill me-1"></i>
                        <span><?= date('d M', strtotime($project['tat_deadline'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="row g-2">
                 <?php if(!empty($project['tm_name'])): ?>
                <div class="col-6">
                    <small class="text-uppercase text-muted d-block" style="font-size: 0.6rem;">Team Mgr</small>
                    <div class="text-truncate small fw-bold text-main"><?= htmlspecialchars($project['tm_name']) ?></div>
                </div>
                <?php endif; ?>
                <?php if(!empty($project['mngr_name'])): ?>
                <div class="col-6">
                    <small class="text-uppercase text-muted d-block" style="font-size: 0.6rem;">Manager</small>
                    <div class="text-truncate small fw-bold text-main"><?= htmlspecialchars($project['mngr_name']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="row g-2 mt-auto">
                <div class="col-6">
                    <small class="text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 600;">Main FO</small>
                    <div class="text-truncate small text-main fw-medium"><?= htmlspecialchars($project['officer_name'] ?? '-') ?></div>
                </div>
                <div class="col-6">
                    <div class="d-flex flex-column">
                        <small class="text-uppercase text-muted" style="font-size: 0.65rem; font-weight: 600;">Client</small>
                        <div class="text-truncate small text-main fw-medium"><?= htmlspecialchars($project['company_name'] ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- Multi-Tier Assignment Info -->
            <div class="border-top pt-2 mt-1">
                <div class="row g-2">
                    <?php if(!empty($project['pt_fo_name'])): ?>
                    <div class="col-4">
                        <small class="text-uppercase text-muted d-block" style="font-size: 0.6rem;">PT FO</small>
                        <div class="text-truncate small fw-bold text-main"><?= htmlspecialchars($project['pt_fo_name']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($project['hp_fo_name'])): ?>
                    <div class="col-4">
                        <small class="text-uppercase text-muted d-block" style="font-size: 0.6rem;">HP FO</small>
                        <div class="text-truncate small fw-bold text-main"><?= htmlspecialchars($project['hp_fo_name']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if(!empty($project['other_fo_name'])): ?>
                    <div class="col-4">
                        <small class="text-uppercase text-muted d-block" style="font-size: 0.6rem;">Other FO</small>
                        <div class="text-truncate small fw-bold text-main"><?= htmlspecialchars($project['other_fo_name']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if($show_fees && $display_fine > 0): ?>
                    <div class="col-12 mt-1">
                        <div class="alert alert-danger py-1 px-2 mb-0 d-flex justify-content-between align-items-center" style="font-size: 0.75rem; border-radius: 4px;">
                            <div class="d-flex flex-column">
                                <span class="fw-bold">₹<?= number_format($display_fine, 2) ?> Fine (<?= $fine_status ?>)</span>
                                <?php if($is_power_user && !$project['is_fine_confirmed']): ?>
                                <button type="button" class="btn btn-link p-0 text-danger text-decoration-none small text-start" style="font-size: 0.65rem;" data-bs-toggle="modal" data-bs-target="#confirmFineModal" data-id="<?= $project['id'] ?>" data-fine="<?= $calculated_fine ?>">
                                    <i class="bi bi-check-circle-fill"></i> Confirm/Edit Fine
                                </button>
                                <?php endif; ?>
                            </div>
                            <i class="bi bi-exclamation-octagon-fill fs-5 opacity-50"></i>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Hard Copy & Payment Status Labels -->
                    <div class="col-12 mt-2">
                        <div class="d-flex flex-wrap gap-1">
                            <?php if($project['is_hard_copy_received']): ?>
                                <span class="badge badge-v2 bg-success-subtle text-success border border-success" style="font-size: 0.6rem;"><i class="bi bi-file-earmark-check"></i> Hard Copy OK</span>
                            <?php elseif($project['is_hard_copy_overridden']): ?>
                                <span class="badge badge-v2 bg-warning-subtle text-warning border border-warning" style="font-size: 0.6rem;"><i class="bi bi-shield-check"></i> HC Override</span>
                            <?php else: ?>
                                <span class="badge badge-v2 bg-secondary-subtle text-muted border border-secondary" style="font-size: 0.6rem;"><i class="bi bi-clock-history"></i> Awaiting Hard Copy</span>
                            <?php endif; ?>

                            <?php if($project['payment_status'] == 'Paid'): ?>
                                <span class="badge badge-v2 bg-primary-subtle text-primary border border-primary" style="font-size: 0.6rem;"><i class="bi bi-cash-coin"></i> Fully Paid</span>
                                <?php if(!empty($project['payment_utr'])): ?>
                                    <span class="badge badge-v2 bg-light text-dark border" style="font-size: 0.6rem;"><i class="bi bi-hash"></i> UTR: <?= htmlspecialchars($project['payment_utr']) ?></span>
                                <?php endif; ?>
                            <?php elseif($show_fees): ?>
                                <span class="badge badge-v2 bg-danger-subtle text-danger border border-danger" style="font-size: 0.6rem;"><i class="bi bi-cash"></i> Unpaid</span>
                            <?php endif; ?>
                            
                            <?php if($show_fees): ?>
                                <span class="ms-auto fw-bold text-main" style="font-size: 0.75rem;">Earnings: ₹<?= number_format(($project['price_hospital']+$project['price_patient']+$project['price_other'] - $display_fine), 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                 <?= $timer_html ?>
            </div>
        </div>

        <!-- Actions Footer -->
        <div class="px-3 py-2 border-top bg-light">
            <form method="POST" class="m-0 w-100">
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                <?php 
                $curr = $project['status']; 
                $role = $_SESSION['role']; 
                $is_ho = in_array($role, ['admin', 'super_admin', 'manager', 'coordinator', 'hr', 'hr_manager']); 
                $is_adm = ($role=='admin'||$role=='super_admin'); 
                ?>
                <select name="status" class="form-select form-select-sm border-0 bg-transparent fw-bold text-center" 
                        style="color: var(--text-main); cursor: pointer;"
                        onchange="this.form.submit()" <?= ($curr=='Completed' && !$is_adm) ? 'disabled' : '' ?>>
                    <?php if (!$is_ho): ?>
                        <option value="<?= $curr ?>" selected><?= $curr == 'FO-Closed' ? 'FO Closer' : $curr ?></option>
                        <?php if ($curr != 'FO-Closed'): ?>
                            <option value="FO-Closed">FO Closer (Finish visit)</option>
                        <?php endif; ?>
                    <?php else: ?>
                        <option value="Pending" <?= $curr=='Pending'?'selected':'' ?>>Pending</option>
                        <option value="In-Progress" <?= $curr=='In-Progress'?'selected':'' ?>>In-Progress</option>
                        <option value="FO-Closed" <?= $curr=='FO-Closed'?'selected':'' ?>>FO Closer</option>
                        <option value="Hold" <?= $curr=='Hold'?'selected':'' ?>>Hold</option>
                        <option value="Completed" <?= $curr=='Completed'?'selected':'' ?>>Completed</option>
                    <?php endif; ?>
                </select>
            </form>
        </div>
    </div>
</div>
<?php ?>
