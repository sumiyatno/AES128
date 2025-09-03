<?php

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/FileManagerController.php';

// Check authentication
$authController = new AuthController($pdo);
if (!$authController->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$fileManagerController = new FileManagerController($pdo);

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_file':
                $fileId = (int)($_POST['file_id'] ?? 0);
                $newName = trim($_POST['new_name'] ?? '');
                $labelId = (int)($_POST['label_id'] ?? 0);
                $accessLevelId = (int)($_POST['access_level_id'] ?? 0);
                
                if ($fileManagerController->updateFileMetadata($fileId, $newName, $labelId, $accessLevelId)) {
                    $message = 'File berhasil diupdate';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal mengupdate file';
                    $messageType = 'error';
                }
                break;
                
            case 'delete_file':
                $fileId = (int)($_POST['file_id'] ?? 0);
                $hardDelete = isset($_POST['hard_delete']);
                
                if ($fileManagerController->deleteFile($fileId, $hardDelete)) {
                    $message = 'File berhasil dihapus';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal menghapus file';
                    $messageType = 'error';
                }
                break;

            // NEW ACTION: Permanent delete
            case 'permanent_delete_file':
                $fileId = (int)($_POST['file_id'] ?? 0);
                
                if ($fileManagerController->permanentDeleteFile($fileId)) {
                    $message = 'File berhasil dihapus permanen dari database';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal menghapus file permanen';
                    $messageType = 'error';
                }
                break;
                
            case 'restore_file':
                $fileId = (int)($_POST['file_id'] ?? 0);
                
                if ($fileManagerController->restoreFile($fileId)) {
                    $message = 'File berhasil direstore';
                    $messageType = 'success';
                } else {
                    $message = 'Gagal merestore file';
                    $messageType = 'error';
                }
                break;
                
            case 'bulk_delete':
                $fileIds = $_POST['file_ids'] ?? [];
                $hardDelete = isset($_POST['hard_delete']);
                
                if (!empty($fileIds)) {
                    $results = $fileManagerController->bulkDeleteFiles($fileIds, $hardDelete);
                    $successCount = count(array_filter($results, function($r) { return $r['success']; }));
                    $totalCount = count($results);
                    
                    $message = "Berhasil menghapus $successCount dari $totalCount file";
                    $messageType = $successCount > 0 ? 'success' : 'error';
                } else {
                    $message = 'Pilih file yang akan dihapus';
                    $messageType = 'error';
                }
                break;

            // NEW ACTION: Bulk permanent delete
            case 'bulk_permanent_delete':
                $fileIds = $_POST['file_ids'] ?? [];
                
                if (!empty($fileIds)) {
                    $results = $fileManagerController->bulkPermanentDeleteFiles($fileIds);
                    $successCount = count(array_filter($results, function($r) { return $r['success']; }));
                    $totalCount = count($results);
                    
                    $message = "Berhasil menghapus permanen $successCount dari $totalCount file";
                    $messageType = $successCount > 0 ? 'success' : 'error';
                } else {
                    $message = 'Pilih file yang akan dihapus permanen';
                    $messageType = 'error';
                }
                break;
        }
        
        // Redirect to prevent form resubmission
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $messageType;
        header('Location: my_files.php?' . http_build_query($_GET));
        exit;
        
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'error';
    }
}

// Get flash messages
if (isset($_SESSION['flash_message'])) {
    $message = $_SESSION['flash_message'];
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
}

// Get parameters
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = max(5, min(50, (int)($_GET['limit'] ?? 10)));
$includeDeleted = isset($_GET['include_deleted']);
$searchTerm = trim($_GET['search'] ?? '');
$labelFilter = trim($_GET['label_filter'] ?? '');

// Get data
$result = $fileManagerController->getMyFiles($page, $limit, $includeDeleted, $searchTerm, $labelFilter);
$files = $result['files'];
$pagination = $result['pagination'];

$labels = $fileManagerController->getAvailableLabels();
$accessLevels = $fileManagerController->getAvailableAccessLevels();
$stats = $fileManagerController->getMyFileStats();

$userInfo = $authController->getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - AES System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .file-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .file-card {
            transition: transform 0.2s;
            border: 1px solid #e0e0e0;
        }
        .file-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .file-size {
            font-size: 0.8rem;
            color: #666;
        }
        .file-date {
            font-size: 0.75rem;
            color: #888;
        }
        .restricted-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.7rem;
        }
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php 
    // Include header dengan path yang benar
    if (file_exists('../includes/header.php')) {
        include '../includes/header.php';
    } else {
        // Fallback header jika file tidak ada
        echo '<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
                <div class="container-fluid">
                    <a class="navbar-brand" href=" dashboard.php">AES System</a>
                    <div class="navbar-nav ms-auto">
                        <a class="nav-link" href=" dashboard.php">Dashboard</a>
                    </div>
                </div>
              </nav>';
    }
    ?>
    <div class="container-fluid mt-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3">
                <div class="card stats-card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-bar me-2"></i>Statistik File
                        </h5>
                        <div class="row text-center">
                            <div class="col-4">
                                <h4><?= $stats['total_files'] ?? 0 ?></h4>
                                <small>Total</small>
                            </div>
                            <div class="col-4">
                                <h4><?= $stats['active_files'] ?? 0 ?></h4>
                                <small>Aktif</small>
                            </div>
                            <div class="col-4">
                                <h4><?= number_format(($stats['total_size'] ?? 0) / 1024 / 1024, 1) ?>MB</h4>
                                <small>Ukuran</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-tools me-2"></i>Aksi Cepat
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="upload_form.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload me-1"></i>Upload File
                            </a>
                            <button class="btn btn-outline-secondary btn-sm" onclick="toggleSelectMode()">
                                <i class="fas fa-check-square me-1"></i>Mode Pilih
                            </button>
                            <a href="?export=csv<?= $includeDeleted ? '&include_deleted=1' : '' ?>" class="btn btn-outline-info btn-sm">
                                <i class="fas fa-download me-1"></i>Export CSV
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-folder-open me-2"></i>File Manager
                    </h2>
                    <span class="badge bg-info fs-6"><?= $pagination['total_files'] ?> files</span>
                </div>

                <!-- Flash Messages -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Cari nama file..." value="<?= htmlspecialchars($searchTerm) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="label_filter" class="form-select">
                                <option value="">Semua Label</option>
                                <?php foreach ($labels as $label): ?>
                                <option value="<?= $label['id'] ?>" <?= $labelFilter == $label['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="limit" class="form-select">
                                <option value="10" <?= $limit == 10 ? 'selected' : '' ?>>10/halaman</option>
                                <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20/halaman</option>
                                <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50/halaman</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Cari
                                </button>
                                <a href="my_files.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="include_deleted" 
                                       id="includeDeleted" <?= $includeDeleted ? 'checked' : '' ?>>
                                <label class="form-check-label" for="includeDeleted">
                                    Tampilkan file yang dihapus
                                </label>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions (Hidden by default) -->
                <div id="bulkActions" class="alert alert-warning" style="display: none;">
                    <form method="POST" id="bulkForm">
                        <input type="hidden" name="action" value="bulk_delete" id="bulkAction">
                        <div class="d-flex align-items-center gap-3">
                            <span><strong id="selectedCount">0</strong> file dipilih</span>
                            <button type="submit" class="btn btn-danger btn-sm" onclick="setBulkAction('bulk_delete')">
                                <i class="fas fa-trash"></i> Hapus (Soft)
                            </button>
                            <button type="submit" class="btn btn-danger btn-sm" name="hard_delete" 
                                    onclick="setBulkAction('bulk_delete'); return confirm('Yakin ingin menghapus dengan hard delete?')">
                                <i class="fas fa-trash-alt"></i> Hapus (Hard)
                            </button>
                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                    onclick="setBulkAction('bulk_permanent_delete'); return confirm('Yakin ingin menghapus PERMANEN dari database? Tindakan ini tidak dapat dibatalkan!')">
                                <i class="fas fa-times-circle"></i> Hapus Permanen
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="toggleSelectMode()">
                                <i class="fas fa-times"></i> Batal
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Files Grid -->
                <?php if (empty($files)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada file</h5>
                    <p class="text-muted">
                        <?= empty($searchTerm) && empty($labelFilter) ? 'Belum ada file yang diupload' : 'Tidak ada file yang sesuai dengan filter' ?>
                    </p>
                    <?php if (empty($searchTerm) && empty($labelFilter)): ?>
                    <a href="upload.php" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload File Pertama
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($files as $file): ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card file-card h-100">
                            <div class="card-body text-center position-relative">
                                <!-- Checkbox for selection (hidden by default) -->
                                <input type="checkbox" class="file-selector position-absolute top-0 start-0 m-2" 
                                       value="<?= $file['id'] ?>" style="display: none;">
                                
                                <!-- Restricted badge -->
                                <?php if ($file['is_restricted_file']): ?>
                                <span class="restricted-badge">
                                    <i class="fas fa-lock"></i> Restricted
                                </span>
                                <?php endif; ?>

                                <!-- File icon -->
                                <div class="file-icon text-primary">
                                    <?php
                                    $ext = strtolower(pathinfo($file['decrypted_name'], PATHINFO_EXTENSION));
                                    $iconClass = match($ext) {
                                        'pdf' => 'fas fa-file-pdf text-danger',
                                        'doc', 'docx' => 'fas fa-file-word text-primary',
                                        'xls', 'xlsx' => 'fas fa-file-excel text-success',
                                        'ppt', 'pptx' => 'fas fa-file-powerpoint text-warning',
                                        'jpg', 'jpeg', 'png', 'gif' => 'fas fa-file-image text-info',
                                        'zip', 'rar' => 'fas fa-file-archive text-secondary',
                                        default => 'fas fa-file text-muted'
                                    };
                                    ?>
                                    <i class="<?= $iconClass ?>"></i>
                                </div>

                                <!-- File name -->
                                <h6 class="card-title text-truncate" title="<?= htmlspecialchars($file['decrypted_name']) ?>">
                                    <?= htmlspecialchars($file['decrypted_name']) ?>
                                </h6>

                                <!-- File info -->
                                <div class="file-size"><?= $file['file_size_formatted'] ?></div>
                                <div class="file-date"><?= $file['uploaded_at_formatted'] ?></div>
                                
                                <!-- Label -->
                                <span class="badge bg-secondary mt-1"><?= htmlspecialchars($file['label_name']) ?></span>
                                
                                <?php if ($file['deleted_at']): ?>
                                <span class="badge bg-danger mt-1">Dihapus</span>
                                <?php endif; ?>

                                <!-- Actions -->
                                <div class="btn-group w-100 mt-3">
                                    <button class="btn btn-outline-primary btn-sm" 
                                            onclick="viewFile(<?= $file['id'] ?>)" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <?php if (!$file['deleted_at']): ?>
                                    <button class="btn btn-outline-success btn-sm" 
                                            onclick="editFile(<?= $file['id'] ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" 
                                            onclick="deleteFile(<?= $file['id'] ?>)" title="Hapus">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="btn btn-outline-warning btn-sm" 
                                            onclick="restoreFile(<?= $file['id'] ?>)" title="Restore">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" 
                                            onclick="permanentDeleteFile(<?= $file['id'] ?>)" title="Hapus Permanen">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($pagination['has_prev']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <li class="page-item <?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($pagination['has_next']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- View File Modal -->
    <div class="modal fade" id="viewFileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detail File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewFileContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit File Modal -->
    <div class="modal fade" id="editFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="update_file">
                    <input type="hidden" name="file_id" id="editFileId">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit File</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nama File</label>
                            <input type="text" class="form-control" name="new_name" id="editFileName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Label</label>
                            <select class="form-select" name="label_id" id="editLabelId" required>
                                <?php foreach ($labels as $label): ?>
                                <option value="<?= $label['id'] ?>"><?= htmlspecialchars($label['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Access Level</label>
                            <select class="form-select" name="access_level_id" id="editAccessLevelId" required>
                                <?php foreach ($accessLevels as $level): ?>
                                <option value="<?= $level['id'] ?>"><?= htmlspecialchars($level['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectMode = false;
        let selectedFiles = [];

        function setBulkAction(action) {
            document.getElementById('bulkAction').value = action;
        }

        function toggleSelectMode() {
            selectMode = !selectMode;
            const checkboxes = document.querySelectorAll('.file-selector');
            const bulkActions = document.getElementById('bulkActions');
            
            checkboxes.forEach(checkbox => {
                checkbox.style.display = selectMode ? 'block' : 'none';
                checkbox.checked = false;
            });
            
            bulkActions.style.display = selectMode ? 'block' : 'none';
            selectedFiles = [];
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const selected = document.querySelectorAll('.file-selector:checked');
            const count = selected.length;
            document.getElementById('selectedCount').textContent = count;
            
            // Update hidden inputs for bulk form
            const bulkForm = document.getElementById('bulkForm');
            const existingInputs = bulkForm.querySelectorAll('input[name="file_ids[]"]');
            existingInputs.forEach(input => input.remove());
            
            selected.forEach(checkbox => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'file_ids[]';
                input.value = checkbox.value;
                bulkForm.appendChild(input);
            });
        }

        // Add event listeners to checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.file-selector').forEach(checkbox => {
                checkbox.addEventListener('change', updateSelectedCount);
            });
        });

        function viewFile(fileId) {
            // Load file details
            fetch(`file_details.php?id=${fileId}`)
                .then(response => response.text())
                .then(html => {
                    document.getElementById('viewFileContent').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('viewFileModal')).show();
                })
                .catch(() => {
                    alert('Gagal memuat detail file');
                });
        }

        function editFile(fileId) {
            // Load file data for editing
            fetch(`get_file_data.php?id=${fileId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('editFileId').value = data.file.id;
                        document.getElementById('editFileName').value = data.file.original_filename;
                        document.getElementById('editLabelId').value = data.file.label_id;
                        document.getElementById('editAccessLevelId').value = data.file.access_level_id;
                        new bootstrap.Modal(document.getElementById('editFileModal')).show();
                    } else {
                        alert('Gagal memuat data file: ' + data.message);
                    }
                })
                .catch(() => {
                    alert('Gagal memuat data file');
                });
        }

        function deleteFile(fileId) {
            if (confirm('Yakin ingin menghapus file ini? (Soft Delete)')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_file">
                    <input type="hidden" name="file_id" value="${fileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // UPDATED: Rename to avoid confusion
        function permanentDeleteFile(fileId) {
            if (confirm('Yakin ingin menghapus PERMANEN file ini dari database? Tindakan ini tidak dapat dibatalkan!')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="permanent_delete_file">
                    <input type="hidden" name="file_id" value="${fileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function restoreFile(fileId) {
            if (confirm('Yakin ingin merestore file ini?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="restore_file">
                    <input type="hidden" name="file_id" value="${fileId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>