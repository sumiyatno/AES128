<?php
// filepath: d:\website\AES128\routes.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/FileController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/LabelController.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    
    // ============ EXISTING CASES ============
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $authController = new AuthController($pdo);
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            if ($authController->login($username, $password)) {
                header('Location: views/dashboard.php');
                exit;
            } else {
                header('Location: views/login.php?error=invalid');
                exit;
            }
        }
        break;

    case 'logout':
        session_destroy();
        header('Location: views/login.php');
        exit;
        break;

    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new FileController($pdo);
            
            try {
                $result = $controller->upload(
                    $_FILES['file'],
                    $_POST['label_id'],
                    $_POST['access_level_id'],
                    $_POST['restricted_password'] ?? null
                );
                
                if ($result) {
                    header('Location: views/dashboard.php?status=upload_success');
                } else {
                    header('Location: views/upload_form.php?error=upload_failed');
                }
            } catch (Exception $e) {
                header('Location: views/upload_form.php?error=' . urlencode($e->getMessage()));
            }
            exit;
        }
        break;

    case 'download':
        $controller = new FileController($pdo);
        $id = $_GET['id'] ?? '';
        $password = $_POST['password'] ?? $_GET['password'] ?? null;
        
        try {
            $controller->download($id, $password);
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        }
        break;

    case 'rotateKey':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new FileController($pdo);
            $newSecret = $_POST['new_secret'] ?? '';
            
            try {
                $result = $controller->rotateKeyAndUpdateDB($newSecret);
                if ($result) {
                    header('Location: views/model.php?status=success');
                } else {
                    header('Location: views/model.php?status=error&message=process_failed');
                }
            } catch (Exception $e) {
                header('Location: views/model.php?status=error&message=' . urlencode($e->getMessage()));
            }
            exit;
        }
        break;

    case 'rotateKeyWithPasswords':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $controller = new FileController($pdo);
            $newSecret = $_POST['new_secret'] ?? '';
            $restrictedPasswords = $_POST['restricted_passwords'] ?? [];
            
            try {
                $result = $controller->rotateKeyAndUpdateDBWithPasswords($newSecret, $restrictedPasswords);
                if ($result) {
                    header('Location: views/model.php?status=success');
                } else {
                    header('Location: views/model.php?status=error&message=process_failed');
                }
            } catch (Exception $e) {
                header('Location: views/model.php?status=error&message=' . urlencode($e->getMessage()));
            }
            exit;
        }
        break;

    // ============ TAMBAHAN: System Lock Toggle ============
    case 'toggle_filename_lock':
        // Set header JSON response
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Cek authentication
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Authentication required'
                ]);
                exit;
            }
            
            // Cek superadmin role
            $userLevel = $_SESSION['user_level'] ?? 1;
            if ($userLevel != 4) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Access denied: Only superadmin can change display mode'
                ]);
                exit;
            }
            
            $controller = new FileController($pdo);
            $mode = $_POST['mode'] ?? 'normal';
            
            try {
                $result = $controller->setFilenameDisplayMode($mode, $_SESSION['user_id']);
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'mode' => $mode,
                        'message' => $mode === 'encrypted' ? 'Filename lock activated' : 'Filename lock deactivated'
                    ]);
                } else {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Failed to update mode'
                    ]);
                }
            } catch (Exception $e) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false, 
                'message' => 'Method not allowed. Use POST.'
            ]);
        }
        exit;
        break;

    // ============ TAMBAHAN: Get Current Lock Status ============
    case 'get_filename_lock_status':
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode([
                'success' => false, 
                'message' => 'Authentication required'
            ]);
            exit;
        }
        
        try {
            // Cek apakah table system_settings ada
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'system_settings'");
            $stmt->execute();
            $tableExists = $stmt->fetch();
            
            if ($tableExists) {
                $stmt = $pdo->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ?');
                $stmt->execute(['filename_display_mode']);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $mode = $result ? $result['setting_value'] : 'normal';
                
                echo json_encode([
                    'success' => true,
                    'mode' => $mode,
                    'is_locked' => ($mode === 'encrypted')
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'mode' => 'normal',
                    'is_locked' => false,
                    'message' => 'System settings table not found'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'success' => false, 
                'message' => $e->getMessage()
            ]);
        }
        exit;
        break;

    // ============ TAMBAHAN: Setup System Settings ============
    case 'setup_system_settings':
        header('Content-Type: application/json');
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Cek authentication
            if (!isset($_SESSION['user_id'])) {
                http_response_code(401);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Authentication required'
                ]);
                exit;
            }
            
            // Cek superadmin role
            $userLevel = $_SESSION['user_level'] ?? 1;
            if ($userLevel != 4) {
                http_response_code(403);
                echo json_encode([
                    'success' => false, 
                    'message' => 'Access denied: Only superadmin can setup system settings'
                ]);
                exit;
            }
            
            try {
                // Create system_settings table
                $sql = "
                    CREATE TABLE IF NOT EXISTS system_settings (
                        id INT PRIMARY KEY AUTO_INCREMENT,
                        setting_key VARCHAR(50) UNIQUE NOT NULL,
                        setting_value TEXT,
                        updated_by INT NULL,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_setting_key (setting_key),
                        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL ON UPDATE CASCADE
                    )
                ";
                $pdo->exec($sql);
                
                // Insert default setting
                $stmt = $pdo->prepare("
                    INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                    VALUES ('filename_display_mode', 'normal', ?)
                    ON DUPLICATE KEY UPDATE setting_value = setting_value
                ");
                $stmt->execute([$_SESSION['user_id']]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'System settings table created successfully'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => $e->getMessage()
                ]);
            }
        } else {
            http_response_code(405);
            echo json_encode([
                'success' => false, 
                'message' => 'Method not allowed. Use POST.'
            ]);
        }
        exit;
        break;

    // ============ DEFAULT CASE ============
    default:
        http_response_code(404);
        echo "Action not found";
        break;
}
?>