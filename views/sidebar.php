<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/AuthController.php';

$auth = new AuthController();
$userController = new UserController($pdo);

// Ambil level dari session (pastikan sesuai dengan AuthController)
$role = isset($_SESSION['user_level']) ? (int)$_SESSION['user_level'] : null;

function showMenu($feature, $label, $url, $icon, $role, $userController) {
    if ($userController->canAccessFeature($role, $feature)) {
        echo "<a href='$url'>$icon $label</a>\n";
    }
}
?>

<!-- Hover area tipis di kiri -->
<div class="hover-area"></div>

<!-- Sidebar -->
<div class="sidebar">
    <h5 class="text-center mb-4">Menu</h5>
    
    <?php showMenu('upload', 'Upload File', 'upload_form.php', '📂', $role, $userController); ?>
    <?php showMenu('create_label', 'Buat Label', 'create_label.php', '🏷️', $role, $userController); ?>
    <?php showMenu('master_key', 'Update Key', 'model.php', '🔑', $role, $userController); ?>
    <?php showMenu('dashboard', 'Dashboard', 'dashboard.php', '📊', $role, $userController); ?>
    <?php showMenu('manage_account', 'Kelola Akun', 'manage_account.php', '👤', $role, $userController); ?>
    <?php showMenu('register', 'Register User', 'register.php', '📝', $role, $userController); ?>
    <?php showMenu('logs', 'Activity Logs', 'logs.php', '📋', $role, $userController); ?>
    <?php showMenu('file_manager', 'File Manager', 'my_files.php', '📁', $role, $userController); ?>

    <div class="mt-4">
        <form action="../routes.php?action=logout" method="POST">
            <button type="submit" class="btn btn-danger w-100">Logout</button>
        </form>
    </div>
</div>