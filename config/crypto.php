<?php
require __DIR__ . '/config.php';
load_env(); // load .env file

function hkdf_sha256($ikm, $salt, $info, $length = 16) {
    return hash_hkdf("sha256", $ikm, $length, $info, $salt);
}

// Derive kunci dari metadata (AES-256 key)
function derive_aes256_key(string $fileId, string $origName, string $salt16, ?string $userSecret = null): string {
    $masterSecret = getenv('MASTER_SECRET');
    if (!$masterSecret) throw new RuntimeException("MASTER_SECRET not set in .env");

    $ikm = base64_decode(explode(':', $masterSecret, 2)[1] ?? '') 
         . '|' . $fileId . '|' . $origName . '|' . ($userSecret ?? '');

    // AES-256 = 32 byte key
    return hkdf_sha256($ikm, $salt16, 'aes-256-cbc:key', 32);
}

function derive_mac_key(string $fileId, string $origName, string $salt16, ?string $userSecret = null): string {
    $masterSecret = getenv('MASTER_SECRET');
    if (!$masterSecret) throw new RuntimeException("MASTER_SECRET not set in .env");

    $ikm = base64_decode(explode(':', $masterSecret, 2)[1] ?? '') 
         . '|' . $fileId . '|' . $origName . '|' . ($userSecret ?? '');

    return hkdf_sha256($ikm, $salt16, 'aes-256-cbc:mac', 32);
}

function encrypt_file(string $srcPath, string $dstPath, string $fileId, string $origName, ?string $userSecret = null): void {
    $salt = random_bytes(16);
    $iv   = random_bytes(16);

    $encKey = derive_aes256_key($fileId, $origName, $salt, $userSecret);
    $macKey = derive_mac_key($fileId, $origName, $salt, $userSecret);

    $plaintext   = file_get_contents($srcPath);
    $ciphertext  = openssl_encrypt($plaintext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
    if ($ciphertext === false) throw new RuntimeException('OpenSSL encrypt failed');

    $mac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);

    $fp = fopen($dstPath, 'wb');
    fwrite($fp, "ENC1");
    fwrite($fp, $salt);
    fwrite($fp, $iv);
    fwrite($fp, $mac);
    fwrite($fp, $ciphertext);
    fclose($fp);
}

function decrypt_file(string $encPath, string $dstPath, string $fileId, string $origName, ?string $userSecret = null): void {
    $fp = fopen($encPath, 'rb');
    $magic = fread($fp, 4);
    if ($magic !== "ENC1") throw new RuntimeException('Invalid file');

    $salt = fread($fp, 16);
    $iv   = fread($fp, 16);
    $mac  = fread($fp, 32);
    $ciphertext = stream_get_contents($fp);
    fclose($fp);

    $encKey = derive_aes256_key($fileId, $origName, $salt, $userSecret);
    $macKey = derive_mac_key($fileId, $origName, $salt, $userSecret);

    $calcMac = hash_hmac('sha256', $iv . $ciphertext, $macKey, true);
    if (!hash_equals($mac, $calcMac)) throw new RuntimeException('MAC verification failed');

    $plaintext = openssl_decrypt($ciphertext, 'aes-256-cbc', $encKey, OPENSSL_RAW_DATA, $iv);
    if ($plaintext === false) throw new RuntimeException('OpenSSL decrypt failed');

    file_put_contents($dstPath, $plaintext);
}
