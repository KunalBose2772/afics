<?php
require_once 'auth.php';
require_once '../config/db.php';
require_once '../includes/functions.php';

// Handle Update
if (isset($_POST['update_template'])) {
    $id = $_POST['template_id'];
    $subject = $_POST['subject'];
    $body = $_POST['body'];
    
    $stmt = $pdo->prepare("UPDATE email_templates SET subject = ?, body = ? WHERE id = ?");
    $stmt->execute([$subject, $body, $id]);
    
    // Log action
    // Assuming log_action exists or we skip
    // log_action('UPDATE_EMAIL_TEMPLATE', "Updated template ID: $id");
    
    header("Location: email_templates?updated=1");
    exit;
}

// Fetch all templates
$templates = $pdo->query("SELECT * FROM email_templates ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png">
    <link rel="manifest" href="../assets/favicon/site.webmanifest">
    <title>Email Templates - Documantraa Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin_style.css">
    <!-- Summernote CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .note-editor .note-toolbar {
            background: #333;
            border-bottom: 1px solid #444;
        }
        .note-editable {
            background: #222;
            color: #fff;
        }
        .note-editor {
            border: 1px solid #444;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <div class="flex-grow-1 p-4" style="margin-left: 280px;">
            <div class="container-fluid">
                <div class="d-flex justify-content-between align-items-center mb-5 glass-panel p-4 glare-container">
                    <div>
                        <h2 class="mb-0 fw-bold">Email Templates</h2>
                        <p class="text-secondary mb-0">Manage automated email content and presets.</p>
                    </div>
                </div>
                
                <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="bi bi-check-circle me-2"></i>Template updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="glass-panel p-0 overflow-hidden">
                    <table class="table table-dark table-hover mb-0 text-white" style="background: transparent; --bs-table-bg: transparent; --bs-table-color: #fff;">
                        <thead style="background: rgba(255,255,255,0.1);">
                            <tr>
                                <th class="p-4 text-secondary text-uppercase small fw-bold">Template Name</th>
                                <th class="p-4 text-secondary text-uppercase small fw-bold">Subject Line</th>
                                <th class="p-4 text-secondary text-uppercase small fw-bold">Last Updated</th>
                                <th class="p-4 text-end text-secondary text-uppercase small fw-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $t): ?>
                            <tr>
                                <td class="p-4 align-middle border-secondary border-opacity-25">
                                    <div class="fw-bold text-white"><?= htmlspecialchars($t['name']) ?></div>
                                    <small class="text-white-50">Slug: <?= $t['slug'] ?></small>
                                </td>
                                <td class="p-4 align-middle border-secondary border-opacity-25 text-white-50"><?= htmlspecialchars($t['subject']) ?></td>
                                <td class="p-4 align-middle border-secondary border-opacity-25 text-white-50"><?= $t['updated_at'] ?></td>
                                <td class="p-4 align-middle text-end border-secondary border-opacity-25">
                                    <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="editTemplate(<?= htmlspecialchars(json_encode($t)) ?>)">
                                        <i class="bi bi-pencil me-2"></i>Edit
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Template Modal -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content bg-dark text-white border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Edit Email Template</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="update_template" value="1">
                        <input type="hidden" name="template_id" id="editId">
                        
                        <h6 id="editName" class="text-info mb-3"></h6>
                        
                        <div class="mb-3">
                            <label class="form-label">Subject Line</label>
                            <input type="text" name="subject" id="editSubject" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Body</label>
                            <textarea name="body" id="summernote" class="form-control" required></textarea>
                        </div>
                        
                        <div class="alert alert-secondary mt-3 text-dark">
                            <i class="bi bi-code-slash me-2"></i><strong>Available Variables:</strong>
                            <p id="editVariables" class="mb-0 mt-1 small font-monospace text-dark" style="color: #000 !important;"></p>
                        </div>
                    </div>
                    <div class="modal-footer border-secondary">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                placeholder: 'Type your email content here...',
                tabsize: 2,
                height: 300,
                toolbar: [
                  ['style', ['style', 'bold', 'italic', 'underline', 'clear']],
                  ['para', ['ul', 'ol', 'paragraph']],
                  ['insert', ['link']],
                  ['view', ['codeview']]
                ]
            });
        });

        function editTemplate(template) {
            document.getElementById('editId').value = template.id;
            document.getElementById('editName').innerText = template.name;
            document.getElementById('editSubject').value = template.subject;
            document.getElementById('editVariables').innerText = template.variables;
            
            $('#summernote').summernote('code', template.body);
            
            var modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        }
    </script>
</body>
</html>
