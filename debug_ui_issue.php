<?php
// filepath: d:\website\AES128\debug_ui_issue.php

session_start();

// Simulate real user session (admin level 3)
$_SESSION['user_id'] = 2; // Use your SuperAdmin ID
$_SESSION['username'] = 'SuperAdmin'; 
$_SESSION['user_level'] = 4; // Super admin level

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/FileManagerController.php';

echo "=== DEBUG UI ISSUE ===\n\n";

// Show current files in database
echo "Current files in database:\n";
$stmt = $pdo->query("
    SELECT f.id, f.filename, f.original_filename, f.uploaded_by, u.username, f.deleted_at
    FROM files f 
    LEFT JOIN users u ON f.uploaded_by = u.id 
    ORDER BY f.uploaded_at DESC
");

$allFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($allFiles as $file) {
    $originalName = base64_decode($file['original_filename']);
    $status = $file['deleted_at'] ? 'DELETED' : 'ACTIVE';
    echo "  ID {$file['id']}: '$originalName' by {$file['username']} - $status\n";
}

if (empty($allFiles)) {
    echo "  No files found!\n";
    exit;
}

// Get first 2 files for testing
$testFileIds = array_slice(array_column($allFiles, 'id'), 0, 2);
echo "\nUsing files for test: " . implode(', ', $testFileIds) . "\n";

// Simulate the EXACT POST request from UI
$_POST = [
    'action' => 'bulk_permanent_delete',
    'file_ids' => $testFileIds,
    'admin_mode' => '1'
];

echo "\nSimulating POST request:\n";
echo "POST: " . json_encode($_POST) . "\n";

// Initialize controllers
$authController = new AuthController($pdo);
$fileManagerController = new FileManagerController($pdo);

$userLevel = $_SESSION['user_level'] ?? 1;
$isAdmin = $userLevel >= 3;
$adminMode = isset($_POST['admin_mode']) && $_POST['admin_mode'] === '1' && $isAdmin;

echo "\nUser context:\n";
echo "User Level: $userLevel\n";
echo "Is Admin: " . ($isAdmin ? 'YES' : 'NO') . "\n"; 
echo "Admin Mode: " . ($adminMode ? 'YES' : 'NO') . "\n";

// Process action (EXACT same logic as my_files.php)
$action = $_POST['action'] ?? '';

if ($action === 'bulk_permanent_delete') {
    $fileIds = $_POST['file_ids'] ?? [];
    
    echo "\nProcessing bulk_permanent_delete...\n";
    echo "File IDs received: " . json_encode($fileIds) . "\n";
    echo "File IDs type: " . gettype($fileIds) . "\n";
    echo "File IDs count: " . count($fileIds) . "\n";
    
    if (!empty($fileIds)) {
        if ($isAdmin && $adminMode) {
            echo "\nUsing ADMIN function: adminBulkPermanentDeleteFiles\n";
            $results = $fileManagerController->adminBulkPermanentDeleteFiles($fileIds);
        } else {
            echo "\nUsing REGULAR function: bulkPermanentDeleteFiles\n";
            $results = $fileManagerController->bulkPermanentDeleteFiles($fileIds);
        }
        
        echo "Raw results: " . json_encode($results) . "\n";
        
        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
        $totalCount = count($results);
        
        echo "\nSUMMARY:\n";
        echo "Total files processed: $totalCount\n";
        echo "Successful deletions: $successCount\n";
        echo "Failed deletions: " . ($totalCount - $successCount) . "\n";
        
        if ($successCount > 0) {
            echo "MESSAGE: Berhasil menghapus permanen $successCount dari $totalCount file\n";
        } else {
            echo "MESSAGE: Gagal menghapus permanen file - $totalCount dari $totalCount gagal\n";
        }
        
        // Check database
        echo "\nVerification in database:\n";
        foreach ($fileIds as $fileId) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $exists = $stmt->fetchColumn();
            echo "  File $fileId: " . ($exists ? 'STILL EXISTS' : 'DELETED') . "\n";
        }
        
    } else {
        echo "ERROR: No file IDs provided in POST data\n";
    }
} else {
    echo "ERROR: Action not recognized: '$action'\n";
}
?>