<?php
// filepath: d:\website\AES128\test_admin_delete.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/FileManagerController.php';

class AdminDeleteFileTester {
    private $pdo;
    private $fileManager;
    private $debugMode = true;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fileManager = new FileManagerController($pdo);
    }
    
    public function runTest() {
        $this->printHeader("ADMIN DELETE FILE TEST - DETAILED DEBUG");
        
        try {
            // Step 1: Analyze current database state
            $this->analyzeCurrentState();
            
            // Step 2: Create test admin user
            $adminUserId = $this->createTestAdmin();
            
            // Step 3: Find existing file owned by other user
            $targetFile = $this->findTargetFile();
            
            if (!$targetFile) {
                $this->printError("No target file found to test admin delete");
                return false;
            }
            
            // Step 4: Simulate admin login
            $this->simulateAdminLogin($adminUserId);
            
            // Step 5: Test admin delete file
            $this->testAdminDeleteFile($targetFile);
            
            // Step 6: Verify deletion
            $this->verifyDeletion($targetFile['id']);
            
            // Step 7: Test admin restore
            $this->testAdminRestore($targetFile['id']);
            
            // Step 8: Test admin hard delete
            $this->testAdminHardDelete($targetFile['id']);
            
            // Step 9: Cleanup
            $this->cleanup($adminUserId);
            
        } catch (Exception $e) {
            $this->printError("Test failed: " . $e->getMessage());
            $this->printDebug("Stack trace: " . $e->getTraceAsString());
        }
    }
    
    private function analyzeCurrentState() {
        $this->printStep("Analyzing Current Database State");
        
        try {
            // Check total files
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM files");
            $totalFiles = $stmt->fetchColumn();
            $this->printDebug("Total files in database: $totalFiles");
            
            // Check active files
            $stmt = $this->pdo->query("SELECT COUNT(*) as active FROM files WHERE deleted_at IS NULL");
            $activeFiles = $stmt->fetchColumn();
            $this->printDebug("Active files: $activeFiles");
            
            // Check deleted files
            $stmt = $this->pdo->query("SELECT COUNT(*) as deleted FROM files WHERE deleted_at IS NOT NULL");
            $deletedFiles = $stmt->fetchColumn();
            $this->printDebug("Deleted files: $deletedFiles");
            
            // List all users and their files
            $stmt = $this->pdo->query("
                SELECT u.id, u.username, 
                       COUNT(f.id) as total_files,
                       COUNT(CASE WHEN f.deleted_at IS NULL THEN 1 END) as active_files
                FROM users u 
                LEFT JOIN files f ON u.id = f.uploaded_by 
                GROUP BY u.id, u.username
                ORDER BY u.id
            ");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->printDebug("Users and their files:");
            foreach ($users as $user) {
                $this->printDebug("  User ID {$user['id']} ({$user['username']}): {$user['active_files']} active, {$user['total_files']} total");
            }
            
            // List some sample files with owners
            $stmt = $this->pdo->query("
                SELECT f.id, f.filename, f.original_filename, f.uploaded_by, u.username, f.deleted_at
                FROM files f 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                ORDER BY f.uploaded_at DESC 
                LIMIT 5
            ");
            $sampleFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->printDebug("Sample files (latest 5):");
            foreach ($sampleFiles as $file) {
                $originalName = base64_decode($file['original_filename']);
                $status = $file['deleted_at'] ? 'DELETED' : 'ACTIVE';
                $this->printDebug("  File ID {$file['id']}: '{$originalName}' owned by {$file['username']} (ID: {$file['uploaded_by']}) - $status");
            }
            
        } catch (Exception $e) {
            $this->printError("Failed to analyze database state: " . $e->getMessage());
        }
    }
    
    private function createTestAdmin() {
        $this->printStep("Creating Test Admin User");
        
        try {
            $adminUsername = 'test_admin_delete_' . uniqid();
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            
            // Get default access level
            $stmt = $this->pdo->query("SELECT id FROM access_levels ORDER BY id LIMIT 1");
            $defaultAccessLevel = $stmt->fetchColumn() ?: 1;
            
            // Create admin user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password, access_level_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$adminUsername, $adminPassword, $defaultAccessLevel]);
            
            $adminUserId = $this->pdo->lastInsertId();
            $this->printDebug("Created admin user: $adminUsername (ID: $adminUserId)");
            
            return $adminUserId;
            
        } catch (Exception $e) {
            $this->printError("Failed to create admin user: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function findTargetFile() {
        $this->printStep("Finding Target File for Admin Delete Test");
        
        try {
            // Find a file that is NOT owned by admin and is currently active
            $stmt = $this->pdo->query("
                SELECT f.*, u.username as owner_username
                FROM files f 
                JOIN users u ON f.uploaded_by = u.id 
                WHERE f.deleted_at IS NULL 
                AND u.username NOT LIKE 'test_admin_delete_%'
                ORDER BY f.uploaded_at DESC
                LIMIT 1
            ");
            
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file) {
                $originalName = base64_decode($file['original_filename']);
                $this->printDebug("Found target file:");
                $this->printDebug("  File ID: {$file['id']}");
                $this->printDebug("  Filename: {$file['filename']}");
                $this->printDebug("  Original Name: $originalName");
                $this->printDebug("  Owner: {$file['owner_username']} (ID: {$file['uploaded_by']})");
                $this->printDebug("  Label ID: {$file['label_id']}");
                $this->printDebug("  Access Level ID: {$file['access_level_id']}");
                $this->printDebug("  Uploaded At: {$file['uploaded_at']}");
                $this->printDebug("  Current Status: ACTIVE");
                
                return $file;
            } else {
                $this->printError("No suitable target file found for testing");
                return null;
            }
            
        } catch (Exception $e) {
            $this->printError("Error finding target file: " . $e->getMessage());
            return null;
        }
    }
    
    private function simulateAdminLogin($adminUserId) {
        $this->printStep("Simulating Admin Login");
        
        try {
            // Get admin user info
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$adminUserId]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin) {
                throw new RuntimeException("Admin user not found");
            }
            
            // Set session for admin (level 3 for admin privileges)
            $_SESSION = [];
            $_SESSION['user_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['user_level'] = 3; // Admin level
            
            $this->printDebug("Admin login simulated:");
            $this->printDebug("  User ID: {$admin['id']}");
            $this->printDebug("  Username: {$admin['username']}");
            $this->printDebug("  Level: 3 (Admin)");
            $this->printDebug("  Session: " . json_encode($_SESSION));
            
        } catch (Exception $e) {
            $this->printError("Failed to simulate admin login: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function testAdminDeleteFile($targetFile) {
        $this->printStep("Testing Admin Delete File Function");
        
        try {
            $fileId = $targetFile['id'];
            $originalName = base64_decode($targetFile['original_filename']);
            $originalOwner = $targetFile['uploaded_by'];
            
            $this->printDebug("About to delete file:");
            $this->printDebug("  File ID: $fileId");
            $this->printDebug("  Original Name: $originalName");
            $this->printDebug("  Current Owner ID: $originalOwner");
            $this->printDebug("  Admin ID: {$_SESSION['user_id']}");
            $this->printDebug("  Delete Type: SOFT DELETE");
            
            // Check file status before deletion
            $stmt = $this->pdo->prepare("SELECT deleted_at FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $beforeDelete = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->printDebug("File status before delete: " . ($beforeDelete['deleted_at'] ? 'DELETED' : 'ACTIVE'));
            
            // Perform admin delete (soft delete)
            $this->printDebug("Calling adminDeleteFile($fileId, false)...");
            $result = $this->fileManager->adminDeleteFile($fileId, false);
            
            $this->printDebug("Admin delete result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            if ($result) {
                $this->printSuccess("✓ Admin successfully deleted file owned by another user");
            } else {
                $this->printError("✗ Admin delete returned false");
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->printError("Admin delete failed with exception: " . $e->getMessage());
            $this->printDebug("Exception details: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private function verifyDeletion($fileId) {
        $this->printStep("Verifying File Deletion");
        
        try {
            // Check file status in database
            $stmt = $this->pdo->prepare("
                SELECT f.*, u.username as owner_username 
                FROM files f 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                WHERE f.id = ?
            ");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($file) {
                $originalName = base64_decode($file['original_filename']);
                $this->printDebug("File verification after delete:");
                $this->printDebug("  File ID: {$file['id']}");
                $this->printDebug("  Original Name: $originalName");
                $this->printDebug("  Owner: {$file['owner_username']} (ID: {$file['uploaded_by']})");
                $this->printDebug("  Deleted At: " . ($file['deleted_at'] ?: 'NULL (NOT DELETED)'));
                
                if ($file['deleted_at']) {
                    $this->printSuccess("✓ File successfully marked as deleted");
                    $this->printDebug("  Deletion timestamp: {$file['deleted_at']}");
                } else {
                    $this->printError("✗ File was NOT marked as deleted");
                }
            } else {
                $this->printError("✗ File not found in database after delete operation");
            }
            
        } catch (Exception $e) {
            $this->printError("Error verifying deletion: " . $e->getMessage());
        }
    }
    
    private function testAdminRestore($fileId) {
        $this->printStep("Testing Admin Restore File Function");
        
        try {
            $this->printDebug("About to restore file ID: $fileId");
            
            // Check if file is actually deleted before restore
            $stmt = $this->pdo->prepare("SELECT deleted_at FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $beforeRestore = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$beforeRestore['deleted_at']) {
                $this->printError("File is not deleted, cannot test restore");
                return false;
            }
            
            $this->printDebug("File status before restore: DELETED at {$beforeRestore['deleted_at']}");
            
            // Perform admin restore
            $this->printDebug("Calling adminRestoreFile($fileId)...");
            $result = $this->fileManager->adminRestoreFile($fileId);
            
            $this->printDebug("Admin restore result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Verify restore
            $stmt = $this->pdo->prepare("SELECT deleted_at FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $afterRestore = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $this->printDebug("File status after restore: " . ($afterRestore['deleted_at'] ? 'STILL DELETED' : 'RESTORED'));
            
            if ($result && !$afterRestore['deleted_at']) {
                $this->printSuccess("✓ Admin successfully restored deleted file");
                return true;
            } else {
                $this->printError("✗ Admin restore failed");
                return false;
            }
            
        } catch (Exception $e) {
            $this->printError("Admin restore failed with exception: " . $e->getMessage());
            $this->printDebug("Exception details: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private function testAdminHardDelete($fileId) {
        $this->printStep("Testing Admin Hard Delete File Function");
        
        try {
            $this->printDebug("About to PERMANENTLY delete file ID: $fileId");
            
            // Get file info before hard delete
            $stmt = $this->pdo->prepare("
                SELECT f.*, u.username as owner_username 
                FROM files f 
                LEFT JOIN users u ON f.uploaded_by = u.id 
                WHERE f.id = ?
            ");
            $stmt->execute([$fileId]);
            $beforeDelete = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($beforeDelete) {
                $originalName = base64_decode($beforeDelete['original_filename']);
                $this->printDebug("File info before hard delete:");
                $this->printDebug("  File ID: {$beforeDelete['id']}");
                $this->printDebug("  Original Name: $originalName");
                $this->printDebug("  Owner: {$beforeDelete['owner_username']} (ID: {$beforeDelete['uploaded_by']})");
                $this->printDebug("  Current Status: " . ($beforeDelete['deleted_at'] ? 'SOFT DELETED' : 'ACTIVE'));
            }
            
            // Perform admin hard delete
            $this->printDebug("Calling adminDeleteFile($fileId, true)...");
            $result = $this->fileManager->adminDeleteFile($fileId, true);
            
            $this->printDebug("Admin hard delete result: " . ($result ? 'SUCCESS' : 'FAILED'));
            
            // Verify hard delete - file should be completely gone
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM files WHERE id = ?");
            $stmt->execute([$fileId]);
            $fileExists = $stmt->fetchColumn();
            
            $this->printDebug("File exists in database after hard delete: " . ($fileExists ? 'YES' : 'NO'));
            
            if ($result && !$fileExists) {
                $this->printSuccess("✓ Admin successfully performed hard delete - file completely removed");
                return true;
            } else {
                $this->printError("✗ Admin hard delete failed - file still exists");
                return false;
            }
            
        } catch (Exception $e) {
            $this->printError("Admin hard delete failed with exception: " . $e->getMessage());
            $this->printDebug("Exception details: " . $e->getTraceAsString());
            return false;
        }
    }
    
    private function cleanup($adminUserId) {
        $this->printStep("Cleaning Up Test Data");
        
        try {
            // Remove test admin user
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$adminUserId]);
            $this->printDebug("Removed test admin user ID: $adminUserId");
            
            // Clear session
            $_SESSION = [];
            $this->printDebug("Cleared session data");
            
            $this->printSuccess("✓ Cleanup completed successfully");
            
        } catch (Exception $e) {
            $this->printError("Cleanup failed: " . $e->getMessage());
        }
    }
    
    private function printHeader($title) {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo str_pad($title, 80, " ", STR_PAD_BOTH) . "\n";
        echo str_repeat("=", 80) . "\n\n";
    }
    
    private function printStep($stepName) {
        echo "\n" . str_repeat("-", 60) . "\n";
        echo "STEP: $stepName\n";
        echo str_repeat("-", 60) . "\n";
    }
    
    private function printDebug($message) {
        if ($this->debugMode) {
            echo "[DEBUG] $message\n";
        }
    }
    
    private function printSuccess($message) {
        echo "\033[32m[SUCCESS]\033[0m $message\n";
    }
    
    private function printError($message) {
        echo "\033[31m[ERROR]\033[0m $message\n";
    }
}

// Run the detailed admin delete test
try {
    $tester = new AdminDeleteFileTester($pdo);
    $tester->runTest();
} catch (Exception $e) {
    echo "\033[31m[FATAL ERROR]\033[0m " . $e->getMessage() . "\n";
}
?>