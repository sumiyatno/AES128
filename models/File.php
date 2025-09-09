<?php
require_once __DIR__ . '/../config/database.php';

class FileModel {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Method yang diperlukan untuk key rotation
    public function all() {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE deleted_at IS NULL ORDER BY uploaded_at DESC');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Method untuk update file data setelah rotasi
    public function updateFileData($id, $newFileData) {
        $stmt = $this->pdo->prepare('UPDATE files SET file_data = ? WHERE id = ?');
        return $stmt->execute([$newFileData, $id]);
    }

    // Simpan file baru dengan uploaded_by
    public function save($data) {
        $stmt = $this->pdo->prepare('
            INSERT INTO files (filename, original_filename, file_description, mime_type, file_data, label_id, access_level_id, encryption_iv, restricted_password_hash, uploaded_by, uploaded_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ');
        return $stmt->execute([
            $data['filename'],
            $data['original_filename'],
            $data['file_description'] ?? null,
            $data['mime_type'],
            $data['file_data'],
            $data['label_id'],
            $data['access_level_id'],
            $data['encryption_iv'],
            $data['restricted_password_hash'],
            $data['uploaded_by']
        ]);
    }

    // Cari file berdasarkan ID (hanya yang tidak dihapus)
    public function find($id) {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Cari file berdasarkan ID dan user (untuk ownership check)
    public function findByIdAndUser($id, $userId) {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ? AND uploaded_by = ? AND deleted_at IS NULL');
        $stmt->execute([$id, $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Increment download counter
    public function incrementDownload($id) {
        $stmt = $this->pdo->prepare('UPDATE files SET download_count = download_count + 1 WHERE id = ? AND deleted_at IS NULL');
        return $stmt->execute([$id]);
    }

    // Ambil files dengan filter access level (hanya yang tidak dihapus)
    public function allWithAccessLevel($level) {
        $level = (int)$level;
        $stmt = $this->pdo->prepare('
            SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                   fa.level AS file_access_level_level, fa.name AS file_access_level_name,
                   u.username AS uploaded_by_username
            FROM files f
            JOIN labels l ON f.label_id = l.id
            LEFT JOIN access_levels fa ON f.access_level_id = fa.id
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.access_level_id <= ? AND f.deleted_at IS NULL
            ORDER BY f.uploaded_at DESC
        ');
        $stmt->execute([$level]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Ambil files milik user tertentu
    public function getFilesByUser($userId, $includeDeleted = false) {
        $deletedCondition = $includeDeleted ? '' : 'AND f.deleted_at IS NULL';
        
        $stmt = $this->pdo->prepare("
            SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                   fa.level AS file_access_level_level, fa.name AS file_access_level_name,
                   u.username AS uploaded_by_username
            FROM files f
            JOIN labels l ON f.label_id = l.id
            LEFT JOIN access_levels fa ON f.access_level_id = fa.id
            LEFT JOIN users u ON f.uploaded_by = u.id
            WHERE f.uploaded_by = ? $deletedCondition
            ORDER BY f.uploaded_at DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Soft delete file (hanya owner yang bisa)
    public function softDelete($id, $userId) {
        $stmt = $this->pdo->prepare('
            UPDATE files 
            SET deleted_at = NOW() 
            WHERE id = ? AND uploaded_by = ? AND deleted_at IS NULL
        ');
        return $stmt->execute([$id, $userId]);
    }

    // Hard delete file (hanya owner yang bisa)
    public function hardDelete($id, $userId) {
        $stmt = $this->pdo->prepare('
            DELETE FROM files 
            WHERE id = ? AND uploaded_by = ?
        ');
        return $stmt->execute([$id, $userId]);
    }

    // Restore soft deleted file (hanya owner yang bisa)
    public function restore($id, $userId) {
        $stmt = $this->pdo->prepare('
            UPDATE files 
            SET deleted_at = NULL 
            WHERE id = ? AND uploaded_by = ? AND deleted_at IS NOT NULL
        ');
        return $stmt->execute([$id, $userId]);
    }

    // Check ownership
    public function isOwner($id, $userId) {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM files WHERE id = ? AND uploaded_by = ?');
        $stmt->execute([$id, $userId]);
        return $stmt->fetchColumn() > 0;
    }

    // Update file metadata (hanya owner yang bisa)
    public function updateFileMetadata($id, $userId, $data) {
        $stmt = $this->pdo->prepare('
            UPDATE files 
            SET original_filename = ?, label_id = ?, access_level_id = ?
            WHERE id = ? AND uploaded_by = ? AND deleted_at IS NULL
        ');
        return $stmt->execute([
            $data['original_filename'],
            $data['label_id'],
            $data['access_level_id'],
            $id,
            $userId
        ]);
    }

    // Get file stats by user
    public function getFileStatsByUser($userId) {
        $stmt = $this->pdo->prepare('
            SELECT 
                COUNT(*) as total_files,
                COUNT(CASE WHEN deleted_at IS NULL THEN 1 END) as active_files,
                COUNT(CASE WHEN deleted_at IS NOT NULL THEN 1 END) as deleted_files,
                SUM(CASE WHEN deleted_at IS NULL THEN download_count ELSE 0 END) as total_downloads
            FROM files 
            WHERE uploaded_by = ?
        ');
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
