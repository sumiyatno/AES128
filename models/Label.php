<?php
require_once __DIR__ . "/../config/database.php";

class Label {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // buat label baru (versi lama, pakai access_level string langsung)
    public function create($name, $description, $access_level) {
        $stmt = $this->pdo->prepare("INSERT INTO labels (name, description, access_level) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $description, $access_level]);
    }

    // ambil semua label (versi lama)
    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM labels ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    // cari label by id (versi lama)
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM labels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    /* =========================
       Tambahan versi baru
    ========================== */

    // buat label baru (versi baru, pakai foreign key access_level_id)
    public function createWithLevelId($name, $description, $access_level_id) {
        $stmt = $this->pdo->prepare("
            INSERT INTO labels (name, description, access_level_id, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$name, $description, $access_level_id]);
    }

    // ambil semua label + join access_levels
    public function allWithLevels() {
        $stmt = $this->pdo->query("
            SELECT l.*, a.name AS access_level_name, a.level AS access_level_level
            FROM labels l
            JOIN access_levels a ON l.access_level_id = a.id
            ORDER BY l.created_at DESC
        ");
        return $stmt->fetchAll();
    }

    // cari label by id + join access_levels
    public function findWithLevel($id) {
        $stmt = $this->pdo->prepare("
            SELECT l.*, a.name AS access_level_name, a.level AS access_level_level
            FROM labels l
            JOIN access_levels a ON l.access_level_id = a.id
            WHERE l.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
