<?php
require_once __DIR__ . '/../config/database.php';

class FileModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Simpan file baru
    public function create($filename, $original_filename, $mime_type, $file_data, $label_id, $iv, $restricted_password_hash = null) {
        $stmt = $this->pdo->prepare('
            INSERT INTO files 
            (filename, original_filename, mime_type, file_data, label_id, encryption_iv, restricted_password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ');
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

    // Ambil semua file + label + access_level (filtering utama: berdasarkan files.access_level_id)
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
        return $stmt->fetchAll();
    }

    // Cari file by id
    public function find($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    // Tambah hitungan download
    public function incrementDownload($id) {
        $stmt = $this->pdo->prepare('UPDATE files SET download_count = download_count + 1 WHERE id = ?');
        return $stmt->execute([$id]);
    }

    // Update data file terenkripsi (ciphertext) setelah rotasi key
    public function updateFileData($id, $newFileData) {
        $stmt = $this->pdo->prepare('UPDATE files SET file_data = ? WHERE id = ?');
        return $stmt->execute([$newFileData, $id]);
    }
}
