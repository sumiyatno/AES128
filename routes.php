<?php
// filepath: d:\website\AES128\routes.php

/**
 * Routes Handler untuk AES128 File Management System
 * Handles all routing logic and API endpoints
 */

// ============ DEPENDENCIES ============
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/FileController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/LabelController.php';
require_once __DIR__ . '/models/File.php';

// ============ SESSION MANAGEMENT ============
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============ MAIN ROUTING LOGIC ============
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        
        // ============ AUTHENTICATION ROUTES ============
        case 'login':
            handleLogin();
            break;
            
        case 'logout':
            handleLogout();
            break;
        
        // ============ FILE MANAGEMENT ROUTES ============
        case 'upload':
            handleUpload();
            break;
            
        case 'download':
            handleDownload();
            break;
        
        // ============ USER MANAGEMENT ROUTES ============
        case 'create_user':
            handleCreateUser();
            break;
            
        case 'update_user':
            handleUpdateUser();
            break;
            
        case 'delete_user':
            handleDeleteUser();
            break;

        // ============ LABEL MANAGEMENT ROUTES (TAMBAHAN BARU) ============
        case 'create_label':
            handleCreateLabel();
            break;
        
        // ============ KEY MANAGEMENT ROUTES ============
        case 'rotateKey':
            handleKeyRotation();
            break;
            
        case 'rotateKeyWithPasswords':
            handleKeyRotationWithPasswords();
            break;
        
        // ============ DISPLAY MODE ROUTES ============
        case 'toggle_filename_lock':
            handleFilenameToggle();
            break;
            
        case 'get_filename_lock_status':
            handleFilenameStatus();
            break;
            
        case 'setup_system_settings':
            handleSetupSystemSettings();
            break;
        
        // ============ LOG MANAGEMENT ROUTES (TAMBAHAN BARU) ============
        case 'logs':
            handleLogs();
            break;

        case 'export_logs':
            handleExportLogs();
            break;

        // ============ API ENDPOINTS ============
        case 'check_file_restriction':
            handleCheckFileRestriction();
            break;
            
        case 'validate_password':
            handleValidatePassword();
            break;
            
        case 'get_file_info':
            handleGetFileInfo();
            break;
        
        // ============ DEFAULT HANDLER ============
        default:
            handleDefault();
            break;
    }
    
} catch (Exception $e) {
    error_log("Routes error: " . $e->getMessage());
    http_response_code(500);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
    } else {
        echo "System error: " . $e->getMessage();
    }
}

// ============ AUTHENTICATION HANDLERS ============

/**
 * Handle user login
 */
function handleLogin() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/login.php');
        exit;
    }
    
    global $pdo;
    $authController = new AuthController($pdo);
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($authController->login($username, $password)) {
        header('Location: views/dashboard.php');
    } else {
        header('Location: views/login.php?error=invalid');
    }
    exit;
}

/**
 * Handle user logout
 */
function handleLogout() {
    session_destroy();
    header('Location: views/login.php');
    exit;
}

// ============ FILE MANAGEMENT HANDLERS ============

/**
 * Handle file upload
 */
function handleUpload() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/upload_form.php?error=invalid_method');
        exit;
    }
    
    global $pdo;
    
    // Enhanced logging
    error_log("=== UPLOAD REQUEST START ===");
    error_log("POST: " . json_encode($_POST, JSON_UNESCAPED_SLASHES));
    error_log("FILES: " . json_encode($_FILES, JSON_UNESCAPED_SLASHES));
    
    try {
        // Validation
        validateUploadRequest();
        
        $controller = new FileController($pdo);
        
        // Extract and validate form data
        $uploadData = extractUploadData();
        
        // Perform upload
        $result = $controller->upload(
            $uploadData['file'],
            $uploadData['label_id'],
            $uploadData['access_level_id'],
            $uploadData['restricted_password']
        );
        
        if ($result) {
            error_log("Upload successful - File ID: $result");
            header('Location: views/dashboard.php?status=upload_success');
        } else {
            throw new Exception('Upload failed - no result returned');
        }
        
    } catch (Exception $e) {
        error_log("Upload error: " . $e->getMessage());
        header('Location: views/upload_form.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

/**
 * Handle file download
 */
function handleDownload() {
    global $pdo;
    
    $fileId = $_GET['id'] ?? '';
    $password = $_POST['password'] ?? $_GET['password'] ?? null;
    
    if (empty($fileId)) {
        http_response_code(400);
        echo "File ID is required";
        exit;
    }
    
    error_log("Download request - ID: $fileId, Has password: " . (!empty($password) ? 'YES' : 'NO'));
    
    try {
        $controller = new FileController($pdo);
        $controller->download($fileId, $password);
        
    } catch (Exception $e) {
        error_log("Download error for file $fileId: " . $e->getMessage());
        handleDownloadError($e);
    }
}

// ============ USER MANAGEMENT HANDLERS ============

/**
 * Handle create user
 */
function handleCreateUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/manage_account.php?error=invalid_method');
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    global $pdo;
    
    try {
        $userController = new UserController($pdo);
        $role = $_SESSION['user_level'] ?? 1;
        
        if (!$userController->canAccessFeature($role, 'manage_account')) {
            throw new Exception('Access denied: You do not have permission to manage accounts');
        }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $accessLevelId = $_POST['access_level_id'] ?? '';
        
        if (empty($username) || empty($password) || empty($accessLevelId)) {
            throw new Exception('All fields are required');
        }
        
        $result = $userController->createUser($username, $password, $accessLevelId);
        
        if ($result) {
            header('Location: views/manage_account.php?success=User created successfully');
        } else {
            throw new Exception('Failed to create user');
        }
        
    } catch (Exception $e) {
        error_log("Create user error: " . $e->getMessage());
        header('Location: views/manage_account.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

/**
 * Handle update user
 */
function handleUpdateUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/manage_account.php?error=invalid_method');
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    global $pdo;
    
    try {
        $userController = new UserController($pdo);
        $role = $_SESSION['user_level'] ?? 1;
        
        if (!$userController->canAccessFeature($role, 'manage_account')) {
            throw new Exception('Access denied: You do not have permission to manage accounts');
        }
        
        $userId = $_GET['id'] ?? '';
        if (empty($userId)) {
            throw new Exception('User ID is required');
        }
        
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $accessLevelId = $_POST['access_level_id'] ?? '';
        
        if (empty($username) || empty($accessLevelId)) {
            throw new Exception('Username and access level are required');
        }
        
        $result = $userController->updateUser($userId, $username, $password, $accessLevelId);
        
        if ($result) {
            header('Location: views/manage_account.php?success=User updated successfully');
        } else {
            throw new Exception('Failed to update user');
        }
        
    } catch (Exception $e) {
        error_log("Update user error: " . $e->getMessage());
        header('Location: views/manage_account.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

/**
 * Handle delete user
 */
function handleDeleteUser() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/manage_account.php?error=invalid_method');
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    global $pdo;
    
    try {
        $userController = new UserController($pdo);
        $role = $_SESSION['user_level'] ?? 1;
        
        if (!$userController->canAccessFeature($role, 'manage_account')) {
            throw new Exception('Access denied: You do not have permission to manage accounts');
        }
        
        $userId = $_GET['id'] ?? '';
        if (empty($userId)) {
            throw new Exception('User ID is required');
        }
        
        if ($userId == $_SESSION['user_id']) {
            throw new Exception('You cannot delete your own account');
        }
        
        $result = $userController->deleteUser($userId);
        
        if ($result) {
            header('Location: views/manage_account.php?success=User deleted successfully');
        } else {
            throw new Exception('Failed to delete user');
        }
        
    } catch (Exception $e) {
        error_log("Delete user error: " . $e->getMessage());
        header('Location: views/manage_account.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}

// ============ KEY MANAGEMENT HANDLERS ============

/**
 * Handle key rotation
 */
function handleKeyRotation() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/model.php?error=invalid_method');
        exit;
    }
    
    global $pdo;
    $controller = new FileController($pdo);
    $newSecret = $_POST['new_secret'] ?? '';
    
    try {
        $result = $controller->rotateKeyAndUpdateDB($newSecret);
        $status = $result ? 'success' : 'error&message=process_failed';
        header("Location: views/model.php?status=$status");
    } catch (Exception $e) {
        header('Location: views/model.php?status=error&message=' . urlencode($e->getMessage()));
    }
    exit;
}

/**
 * Handle key rotation with passwords
 */
function handleKeyRotationWithPasswords() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/model.php?error=invalid_method');
        exit;
    }
    
    global $pdo;
    $controller = new FileController($pdo);
    $newSecret = $_POST['new_secret'] ?? '';
    $restrictedPasswords = $_POST['restricted_passwords'] ?? [];
    
    try {
        $result = $controller->rotateKeyAndUpdateDBWithPasswords($newSecret, $restrictedPasswords);
        $status = $result ? 'success' : 'error&message=process_failed';
        header("Location: views/model.php?status=$status");
    } catch (Exception $e) {
        header('Location: views/model.php?status=error&message=' . urlencode($e->getMessage()));
    }
    exit;
}

// ============ DISPLAY MODE HANDLERS ============

/**
 * Handle filename display toggle
 */
function handleFilenameToggle() {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $userLevel = $_SESSION['user_level'] ?? 1;
    if ($userLevel != 4) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Only superadmin can change display mode']);
        exit;
    }
    
    global $pdo;
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
            echo json_encode(['success' => false, 'message' => 'Failed to update mode']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Handle filename lock status check
 */
function handleFilenameStatus() {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    global $pdo;
    
    try {
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
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * Handle setup system settings
 */
function handleSetupSystemSettings() {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed. Use POST.']);
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $userLevel = $_SESSION['user_level'] ?? 1;
    if ($userLevel != 4) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied: Only superadmin can setup system settings']);
        exit;
    }
    
    global $pdo;
    
    try {
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
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============ LOG MANAGEMENT HANDLERS (TAMBAHAN BARU) ============

/**
 * Handle logs page access
 */
function handleLogs() {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    // Check authorization
    global $pdo;
    $userController = new UserController($pdo);
    $role = $_SESSION['user_level'] ?? 1;
    
    if (!$userController->canAccessFeature($role, 'logs')) {
        http_response_code(403);
        die('Access denied! Only Super Admin can view logs.');
    }
    
    // Redirect to logs page
    header('Location: views/logs.php');
    exit;
}

/**
 * Handle logs export
 */
function handleExportLogs() {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    // Check authorization
    global $pdo;
    $userController = new UserController($pdo);
    $role = $_SESSION['user_level'] ?? 1;
    
    if (!$userController->canAccessFeature($role, 'logs')) {
        http_response_code(403);
        die('Access denied! Only Super Admin can export logs.');
    }
    
    try {
        // Load LogController
        require_once __DIR__ . '/controllers/LogController.php';
        $logController = new LogController($pdo);
        
        // Get filter parameters
        $filter = [
            'action' => $_GET['action_filter'] ?? '',
            'status' => $_GET['status_filter'] ?? '',
            'user_id' => $_GET['user_filter'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? ''
        ];
        
        // Export logs as CSV
        $csv = $logController->exportToCSV($filter);
        
        // Set headers for CSV download
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        echo $csv;
        
    } catch (Exception $e) {
        error_log("Export logs error: " . $e->getMessage());
        header('Location: views/logs.php?error=' . urlencode('Export failed: ' . $e->getMessage()));
    }
    exit;
}

// ============ API ENDPOINTS ============

/**
 * API: Check file restriction status
 */
function handleCheckFileRestriction() {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $fileId = $_GET['file_id'] ?? $_POST['file_id'] ?? '';
    if (empty($fileId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID required']);
        exit;
    }
    
    global $pdo;
    
    try {
        $fileModel = new FileModel($pdo);
        $file = $fileModel->find($fileId);
        
        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        $isRestricted = !empty($file['restricted_password_hash']);
        
        echo json_encode([
            'success' => true,
            'file_id' => $fileId,
            'is_restricted' => $isRestricted,
            'requires_password' => $isRestricted
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * API: Validate restricted password
 */
function handleValidatePassword() {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $fileId = $_POST['file_id'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($fileId) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID and password required']);
        exit;
    }
    
    global $pdo;
    
    try {
        $fileModel = new FileModel($pdo);
        $file = $fileModel->find($fileId);
        
        if (!$file) {
            echo json_encode(['success' => false, 'message' => 'File not found']);
            exit;
        }
        
        $isValid = false;
        if (!empty($file['restricted_password_hash'])) {
            $isValid = password_verify($password, $file['restricted_password_hash']);
        }
        
        echo json_encode([
            'success' => $isValid,
            'message' => $isValid ? 'Password is valid' : 'Invalid password',
            'file_id' => $fileId
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

/**
 * API: Get basic file information
 */
function handleGetFileInfo() {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required']);
        exit;
    }
    
    $fileId = $_GET['file_id'] ?? '';
    if (empty($fileId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'File ID required']);
        exit;
    }
    
    global $pdo;
    
    try {
        $fileModel = new FileModel($pdo);
        $file = $fileModel->find($fileId);
        
        if ($file) {
            echo json_encode([
                'success' => true,
                'file_info' => [
                    'id' => $file['id'],
                    'original_filename' => base64_decode($file['original_filename']),
                    'mime_type' => base64_decode($file['mime_type']),
                    'is_restricted' => !empty($file['restricted_password_hash']),
                    'uploaded_at' => $file['uploaded_at'],
                    'download_count' => $file['download_count']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'File not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// ============ DEFAULT HANDLER ============

/**
 * Handle default/unknown actions
 */
function handleDefault() {
    http_response_code(404);
    
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Action not found']);
    } else {
        echo "Action not found";
    }
}

// ============ HELPER FUNCTIONS ============

/**
 * Check if request is AJAX
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Validate upload request
 */
function validateUploadRequest() {
    if (!isset($_FILES['file']) || empty($_FILES['file']['name'])) {
        throw new Exception('No file uploaded');
    }
    
    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => 'File too large (ini_size)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form_size)',
            UPLOAD_ERR_PARTIAL => 'File partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION => 'Upload blocked by extension'
        ];
        throw new Exception($errorMessages[$_FILES['file']['error']] ?? 'Unknown upload error');
    }
    
    $requiredFields = ['label_id', 'access_level_id'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
}

/**
 * Extract and validate upload data
 */
function extractUploadData() {
    global $pdo;
    
    $labelId = (int)$_POST['label_id'];
    $restrictedPassword = !empty($_POST['restricted_password']) ? trim($_POST['restricted_password']) : null;
    
    // FIXED: Cek apakah label adalah "restricted"
    $labelModel = new Label($pdo);
    $label = $labelModel->find($labelId);
    
    if (!$label) {
        throw new Exception('Invalid label selected');
    }
    
    $isRestrictedLabel = ($label['access_level'] === 'restricted');
    
    // FIXED: Jika label restricted, password wajib ada
    if ($isRestrictedLabel && empty($restrictedPassword)) {
        throw new Exception('Password is required for restricted label files');
    }
    
    // FIXED: Jika label bukan restricted, password tidak boleh ada
    if (!$isRestrictedLabel && !empty($restrictedPassword)) {
        throw new Exception('Password only allowed for restricted label files');
    }
    
    return [
        'file' => $_FILES['file'],
        'label_id' => $labelId,
        'access_level_id' => (int)$_POST['access_level_id'],
        'is_restricted' => $isRestrictedLabel,
        'restricted_password' => $isRestrictedLabel ? $restrictedPassword : null,
        'label' => $label
    ];
}

/**
 * Handle download errors
 */
function handleDownloadError($exception) {
    if (isAjaxRequest()) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Download error: ' . $exception->getMessage()]);
    } else {
        http_response_code(500);
        echo "Download error: " . $exception->getMessage();
    }
    exit;
}
/**
 * Handle create label
 */
function handleCreateLabel() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Location: views/create_label.php?error=invalid_method');
        exit;
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: views/login.php');
        exit;
    }
    
    global $pdo;
    
    try {
        $userController = new UserController($pdo);
        $role = $_SESSION['user_level'] ?? 1;
        
        if (!$userController->canAccessFeature($role, 'create_label')) {
            throw new Exception('Access denied: You do not have permission to create labels');
        }
        
        // Validate input
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $accessLevel = $_POST['access_level'] ?? '';
        
        if (empty($name) || empty($accessLevel)) {
            throw new Exception('Name and access level are required');
        }
        
        // Validate access level
        if (!in_array($accessLevel, ['public', 'restricted', 'private'])) {
            throw new Exception('Invalid access level');
        }
        
        // Use LabelController method yang ada
        $labelController = new LabelController($pdo);
        
        // Call create method yang sudah ada di LabelController
        $result = $labelController->create($name, $description, $accessLevel);
        
        if ($result) {
            // MODIFIED: Redirect ke dashboard setelah berhasil create label
            header('Location: views/dashboard.php?status=label_created&message=' . urlencode('Label "' . $name . '" created successfully'));
        } else {
            throw new Exception('Failed to create label');
        }
        
    } catch (Exception $e) {
        error_log("Create label error: " . $e->getMessage());
        // Error tetap redirect ke create_label.php untuk user experience yang baik
        header('Location: views/create_label.php?error=' . urlencode($e->getMessage()));
    }
    exit;
}
?>