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

// Cek apakah user punya akses ke create_label
if (!$userController->canAccessFeature($role, 'create_label')) {
    http_response_code(403);
    echo "❌ Anda tidak punya akses ke fitur ini.";
    exit;
}
?>

<?php include __DIR__ . '/sidebar.php'; ?> <!-- sudah diperbaiki -->



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Buat Label</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="sidebar.css" rel="stylesheet"> <!-- sudah diperbaiki -->
</head>
<body class="bg-light">
<div class="container mt-5">
  <h2 class="mb-4">Buat Label Baru</h2>

  <form action="../routes.php?action=create_label" method="POST" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="name" class="form-label">Nama Label</label>
      <input type="text" class="form-control" id="name" name="name" required>
    </div>

    <div class="mb-3">
      <label for="description" class="form-label">Deskripsi</label>
      <textarea class="form-control" id="description" name="description"></textarea>
    </div>

    <div class="mb-3">
      <label for="access_level" class="form-label">Tipe Akses</label>
      <select name="access_level" id="access_level" class="form-select" required>
        <option value="public">Public</option>
        <option value="restricted">Restricted</option>
        <option value="private">Private</option>
      </select>
    </div>

    <button type="submit" class="btn btn-primary">Simpan</button>
    <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
  </form>
</div>
</body>
</html>
