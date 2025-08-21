<?php
require_once __DIR__ . '/config/database.php';  // pastikan koneksi $pdo tersedia
require_once __DIR__ . '/controllers/FileController.php';
require_once __DIR__ . '/controllers/LabelController.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ============ Upload File ============
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $controller = new FileController($pdo);

            $file     = $_FILES['file'];
            $labelId  = $_POST['label_id'] ?? null;
            $password = $_POST['restricted_password'] ?? null;

            try {
                $controller->upload($file, $labelId, $password);
                echo "✅ File berhasil diupload";
            } catch (Exception $e) {
                http_response_code(500);
                echo "❌ Upload gagal: " . $e->getMessage();
            }
        } else {
            echo "Gunakan form POST untuk upload file";
        }
        break;

    // ============ Buat Label ============
    case 'create_label':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new LabelController($pdo);

            $name        = $_POST['name'] ?? null;
            $description = $_POST['description'] ?? null;
            $accessLevel = $_POST['access_level'] ?? null;

            try {
                $controller->create($name, $description, $accessLevel);
                echo "✅ Label berhasil dibuat";
            } catch (Exception $e) {
                http_response_code(500);
                echo "❌ Gagal membuat label: " . $e->getMessage();
            }
        } else {
            echo "Gunakan POST untuk membuat label";
        }
        break;

    // ============ Download File ============
    case 'download':
        $controller = new FileController($pdo);

        $id       = $_GET['id'] ?? null;
        $password = $_POST['password'] ?? null; // ambil dari form kalau restricted

        if ($id) {
            try {
                $controller->download($id, $password);
            } catch (Exception $e) {
                http_response_code(403);
                echo "❌ Download gagal: " . $e->getMessage();
            }
        } else {
            echo "ID file tidak diberikan";
        }
        break;

    // ============ Default (dashboard) ============
    default:
        $controller = new FileController($pdo);
        $files = $controller->dashboard();

        echo "<h2>📂 Dashboard</h2>";
        echo "<ul>";
        foreach ($files as $f) {
            echo "<li>";
            echo htmlspecialchars($f['decrypted_name']);
            echo " - <a href='?action=download&id=" . urlencode($f['id']) . "'>Download</a>";
            echo "</li>";
        }
        echo "</ul>";
        break;
}
