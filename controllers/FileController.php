<?php

require_once __DIR__ . "/../models/File.php";
require_once __DIR__ . "/../models/Label.php";
require_once __DIR__ . "/../config/crypto.php";
require_once __DIR__ . "/../config/CryptoKeyRotate.php";

class FileController {
    private $pdo;
    private $fileModel;
    private $labelModel;
    private $logController; // TAMBAHAN: untuk logging

    // Fungsi pembatasan hak akses file
    // $fileAccessLevel: level akses file (1,2,3)
    // $userRole: role user (1=staff, 2=kasub, 3=kabid, 4=super admin)
    public function canAccessFile($fileAccessLevel, $userRole) {
        // Super admin (role 4) bisa akses semua file
        if ($userRole == 4) return true;
        // Role lain hanya bisa akses file dengan level <= role
        return $userRole >= $fileAccessLevel;
    }

    public function __construct($pdo) {
        $this->pdo        = $pdo;
        $this->fileModel  = new FileModel($pdo);
        $this->labelModel = new Label($pdo);
        
        // TAMBAHAN: LogController dengan error handling
        try {
            require_once __DIR__ . '/LogController.php';
            $this->logController = new LogController($pdo);
        } catch (Exception $e) {
            $this->logController = null;
            error_log("LogController not available: " . $e->getMessage());
        }
    }

    // TAMBAHAN: Helper method untuk safe logging
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

    // ================= Upload (FUNGSI ASLI + LOGGING) =================
    public function upload($file, $label_id, $access_level_id, $restricted_password = null) {
        try {
            $label = $this->labelModel->find($label_id);
            if (!$label) {
                throw new RuntimeException("Label tidak ditemukan");
            }

            $fileId     = uniqid("file_");
            $userSecret = $restricted_password ? $restricted_password : null;

            // FUNGSI ASLI: password hash untuk restricted
            $restrictedPasswordHash = null;
            if ($label['access_level'] === 'restricted' && $restricted_password) {
                $restrictedPasswordHash = password_hash($restricted_password, PASSWORD_ARGON2ID);
            }

            $tmpEncPath = sys_get_temp_dir() . "/enc_" . $fileId;
            $srcPath  = $file['tmp_name'];
            $origName = $file['name'];
            $dstPath  = $tmpEncPath;

            encrypt_file($srcPath, $dstPath, $fileId, $origName, $userSecret);
            $encData = file_get_contents($tmpEncPath);

            // FUNGSI ASLI: Simpan ke DB dengan query yang sama persis
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
                "", // iv
                $restrictedPasswordHash
            ]);

            if ($result) {
                // TAMBAHAN: LOG UPLOAD SUCCESS
                $this->safeLog(
                    'upload', 
                    'success', 
                    'file', 
                    $fileId, 
                    $origName, 
                    [
                        'file_size' => $file['size'],
                        'mime_type' => $file['type'],
                        'label_id' => $label_id,
                        'access_level_id' => $access_level_id,
                        'access_level' => $label['access_level'],
                        'restricted' => !empty($restricted_password) ? 'yes' : 'no'
                    ]
                );
            }

            unlink($tmpEncPath);
            return true;
            
        } catch (Exception $e) {
            // TAMBAHAN: LOG UPLOAD FAILED
            $this->safeLog(
                'upload', 
                'failed', 
                'file', 
                $fileId ?? 'unknown', 
                $file['name'] ?? 'unknown', 
                [
                    'error' => $e->getMessage(),
                    'label_id' => $label_id ?? 'unknown',
                    'access_level_id' => $access_level_id ?? 'unknown'
                ]
            );
            throw $e;
        }
    }

    // ================= Download (FUNGSI ASLI + LOGGING) =================
    public function download($id, $password = null) {
        try {
            $file = $this->fileModel->find($id);
            if (!$file) {
                throw new RuntimeException("File tidak ditemukan");
            }

            $origName = base64_decode($file['original_filename']);

            // FUNGSI ASLI: restricted check
            if (!empty($file['restricted_password_hash'])) {
                if (empty($password) || !password_verify($password, $file['restricted_password_hash'])) {
                    // TAMBAHAN: LOG DOWNLOAD FAILED - WRONG PASSWORD
                    $this->safeLog(
                        'download', 
                        'failed', 
                        'file', 
                        $file['filename'], 
                        $origName, 
                        [
                            'reason' => 'invalid_password',
                            'file_id' => $id
                        ]
                    );
                    throw new RuntimeException("Password salah untuk file restricted!");
                }
            }

            $fileId     = $file['filename'];
            $userSecret = $password ?: null;

            // FUNGSI ASLI: simpan ciphertext ke temp file
            $tmpEnc = sys_get_temp_dir() . "/dl_" . $fileId . ".enc";
            $tmpOut = sys_get_temp_dir() . "/dl_" . $fileId;

            file_put_contents($tmpEnc, $file['file_data']);

            // FUNGSI ASLI: Sesuaikan ke crypto.php
            decrypt_file($tmpEnc, $tmpOut, $fileId, $origName, $userSecret);

            // FUNGSI ASLI: baca hasil
            $plaintext = file_get_contents($tmpOut);

            // FUNGSI ASLI: hapus temp
            unlink($tmpEnc);
            unlink($tmpOut);

            // FUNGSI ASLI: increment download
            $this->fileModel->incrementDownload($id);

            // TAMBAHAN: LOG DOWNLOAD SUCCESS
            $this->safeLog(
                'download', 
                'success', 
                'file', 
                $fileId, 
                $origName, 
                [
                    'file_id' => $id,
                    'file_size' => strlen($plaintext),
                    'restricted' => !empty($password) ? 'yes' : 'no'
                ]
            );

            // FUNGSI ASLI: kirim ke browser
            $mime = base64_decode($file['mime_type']);
            header("Content-Type: $mime");
            header("Content-Disposition: attachment; filename=\"$origName\"");
            echo $plaintext;
            exit;
            
        } catch (Exception $e) {
            // TAMBAHAN: LOG DOWNLOAD FAILED
            $this->safeLog(
                'download', 
                'failed', 
                'file', 
                $file['filename'] ?? 'unknown', 
                $origName ?? 'unknown', 
                [
                    'error' => $e->getMessage(),
                    'file_id' => $id
                ]
            );
            throw $e;
        }
    }

    // ================= Dashboard (FUNGSI ASLI) =================
    public function dashboard() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $level = $_SESSION['user_level'] ?? 1;
        $files = $this->fileModel->allWithAccessLevel($level);
        foreach ($files as &$f) {
            // Cek apakah label restricted/private
            if (isset($f["label_access_level_enum"]) && 
                ($f["label_access_level_enum"] === 'restricted' || $f["label_access_level_enum"] === 'private')) {
                $f["decrypted_name"] = "[Restricted/Private]";
            } else {
                $f["decrypted_name"] = base64_decode($f["original_filename"]);
            }
        }
        return $files;
    }

    // ================= Key Rotate (FUNGSI ASLI + LOGGING) =================
    public function rotateKeyAndUpdateDB($newSecret) {
        try {
            // Validasi input
            if (empty(trim($newSecret))) {
                throw new RuntimeException("Secret tidak boleh kosong");
            }

            // Pass PDO ke rotator untuk query restricted password
            $rotator = new CryptoKeyRotate($newSecret, null, $this->pdo);

            // ambil semua file dari DB
            $files = $this->fileModel->all();
            
            if (empty($files)) {
                // Jika tidak ada file, langsung update .env
                $rotator->rotateFiles([]);
                
                // TAMBAHAN: LOG KEY ROTATION SUCCESS (no files)
                $this->safeLog(
                    'rotate_key', 
                    'success', 
                    'system', 
                    'master_key', 
                    'Master Key Rotation', 
                    [
                        'files_processed' => 0,
                        'new_secret_length' => strlen($newSecret)
                    ]
                );
                
                return true;
            }

            $tempFiles = [];

            foreach ($files as $f) {
                $fileId   = $f['filename'];
                $origName = base64_decode($f['original_filename']);

                // buat file sementara
                $tmpEnc = sys_get_temp_dir() . "/rotate_" . $fileId . "_" . time() . ".enc";
                file_put_contents($tmpEnc, $f['file_data']);

                // simpan mapping untuk diproses oleh rotator (tambahkan dbId)
                $tempFiles[] = [
                    'encPath'  => $tmpEnc,
                    'fileId'   => $fileId,
                    'origName' => $origName,
                    'dbId'     => $f['id'] // Penting: pass dbId untuk query restricted password
                ];
            }

            // jalankan rotasi file
            $rotator->rotateFiles($tempFiles);

            // setelah rotate → baca ulang hasilnya, simpan ke DB
            foreach ($tempFiles as $tmp) {
                if (file_exists($tmp['encPath'])) {
                    $newCipher = file_get_contents($tmp['encPath']);
                    $this->fileModel->updateFileData($tmp['dbId'], $newCipher);
                }

                // hapus file sementara
                @unlink($tmp['encPath']);
            }

            // TAMBAHAN: LOG KEY ROTATION SUCCESS
            $this->safeLog(
                'rotate_key', 
                'success', 
                'system', 
                'master_key', 
                'Master Key Rotation', 
                [
                    'files_processed' => count($tempFiles),
                    'new_secret_length' => strlen($newSecret)
                ]
            );

            return true;
            
        } catch (Exception $e) {
            // Log error untuk debugging
            error_log("Key rotation failed: " . $e->getMessage());
            
            // TAMBAHAN: LOG KEY ROTATION FAILED
            $this->safeLog(
                'rotate_key', 
                'failed', 
                'system', 
                'master_key', 
                'Master Key Rotation', 
                [
                    'error' => $e->getMessage(),
                    'files_to_process' => count($tempFiles ?? [])
                ]
            );
            
            // Cleanup temporary files jika ada error
            if (isset($tempFiles)) {
                foreach ($tempFiles as $tmp) {
                    @unlink($tmp['encPath']);
                }
            }
            
            throw new RuntimeException("Gagal melakukan rotasi key: " . $e->getMessage());
        }
    }

    // ================= Key Rotate dengan Password Map (FUNGSI ASLI + LOGGING) =================
    public function rotateKeyAndUpdateDBWithPasswords($newSecret, array $restrictedPasswordMap) {
        try {
            // Validasi input
            if (empty(trim($newSecret))) {
                throw new RuntimeException("Secret tidak boleh kosong");
            }

            // Pass PDO dan password map ke rotator
            $rotator = new CryptoKeyRotate($newSecret, null, $this->pdo, $restrictedPasswordMap);

            // ambil semua file dari DB
            $files = $this->fileModel->all();
            
            if (empty($files)) {
                $rotator->rotateFiles([]);
                
                // TAMBAHAN: LOG KEY ROTATION SUCCESS (no files)
                $this->safeLog(
                    'rotate_key_with_passwords', 
                    'success', 
                    'system', 
                    'master_key', 
                    'Master Key Rotation with Passwords', 
                    [
                        'files_processed' => 0,
                        'restricted_files' => count($restrictedPasswordMap)
                    ]
                );
                
                return true;
            }

            $tempFiles = [];

            foreach ($files as $f) {
                $fileId   = $f['filename'];
                $origName = base64_decode($f['original_filename']);

                $tmpEnc = sys_get_temp_dir() . "/rotate_" . $fileId . "_" . time() . ".enc";
                file_put_contents($tmpEnc, $f['file_data']);

                $tempFiles[] = [
                    'encPath'  => $tmpEnc,
                    'fileId'   => $fileId,
                    'origName' => $origName,
                    'dbId'     => $f['id']
                ];
            }

            // jalankan rotasi file
            $rotator->rotateFiles($tempFiles);

            // setelah rotate → baca ulang hasilnya, simpan ke DB
            foreach ($tempFiles as $tmp) {
                if (file_exists($tmp['encPath'])) {
                    $newCipher = file_get_contents($tmp['encPath']);
                    $this->fileModel->updateFileData($tmp['dbId'], $newCipher);
                }
                @unlink($tmp['encPath']);
            }

            // TAMBAHAN: LOG KEY ROTATION SUCCESS
            $this->safeLog(
                'rotate_key_with_passwords', 
                'success', 
                'system', 
                'master_key', 
                'Master Key Rotation with Passwords', 
                [
                    'files_processed' => count($tempFiles),
                    'restricted_files' => count($restrictedPasswordMap)
                ]
            );

            return true;
            
        } catch (Exception $e) {
            error_log("Key rotation with passwords failed: " . $e->getMessage());
            
            // TAMBAHAN: LOG KEY ROTATION FAILED
            $this->safeLog(
                'rotate_key_with_passwords', 
                'failed', 
                'system', 
                'master_key', 
                'Master Key Rotation with Passwords', 
                [
                    'error' => $e->getMessage(),
                    'files_to_process' => count($tempFiles ?? []),
                    'restricted_files' => count($restrictedPasswordMap)
                ]
            );
            
            if (isset($tempFiles)) {
                foreach ($tempFiles as $tmp) {
                    @unlink($tmp['encPath']);
                }
            }
            
            throw new RuntimeException("Gagal melakukan rotasi key: " . $e->getMessage());
        }
    }

    // Cek apakah user sudah login (gunakan session)
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}