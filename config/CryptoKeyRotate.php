<?php
// File: App/Core/CryptoKeyRotate.php
namespace App\Core;

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crypto.php';
use RuntimeException;

class CryptoKeyRotate
{
    private string $oldSecret;
    private string $newSecret;
    private string $envPath;

    public function __construct(string $newSecret, string $envPath = __DIR__ . '/../.env')
    {
        $this->oldSecret = getenv('MASTER_SECRET') ?: throw new RuntimeException("MASTER_SECRET not set");
        $this->newSecret = $newSecret;
        $this->envPath   = $envPath;
    }

    /**
     * Proses rotasi semua file
     */
    public function rotateFiles(array $files): void
    {
        foreach ($files as $file) {
            $this->rotateSingleFile($file['encPath'], $file['fileId'], $file['origName']);
        }

        $this->updateEnv();
    }

    /**
     * Rotasi satu file terenkripsi
     */
    private function rotateSingleFile(string $encPath, string $fileId, string $origName): void
    {
        $tmpPlain = $encPath . '.tmp.plain';
        $tmpNew   = $encPath . '.tmp.new';

        // step 1: decrypt pakai old secret
        putenv("MASTER_SECRET=" . $this->oldSecret);
        Crypto::decrypt_file($encPath, $tmpPlain, $fileId, $origName);

        // step 2: encrypt ulang pakai new secret
        putenv("MASTER_SECRET=" . $this->newSecret);
        Crypto::encrypt_file($tmpPlain, $tmpNew, $fileId, $origName);

        // step 3: ganti file lama dengan hasil baru
        unlink($encPath);
        rename($tmpNew, $encPath);
        unlink($tmpPlain);
    }

    /**
     * Update isi file .env dengan MASTER_SECRET baru
     */
    private function updateEnv(): void
    {
        $lines = file($this->envPath, FILE_IGNORE_NEW_LINES);
        $found = false;

        foreach ($lines as &$line) {
            if (str_starts_with(trim($line), 'MASTER_SECRET=')) {
                $line = "MASTER_SECRET=" . $this->newSecret;
                $found = true;
                break;
            }
        }

        if (!$found) {
            $lines[] = "MASTER_SECRET=" . $this->newSecret;
        }

        file_put_contents($this->envPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    /**
     * Normalisasi input user menjadi MASTER_SECRET yang valid
     * Format: v2:BASE64_STRING
     */
    public static function normalizeSecret(string $input): string
    {
        $input = trim($input);

        // Jika kosong → generate secret random
        if ($input === '') {
            $randomBytes = random_bytes(32);
            return 'v2:' . base64_encode($randomBytes);
        }

        // Jika sudah format v2:xxxx → langsung dipakai
        if (str_starts_with($input, 'v2:')) {
            return $input;
        }

        // Tambahkan random salt supaya unik
        $randomSalt = bin2hex(random_bytes(8));
        $rawSecret  = $input . '|' . $randomSalt;

        // Encode ke base64
        $encoded = base64_encode($rawSecret);

        return 'v2:' . $encoded;
    }
}
