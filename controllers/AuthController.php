<?php
// filepath: d:\website\AES128\controllers\AuthController.php
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../controllers/LogController.php';  // ← TAMBAHAN INI
require_once __DIR__ . '/../config/database.php';            // ← TAMBAHAN INI

class AuthController {
    private $userModel;
    private $pdo;  // ← TAMBAHAN INI

    public function __construct($pdo = null) {  // ← MODIFIKASI PARAMETER
        $this->userModel = new User();
        
        // Handle PDO connection
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            // Fallback ke global PDO
            global $pdo;
            $this->pdo = $pdo;
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Proses login
    public function login($username, $password) {
        $user = $this->userModel->getUserByUsername($username);
        
        if ($user && password_verify($password, $user['password'])) {
            // Login berhasil
            try {
                $logController = new LogController($this->pdo);
                $logController->writeLog('login', 'success', 'user', $user['id'], $username);
            } catch (Exception $e) {
                // Jangan sampai error logging mengganggu login
                error_log("Failed to log login success: " . $e->getMessage());
            }
            
            // Simpan info user ke session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_level'] = $user['access_level_id'];
            return true;
        } else {
            // Login gagal
            try {
                $logController = new LogController($this->pdo);
                $logController->writeLog('login', 'failed', 'user', null, $username, [
                    'attempted_username' => $username,
                    'reason' => 'invalid_credentials'
                ]);
            } catch (Exception $e) {
                // Jangan sampai error logging mengganggu proses
                error_log("Failed to log login failure: " . $e->getMessage());
            }
        }
        return false;
    }

    // Logout user
    public function logout() {
        // Log logout sebelum destroy session
        try {
            if (isset($_SESSION['username'])) {
                $logController = new LogController($this->pdo);
                $logController->writeLog('logout', 'success', 'user', $_SESSION['user_id'] ?? null, $_SESSION['username']);
            }
        } catch (Exception $e) {
            error_log("Failed to log logout: " . $e->getMessage());
        }
        
        session_unset();
        session_destroy();
    }

    // Cek apakah user sudah login
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    // Ambil info user yang sedang login
    public function getCurrentUser() {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'access_level_id' => $_SESSION['user_level']
            ];
        }
        return null;
    }
}
