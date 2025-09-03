<?php

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/FileManagerController.php';

$authController = new AuthController($pdo);
if (!$authController->isLoggedIn()) {
    http_response_code(401);
    echo '<div class="alert alert-danger">Unauthorized</div>';
    exit;
}

$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) {
    echo '<div class="alert alert-danger">File ID tidak valid</div>';
    exit;
}

try {
    $fileManagerController = new FileManagerController($pdo);
    $file = $fileManagerController->getFileDetails($fileId);
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6>Informasi File</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Nama File:</strong></td>
                    <td><?= htmlspecialchars($file['original_filename']) ?></td>
                </tr>
                <tr>
                    <td><strong>Ukuran:</strong></td>
                    <td><?= number_format($file['file_size'] / 1024, 2) ?> KB</td>
                </tr>
                <tr>
                    <td><strong>Tipe MIME:</strong></td>
                    <td><?= htmlspecialchars($file['mime_type']) ?></td>
                </tr>
                <tr>
                    <td><strong>Jumlah Download:</strong></td>
                    <td><?= $file['download_count'] ?> kali</td>
                </tr>
                <tr>
                    <td><strong>Upload:</strong></td>
                    <td><?= date('d M Y H:i', strtotime($file['uploaded_at'])) ?></td>
                </tr>
                <?php if ($file['deleted_at']): ?>
                <tr>
                    <td><strong>Dihapus:</strong></td>
                    <td><?= date('d M Y H:i', strtotime($file['deleted_at'])) ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        <div class="col-md-6">
            <h6>Metadata</h6>
            <table class="table table-sm">
                <tr>
                    <td><strong>Label:</strong></td>
                    <td>
                        <span class="badge bg-secondary"><?= htmlspecialchars($file['label']['name']) ?></span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Access Level:</strong></td>
                    <td>
                        <span class="badge bg-info"><?= htmlspecialchars($file['access_level']['name']) ?></span>
                    </td>
                </tr>
                <tr>
                    <td><strong>Restricted:</strong></td>
                    <td>
                        <?php if ($file['is_restricted']): ?>
                            <span class="badge bg-danger">Ya</span>
                        <?php else: ?>
                            <span class="badge bg-success">Tidak</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>File ID:</strong></td>
                    <td><code><?= $file['filename'] ?></code></td>
                </tr>
            </table>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="download.php?id=<?= $file['id'] ?>" class="btn btn-primary">
            <i class="fas fa-download"></i> Download
        </a>
        <?php if (!$file['deleted_at']): ?>
        <button class="btn btn-outline-success" onclick="editFile(<?= $file['id'] ?>)" data-bs-dismiss="modal">
            <i class="fas fa-edit"></i> Edit
        </button>
        <?php endif; ?>
    </div>
    
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>