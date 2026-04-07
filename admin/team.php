<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM team_members WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: team");
    exit;
}

$stmt = $pdo->query("SELECT * FROM team_members ORDER BY sort_order ASC");
$team = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Management - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="admin_style.css">
</head>
<body class="bg-dark text-white">

    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4" style="margin-left: 280px;">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                    <h2 class="mb-0 fw-bold">Team Management</h2>
                    <a href="team_form" class="btn btn-primary rounded-pill px-4 shadow-lg"><i class="bi bi-plus-lg me-2"></i> Add Member</a>
                </div>

                <div class="glass-panel overflow-hidden p-0">
                    <table class="table table-hover mb-0 text-white" style="--bs-table-bg: transparent; --bs-table-color: #fff; --bs-table-hover-bg: rgba(255,255,255,0.05);">
                        <thead style="background: rgba(0,0,0,0.3);">
                            <tr>
                                <th class="py-3 ps-4">Image</th>
                                <th class="py-3">Name</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Sort Order</th>
                                <th class="py-3 text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($team as $member): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php if ($member['image_path']): ?>
                                        <img src="../<?= htmlspecialchars($member['image_path']) ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;"><i class="bi bi-person"></i></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($member['name']) ?></td>
                                <td><?= htmlspecialchars($member['role']) ?></td>
                                <td><?= $member['sort_order'] ?></td>
                                <td class="text-end pe-4">
                                    <a href="team_form?id=<?= $member['id'] ?>" class="btn btn-sm btn-outline-light me-2"><i class="bi bi-pencil"></i></a>
                                    <a href="team?delete=<?= $member['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')"><i class="bi bi-trash"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
