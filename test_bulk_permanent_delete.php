<?php
// Start output buffering to prevent session warnings
ob_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/FileManagerController.php';

class BulkPermanentDeleteTester {
    private $pdo;
    private $fileManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fileManager = new FileManagerController($pdo);
    }
    
    public function runTest() {
        echo "=== BULK PERMANENT DELETE TEST ===\n\n";
        
        try {
            // Step 1: Create test admin
            $adminId = $this->createTestAdmin();
            
            // Step 2: Create test files
            $testFileIds = $this->createTestFiles($adminId);
            
            if (empty($testFileIds)) {
                echo "ERROR: No test files created\n";
                return;
            }
            
            // Step 3: Simulate admin login
            $this->simulateAdminLogin($adminId);
            
            // Step 4: Test bulk permanent delete
            $this->testBulkPermanentDelete($testFileIds);
            
            // Step 5: Cleanup admin
            $this->cleanup($adminId);
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage() . "\n";
            echo "Trace: " . $e->getTraceAsString() . "\n";
        }
    }
    
    private function createTestAdmin() {
        echo "Creating test admin...\n";
        
        $username = 'test_bulk_admin_' . uniqid();
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        
        // Get default access level
        $stmt = $this->pdo->query("SELECT id FROM access_levels ORDER BY id LIMIT 1");
        $defaultAccessLevel = $stmt->fetchColumn() ?: 1;
        
        $stmt = $this->pdo->prepare("INSERT INTO users (username, password, access_level_id) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $defaultAccessLevel]);
        
        $adminId = $this->pdo->lastInsertId();
        echo "Admin created: ID $adminId, Username: $username\n";
        
        return $adminId;
    }
    
    private function createTestFiles($adminId) {
        echo "Creating test files...\n";
        
        $testFileIds = [];
        
        // Get default label and access level
        $stmt = $this->pdo->query("SELECT id FROM labels ORDER BY id LIMIT 1");
        $labelId = $stmt->fetchColumn() ?: 1;
        
        $stmt = $this->pdo->query("SELECT id FROM access_levels ORDER BY id LIMIT 1");
        $accessLevelId = $stmt->fetchColumn() ?: 1;
        
        for ($i = 1; $i <= 3; $i++) {
            $filename = 'test_bulk_file_' . uniqid();
            $originalName = "Test Bulk File $i.txt";
            $content = "This is test content for bulk delete file $i";
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
            
            $fileId = $this->pdo->lastInsertId();
            $testFileIds[] = $fileId;
            
            echo "Test file created: ID $fileId, Name: $originalName\n";
        }
        
        return $testFileIds;
    }
    
    private function simulateAdminLogin($adminId) {
        echo "Simulating admin login...\n";
        
        $_SESSION = [];
        $_SESSION['user_id'] = $adminId;
        $_SESSION['username'] = 'test_bulk_admin';
        $_SESSION['user_level'] = 3; // Admin level
        
        echo "Admin session created: " . json_encode($_SESSION) . "\n";
    }
    
    private function testBulkPermanentDelete($fileIds) {
        echo "\nTesting bulk permanent delete...\n";
        echo "File IDs to delete: " . implode(', ', $fileIds) . "\n";
        
        // Test regular bulk permanent delete
        echo "\n--- Testing Regular Bulk Permanent Delete ---\n";
        $results1 = $this->fileManager->bulkPermanentDeleteFiles($fileIds);
        
        echo "Regular bulk delete results:\n";
        foreach ($results1 as $fileId => $result) {
            echo "  File $fileId: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . " - {$result['message']}\n";
        }
        
        // Create more test files for admin test
        echo "\n--- Creating new files for admin test ---\n";
        $newFileIds = $this->createTestFiles($_SESSION['user_id']);
        
        // Test admin bulk permanent delete
        echo "\n--- Testing Admin Bulk Permanent Delete ---\n";
        try {
            $results2 = $this->fileManager->adminBulkPermanentDeleteFiles($newFileIds);
            
            echo "Admin bulk delete results:\n";
            foreach ($results2 as $fileId => $result) {
                echo "  File $fileId: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . " - {$result['message']}\n";
            }
        } catch (Exception $e) {
            echo "Admin bulk delete failed: " . $e->getMessage() . "\n";
        }
        
        // Verify deletion
        echo "\n--- Verifying Deletion ---\n";
        $allTestFiles = array_merge($fileIds, $newFileIds);
        foreach ($allTestFiles as $fileId) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $exists = $stmt->fetchColumn();
            
            echo "  File $fileId: " . ($exists ? 'STILL EXISTS' : 'DELETED') . "\n";
        }
    }
    
    private function cleanup($adminId) {
        echo "\nCleaning up...\n";
        
        // Delete test files (any remaining)
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE filename LIKE 'test_bulk_file_%'");
        $stmt->execute();
        echo "Cleaned up test files\n";
        
        // Delete test admin
        $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$adminId]);
        echo "Cleaned up test admin\n";
        
        $_SESSION = [];
        echo "Cleanup completed\n";
    }
}

// Run the test
try {
    $tester = new BulkPermanentDeleteTester($pdo);
    $tester->runTest();
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
}
?>