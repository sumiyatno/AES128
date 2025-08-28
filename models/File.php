<?php
require_once __DIR__ . '/../config/database.php';

class FileModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Method yang diperlukan untuk key rotation
    public function all() {
        $stmt = $this->pdo->prepare('SELECT * FROM files ORDER BY uploaded_at DESC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method untuk update file data setelah rotasi
    public function updateFileData($id, $newFileData) {
        $stmt = $this->pdo->prepare('UPDATE files SET file_data = ? WHERE id = ?');
        return $stmt->execute([$newFileData, $id]);
    }

    // Simpan file baru
    public function save($data) {
        $stmt = $this->pdo->prepare('
            INSERT INTO files (filename, original_filename, mime_type, file_data, label_id, access_level_id, encryption_iv, restricted_password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        return $stmt->execute([
            $data['filename'],
            $data['original_filename'],
            $data['mime_type'],
            $data['file_data'],
            $data['label_id'],
            $data['access_level_id'],
            $data['encryption_iv'],
            $data['restricted_password_hash']
        ]);
    }

    // Cari file berdasarkan ID
    public function find($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Increment download counter
    public function incrementDownload($id) {
        $stmt = $this->pdo->prepare('UPDATE files SET download_count = download_count + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // Ambil files dengan filter access level
    public function allWithAccessLevel($level) {
        $level = (int)$level;
        $stmt = $this->pdo->prepare('
            SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                   fa.level AS file_access_level_level, fa.name AS file_access_level_name
            FROM files f
            JOIN labels l ON f.label_id = l.id
            LEFT JOIN access_levels fa ON f.access_level_id = fa.id
            WHERE f.access_level_id <= ?
            ORDER BY f.uploaded_at DESC
        ');
        $stmt->execute([$level]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
