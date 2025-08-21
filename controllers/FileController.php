<?php
require_once __DIR__ . "/../models/File.php";
require_once __DIR__ . "/../models/Label.php";
require_once __DIR__ . "/../config/crypto.php";

class FileController {
    private $pdo;
    private $fileModel;
    private $labelModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->fileModel = new FileModel($pdo);
        $this->labelModel = new Label($pdo);
    }

    // ================= Upload =================
    public function upload($file, $label_id, $restricted_password = null) {
        $label = $this->labelModel->find($label_id);
        if (!$label) {
            throw new RuntimeException("Label tidak ditemukan");
        }

        $fileId = uniqid("file_");
        $userSecret = $restricted_password ? $restricted_password : null;

        // buat password hash khusus restricted
        $restrictedPasswordHash = null;
        if ($label['access_level'] === 'restricted' && $restricted_password) {
            $restrictedPasswordHash = password_hash($restricted_password, PASSWORD_ARGON2ID);
        }

        // path sementara
        $tmpEncPath = sys_get_temp_dir() . "/enc_" . $fileId;

        // === PERBAIKAN: sesuaikan ke crypto.php ===
        $srcPath  = $file['tmp_name'];   // file asli
        $origName = $file['name'];       // nama file asli
        $dstPath  = $tmpEncPath;         // hasil terenkripsi

        encrypt_file($srcPath, $dstPath, $fileId, $origName, $userSecret);

        // baca hasil encrypt
        $encData = file_get_contents($tmpEncPath);

        // simpan ke DB
        $this->fileModel->create(
            $fileId,
            base64_encode($file['name']),
            base64_encode($file['type']),
            $encData,
            $label_id,
            "", // iv tidak perlu, sudah include di file
            $restrictedPasswordHash
        );

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

        $fileId    = $file['filename'];
        $origName  = base64_decode($file['original_filename']);
        $userSecret = $password ?: null;

        // simpan ciphertext ke temp file
        $tmpEnc = sys_get_temp_dir() . "/dl_" . $fileId . ".enc";
        $tmpOut = sys_get_temp_dir() . "/dl_" . $fileId;

        file_put_contents($tmpEnc, $file['file_data']);

        // === PERBAIKAN: sesuaikan ke crypto.php ===
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
        $files = $this->fileModel->all();
        foreach ($files as &$f) {
            $label = $this->labelModel->find($f['label_id']);
            if ($label && $label['access_level'] === 'public') {
                $f['decrypted_name'] = base64_decode($f['original_filename']);
            } else {
                $f['decrypted_name'] = "[Restricted/Private]";
            }
        }
        return $files;
    }
}
