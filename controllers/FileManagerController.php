<?php
// filepath: d:\website\AES128\controllers\FileManagerController.php

require_once __DIR__ . '/../models/File.php';
require_once __DIR__ . '/../models/Label.php';
require_once __DIR__ . '/../models/AccessLevel.php';
require_once __DIR__ . '/../controllers/LogController.php';
require_once __DIR__ . "/../config/crypto.php";

class FileManagerController {
    private $pdo;
    private $fileModel;
    private $labelModel;
    private $accessLevelModel;
    private $logController;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fileModel = new FileModel($pdo);
        $this->labelModel = new Label($pdo);
        $this->accessLevelModel = new AccessLevel($pdo);
        
        try {
            $this->logController = new LogController($pdo);
        } catch (Exception $e) {
            $this->logController = null;
            error_log("LogController not available: " . $e->getMessage());
        }
    }

    private function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    private function getCurrentUserId() {
        if (!$this->isAuthenticated()) {
            throw new RuntimeException("User harus login");
        }
        return $_SESSION['user_id'];
    }

    private function getCurrentUserLevel() {
        if (!$this->isAuthenticated()) {
            return 1;
        }
        return $_SESSION['user_level'] ?? 1;
    }

    private function safeLog($action, $status = 'success', $targetType = null, $targetId = null, $targetName = null, $details = null) {
        try {
            if ($this->logController) {
                return $this->logController->writeLog($action, $status, $targetType, $targetId, $targetName, $details);
            }
        } catch (Exception $e) {
            error_log("Failed to write log: " . $e->getMessage());
        }
        return false;
    }

    /**
     * FIXED: Get files milik user dengan pagination - Perbaikan SQL query
     */
    public function getMyFiles($page = 1, $limit = 10, $includeDeleted = false, $searchTerm = '', $labelFilter = '') {
        $userId = $this->getCurrentUserId();
        
        try {
            // Calculate offset
            $offset = ($page - 1) * $limit;
            
            // Build base query
            $sql = "
                SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                       fa.level AS file_access_level_level, fa.name AS file_access_level_name,
                       u.username AS uploaded_by_username,
                       CASE 
                           WHEN f.restricted_password_hash IS NOT NULL AND f.restricted_password_hash != '' 
                           THEN 1 
                           ELSE 0 
                       END as is_restricted_file
                FROM files f
                JOIN labels l ON f.label_id = l.id
                LEFT JOIN access_levels fa ON f.access_level_id = fa.id
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE f.uploaded_by = ?
            ";
            
            $params = [$userId];
            
            // Add deleted filter
            if (!$includeDeleted) {
                $sql .= " AND f.deleted_at IS NULL";
            }
            
            // Add search filter
            if (!empty($searchTerm)) {
                $sql .= " AND f.original_filename LIKE ?";
                $params[] = '%' . base64_encode($searchTerm) . '%';
            }
            
            // Add label filter
            if (!empty($labelFilter)) {
                $sql .= " AND f.label_id = ?";
                $params[] = $labelFilter;
            }
            
            // Add ordering and pagination
            $sql .= " ORDER BY f.uploaded_at DESC";
            
            // FIXED: Use direct string concatenation for LIMIT/OFFSET to avoid quotes
            $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
            
            error_log("SQL Query: " . $sql);
            error_log("Parameters: " . json_encode($params));
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Raw files found: " . count($files));
            
            // Process filenames
            $processedFiles = $this->processFilenamesForDisplay($files);
            
            // Get total count for pagination
            $totalFiles = $this->getTotalMyFilesCount($includeDeleted, $searchTerm, $labelFilter);
            $totalPages = ceil($totalFiles / $limit);
            
            return [
                'files' => $processedFiles,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_files' => $totalFiles,
                    'per_page' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Get my files error: " . $e->getMessage());
            error_log("SQL: " . ($sql ?? 'N/A'));
            error_log("Params: " . json_encode($params ?? []));
            
            return [
                'files' => [],
                'pagination' => [
                    'current_page' => 1,
                    'total_pages' => 0,
                    'total_files' => 0,
                    'per_page' => $limit,
                    'has_next' => false,
                    'has_prev' => false
                ]
            ];
        }
    }

    private function getTotalMyFilesCount($includeDeleted = false, $searchTerm = '', $labelFilter = '') {
        $userId = $this->getCurrentUserId();
        
        try {
            $sql = "SELECT COUNT(*) as total FROM files WHERE uploaded_by = ?";
            $params = [$userId];
            
            if (!$includeDeleted) {
                $sql .= " AND deleted_at IS NULL";
            }
            
            if (!empty($searchTerm)) {
                $sql .= " AND original_filename LIKE ?";
                $params[] = '%' . base64_encode($searchTerm) . '%';
            }
            
            if (!empty($labelFilter)) {
                $sql .= " AND label_id = ?";
                $params[] = $labelFilter;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
        } catch (Exception $e) {
            error_log("Get total files count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * FIXED: Process filenames untuk display
     */
    private function processFilenamesForDisplay($files) {
        foreach ($files as &$f) {
            // Set restricted status
            $f['is_restricted_file'] = !empty($f['restricted_password_hash']);
            
            // Decode original filename
            $f["decrypted_name"] = base64_decode($f["original_filename"]);
            $f["display_mode"] = "normal";
            
            // Additional info
            $f["global_mode"] = "normal";
            $f["is_locked"] = false;
            
            // Calculate file size
            if (isset($f['file_data'])) {
                $f['file_size_formatted'] = $this->formatFileSize(strlen($f['file_data']));
            } else {
                $f['file_size_formatted'] = 'Unknown';
            }
            
            // Format dates
            $f['uploaded_at_formatted'] = date('d M Y H:i', strtotime($f['uploaded_at']));
            if (isset($f['deleted_at']) && $f['deleted_at']) {
                $f['deleted_at_formatted'] = date('d M Y H:i', strtotime($f['deleted_at']));
            }
        }
        
        return $files;
    }

    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    public function updateFileMetadata($id, $newOriginalName, $labelId, $accessLevelId) {
        $userId = $this->getCurrentUserId();
        
        try {
            if (!$this->fileModel->isOwner($id, $userId)) {
                throw new RuntimeException("Anda tidak memiliki akses untuk mengubah file ini");
            }

            if (empty(trim($newOriginalName))) {
                throw new RuntimeException("Nama file tidak boleh kosong");
            }

            $label = $this->labelModel->find($labelId);
            if (!$label) {
                throw new RuntimeException("Label tidak ditemukan");
            }

            $accessLevel = $this->accessLevelModel->find($accessLevelId);
            if (!$accessLevel) {
                throw new RuntimeException("Access level tidak ditemukan");
            }

            $updateData = [
                'original_filename' => base64_encode(trim($newOriginalName)),
                'label_id' => $labelId,
                'access_level_id' => $accessLevelId
            ];

            $result = $this->fileModel->updateFileMetadata($id, $userId, $updateData);

            if ($result) {
                $this->safeLog('file_update', 'success', 'file', $id, $newOriginalName, [
                    'updated_by' => $userId,
                    'new_label_id' => $labelId,
                    'new_access_level_id' => $accessLevelId,
                    'label_name' => $label['name'],
                    'access_level_name' => $accessLevel['name']
                ]);
                return true;
            }

            return false;
            
        } catch (Exception $e) {
            $this->safeLog('file_update', 'failed', 'file', $id, $newOriginalName ?? 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    public function deleteFile($id, $hardDelete = false) {
        $userId = $this->getCurrentUserId();
        
        try {
            $file = $this->fileModel->findByIdAndUser($id, $userId);
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan atau Anda tidak memiliki akses");
            }

            $origName = base64_decode($file['original_filename']);
            
            if ($hardDelete) {
                $result = $this->fileModel->hardDelete($id, $userId);
                $action = 'hard_delete';
            } else {
                $result = $this->fileModel->softDelete($id, $userId);
                $action = 'soft_delete';
            }

            if ($result) {
                $this->safeLog($action, 'success', 'file', $file['filename'], $origName, [
                    'file_id' => $id,
                    'deleted_by' => $userId,
                    'delete_type' => $hardDelete ? 'hard' : 'soft',
                    'file_size' => strlen($file['file_data']),
                    'label_id' => $file['label_id']
                ]);
                return true;
            }

            return false;
            
        } catch (Exception $e) {
            $this->safeLog($hardDelete ? 'hard_delete' : 'soft_delete', 'failed', 'file', $id, 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    public function restoreFile($id) {
        $userId = $this->getCurrentUserId();
        
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ? AND uploaded_by = ? AND deleted_at IS NOT NULL');
            $stmt->execute([$id, $userId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan atau tidak dapat direstore");
            }

            $result = $this->fileModel->restore($id, $userId);

            if ($result) {
                $origName = base64_decode($file['original_filename']);
                
                $this->safeLog('file_restore', 'success', 'file', $id, $origName, [
                    'restored_by' => $userId,
                    'file_id' => $id
                ]);
                return true;
            }

            return false;
            
        } catch (Exception $e) {
            $this->safeLog('file_restore', 'failed', 'file', $id, 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * ENHANCED: Add logging for file details view
     */
    public function getFileDetails($id) {
        $userId = $this->getCurrentUserId();
        
        try {
            $file = $this->fileModel->findByIdAndUser($id, $userId);
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan atau Anda tidak memiliki akses");
            }

            $label = $this->labelModel->find($file['label_id']);
            $accessLevel = $this->accessLevelModel->find($file['access_level_id']);

            // NEW: Log view file details activity
            $this->safeLog('view_file_details', 'success', 'file', $id, base64_decode($file['original_filename']), [
                'viewed_by' => $userId,
                'file_id' => $id,
                'file_size' => strlen($file['file_data']),
                'label_id' => $file['label_id'],
                'access_level_id' => $file['access_level_id']
            ]);

            return [
                'id' => $file['id'],
                'filename' => $file['filename'],
                'original_filename' => base64_decode($file['original_filename']),
                'mime_type' => base64_decode($file['mime_type']),
                'file_size' => strlen($file['file_data']),
                'download_count' => $file['download_count'],
                'uploaded_at' => $file['uploaded_at'],
                'deleted_at' => $file['deleted_at'],
                'is_restricted' => !empty($file['restricted_password_hash']),
                'label' => [
                    'id' => $label['id'],
                    'name' => $label['name'],
                    'access_level' => $label['access_level']
                ],
                'access_level' => [
                    'id' => $accessLevel['id'],
                    'name' => $accessLevel['name'],
                    'level' => $accessLevel['level']
                ]
            ];
            
        } catch (Exception $e) {
            // Log failed attempt
            $this->safeLog('view_file_details', 'failed', 'file', $id, 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    public function getMyFileStats() {
        $userId = $this->getCurrentUserId();
        return $this->fileModel->getFileStatsByUser($userId);
    }

    public function getAvailableLabels() {
        try {
            return $this->labelModel->all();
        } catch (Exception $e) {
            error_log("Get available labels error: " . $e->getMessage());
            return [];
        }
    }

    public function getAvailableAccessLevels() {
        try {
            return $this->accessLevelModel->all();
        } catch (Exception $e) {
            error_log("Get available access levels error: " . $e->getMessage());
            return [];
        }
    }

    public function canManageFile($fileId) {
        try {
            $userId = $this->getCurrentUserId();
            return $this->fileModel->isOwner($fileId, $userId);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * ENHANCED: Add more detailed logging for bulk operations
     */
    public function bulkDeleteFiles($fileIds, $hardDelete = false) {
        $userId = $this->getCurrentUserId();
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($fileIds as $fileId) {
            try {
                $success = $this->deleteFile($fileId, $hardDelete);
                $results[$fileId] = [
                    'success' => $success,
                    'message' => $success ? 'File berhasil dihapus' : 'Gagal menghapus file'
                ];
                
                if ($success) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (Exception $e) {
                $results[$fileId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;
            }
        }
        
        // NEW: Log bulk delete summary
        $this->safeLog('bulk_delete', 'success', 'files', 'bulk_operation', 'Bulk Delete Operation', [
            'performed_by' => $userId,
            'delete_type' => $hardDelete ? 'hard' : 'soft',
            'total_files' => count($fileIds),
            'successful_deletes' => $successCount,
            'failed_deletes' => $failureCount,
            'file_ids' => $fileIds
        ]);
        
        return $results;
    }

    /**
     * NEW METHOD: Permanent delete file from database
     */
    public function permanentDeleteFile($id) {
        $userId = $this->getCurrentUserId();
        
        try {
            // First check if user owns the file
            $file = $this->fileModel->findByIdAndUser($id, $userId);
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan atau Anda tidak memiliki akses");
            }

            $origName = base64_decode($file['original_filename']);
            
            // Perform permanent deletion from database
            $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ? AND uploaded_by = ?');
            $result = $stmt->execute([$id, $userId]);

            if ($result && $stmt->rowCount() > 0) {
                $this->safeLog('permanent_delete', 'success', 'file', $id, $origName, [
                    'file_id' => $id,
                    'deleted_by' => $userId,
                    'delete_type' => 'permanent',
                    'file_size' => strlen($file['file_data']),
                    'label_id' => $file['label_id'],
                    'original_filename' => $origName
                ]);
                return true;
            }

            return false;
            
        } catch (Exception $e) {
            $this->safeLog('permanent_delete', 'failed', 'file', $id, 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    } 
    public function exportMyFilesToCSV($includeDeleted = false) {
        $userId = $this->getCurrentUserId();
        
        try {
            $files = $this->fileModel->getFilesByUser($userId, $includeDeleted);
            
            $csvData = "ID,Filename,Label,Access Level,File Size,Downloads,Uploaded At,Status\n";
            
            foreach ($files as $file) {
                $filename = base64_decode($file['original_filename']);
                $status = $file['deleted_at'] ? 'Deleted' : 'Active';
                $fileSize = $this->formatFileSize(strlen($file['file_data']));
                $uploadedAt = date('Y-m-d H:i:s', strtotime($file['uploaded_at']));
                
                $csvData .= sprintf(
                    "%d,\"%s\",\"%s\",\"%s\",\"%s\",%d,\"%s\",\"%s\"\n",
                    $file['id'],
                    str_replace('"', '""', $filename),
                    str_replace('"', '""', $file['label_name']),
                    str_replace('"', '""', $file['file_access_level_name']),
                    $fileSize,
                    $file['download_count'],
                    $uploadedAt,
                    $status
                );
            }
            
            $this->safeLog('export_my_files', 'success', 'files', 'csv_export', 'My Files Export', [
                'exported_by' => $userId,
                'file_count' => count($files),
                'include_deleted' => $includeDeleted
            ]);
            
            return $csvData;
            
        } catch (Exception $e) {
            $this->safeLog('export_my_files', 'failed', 'files', 'csv_export', 'My Files Export Failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * ENHANCED: Add logging for bulk permanent delete
     */
    public function bulkPermanentDeleteFiles($fileIds) {
        $userId = $this->getCurrentUserId();
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($fileIds as $fileId) {
            try {
                $success = $this->permanentDeleteFile($fileId);
                $results[$fileId] = [
                    'success' => $success,
                    'message' => $success ? 'File berhasil dihapus permanen' : 'Gagal menghapus file permanen'
                ];
                
                if ($success) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            } catch (Exception $e) {
                $results[$fileId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;
            }
        }
        
        // NEW: Log bulk permanent delete summary
        $this->safeLog('bulk_permanent_delete', 'success', 'files', 'bulk_operation', 'Bulk Permanent Delete Operation', [
            'performed_by' => $userId,
            'delete_type' => 'permanent',
            'total_files' => count($fileIds),
            'successful_deletes' => $successCount,
            'failed_deletes' => $failureCount,
            'file_ids' => $fileIds
        ]);
        
        return $results;
    }
}
?>