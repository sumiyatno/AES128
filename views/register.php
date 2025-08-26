<?php
require_once __DIR__ . '/../controllers/UserController.php';

$controller = new UserController($pdo);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $access_level_id = $_POST['access_level_id'] ?? '';
    if ($username && $password && $access_level_id) {
        $result = $controller->createUser($username, $password, $access_level_id);
        if ($result) {
            $message = '<div class="alert alert-success">Registrasi berhasil! Silakan login.</div>';
        } else {
            $message = '<div class="alert alert-danger">Registrasi gagal! Username mungkin sudah digunakan.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Semua field harus diisi.</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Registrasi User</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="sidebar.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="container mt-5" style="max-width: 500px;">
  <h2 class="mb-4">Registrasi User Baru</h2>
  <?= $message ?>
  <form action="" method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="username" class="form-label">Username</label>
      <input type="text" class="form-control" id="username" name="username" required>
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" class="form-control" id="password" name="password" required>
    </div>
    <div class="mb-3">
      <label for="access_level_id" class="form-label">Role</label>
      <select name="access_level_id" id="access_level_id" class="form-select" required>
        <option value="">Pilih Role</option>
        <option value="1">Staff</option>
        <option value="2">Kasub</option>
        <option value="3">Kabid</option>
        <option value="4">Super Admin</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Registrasi</button>
    <a href="login.php" class="btn btn-secondary">Login</a>
  </form>
</div>
</body>
</html>
