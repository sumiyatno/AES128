<?php
/**
 * database.php
 * Koneksi dasar ke MySQL (Laragon default: user root, tanpa password)
 */

$host = "127.0.0.1";   // atau "localhost"
$db   = "dasar";  // ganti dengan nama database kamu
$user = "root";        // default Laragon
$pass = "";            // kosongkan password jika default

try {
    // Gunakan PDO biar aman
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);

    // Set mode error ke Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // opsional: fetch default associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Tes koneksi
    // echo "Koneksi berhasil!";
} catch (PDOException $e) {
    die("Koneksi gagal: " . $e->getMessage());
}
