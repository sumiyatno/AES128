<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/FileController.php';
require_once __DIR__ . '/controllers/LabelController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/LogController.php'; // Tambahkan ini

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? '';
$userController = new UserController($pdo);
$role = $_SESSION['user_level'] ?? 1;

// Mapping action ke feature yang ada di UserController
$actionToFeature = [
    'rotateKey' => 'master_key',
    'rotateKeyWithPasswords' => 'master_key',  // ← TAMBAHAN INI
    'rotate_form' => 'master_key',
    'create_user' => 'manage_account',
    'delete_user' => 'manage_account', 
    'update_user' => 'manage_account'
];

// Logout tidak perlu pembatasan akses
if ($action === 'logout') {
    require_once __DIR__ . '/controllers/AuthController.php';
    $auth = new AuthController($pdo);  // ← PASS PDO
    $auth->logout();
    header('Location: views/login.php');
    exit;
}

// Tentukan feature berdasarkan action
$feature = $actionToFeature[$action] ?? $action;

// Gunakan fungsi pembatasan yang sudah ada di UserController (kecuali logout)
if ($action !== 'logout' && !$userController->canAccessFeature($role, $feature)) {
    http_response_code(403);
    echo "❌ Anda tidak punya akses ke fitur ini.";
    exit;
}

switch ($action) {
    // ============ Tambah User ============
    case 'create_user':
        $controller = new UserController($pdo);
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $access_level_id = $_POST['access_level_id'] ?? '';
        if ($username && $password && $access_level_id) {
            $result = $controller->createUser($username, $password, $access_level_id);
            if ($result) {
                header('Location: views/manage_account.php?status=success');
                exit;
            } else {
                header('Location: views/manage_account.php?status=error');
                exit;
            }
        } else {
            header('Location: views/manage_account.php?status=error');
            exit;
        }
        break;

    // ============ Hapus User ============
    case 'delete_user':
        $controller = new UserController($pdo);
        $id = $_GET['id'] ?? null;
        if ($id) {
            $result = $controller->deleteUser($id);
            if ($result) {
                header('Location: views/manage_account.php?status=success');
                exit;
            } else {
                header('Location: views/manage_account.php?status=error');
                exit;
            }
        } else {
            header('Location: views/manage_account.php?status=error');
            exit;
        }
        break;

   
    
    // ============ Update User ============
    case 'update_user':
        $controller = new UserController($pdo);
        $id = $_GET['id'] ?? null;
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $access_level_id = $_POST['access_level_id'] ?? '';
        if ($id && $username && $access_level_id) {
            if (empty($password)) {
                $user = $controller->getUser($id);
                $password = $user ? $user['password'] : '';
            }
            $result = $controller->updateUser($id, $username, $password, $access_level_id);
            if ($result) {
                header('Location: views/manage_account.php?status=success');
                exit;
            } else {
                header('Location: views/manage_account.php?status=error');
                exit;
            }
        } else {
            header('Location: views/manage_account.php?status=error');
            exit;
        }
        break;

    // ============ Registrasi User ============
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new UserController($pdo);
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $access_level_id = $_POST['access_level_id'] ?? '';
            if ($username && $password && $access_level_id) {
                $result = $controller->createUser($username, $password, $access_level_id);
                if ($result) {
                    echo "✅ Registrasi berhasil! Silakan login.";
                } else {
                    echo "❌ Registrasi gagal! Username mungkin sudah digunakan.";
                }
            } else {
                echo "❌ Semua field harus diisi.";
            }
        } else {
            echo "Gunakan POST untuk registrasi user";
        }
        break;

    // ============ Upload File ============
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $controller = new FileController($pdo);

            $file     = $_FILES['file'];
            $labelId  = $_POST['label_id'] ?? null;
            $password = $_POST['restricted_password'] ?? null;
            $access_level_id = $_POST['access_level_id'] ?? null;
            if (empty($access_level_id) || !is_numeric($access_level_id)) {
                echo "❌ Upload gagal: Level akses harus dipilih!";
                exit;
            }

            try {
                $controller->upload($file, $labelId, $access_level_id, $password);
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
        $password = $_POST['password'] ?? null;

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

    // ============ Tampilkan Form Rotate Key ============
    case 'rotate_form':
        require __DIR__ . '/views/model.php';
        break;

    // ============ Proses Rotate Key ============
    case 'rotateKey':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new FileController($pdo);
            $newSecret  = $_POST['new_secret'] ?? null;

            if (empty($newSecret)) {
                header("Location: views/model.php?status=error&message=secret_empty");
                exit;
            }

            try {
                $result = $controller->rotateKeyAndUpdateDB($newSecret);
                if ($result) {
                    header("Location: views/model.php?status=success");
                    exit;
                } else {
                    header("Location: views/model.php?status=error&message=process_failed");
                    exit;
                }
            } catch (Exception $e) {
                // Log error untuk debugging
                error_log("Key rotation error in routes.php: " . $e->getMessage());
                header("Location: views/model.php?status=error&message=" . urlencode($e->getMessage()));
                exit;
            }
        } else {
            echo "Gunakan form POST untuk rotate key";
        }
        break;

    // ============ Proses Rotate Key dengan Password ============
    case 'rotateKeyWithPasswords':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new FileController($pdo);
            $newSecret  = $_POST['new_secret'] ?? null;
            $restrictedPasswords = $_POST['restricted_passwords'] ?? [];

            if (empty($newSecret)) {
                header("Location: views/model.php?status=error&message=secret_empty");
                exit;
            }

            try {
                $result = $controller->rotateKeyAndUpdateDBWithPasswords($newSecret, $restrictedPasswords);
                if ($result) {
                    header("Location: views/model.php?status=success");
                    exit;
                } else {
                    header("Location: views/model.php?status=error&message=process_failed");
                    exit;
                }
            } catch (Exception $e) {
                error_log("Key rotation with passwords error: " . $e->getMessage());
                header("Location: views/model.php?status=error&message=" . urlencode($e->getMessage()));
                exit;
            }
        } else {
            echo "Gunakan form POST untuk rotate key";
        }
        break;

    // ============ Log Management ============
    case 'logs':
        // Redirect ke view logs
        header("Location: views/logs.php");
        exit;
        break;

    case 'export_logs':
        if (!$userController->canAccessFeature($role, 'admin')) {
            http_response_code(403);
            echo "❌ Access denied!";
            exit;
        }
        
        $logController = new LogController($pdo);
        $filter = [
            'action' => $_GET['action_filter'] ?? '',
            'status' => $_GET['status_filter'] ?? '',
            'user_id' => $_GET['user_filter'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        $logController->exportToCSV($filter);
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
        echo "<p><a href='?action=rotate_form'>🔑 Rotate Master Key</a></p>";
        break;
}