<?php
require_once __DIR__ . '/../models/User.php';

class AuthController {
    private $userModel;

    public function __construct() {
        $this->userModel = new User();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // Proses login
    public function login($username, $password) {
        $user = $this->userModel->getUserByUsername($username);
        if ($user && password_verify($password, $user['password'])) {
            // Simpan info user ke session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_level'] = $user['access_level_id'];
            return true;
        }
        return false;
    }

    // Logout user
    public function logout() {
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
