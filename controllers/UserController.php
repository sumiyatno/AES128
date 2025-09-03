<?php
// filepath: d:\website\AES128\controllers\UserController.php

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/AccessLevel.php';
require_once __DIR__ . '/../controllers/FileController.php';

class UserController {
    private $pdo;
    private $userModel;
    private $accessLevelModel;
    private $fileController;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->userModel = new User();
        $this->accessLevelModel = new AccessLevel($pdo);
        $this->fileController = new FileController($pdo);
    }

    // ============ EXISTING METHODS (TIDAK BERUBAH) ============

    // Ambil semua user beserta level akses
    public function allUsers() {
        return $this->userModel->getAllUsers();
    }

    // Ambil user by id
    public function getUser($id) {
        return $this->userModel->getUserById($id);
    }

    // Buat user baru
    public function createUser($username, $password, $access_level_id) {
        return $this->userModel->createUser($username, $password, $access_level_id);
    }

    // Update user - FIXED LOGIC
    public function updateUser($id, $username, $password, $access_level_id) {
        // Jika password kosong, jangan update password
        if (empty($password)) {
            // Update tanpa password
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET username = ?, access_level_id = ?
                WHERE id = ?
            ");
            return $stmt->execute([$username, $access_level_id, $id]);
        } else {
            // Update dengan password baru
            return $this->userModel->updateUser($id, $username, $password, $access_level_id);
        }
    }

    // Hapus user
    public function deleteUser($id) {
        return $this->userModel->deleteUser($id);
    }

    // Cek apakah user bisa akses file tertentu
    // $fileAccessLevel: level akses file (1,2,3)
    // $userId: id user
    public function canUserAccessFile($fileAccessLevel, $userId) {
        $user = $this->userModel->getUserById($userId);
        if (!$user) return false;
        $userRole = (int)$user['level'];
        return $this->fileController->canAccessFile($fileAccessLevel, $userRole);
    }

    // Fungsi untuk cek akses fitur berdasarkan role
    // $role: level role user (1=staff, 2=kasub, 3=kabid, 4=super admin)
    // $feature: nama fitur ('upload', 'dashboard', 'create_label', 'manage_account', 'master_key', 'admin')
    public function canAccessFeature($role, $feature) {
        $role = (int)$role;
        $features = [
            1 => ['upload', 'dashboard', 'download', 'file_manager'],
            2 => ['upload', 'dashboard', 'create_label','download', 'file_manager'],
            3 => ['upload', 'dashboard', 'create_label','download', 'file_manager'],
            4 => ['upload', 'dashboard', 'create_label', 'manage_account', 'manage', 'admin', 'master_key', 'rotateKeyWithPasswords', 'register','download', 'logs', 'file_manager']
        ];
        return in_array($feature, $features[$role] ?? []);
    }

    // Method khusus untuk cek akses key rotation
    public function canRotateKey($role) {
        return $this->canAccessFeature($role, 'master_key');
    }

    // ============ TAMBAHAN CRUD METHODS (FIXED) ============

    /**
     * Get all users with pagination
     */
    public function getUsersPaginated($page = 1, $limit = 10) {
        try {
            $offset = ($page - 1) * $limit;
            
            $stmt = $this->pdo->prepare("
                SELECT u.*, a.name AS access_name, a.description 
                FROM users u
                LEFT JOIN access_levels a ON u.access_level_id = a.id
                ORDER BY u.id DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersPaginated: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get total users count
     */
    public function getTotalUsersCount() {
        try {
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM users");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error in getTotalUsersCount: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username, $excludeId = null) {
        try {
            if ($excludeId) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
                $stmt->execute([$username, $excludeId]);
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
                $stmt->execute([$username]);
            }
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error in usernameExists: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get users by access level
     */
    public function getUsersByAccessLevel($accessLevelId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, a.name AS access_name, a.description 
                FROM users u
                LEFT JOIN access_levels a ON u.access_level_id = a.id
                WHERE u.access_level_id = ?
                ORDER BY u.username
            ");
            $stmt->execute([$accessLevelId]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getUsersByAccessLevel: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search users by username
     */
    public function searchUsers($searchTerm) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT u.*, a.name AS access_name, a.description 
                FROM users u
                LEFT JOIN access_levels a ON u.access_level_id = a.id
                WHERE u.username LIKE ?
                ORDER BY u.username
            ");
            $stmt->execute(['%' . $searchTerm . '%']);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in searchUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update user status (active/inactive) - HANYA JIKA ADA COLUMN STATUS
     */
    public function updateUserStatus($id, $status) {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            return $stmt->execute([$status, $id]);
        } catch (Exception $e) {
            error_log("Error in updateUserStatus: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Change user password only
     */
    public function changePassword($id, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            return $stmt->execute([$hashedPassword, $id]);
        } catch (Exception $e) {
            error_log("Error in changePassword: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate user data before create/update
     */
    public function validateUserData($username, $password = null, $accessLevelId = null, $excludeId = null) {
        $errors = [];
        
        // Validate username
        if (empty($username)) {
            $errors[] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters';
        } elseif ($this->usernameExists($username, $excludeId)) {
            $errors[] = 'Username already exists';
        }
        
        // Validate password (only if provided)
        if ($password !== null && !empty($password)) {
            if (strlen($password) < 6) {
                $errors[] = 'Password must be at least 6 characters';
            }
        }
        
        // Validate access level
        if ($accessLevelId !== null) {
            if (empty($accessLevelId)) {
                $errors[] = 'Access level is required';
            } elseif (!$this->accessLevelExists($accessLevelId)) {
                $errors[] = 'Invalid access level';
            }
        }
        
        return $errors;
    }

    /**
     * Check if access level exists
     */
    private function accessLevelExists($accessLevelId) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM access_levels WHERE id = ?");
            $stmt->execute([$accessLevelId]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get available access levels for dropdown
     */
    public function getAccessLevels() {
        try {
            $stmt = $this->pdo->query("
                SELECT id, name AS access_name, description 
                FROM access_levels 
                ORDER BY id
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error in getAccessLevels: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Bulk operations - Delete multiple users
     */
    public function deleteMultipleUsers($userIds, $currentUserId) {
        try {
            // Remove current user from deletion list
            $userIds = array_filter($userIds, function($id) use ($currentUserId) {
                return $id != $currentUserId;
            });
            
            if (empty($userIds)) {
                return false;
            }
            
            $placeholders = str_repeat('?,', count($userIds) - 1) . '?';
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE id IN ($placeholders)");
            return $stmt->execute($userIds);
        } catch (Exception $e) {
            error_log("Error in deleteMultipleUsers: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Export users data untuk admin
     */
    public function exportUsers($format = 'array') {
        try {
            $users = $this->allUsers();
            
            if ($format === 'csv') {
                return $this->convertToCSV($users);
            }
            
            return $users;
        } catch (Exception $e) {
            error_log("Error in exportUsers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Convert users array to CSV format
     */
    private function convertToCSV($users) {
        if (empty($users)) return '';
        
        $output = "ID,Username,Access Level,Description\n";
        
        foreach ($users as $user) {
            $output .= sprintf(
                "%s,%s,%s,%s\n",
                $user['id'],
                $user['username'],
                $user['access_name'] ?? '',
                $user['description'] ?? ''
            );
        }
        
        return $output;
    }
}
?>