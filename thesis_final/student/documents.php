<?php
/**
 * Thesis Documents Management Page
 * Upload and manage thesis-related documents
 */

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../login.php");
    exit();
}

require_once '../db_connect.php';

// Page title and subtitle
$page_title = "Documents";
$page_subtitle = "Upload and manage your thesis documents";

// Initialize variables
$errors = [];
$success = '';

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user has a thesis group
try {
    $group_check = $pdo->prepare("
        SELECT tg.group_id, tg.group_name, tg.status
        FROM thesis_groups tg
        INNER JOIN group_members gm ON tg.group_id = gm.group_id
        WHERE gm.user_id = ?
    ");
    $group_check->execute([$_SESSION['user_id']]);
    $thesis_group = $group_check->fetch();
} catch (PDOException $e) {
    error_log("Group Check Error: " . $e->getMessage());
    $thesis_group = null;
}

// Get all documents for the group
$documents = [];
if ($thesis_group) {
    try {
        $docs_stmt = $pdo->prepare("
            SELECT td.*, u.name as uploaded_by_name
            FROM thesis_documents td
            INNER JOIN users u ON td.uploaded_by = u.user_id
            WHERE td.group_id = ?
            ORDER BY td.uploaded_at DESC
        ");
        $docs_stmt->execute([$thesis_group['group_id']]);
        $documents = $docs_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Documents Fetch Error: " . $e->getMessage());
    }
}

// Document types
$document_types = [
    'Proposal', 'Chapter 1', 'Chapter 2', 'Chapter 3', 'Chapter 4', 'Chapter 5',
    'Full Manuscript', 'Presentation', 'Abstract', 'Approval Sheet', 'Other'
];

// Process file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid request. Please try again.";
    } elseif (!$thesis_group) {
        $errors[] = "You must be part of a thesis group to upload documents.";
    } else {
        $action = $_POST['action'];

        // UPLOAD DOCUMENT
        if ($action === 'upload') {
            $document_type = trim($_POST['document_type']);

            // Validate document type
            if (empty($document_type) || !in_array($document_type, $document_types)) {
                $errors[] = "Please select a valid document type.";
            }

            // Validate file upload
            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Please select a file to upload.";
            } else {
                $file = $_FILES['document'];
                $file_size = $file['size'];
                $file_tmp = $file['tmp_name'];
                $file_name = $file['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                // Allowed extensions
                $allowed_extensions = ['pdf', 'doc', 'docx', 'ppt', 'pptx'];

                // Validate file extension
                if (!in_array($file_ext, $allowed_extensions)) {
                    $errors[] = "Only PDF, DOC, DOCX, PPT, and PPTX files are allowed.";
                }

                // Validate file size (10MB max)
                if ($file_size > 10485760) {
                    $errors[] = "File size must be less than 10MB.";
                }
            }

            // Upload file if no errors
            if (empty($errors)) {
                try {
                    // Create uploads directory if not exists
                    $upload_dir = '../uploads/documents/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    // Generate unique filename
                    $unique_filename = 'doc_' . $thesis_group['group_id'] . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $file_path = $upload_dir . $unique_filename;

                    // Move uploaded file
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Insert into database
                        $insert_stmt = $pdo->prepare("
                            INSERT INTO thesis_documents (group_id, document_type, file_name, file_path, file_size, uploaded_by, uploaded_at) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ");
                        $insert_stmt->execute([
                            $thesis_group['group_id'],
                            $document_type,
                            $file_name,
                            $file_path,
                            $file_size,
                            $_SESSION['user_id']
                        ]);

                        // Log activity
                        $log_stmt = $pdo->prepare("
                            INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                            VALUES (?, 'upload_document', 'thesis_documents', ?, ?, NOW())
                        ");
                        $log_stmt->execute([$_SESSION['user_id'], $pdo->lastInsertId(), $_SERVER['REMOTE_ADDR']]);

                        // Notify group members
                        $members_stmt = $pdo->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ?");
                        $members_stmt->execute([$thesis_group['group_id'], $_SESSION['user_id']]);
                        $members = $members_stmt->fetchAll();

                        foreach ($members as $member) {
                            $notif_stmt = $pdo->prepare("
                                INSERT INTO notifications (user_id, title, message, type, created_at) 
                                VALUES (?, 'New Document Uploaded', ?, 'document', NOW())
                            ");
                            $message = $_SESSION['name'] . " uploaded a new document: " . $document_type;
                            $notif_stmt->execute([$member['user_id'], $message]);
                        }

                        $success = "Document uploaded successfully!";

                        // Refresh documents list
                        $docs_stmt->execute([$thesis_group['group_id']]);
                        $documents = $docs_stmt->fetchAll();

                    } else {
                        $errors[] = "Failed to upload file. Please try again.";
                    }
                } catch (PDOException $e) {
                    error_log("Document Upload Error: " . $e->getMessage());
                    $errors[] = "An error occurred while uploading the document. Please try again.";
                }
            }
        }

        // DELETE DOCUMENT
        elseif ($action === 'delete') {
            $document_id = intval($_POST['document_id']);

            try {
                // Get document info
                $doc_stmt = $pdo->prepare("
                    SELECT * FROM thesis_documents 
                    WHERE document_id = ? AND group_id = ?
                ");
                $doc_stmt->execute([$document_id, $thesis_group['group_id']]);
                $document = $doc_stmt->fetch();

                if (!$document) {
                    $errors[] = "Document not found.";
                } else {
                    // Delete file from server
                    if (file_exists($document['file_path'])) {
                        unlink($document['file_path']);
                    }

                    // Delete from database
                    $delete_stmt = $pdo->prepare("DELETE FROM thesis_documents WHERE document_id = ?");
                    $delete_stmt->execute([$document_id]);

                    // Log activity
                    $log_stmt = $pdo->prepare("
                        INSERT INTO activity_logs (user_id, action, table_name, record_id, ip_address, created_at) 
                        VALUES (?, 'delete_document', 'thesis_documents', ?, ?, NOW())
                    ");
                    $log_stmt->execute([$_SESSION['user_id'], $document_id, $_SERVER['REMOTE_ADDR']]);

                    $success = "Document deleted successfully!";

                    // Refresh documents list
                    $docs_stmt->execute([$thesis_group['group_id']]);
                    $documents = $docs_stmt->fetchAll();
                }
            } catch (PDOException $e) {
                error_log("Document Delete Error: " . $e->getMessage());
                $errors[] = "An error occurred while deleting the document. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents - Thesis Panel System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/forms.css">
    <link rel="stylesheet" href="../assets/css/icons.css">
</head>
<body>
    <div class="dashboard-wrapper">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="dashboard-content">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error">
                        <ul>
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$thesis_group): ?>
                    <!-- No Group Message -->
                    <div class="content-card">
                        <div class="card-body">
                            <div class="empty-state">
                                <div class="icon">📄</div>
                                <h3>No Thesis Group</h3>
                                <p>You need to create or join a thesis group before uploading documents.</p>
                                <a href="thesis-group.php" class="btn btn-primary">Create Thesis Group</a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Upload Document Form -->
                    <div class="form-card">
                        <div class="form-card-header">
                            <h3>📤 Upload Document</h3>
                            <p>Upload thesis documents for your group: <?php echo htmlspecialchars($thesis_group['group_name']); ?></p>
                        </div>
                        
                        <form method="POST" action="documents.php" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <input type="hidden" name="action" value="upload">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="document_type">Document Type <span class="required">*</span></label>
                                    <select id="document_type" name="document_type" required>
                                        <option value="">Select Document Type</option>
                                        <?php foreach ($document_types as $type): ?>
                                            <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="document">Select File <span class="required">*</span></label>
                                    <input 
                                        type="file" 
                                        id="document" 
                                        name="document" 
                                        required 
                                        accept=".pdf,.doc,.docx,.ppt,.pptx"
                                    >
                                    <small class="form-help">Allowed: PDF, DOC, DOCX, PPT, PPTX (Max: 10MB)</small>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" id="uploadBtn">
                                    📤 Upload Document
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Documents List -->
                    <div class="content-card">
                        <div class="card-header">
                            <h3>📚 Uploaded Documents (<?php echo count($documents); ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($documents)): ?>
                                <div class="empty-state">
                                    <div class="icon">📄</div>
                                    <h3>No Documents Yet</h3>
                                    <p>Start by uploading your thesis documents above.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Document Type</th>
                                                <th>File Name</th>
                                                <th>File Size</th>
                                                <th>Uploaded By</th>
                                                <th>Upload Date</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documents as $doc): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge badge-primary">
                                                            <?php echo htmlspecialchars($doc['document_type']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($doc['file_name']); ?></td>
                                                    <td><?php echo number_format($doc['file_size'] / 1024, 2); ?> KB</td>
                                                    <td><?php echo htmlspecialchars($doc['uploaded_by_name']); ?></td>
                                                    <td><?php echo date('M d, Y H:i', strtotime($doc['uploaded_at'])); ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="download.php?id=<?php echo $doc['document_id']; ?>" class="btn-icon btn-view" title="Download">
                                                                ⬇️
                                                            </a>
                                                            <?php if ($doc['uploaded_by'] === $_SESSION['user_id']): ?>
                                                                <form method="POST" action="documents.php" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this document?');">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                                    <input type="hidden" name="action" value="delete">
                                                                    <input type="hidden" name="document_id" value="<?php echo $doc['document_id']; ?>">
                                                                    <button type="submit" class="btn-icon btn-delete" title="Delete">
                                                                        🗑️
                                                                    </button>
                                                                </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // File size validation
        document.getElementById('document')?.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // Convert to MB
                if (fileSize > 10) {
                    alert('File size must be less than 10MB!');
                    this.value = '';
                    return false;
                }

                // Display file info
                const fileName = file.name;
                const fileSizeMB = fileSize.toFixed(2);
                console.log('Selected file: ' + fileName + ' (' + fileSizeMB + ' MB)');
            }
        });

        // Form submission
        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('document');
            const documentType = document.getElementById('document_type');

            if (!fileInput.files.length) {
                e.preventDefault();
                alert('Please select a file to upload.');
                return false;
            }

            if (!documentType.value) {
                e.preventDefault();
                alert('Please select a document type.');
                return false;
            }

            // Disable submit button
            const submitBtn = document.getElementById('uploadBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
        });
    </script>
    <script src="../assets/js/icons.js"></script>
</body>
</html>