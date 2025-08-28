<?php
// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek pembatasan akses
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../config/database.php';

$userController = new UserController($pdo);
$role = $_SESSION['user_level'] ?? 1;

// Cek apakah user punya akses ke master_key
if (!$userController->canAccessFeature($role, 'master_key')) {
    http_response_code(403);
    echo "❌ Anda tidak punya akses ke fitur ini.";
    exit;
}

// Cek apakah ada file restricted
$stmt = $pdo->prepare('SELECT id, filename, original_filename FROM files WHERE restricted_password_hash IS NOT NULL');
$stmt->execute();
$restrictedFiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include __DIR__ . '/sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rotate Master Key</title>
    <link href="sidebar.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 22px;
            margin-bottom: 20px;
        }
        h3 {
            font-size: 18px;
            margin-top: 25px;
            margin-bottom: 15px;
        }
        .form-group {
            margin: 15px 0;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .form-group input {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ddd;
            box-sizing: border-box;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #2d89ef;
            border: none;
            color: #fff;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #1b5cb8;
        }
        .alert {
            margin: 15px 0;
            padding: 12px;
            border-radius: 5px;
        }
        .success {
            background: #e0f7e9;
            color: #2d7a3f;
        }
        .error {
            background: #fdecea;
            color: #b32a2a;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .restricted-files {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border: 1px solid #dee2e6;
        }
        .file-item {
            background: #ffffff;
            padding: 12px;
            margin: 10px 0;
            border-radius: 5px;
            border: 1px solid #e9ecef;
        }
        .file-name {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        .file-id {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Rotate Master Key</h1>
    
    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'success'): ?>
            <div class="alert success">✅ Master Key berhasil di-rotate dan database diperbarui!</div>
            <script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 3000);
            </script>
        <?php elseif ($_GET['status'] === 'error'): ?>
            <?php 
            $errorMessage = $_GET['message'] ?? 'Gagal melakukan rotasi key!';
            if ($errorMessage === 'secret_empty') {
                $errorMessage = 'Secret tidak boleh kosong!';
            } elseif ($errorMessage === 'process_failed') {
                $errorMessage = 'Proses rotasi gagal!';
            }
            ?>
            <div class="alert error">❌ <?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($restrictedFiles)): ?>
        <!-- Form untuk file restricted -->
        <div class="alert warning">
            ⚠️ <strong>Perhatian!</strong> Terdapat <?= count($restrictedFiles) ?> file restricted yang memerlukan password untuk rotasi key.
        </div>
        
        <div class="restricted-files">
            <h3>📋 File Restricted yang Ditemukan:</h3>
            <?php foreach ($restrictedFiles as $file): ?>
                <div class="file-item">
                    <div class="file-name">📁 <?= htmlspecialchars(base64_decode($file['original_filename'])) ?></div>
                    <div class="file-id">ID: <?= htmlspecialchars($file['filename']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="../routes.php?action=rotateKeyWithPasswords">
            <div class="form-group">
                <label for="new_secret">Master Secret Baru:</label>
                <input type="text" id="new_secret" name="new_secret" required 
                       placeholder="Masukkan master secret baru">
            </div>

            <h3>🔐 Password untuk File Restricted:</h3>
            <?php foreach ($restrictedFiles as $file): ?>
                <div class="form-group">
                    <label for="pwd_<?= $file['id'] ?>">
                        Password untuk: <?= htmlspecialchars(base64_decode($file['original_filename'])) ?>
                    </label>
                    <input type="password" 
                           id="pwd_<?= $file['id'] ?>"
                           name="restricted_passwords[<?= $file['id'] ?>]" 
                           placeholder="Masukkan password file restricted"
                           required>
                </div>
            <?php endforeach; ?>

            <button type="submit">🔄 Rotate Key dengan Password</button>
        </form>

    <?php else: ?>
        <!-- Form normal jika tidak ada file restricted -->
        <p>Rotasi Master Key akan mengubah kunci enkripsi untuk semua file yang tersimpan.</p>
        <p><strong>Peringatan:</strong> Pastikan Anda menyimpan Master Key yang baru!</p>

        <form method="post" action="../routes.php?action=rotateKey">
            <div class="form-group">
                <label for="new_secret">Masukkan Master Secret Baru:</label>
                <input type="text" id="new_secret" name="new_secret" required 
                       placeholder="Masukkan secret baru (minimal 8 karakter)">
            </div>
            <button type="submit">🔄 Rotate Key</button>
        </form>
    <?php endif; ?>

</div>
</body>
</html>
