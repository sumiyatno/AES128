<?php
require_once __DIR__ . "/../config/database.php";

class AccessLevel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // buat access level baru
    public function create($name, $description, $level) {
        $stmt = $this->pdo->prepare("
            INSERT INTO access_levels (name, description, level, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        return $stmt->execute([$name, $description, $level]);
    }

    // ambil semua access level
    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM access_levels ORDER BY level ASC");
        return $stmt->fetchAll();
    }

    // cari access level by id
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM access_levels WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // cari access level by name (misal "restricted", "confidential", dll)
    public function findByName($name) {
        $stmt = $this->pdo->prepare("SELECT * FROM access_levels WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetch();
    }

    // update access level
    public function update($id, $name, $description, $level) {
        $stmt = $this->pdo->prepare("
            UPDATE access_levels 
            SET name = ?, description = ?, level = ?, updated_at = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$name, $description, $level, $id]);
    }

    // hapus access level
    public function delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM access_levels WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
