<?php
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

    // Update user
    public function updateUser($id, $username, $password, $access_level_id) {
        return $this->userModel->updateUser($id, $username, $password, $access_level_id);
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
                1 => ['upload', 'dashboard'],
                2 => ['upload', 'dashboard', 'create_label'],
                3 => ['upload', 'dashboard', 'create_label'],
                4 => ['upload', 'dashboard', 'create_label', 'manage_account', 'manage', 'admin', 'master_key']
            ];
            return in_array($feature, $features[$role] ?? []);
        }
}
