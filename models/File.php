<?php
require_once __DIR__ . "/../config/database.php";

class FileModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // simpan file baru
    public function create($filename, $original_filename, $mime_type, $file_data, $label_id, $iv, $restricted_password_hash = null) {
        $stmt = $this->pdo->prepare("
            INSERT INTO files 
            (filename, original_filename, mime_type, file_data, label_id, encryption_iv, restricted_password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $filename,
            $original_filename,
            $mime_type,
            $file_data,
            $label_id,
            $iv,
            $restricted_password_hash
        ]);
    }

    // ambil semua file
    public function all() {
        $stmt = $this->pdo->query("SELECT * FROM files ORDER BY uploaded_at DESC");
        return $stmt->fetchAll();
    }

    // cari file by id
    public function find($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // tambah hitungan download
    public function incrementDownload($id) {
        $stmt = $this->pdo->prepare("UPDATE files SET download_count = download_count + 1 WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
