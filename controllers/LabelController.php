    
<?php
require_once __DIR__ . "/../models/Label.php";

class LabelController {
    private $pdo;
    private $labelModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->labelModel = new Label($pdo);
    }

    // buat label baru
    public function create($name, $description, $access_level) {
        return $this->labelModel->create($name, $description, $access_level);
    }

    // ambil semua label
    public function all() {
        return $this->labelModel->all();
    }
    // Cek apakah user sudah login (gunakan AuthController)
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}
