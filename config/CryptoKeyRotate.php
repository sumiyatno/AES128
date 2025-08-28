<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';

class CryptoKeyRotate
{
    private string $oldSecret;
    private string $newSecret;
    private string $envPath;
    private $pdo;

    public function __construct(string $newSecret, string $envPath = null, $pdo = null)
    {
        // Path .env yang benar
        if ($envPath === null) {
            $envPath = __DIR__ . '/.env';
        }
        
        // Pastikan .env sudah di-load
        load_env();
        
        $this->oldSecret = getenv('MASTER_SECRET');
        if (!$this->oldSecret) {
            throw new RuntimeException("MASTER_SECRET not set");
        }

        $this->newSecret = $this->normalizeSecret($newSecret);
        $this->envPath   = $envPath;
        $this->pdo       = $pdo;
        
        error_log("Old secret: " . $this->oldSecret);
        error_log("New secret: " . $this->newSecret);
    }

    /**
     * Proses rotasi semua file
     */
    public function rotateFiles(array $files): void
    {
        foreach ($files as $file) {
            error_log("Processing file: " . $file['fileId']);
            $this->rotateSingleFile($file['encPath'], $file['fileId'], $file['origName'], $file['dbId']);
        }

        $this->updateEnv();
    }

    /**
     * Rotasi satu file terenkripsi menggunakan logika sama dengan FileController
     */
    private function rotateSingleFile(string $encPath, string $fileId, string $origName, int $dbId): void
    {
        $tmpPlain = $encPath . '.tmp.plain';
        $tmpNew   = $encPath . '.tmp.new';

        try {
            // ===== Step 1: Ambil info file dari database untuk cek restricted =====
            $restrictedPassword = $this->getRestrictedPasswordForFile($dbId);
            $userSecret = $restrictedPassword; // Sama seperti di FileController

            error_log("File $fileId - Restricted password: " . ($restrictedPassword ? 'YES' : 'NO'));

            // ===== Step 2: Decrypt dengan old secret =====
            // Backup current env
            $originalSecret = getenv('MASTER_SECRET');
            
            // Set old secret di environment
            putenv("MASTER_SECRET=" . $this->oldSecret);
            
            // Gunakan fungsi decrypt_file asli dari crypto.php
            decrypt_file($encPath, $tmpPlain, $fileId, $origName, $userSecret);
            error_log("Decrypt success for file: $fileId");
            
            // ===== Step 3: Encrypt dengan new secret =====
            // Set new secret di environment
            putenv("MASTER_SECRET=" . $this->newSecret);
            
            // Gunakan fungsi encrypt_file asli dari crypto.php
            encrypt_file($tmpPlain, $tmpNew, $fileId, $origName, $userSecret);
            error_log("Encrypt success for file: $fileId");
            
            // ===== Step 4: Replace file =====
            if (file_exists($tmpNew)) {
                unlink($encPath);
                rename($tmpNew, $encPath);
                error_log("File replaced successfully: $fileId");
            }
            
            // Restore environment
            putenv("MASTER_SECRET=" . $originalSecret);
            
        } catch (Exception $e) {
            // Restore environment jika error
            if (isset($originalSecret)) {
                putenv("MASTER_SECRET=" . $originalSecret);
            }
            error_log("Error processing file $fileId: " . $e->getMessage());
            throw $e;
        } finally {
            // cleanup
            @unlink($tmpPlain);
        }
    }

    /**
     * Ambil password restricted dari database untuk file tertentu
     * Menggunakan brute force dengan password umum
     */
    private function getRestrictedPasswordForFile(int $fileDbId): ?string
    {
        if (!$this->pdo) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT restricted_password_hash FROM files WHERE id = ?');
            $stmt->execute([$fileDbId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['restricted_password_hash'])) {
                // File ini restricted, coba password umum
                $commonPasswords = $this->getCommonRestrictedPasswords();
                
                foreach ($commonPasswords as $password) {
                    if (password_verify($password, $result['restricted_password_hash'])) {
                        error_log("Found restricted password for file ID: $fileDbId");
                        return $password;
                    }
                }
                
                // Jika tidak ketemu, coba dengan password kosong dulu
                // Kemungkinan file di-upload tanpa restricted password
                error_log("No matching password found for restricted file ID: $fileDbId, trying without password");
                return null;
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error getting restricted password for file ID $fileDbId: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Daftar password umum yang mungkin digunakan untuk restricted files
     */
    private function getCommonRestrictedPasswords(): array
    {
        return [
            'admin', 'password', 'secret', '123456', 'test', 'restricted',
            'private', 'confidential', 'secure', 'default', 'rahasia',
            '12345', 'qwerty', 'password123', 'admin123'
        ];
    }

    /**
     * Update isi file .env dengan MASTER_SECRET baru
     */
    private function updateEnv(): void
    {
        if (!file_exists($this->envPath)) {
            file_put_contents($this->envPath, "MASTER_SECRET=" . $this->newSecret . PHP_EOL);
            return;
        }

        $content = file_get_contents($this->envPath);
        if (preg_match('/^MASTER_SECRET=.*$/m', $content)) {
            $content = preg_replace('/^MASTER_SECRET=.*$/m', 'MASTER_SECRET=' . $this->newSecret, $content);
        } else {
            $content .= "\nMASTER_SECRET=" . $this->newSecret . "\n";
        }
        
        file_put_contents($this->envPath, $content);
        
        // Update environment variable untuk session saat ini
        putenv("MASTER_SECRET=" . $this->newSecret);
        
        // Reload env untuk memastikan perubahan ter-apply
        load_env();
    }

    /**
     * Normalisasi input user menjadi MASTER_SECRET yang valid
     */
    public function normalizeSecret(string $input): string
    {
        $input = trim($input);

        // Jika kosong → generate secret random
        if ($input === '') {
            $randomBytes = random_bytes(32);
            return 'base64:' . base64_encode($randomBytes);
        }

        // Jika sudah format base64:xxxx → langsung dipakai
        if (str_starts_with($input, 'base64:')) {
            return $input;
        }

        // Hash input user untuk mendapatkan 32 byte yang konsisten
        $hashedSecret = hash('sha256', $input, true);

        // Encode ke base64
        $encoded = base64_encode($hashedSecret);

        return 'base64:' . $encoded;
    }
}
