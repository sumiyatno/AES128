<?php

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/FileManagerController.php';

header('Content-Type: application/json');

$authController = new AuthController($pdo);
if (!$authController->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$fileId = (int)($_GET['id'] ?? 0);
if (!$fileId) {
    echo json_encode(['success' => false, 'message' => 'File ID tidak valid']);
    exit;
}

try {
    $fileManagerController = new FileManagerController($pdo);
    $file = $fileManagerController->getFileDetails($fileId);
    
    echo json_encode([
        'success' => true,
        'file' => [
            'id' => $file['id'],
            'original_filename' => $file['original_filename'],
            'label_id' => $file['label']['id'],
            'access_level_id' => $file['access_level']['id']
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>