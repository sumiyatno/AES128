<?php
require_once __DIR__ . "/../config/database.php";

class Label {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // buat label baru
    public function create($name, $description, $access_level) {
        $stmt = $this->pdo->prepare("INSERT INTO labels (name, description, access_level) VALUES (?, ?, ?)");
        return $stmt->execute([$name, $description, $access_level]);
    }

    // ambil semua label
    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM labels ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    // cari label by id
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM labels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
