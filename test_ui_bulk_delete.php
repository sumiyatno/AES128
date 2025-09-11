<?php
// filepath: d:\website\AES128\test_ui_bulk_delete.php

ob_start();
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/FileManagerController.php';

class UIBulkDeleteTester {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function runTest() {
        echo "=== UI BULK PERMANENT DELETE SIMULATION ===\n\n";
        
        try {
            // Step 1: Create test admin and files
            $adminId = $this->createTestAdmin();
            $fileIds = $this->createTestFiles($adminId);
            
            // Step 2: Simulate admin login
            $this->simulateAdminLogin($adminId);
            
            // Step 3: Simulate UI POST request
            $this->simulateUIRequest($fileIds);
            
            // Step 4: Cleanup
            $this->cleanup($adminId);
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function createTestAdmin() {
        echo "Creating test admin...\n";
        
        $username = 'test_ui_admin_' . uniqid();
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        
        $stmt = $this->pdo->query("SELECT id FROM access_levels ORDER BY id LIMIT 1");
        $defaultAccessLevel = $stmt->fetchColumn() ?: 1;
        
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, access_level_id) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $defaultAccessLevel]);
        
        $adminId = $this->pdo->lastInsertId();
        echo "Admin created: ID $adminId\n";
        
        return $adminId;
    }
    
    private function createTestFiles($adminId) {
        echo "Creating test files...\n";
        
        $fileIds = [];
        
        $stmt = $this->pdo->query("SELECT id FROM labels ORDER BY id LIMIT 1");
        $labelId = $stmt->fetchColumn() ?: 1;
        
        $stmt = $this->pdo->query("SELECT id FROM access_levels ORDER BY id LIMIT 1");
        $accessLevelId = $stmt->fetchColumn() ?: 1;
        
        for ($i = 1; $i <= 2; $i++) {
            $filename = 'test_ui_file_' . uniqid();
            $originalName = "Test UI File $i.txt";
            $content = "UI test content $i";
            $encryptionIv = random_bytes(16);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO files (filename, original_filename, mime_type, file_data, uploaded_by, label_id, access_level_id, encryption_iv, uploaded_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $filename,
                base64_encode($originalName),
                base64_encode('text/plain'),
                $content,
                $adminId,
                $labelId,
                $accessLevelId,
                $encryptionIv
            ]);
            
            $fileIds[] = $this->pdo->lastInsertId();
        }
        
        echo "Created files: " . implode(', ', $fileIds) . "\n";
        return $fileIds;
    }
    
    private function simulateAdminLogin($adminId) {
        echo "Simulating admin login...\n";
        
        $_SESSION['user_id'] = $adminId;
        $_SESSION['username'] = 'test_ui_admin';
        $_SESSION['user_level'] = 3;
        
        echo "Session: " . json_encode($_SESSION) . "\n";
    }
    
    private function simulateUIRequest($fileIds) {
        echo "\nSimulating UI POST request...\n";
        
        // Simulate exact POST data from UI form
        $_POST = [
            'action' => 'bulk_permanent_delete',
            'file_ids' => $fileIds,
            'admin_mode' => '1'
        ];
        
        echo "POST data: " . json_encode($_POST) . "\n";
        
        // Simulate the exact logic from my_files.php
        try {
            $authController = new AuthController($this->pdo);
            
            if (!$authController->isLoggedIn()) {
                throw new RuntimeException("Not logged in");
            }
            
            $userLevel = $_SESSION['user_level'] ?? 1;
            $isAdmin = $userLevel >= 3;
            $adminMode = isset($_POST['admin_mode']) && $_POST['admin_mode'] === '1' && $isAdmin;
            
            echo "User Level: $userLevel\n";
            echo "Is Admin: " . ($isAdmin ? 'YES' : 'NO') . "\n";
            echo "Admin Mode: " . ($adminMode ? 'YES' : 'NO') . "\n";
            
            $fileManagerController = new FileManagerController($this->pdo);
            
            // Process bulk permanent delete
            $action = $_POST['action'] ?? '';
            
            if ($action === 'bulk_permanent_delete') {
                $fileIds = $_POST['file_ids'] ?? [];
                
                echo "Processing bulk permanent delete...\n";
                echo "File IDs received: " . json_encode($fileIds) . "\n";
                
                if (!empty($fileIds)) {
                    if ($isAdmin && $adminMode) {
                        echo "Using adminBulkPermanentDeleteFiles...\n";
                        $results = $fileManagerController->adminBulkPermanentDeleteFiles($fileIds);
                    } else {
                        echo "Using bulkPermanentDeleteFiles...\n";
                        $results = $fileManagerController->bulkPermanentDeleteFiles($fileIds);
                    }
                    
                    $successCount = count(array_filter($results, function($r) { return $r['success']; }));
                    $totalCount = count($results);
                    
                    echo "Results: $successCount/$totalCount successful\n";
                    echo "Detailed results:\n";
                    
                    foreach ($results as $fileId => $result) {
                        echo "  File $fileId: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . " - {$result['message']}\n";
                    }
                    
                    // Verify deletion
                    echo "\nVerifying deletion in database...\n";
                    foreach ($fileIds as $fileId) {
                        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM files WHERE id = ?");
                        $stmt->execute([$fileId]);
                        $exists = $stmt->fetchColumn();
                        
                        echo "  File $fileId: " . ($exists ? 'STILL EXISTS' : 'DELETED') . "\n";
                    }
                    
                } else {
                    echo "ERROR: No file IDs provided\n";
                }
            }
            
        } catch (Exception $e) {
            echo "UI SIMULATION ERROR: " . $e->getMessage() . "\n";
        }
    }
    
    private function cleanup($adminId) {
        echo "\nCleaning up...\n";
        
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE filename LIKE 'test_ui_file_%'");
        $stmt->execute();
        
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        
        $_SESSION = [];
        $_POST = [];
        
        echo "Cleanup completed\n";
    }
}

// Run the test
try {
    $tester = new UIBulkDeleteTester($pdo);
    $tester->runTest();
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}

ob_end_flush();
?>