<?php

require_once __DIR__ . "/../models/File.php";
require_once __DIR__ . "/../models/Label.php";
require_once __DIR__ . "/../config/crypto.php";
require_once __DIR__ . "/../config/CryptoKeyRotate.php";

class FileController {
    private $pdo;
    private $fileModel;
    private $labelModel;

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
    }

    // ================= Upload =================
    public function upload($file, $label_id, $access_level_id, $restricted_password = null) {
        $label = $this->labelModel->find($label_id);
        if (!$label) {
            throw new RuntimeException("Label tidak ditemukan");
        }

        $fileId     = uniqid("file_");
        $userSecret = $restricted_password ? $restricted_password : null;

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

        // Simpan ke DB, tambahkan access_level_id
        $stmt = $this->pdo->prepare('
            INSERT INTO files 
            (filename, original_filename, mime_type, file_data, label_id, access_level_id, encryption_iv, restricted_password_hash) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $fileId,
            base64_encode($file['name']),
            base64_encode($file['type']),
            $encData,
            $label_id,
            $access_level_id,
            "", // iv
            $restrictedPasswordHash
        ]);

        unlink($tmpEncPath);
        return true;
    }

    // ================= Download =================
    public function download($id, $password = null) {
        $file = $this->fileModel->find($id);
        if (!$file) {
            throw new RuntimeException("File tidak ditemukan");
        }

        // restricted check
        if (!empty($file['restricted_password_hash'])) {
            if (empty($password) || !password_verify($password, $file['restricted_password_hash'])) {
                throw new RuntimeException("Password salah untuk file restricted!");
            }
        }

        $fileId     = $file['filename'];
        $origName   = base64_decode($file['original_filename']);
        $userSecret = $password ?: null;

        // simpan ciphertext ke temp file
        $tmpEnc = sys_get_temp_dir() . "/dl_" . $fileId . ".enc";
        $tmpOut = sys_get_temp_dir() . "/dl_" . $fileId;

        file_put_contents($tmpEnc, $file['file_data']);

        // === Sesuaikan ke crypto.php ===
        decrypt_file($tmpEnc, $tmpOut, $fileId, $origName, $userSecret);

        // baca hasil
        $plaintext = file_get_contents($tmpOut);

        // hapus temp
        unlink($tmpEnc);
        unlink($tmpOut);

        // increment download
        $this->fileModel->incrementDownload($id);

        // kirim ke browser
        $mime = base64_decode($file['mime_type']);
        header("Content-Type: $mime");
        header("Content-Disposition: attachment; filename=\"$origName\"");
        echo $plaintext;
        exit;
    }

    // ================= Dashboard =================
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

    // ================= Key Rotate dengan update DB =================
    public function rotateKeyAndUpdateDB($newSecret) {
        $rotator = new CryptoKeyRotate($newSecret);

        // ambil semua file dari DB
        $files = $this->fileModel->all();

        $tempFiles = [];
        foreach ($files as $f) {
            $fileId   = $f['filename'];
            $origName = base64_decode($f['original_filename']);

            // buat file sementara
            $tmpEnc = sys_get_temp_dir() . "/rotate_" . $fileId . ".enc";
            file_put_contents($tmpEnc, $f['file_data']);

            // simpan mapping untuk diproses oleh rotator
            $tempFiles[] = [
                'encPath'  => $tmpEnc,
                'fileId'   => $fileId,
                'origName' => $origName,
                'dbId'     => $f['id'] // simpan id DB untuk update nanti
            ];
        }

        // jalankan rotasi file
        $rotator->rotateFiles($tempFiles);

        // setelah rotate → baca ulang hasilnya, simpan ke DB
        foreach ($tempFiles as $tmp) {
            $newCipher = file_get_contents($tmp['encPath']);
            $this->fileModel->updateFileData($tmp['dbId'], $newCipher);

            // hapus file sementara
            @unlink($tmp['encPath']);
        }

        return "Master Key berhasil di-rotate dan database sudah diperbarui!";
    }

    // Cek apakah user sudah login (gunakan session)
    public function isAuthenticated() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}