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
            $userId = $this->getCurrentUserId();
        
        // FIX: Validate user exists before logging
            if ($userId) {
                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                if (!$stmt->fetch()) {
                    // User doesn't exist, skip logging or use null user_id
                    error_log("Skipping log for non-existent user ID: $userId");
                    return false;
                }
            }

            return $this->logController->writeLog($action, $status, $targetType, $targetId, $targetName, $details);
        } catch (Exception $e) {
            // Log the error but don't break the main functionality
            error_log("Failed to write activity log: " . $e->getMessage());
            return false;
        }
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
        $userLevel = $this->getCurrentUserLevel();
        
        try {
            // Check if admin - admin dapat hapus file siapapun
            if ($userLevel >= 3) {
                // Admin mode - get any file
                $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
                $stmt->execute([$id]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$file) {
                    throw new RuntimeException("File tidak ditemukan");
                }
                
                $origName = base64_decode($file['original_filename']);
                
                // Admin permanent delete - tanpa ownership check
                $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ?');
                $result = $stmt->execute([$id]);
                
                error_log("ADMIN PERMANENT DELETE: File ID $id, Result: " . ($result ? 'TRUE' : 'FALSE') . ", Affected: " . $stmt->rowCount());
                
            } else {
                // Regular user - check ownership
                $file = $this->fileModel->findByIdAndUser($id, $userId);
                if (!$file) {
                    throw new RuntimeException("File tidak ditemukan atau Anda tidak memiliki akses");
                }
                
                $origName = base64_decode($file['original_filename']);
                
                // User permanent delete - dengan ownership check
                $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ? AND uploaded_by = ?');
                $result = $stmt->execute([$id, $userId]);
                
                error_log("USER PERMANENT DELETE: File ID $id, User ID $userId, Result: " . ($result ? 'TRUE' : 'FALSE') . ", Affected: " . $stmt->rowCount());
            }

            if ($result && $stmt->rowCount() > 0) {
                $this->safeLog('permanent_delete', 'success', 'file', $id, $origName, [
                    'file_id' => $id,
                    'deleted_by' => $userId,
                    'delete_type' => 'permanent',
                    'file_size' => strlen($file['file_data']),
                    'label_id' => $file['label_id'],
                    'original_filename' => $origName,
                    'admin_mode' => $userLevel >= 3 ? 'yes' : 'no',
                    'affected_rows' => $stmt->rowCount()
                ]);
                return true;
            } else {
                error_log("PERMANENT DELETE FAILED: No rows affected for file $id");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("PERMANENT DELETE EXCEPTION: " . $e->getMessage());
            $this->safeLog('permanent_delete', 'failed', 'file', $id, 'unknown', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'user_level' => $userLevel
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
        $userLevel = $this->getCurrentUserLevel();
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        error_log("BULK PERMANENT DELETE START: User ID $userId, Level $userLevel, Files: " . json_encode($fileIds));
        
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
                
                error_log("BULK PERMANENT DELETE: File $fileId - " . ($success ? 'SUCCESS' : 'FAILED'));
                
            } catch (Exception $e) {
                $results[$fileId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;
                error_log("BULK PERMANENT DELETE EXCEPTION: File $fileId - " . $e->getMessage());
            }
        }
        
        error_log("BULK PERMANENT DELETE SUMMARY: Success=$successCount, Failed=$failureCount");
        
        // NEW: Log bulk permanent delete summary
        $this->safeLog('bulk_permanent_delete', 'success', 'files', 'bulk_operation', 'Bulk Permanent Delete Operation', [
            'performed_by' => $userId,
            'user_level' => $userLevel,
            'delete_type' => 'permanent',
            'total_files' => count($fileIds),
            'successful_deletes' => $successCount,
            'failed_deletes' => $failureCount,
            'file_ids' => $fileIds,
            'admin_mode' => $userLevel >= 3 ? 'yes' : 'no'
        ]);
        
        return $results;
    }

    /**
     * ADMIN FUNCTION: Bulk permanent delete files (admin can delete any file)
     */
    public function adminBulkPermanentDeleteFiles($fileIds) {
        $adminLevel = $this->checkAdminAccess(); // Admin only
        $adminId = $this->getCurrentUserId();
        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        error_log("ADMIN BULK PERMANENT DELETE START: Admin ID $adminId, Level $adminLevel, Files: " . json_encode($fileIds));
        
        foreach ($fileIds as $fileId) {
            try {
                // Get file info (no ownership check for admin)
                $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
                $stmt->execute([$fileId]);
                $file = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$file) {
                    error_log("ADMIN BULK PERMANENT DELETE: File $fileId not found");
                    $results[$fileId] = [
                        'success' => false,
                        'message' => 'File tidak ditemukan'
                    ];
                    $failureCount++;
                    continue;
                }
                
                $origName = base64_decode($file['original_filename']);
                $ownerId = $file['uploaded_by'];
                
                // Admin permanent delete - no ownership check
                $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ?');
                $result = $stmt->execute([$fileId]);
                $affectedRows = $stmt->rowCount();
                
                error_log("ADMIN BULK PERMANENT DELETE: File $fileId ($origName), Owner: $ownerId, Result: " . ($result ? 'TRUE' : 'FALSE') . ", Affected: $affectedRows");
                
                if ($result && $affectedRows > 0) {
                    $results[$fileId] = [
                        'success' => true,
                        'message' => 'File berhasil dihapus permanen oleh admin'
                    ];
                    $successCount++;
                    
                    // Log individual delete
                    $this->safeLog('admin_permanent_delete', 'success', 'file', $fileId, $origName, [
                        'admin_id' => $adminId,
                        'admin_level' => $adminLevel,
                        'file_owner_id' => $ownerId,
                        'delete_type' => 'permanent',
                        'file_size' => strlen($file['file_data']),
                        'label_id' => $file['label_id'],
                        'affected_rows' => $affectedRows
                    ]);
                    
                } else {
                    error_log("ADMIN BULK PERMANENT DELETE: File $fileId - No rows affected");
                    $results[$fileId] = [
                        'success' => false,
                        'message' => 'File tidak dapat dihapus - tidak ada perubahan di database'
                    ];
                    $failureCount++;
                }
                
            } catch (Exception $e) {
                error_log("ADMIN BULK PERMANENT DELETE EXCEPTION: File $fileId - " . $e->getMessage());
                $results[$fileId] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                $failureCount++;
                
                $this->safeLog('admin_permanent_delete', 'failed', 'file', $fileId, 'unknown', [
                    'admin_id' => $adminId,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e)
                ]);
            }
        }
        
        error_log("ADMIN BULK PERMANENT DELETE SUMMARY: Success=$successCount, Failed=$failureCount");
        
        // Log bulk operation summary
        $this->safeLog('admin_bulk_permanent_delete', 'success', 'files', 'bulk_operation', 'Admin Bulk Permanent Delete Operation', [
            'admin_id' => $adminId,
            'admin_level' => $adminLevel,
            'delete_type' => 'permanent',
            'total_files' => count($fileIds),
            'successful_deletes' => $successCount,
            'failed_deletes' => $failureCount,
            'file_ids' => $fileIds
        ]);
        
        return $results;
    }

    // ================= ADMIN FUNCTIONS (TAMBAHAN BARU) =================

    /**
     * Check if current user is admin (level 3/4) - TAMBAHAN BARU
     */
    private function checkAdminAccess() {
        $userLevel = $this->getCurrentUserLevel();
        if ($userLevel < 3) {
            throw new RuntimeException("Akses ditolak. Hanya admin yang bisa mengakses fitur ini.");
        }
        return $userLevel;
    }

    /**
     * ADMIN FUNCTION: Get ALL files from database (tanpa filter user) - TAMBAHAN BARU
     */
    public function getAllFiles($page = 1, $limit = 10, $includeDeleted = false, $searchTerm = '', $labelFilter = '', $userFilter = '') {
        $this->checkAdminAccess(); // Admin only
        
        try {
            $offset = ($page - 1) * $limit;
            
            // Query TANPA filter uploaded_by (admin dapat melihat semua file)
            $sql = "
                SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                       fa.level AS file_access_level_level, fa.name AS file_access_level_name,
                       u.username AS uploaded_by_username, u.username AS uploaded_by_email,
                       1 AS uploaded_by_level,
                       CASE 
                           WHEN f.restricted_password_hash IS NOT NULL AND f.restricted_password_hash != '' 
                           THEN 1 
                           ELSE 0 
                       END as is_restricted_file
                FROM files f
                JOIN labels l ON f.label_id = l.id
                LEFT JOIN access_levels fa ON f.access_level_id = fa.id
                LEFT JOIN users u ON f.uploaded_by = u.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!$includeDeleted) {
                $sql .= " AND f.deleted_at IS NULL";
            }
            
            if (!empty($searchTerm)) {
                $sql .= " AND f.original_filename LIKE ?";
                $params[] = '%' . base64_encode($searchTerm) . '%';
            }
            
            if (!empty($labelFilter)) {
                $sql .= " AND f.label_id = ?";
                $params[] = $labelFilter;
            }
            
            // ADMIN FEATURE: Add user filter
            if (!empty($userFilter)) {
                $sql .= " AND f.uploaded_by = ?";
                $params[] = $userFilter;
            }
            
            $sql .= " ORDER BY f.uploaded_at DESC";
            $sql .= " LIMIT " . intval($limit) . " OFFSET " . intval($offset);
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Process dengan admin context
            $processedFiles = $this->processAdminFilenamesForDisplay($files);
            
            // Get total count
            $totalFiles = $this->getTotalAllFilesCount($includeDeleted, $searchTerm, $labelFilter, $userFilter);
            $totalPages = ceil($totalFiles / $limit);
            
            // Log admin activity
            $this->safeLog('admin_view_all_files', 'success', 'system', 'all_files', 'Admin View All Files', [
                'admin_id' => $this->getCurrentUserId(),
                'total_files' => $totalFiles,
                'page' => $page,
                'search_term' => $searchTerm,
                'user_filter' => $userFilter
            ]);
            
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
            error_log("Admin get all files error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Get files by specific user ID - TAMBAHAN BARU
     */
    public function getFilesByUserId($userId, $page = 1, $limit = 10, $includeDeleted = false) {
        $this->checkAdminAccess(); // Admin only
        
        try {
            // Verify user exists
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                throw new RuntimeException("User dengan ID $userId tidak ditemukan");
            }
            
            // Use getAllFiles with specific user filter
            return $this->getAllFiles($page, $limit, $includeDeleted, '', '', $userId);
            
        } catch (Exception $e) {
            error_log("Admin get files by user error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Admin update file metadata (any file) - TAMBAHAN BARU
     */
    public function adminUpdateFileMetadata($fileId, $newOriginalName, $labelId, $accessLevelId, $newOwnerId = null) {
        $adminLevel = $this->checkAdminAccess(); // Admin only
        $adminId = $this->getCurrentUserId();
        
        try {
            // Get file info (TANPA ownership check untuk admin)
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan");
            }
            
            $oldOwnerId = $file['uploaded_by'];
            $oldName = base64_decode($file['original_filename']);
            
            // Validate inputs
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
            
            // Admin bypass ownership check - direct update
            $stmt = $this->pdo->prepare('
                UPDATE files 
                SET original_filename = ?, label_id = ?, access_level_id = ?
                WHERE id = ?
            ');
            $result = $stmt->execute([
                base64_encode(trim($newOriginalName)),
                $labelId,
                $accessLevelId,
                $fileId
            ]);
            
            // Transfer ownership if specified
            if ($newOwnerId && $newOwnerId != $oldOwnerId) {
            // Validate new owner exists
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$newOwnerId]);
            $newOwner = $stmt->fetch();                if (!$newOwner) {
                    throw new RuntimeException("User baru tidak ditemukan");
                }
                
                // Transfer ownership
                $stmt = $this->pdo->prepare('UPDATE files SET uploaded_by = ? WHERE id = ?');
                $stmt->execute([$newOwnerId, $fileId]);
            }
            
            if ($result) {
                // Detailed logging
                $this->safeLog('admin_file_update', 'success', 'file', $fileId, $newOriginalName, [
                    'admin_id' => $adminId,
                    'admin_level' => $adminLevel,
                    'old_owner_id' => $oldOwnerId,
                    'new_owner_id' => $newOwnerId ?? $oldOwnerId,
                    'old_filename' => $oldName,
                    'new_filename' => $newOriginalName,
                    'new_label_id' => $labelId,
                    'new_access_level_id' => $accessLevelId,
                    'ownership_transferred' => $newOwnerId ? 'yes' : 'no'
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->safeLog('admin_file_update', 'failed', 'file', $fileId, $newOriginalName ?? 'unknown', [
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
                'target_owner_id' => $oldOwnerId ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Admin delete file (any file) - TAMBAHAN BARU
     */
    public function adminDeleteFile($fileId, $hardDelete = false) {
        $adminLevel = $this->checkAdminAccess(); // Admin only
        $adminId = $this->getCurrentUserId();
        
        try {
            // Get file (TANPA ownership check untuk admin)
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan");
            }
            
            $origName = base64_decode($file['original_filename']);
            $ownerId = $file['uploaded_by'];
            
            // Admin bypass ownership check - direct delete
            if ($hardDelete) {
                $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = ?');
                $result = $stmt->execute([$fileId]);
                $action = 'admin_hard_delete';
            } else {
                $stmt = $this->pdo->prepare('UPDATE files SET deleted_at = NOW() WHERE id = ?');
                $result = $stmt->execute([$fileId]);
                $action = 'admin_soft_delete';
            }
            
            if ($result) {
                $this->safeLog($action, 'success', 'file', $fileId, $origName, [
                    'admin_id' => $adminId,
                    'admin_level' => $adminLevel,
                    'file_owner_id' => $ownerId,
                    'delete_type' => $hardDelete ? 'hard' : 'soft',
                    'file_size' => strlen($file['file_data']),
                    'label_id' => $file['label_id']
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->safeLog($hardDelete ? 'admin_hard_delete' : 'admin_soft_delete', 'failed', 'file', $fileId, 'unknown', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Admin restore file (any file) - TAMBAHAN BARU
     */
    public function adminRestoreFile($fileId) {
        $adminLevel = $this->checkAdminAccess(); // Admin only
        $adminId = $this->getCurrentUserId();
        
        try {
            // Get deleted file (TANPA ownership check untuk admin)
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ? AND deleted_at IS NOT NULL');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan atau belum dihapus");
            }
            
            // Admin restore - bypass ownership
            $stmt = $this->pdo->prepare('UPDATE files SET deleted_at = NULL WHERE id = ?');
            $result = $stmt->execute([$fileId]);
            
            if ($result) {
                $origName = base64_decode($file['original_filename']);
                
                $this->safeLog('admin_file_restore', 'success', 'file', $fileId, $origName, [
                    'admin_id' => $adminId,
                    'admin_level' => $adminLevel,
                    'file_owner_id' => $file['uploaded_by'],
                    'restored_by_admin' => 'yes'
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->safeLog('admin_file_restore', 'failed', 'file', $fileId, 'unknown', [
                'admin_id' => $adminId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Transfer file ownership - TAMBAHAN BARU
     */
    public function transferFileOwnership($fileId, $newOwnerId) {
        $adminLevel = $this->checkAdminAccess(); // Admin only
        $adminId = $this->getCurrentUserId();
        
        try {
            // Validate file exists
            $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = ?');
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan");
            }
            
            // Validate new owner exists
            $stmt = $this->pdo->prepare("SELECT username FROM users WHERE id = ?");
            $stmt->execute([$newOwnerId]);
            $newOwner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$newOwner) {
                throw new RuntimeException("User tujuan tidak ditemukan");
            }
            
            $oldOwnerId = $file['uploaded_by'];
            
            if ($oldOwnerId == $newOwnerId) {
                throw new RuntimeException("File sudah dimiliki oleh user tersebut");
            }
            
            // Transfer ownership
            $stmt = $this->pdo->prepare('UPDATE files SET uploaded_by = ? WHERE id = ?');
            $result = $stmt->execute([$newOwnerId, $fileId]);
            
            if ($result) {
                $this->safeLog('admin_transfer_ownership', 'success', 'file', $fileId, base64_decode($file['original_filename']), [
                    'admin_id' => $adminId,
                    'admin_level' => $adminLevel,
                    'old_owner_id' => $oldOwnerId,
                    'new_owner_id' => $newOwnerId,
                    'new_owner_username' => $newOwner['username']
                ]);
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            $this->safeLog('admin_transfer_ownership', 'failed', 'file', $fileId, 'unknown', [
                'admin_id' => $adminId,
                'error' => $e->getMessage(),
                'target_owner_id' => $newOwnerId ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Get system-wide file statistics - TAMBAHAN BARU
     */
    public function getSystemFileStats() {
        $this->checkAdminAccess(); // Admin only
        
        try {
            $stats = [];
            
            // Total files
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM files");
            $stats['total_files'] = $stmt->fetchColumn();
            
            // Active files
            $stmt = $this->pdo->query("SELECT COUNT(*) as active FROM files WHERE deleted_at IS NULL");
            $stats['active_files'] = $stmt->fetchColumn();
            
            // Deleted files
            $stmt = $this->pdo->query("SELECT COUNT(*) as deleted FROM files WHERE deleted_at IS NOT NULL");
            $stats['deleted_files'] = $stmt->fetchColumn();
            
            // Total downloads
            $stmt = $this->pdo->query("SELECT SUM(download_count) as downloads FROM files WHERE deleted_at IS NULL");
            $stats['total_downloads'] = $stmt->fetchColumn() ?? 0;
            
            // Total file size
            $stmt = $this->pdo->query("SELECT SUM(LENGTH(file_data)) as size FROM files WHERE deleted_at IS NULL");
            $stats['total_size'] = $stmt->fetchColumn() ?? 0;
            
            // Files by user
            $stmt = $this->pdo->query("
                SELECT u.username, u.username as email, 1 as level, COUNT(*) as count,
                       SUM(LENGTH(f.file_data)) as total_size
                FROM files f 
                JOIN users u ON f.uploaded_by = u.id 
                WHERE f.deleted_at IS NULL 
                GROUP BY u.id, u.username
                ORDER BY count DESC
            ");
            $stats['by_user'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Get system stats error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * ADMIN FUNCTION: Get all users for admin dropdown - TAMBAHAN BARU
     */
    public function getAllUsers() {
        $this->checkAdminAccess(); // Admin only
        
        try {
            $stmt = $this->pdo->query("
                SELECT id, username, username as email, 1 as level, created_at,
                       (SELECT COUNT(*) FROM files WHERE uploaded_by = u.id AND deleted_at IS NULL) as file_count
                FROM users u 
                ORDER BY username
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get all users error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process filenames for admin display (with owner info) - TAMBAHAN BARU
     */
    private function processAdminFilenamesForDisplay($files) {
        foreach ($files as &$f) {
            // Use existing method first
            $processed = $this->processFilenamesForDisplay([$f]);
            $f = $processed[0];
            
            // Add admin-specific info
            $f['owner_info'] = [
                'username' => $f['uploaded_by_username'] ?? 'Unknown',
                'email' => $f['uploaded_by_email'] ?? 'Unknown',
                'level' => $f['uploaded_by_level'] ?? 1
            ];
            
            // Add admin badges
            $f['admin_badges'] = [];
            if (($f['uploaded_by_level'] ?? 1) >= 3) {
                $f['admin_badges'][] = 'admin_owner';
            }
            if ($f['is_restricted_file']) {
                $f['admin_badges'][] = 'restricted';
            }
            if (!empty($f['deleted_at'])) {
                $f['admin_badges'][] = 'deleted';
            }
        }
        
        return $files;
    }

    /**
     * Get total files count for admin view - TAMBAHAN BARU
     */
    private function getTotalAllFilesCount($includeDeleted = false, $searchTerm = '', $labelFilter = '', $userFilter = '') {
        try {
            $sql = "SELECT COUNT(*) as total FROM files f WHERE 1=1";
            $params = [];
            
            if (!$includeDeleted) {
                $sql .= " AND f.deleted_at IS NULL";
            }
            
            if (!empty($searchTerm)) {
                $sql .= " AND f.original_filename LIKE ?";
                $params[] = '%' . base64_encode($searchTerm) . '%';
            }
            
            if (!empty($labelFilter)) {
                $sql .= " AND f.label_id = ?";
                $params[] = $labelFilter;
            }
            
            if (!empty($userFilter)) {
                $sql .= " AND f.uploaded_by = ?";
                $params[] = $userFilter;
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
        } catch (Exception $e) {
            error_log("Get total all files count error: " . $e->getMessage());
            return 0;
        }
    }
}
?>