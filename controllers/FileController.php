<?php
// filepath: d:\website\AES128\controllers\FileController.php

require_once __DIR__ . "/../models/File.php";
require_once __DIR__ . "/../models/Label.php";
require_once __DIR__ . "/../config/crypto.php";
require_once __DIR__ . "/../config/CryptoKeyRotate.php";

class FileController {
    private $pdo;
    private $fileModel;
    private $labelModel;
    private $logController;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fileModel = new FileModel($pdo);
        $this->labelModel = new Label($pdo);
        
        // LogController dengan error handling
        try {
            require_once __DIR__ . '/LogController.php';
            $this->logController = new LogController($pdo);
        } catch (Exception $e) {
            $this->logController = null;
            error_log("LogController not available: " . $e->getMessage());
        }
    }

    // ================= ACCESS CONTROL =================
    
    /**
     * Fungsi pembatasan hak akses file
     * $fileAccessLevel: level akses file (1,2,3,4)
     * $userRole: role user (1=staff, 2=kasub, 3=kabid, 4=super admin)
     */
    public function canAccessFile($fileAccessLevel, $userRole) {
        // Super admin (role 4) bisa akses semua file
        if ($userRole == 4) return true;
        // Role lain hanya bisa akses file dengan level <= role
        return $userRole >= $fileAccessLevel;
    }

    /**
     * Cek apakah user sudah login
     */
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }

    // ================= LOGGING HELPER =================
    
    /**
     * Helper method untuk safe logging
     */
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

    // ================= FILE OPERATIONS =================
    
    /**
     * Upload file dengan enkripsi dan logging
     */
    public function upload($file, $label_id, $access_level_id, $restricted_password = null) {
        $fileId = uniqid("file_");
        
        try {
            $label = $this->labelModel->find($label_id);
            if (!$label) {
                throw new RuntimeException("Label tidak ditemukan");
            }

            $userSecret = $restricted_password ?: null;

            // Password hash untuk restricted files
            $restrictedPasswordHash = null;
            if ($label['access_level'] === 'restricted' && $restricted_password) {
                $restrictedPasswordHash = password_hash($restricted_password, PASSWORD_ARGON2ID);
            }

            // Enkripsi file
            $tmpEncPath = sys_get_temp_dir() . "/enc_" . $fileId;
            encrypt_file($file['tmp_name'], $tmpEncPath, $fileId, $file['name'], $userSecret);
            $encData = file_get_contents($tmpEncPath);

            // Simpan ke database
            $stmt = $this->pdo->prepare('
                INSERT INTO files 
                (filename, original_filename, mime_type, file_data, label_id, access_level_id, encryption_iv, restricted_password_hash) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');
            
            $result = $stmt->execute([
                $fileId,
                base64_encode($file['name']),
                base64_encode($file['type']),
                $encData,
                $label_id,
                $access_level_id,
                "",
                $restrictedPasswordHash
            ]);

            if ($result) {
                // Log upload success
                $this->safeLog('upload', 'success', 'file', $fileId, $file['name'], [
                    'file_size' => $file['size'],
                    'mime_type' => $file['type'],
                    'label_id' => $label_id,
                    'access_level_id' => $access_level_id,
                    'access_level' => $label['access_level'],
                    'restricted' => !empty($restricted_password) ? 'yes' : 'no'
                ]);
            }

            unlink($tmpEncPath);
            return true;
            
        } catch (Exception $e) {
            // Log upload failed
            $this->safeLog('upload', 'failed', 'file', $fileId, $file['name'] ?? 'unknown', [
                'error' => $e->getMessage(),
                'label_id' => $label_id ?? 'unknown',
                'access_level_id' => $access_level_id ?? 'unknown'
            ]);
            throw $e;
        }
    }

    /**
     * Download file dengan dekripsi dan logging
     */
    public function download($id, $password = null) {
        try {
            $file = $this->fileModel->find($id);
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan");
            }

            $origName = base64_decode($file['original_filename']);

            // Validasi restricted password
            if (!empty($file['restricted_password_hash'])) {
                if (empty($password) || !password_verify($password, $file['restricted_password_hash'])) {
                    $this->safeLog('download', 'failed', 'file', $file['filename'], $origName, [
                        'reason' => 'invalid_password',
                        'file_id' => $id
                    ]);
                    throw new RuntimeException("Password salah untuk file restricted!");
                }
            }

            $fileId = $file['filename'];
            $userSecret = $password ?: null;

            // Dekripsi file menggunakan temporary files
            $tmpEnc = sys_get_temp_dir() . "/dl_" . $fileId . ".enc";
            $tmpOut = sys_get_temp_dir() . "/dl_" . $fileId;

            file_put_contents($tmpEnc, $file['file_data']);
            decrypt_file($tmpEnc, $tmpOut, $fileId, $origName, $userSecret);
            $plaintext = file_get_contents($tmpOut);

            // Cleanup temporary files
            unlink($tmpEnc);
            unlink($tmpOut);

            // Increment download counter
            $this->fileModel->incrementDownload($id);

            // Log download success
            $this->safeLog('download', 'success', 'file', $fileId, $origName, [
                'file_id' => $id,
                'file_size' => strlen($plaintext),
                'restricted' => !empty($password) ? 'yes' : 'no'
            ]);

            // Send file to browser
            $mime = base64_decode($file['mime_type']);
            header("Content-Type: $mime");
            header("Content-Disposition: attachment; filename=\"$origName\"");
            echo $plaintext;
            exit;
            
        } catch (Exception $e) {
            $this->safeLog('download', 'failed', 'file', $file['filename'] ?? 'unknown', 
                $origName ?? 'unknown', [
                    'error' => $e->getMessage(),
                    'file_id' => $id
                ]);
            throw $e;
        }
    }

    // ================= DASHBOARD & DISPLAY =================
    
    /**
     * Dashboard dengan toggle encrypted untuk superadmin
     */
    public function dashboard($showEncrypted = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $level = $_SESSION['user_level'] ?? 1;
        $files = $this->fileModel->allWithAccessLevel($level);
        
        // Cek mode display global dari database
        $globalMode = $this->getFilenameDisplayMode();
        
        // Superadmin bisa override dengan parameter
        $showEncrypted = ($level == 4 && $showEncrypted) ? true : ($globalMode === 'encrypted');
        
        return $this->processFilenamesForDisplay($files, $level, $showEncrypted, $globalMode);
    }

    /**
     * Search and filter dengan toggle encrypted untuk superadmin
     */
    public function searchAndFilter($searchTerm = '', $labelFilter = '', $showEncrypted = false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userLevel = $_SESSION['user_level'] ?? 1;
        
        // Build query dengan filters
        $sql = '
            SELECT f.*, l.name AS label_name, l.access_level AS label_access_level_enum,
                   al.level AS file_access_level_level, al.name AS file_access_level_name
            FROM files f
            JOIN labels l ON f.label_id = l.id
            LEFT JOIN access_levels al ON f.access_level_id = al.id
            WHERE f.access_level_id <= ?
        ';
        
        $params = [$userLevel];
        
        // Add search filter (di mode encrypted, search tetap berdasarkan nama asli)
        if (!empty($searchTerm)) {
            $sql .= ' AND f.original_filename LIKE ?';
            $params[] = '%' . base64_encode($searchTerm) . '%';
        }
        
        // Add label filter
        if (!empty($labelFilter)) {
            $sql .= ' AND f.label_id = ?';
            $params[] = $labelFilter;
        }
        
        $sql .= ' ORDER BY f.uploaded_at DESC';
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cek mode display global
            $globalMode = $this->getFilenameDisplayMode();
            $showEncrypted = ($userLevel == 4 && $showEncrypted) ? true : ($globalMode === 'encrypted');
            
            return $this->processFilenamesForDisplay($files, $userLevel, $showEncrypted, $globalMode);
            
        } catch (Exception $e) {
            error_log("Search and filter error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Process filenames untuk display dengan sistem lock global
     */
    private function processFilenamesForDisplay($files, $userLevel, $showEncrypted = false, $globalMode = 'normal') {
        foreach ($files as &$f) {
            if ($showEncrypted) {
                // Mode encrypted: tampilkan nama ter-encrypt dengan AES-256
                $f["decrypted_name"] = $this->encryptFilenameAES256($f["original_filename"]);
                $f["display_mode"] = "encrypted";
            } else {
                // Mode normal: cek restricted/private atau decode nama asli
                if (isset($f["label_access_level_enum"]) && 
                    ($f["label_access_level_enum"] === 'restricted' || $f["label_access_level_enum"] === 'private')) {
                    $f["decrypted_name"] = "[Restricted/Private]";
                } else {
                    $f["decrypted_name"] = base64_decode($f["original_filename"]);
                }
                $f["display_mode"] = "normal";
            }
            
            // Tambah info global mode
            $f["global_mode"] = $globalMode;
            $f["is_locked"] = ($globalMode === 'encrypted');
        }
        
        return $files;
    }

    /**
     * Encrypt filename dengan AES-256 untuk display superadmin
     */
    private function encryptFilenameAES256($base64Filename) {
        try {
            // Load environment variables
            if (file_exists(__DIR__ . '/../config/.env')) {
                $lines = file(__DIR__ . '/../config/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                        list($key, $value) = explode('=', $line, 2);
                        $_ENV[trim($key)] = trim($value);
                    }
                }
            }
            
            $masterSecret = $_ENV['MASTER_SECRET'] ?? 'default_secret_key_for_display';
            $originalName = base64_decode($base64Filename);
            
            // Encrypt dengan AES-256-CBC
            $key = hash('sha256', $masterSecret . 'filename_display_encryption', true);
            $iv = openssl_random_pseudo_bytes(16);
            $encrypted = openssl_encrypt($originalName, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            
            if ($encrypted === false) {
                return "[Encryption Error]";
            }
            
            $encryptedWithIv = base64_encode($iv . $encrypted);
            return "🔐 " . substr($encryptedWithIv, 0, 32) . "...";
            
        } catch (Exception $e) {
            error_log("Filename encryption error: " . $e->getMessage());
            return "[Encryption Error]";
        }
    }

    // ================= KEY ROTATION =================
    
    /**
     * Rotate master key dan update semua file di database
     */
    public function rotateKeyAndUpdateDB($newSecret) {
        return $this->performKeyRotation($newSecret, []);
    }

    /**
     * Rotate master key dengan password map untuk restricted files
     */
    public function rotateKeyAndUpdateDBWithPasswords($newSecret, array $restrictedPasswordMap) {
        return $this->performKeyRotation($newSecret, $restrictedPasswordMap);
    }

    /**
     * Core method untuk key rotation
     */
    private function performKeyRotation($newSecret, $restrictedPasswordMap = []) {
        $tempFiles = [];
        $logAction = empty($restrictedPasswordMap) ? 'rotate_key' : 'rotate_key_with_passwords';
        
        try {
            // Validasi input
            if (empty(trim($newSecret))) {
                throw new RuntimeException("Secret tidak boleh kosong");
            }

            // Setup rotator
            $rotator = new CryptoKeyRotate($newSecret, null, $this->pdo, $restrictedPasswordMap);
            $files = $this->fileModel->all();
            
            if (empty($files)) {
                $rotator->rotateFiles([]);
                $this->safeLog($logAction, 'success', 'system', 'master_key', 'Master Key Rotation', [
                    'files_processed' => 0,
                    'restricted_files' => count($restrictedPasswordMap),
                    'new_secret_length' => strlen($newSecret)
                ]);
                return true;
            }

            // Prepare temporary files
            foreach ($files as $f) {
                $fileId = $f['filename'];
                $origName = base64_decode($f['original_filename']);
                $tmpEnc = sys_get_temp_dir() . "/rotate_" . $fileId . "_" . time() . ".enc";
                
                file_put_contents($tmpEnc, $f['file_data']);
                
                $tempFiles[] = [
                    'encPath' => $tmpEnc,
                    'fileId' => $fileId,
                    'origName' => $origName,
                    'dbId' => $f['id']
                ];
            }

            // Perform rotation
            $rotator->rotateFiles($tempFiles);

            // Update database dengan file baru
            foreach ($tempFiles as $tmp) {
                if (file_exists($tmp['encPath'])) {
                    $newCipher = file_get_contents($tmp['encPath']);
                    $this->fileModel->updateFileData($tmp['dbId'], $newCipher);
                }
                @unlink($tmp['encPath']);
            }

            // Log success
            $this->safeLog($logAction, 'success', 'system', 'master_key', 'Master Key Rotation', [
                'files_processed' => count($tempFiles),
                'restricted_files' => count($restrictedPasswordMap),
                'new_secret_length' => strlen($newSecret)
            ]);

            return true;
            
        } catch (Exception $e) {
            error_log("Key rotation failed: " . $e->getMessage());
            
            // Cleanup temporary files
            foreach ($tempFiles as $tmp) {
                @unlink($tmp['encPath']);
            }
            
            // Log failure
            $this->safeLog($logAction, 'failed', 'system', 'master_key', 'Master Key Rotation', [
                'error' => $e->getMessage(),
                'files_to_process' => count($tempFiles),
                'restricted_files' => count($restrictedPasswordMap)
            ]);
            
            throw new RuntimeException("Gagal melakukan rotasi key: " . $e->getMessage());
        }
    }

    // ================= DATA RETRIEVAL =================
    
    /**
     * Get semua labels untuk dropdown filter
     */
    public function getAllLabels() {
        try {
            $stmt = $this->pdo->prepare('SELECT id, name FROM labels ORDER BY name ASC');
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Get labels error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get statistik untuk dashboard
     */
    public function getDashboardStats() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $userLevel = $_SESSION['user_level'] ?? 1;
        
        try {
            // Total files
            $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM files WHERE access_level_id <= ?');
            $stmt->execute([$userLevel]);
            $totalFiles = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Total downloads
            $stmt = $this->pdo->prepare('SELECT SUM(download_count) as total_downloads FROM files WHERE access_level_id <= ?');
            $stmt->execute([$userLevel]);
            $totalDownloads = $stmt->fetch(PDO::FETCH_ASSOC)['total_downloads'] ?? 0;
            
            // Files by label
            $stmt = $this->pdo->prepare('
                SELECT l.name, COUNT(f.id) as count 
                FROM labels l 
                LEFT JOIN files f ON l.id = f.label_id AND f.access_level_id <= ?
                GROUP BY l.id, l.name 
                ORDER BY count DESC
            ');
            $stmt->execute([$userLevel]);
            $filesByLabel = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Recent uploads (7 days)
            $stmt = $this->pdo->prepare('
                SELECT COUNT(*) as recent_uploads 
                FROM files 
                WHERE access_level_id <= ? AND uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ');
            $stmt->execute([$userLevel]);
            $recentUploads = $stmt->fetch(PDO::FETCH_ASSOC)['recent_uploads'];
            
            return [
                'total_files' => $totalFiles,
                'total_downloads' => $totalDownloads,
                'files_by_label' => $filesByLabel,
                'recent_uploads' => $recentUploads
            ];
            
        } catch (Exception $e) {
            error_log("Dashboard stats error: " . $e->getMessage());
            return [
                'total_files' => 0,
                'total_downloads' => 0,
                'files_by_label' => [],
                'recent_uploads' => 0
            ];
        }
    }

    // ================= SYSTEM LOCK FUNCTIONS =================
    
    /**
     * Get current filename display mode dari database (PUBLIC untuk testing)
     */
    public function getFilenameDisplayMode() {
        try {
            $stmt = $this->pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
            $stmt->execute(['filename_display_mode']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['setting_value'] : 'normal';
        } catch (Exception $e) {
            error_log("Get display mode error: " . $e->getMessage());
            return 'normal';
        }
    }

    /**
     * Set filename display mode (hanya superadmin)
     */
    public function setFilenameDisplayMode($mode, $adminUserId) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userLevel = $_SESSION['user_level'] ?? 1;
        
        // Hanya superadmin yang bisa mengubah mode
        if ($userLevel != 4) {
            throw new RuntimeException("Access denied: Only superadmin can change display mode");
        }
        
        if (!in_array($mode, ['normal', 'encrypted'])) {
            throw new RuntimeException("Invalid display mode");
        }
        
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                setting_value = VALUES(setting_value), 
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP
            ');
            $stmt->execute(['filename_display_mode', $mode, $adminUserId]);
            
            // Log perubahan mode
            $this->safeLog('system_lock', 'success', 'system', 'filename_display', 'Filename Display Mode Change', [
                'mode' => $mode,
                'changed_by' => $adminUserId,
                'affects_all_users' => true
            ]);
            
            return true;
            
        } catch (Exception $e) {
            error_log("Set display mode error: " . $e->getMessage());
            throw new RuntimeException("Failed to update display mode");
        }
    }
}