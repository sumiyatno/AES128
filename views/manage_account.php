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

// Cek apakah user punya akses ke manage_account
if (!$userController->canAccessFeature($role, 'manage_account')) {
    http_response_code(403);
    echo "❌ Anda tidak punya akses ke fitur ini.";
    exit;
}

// Inisialisasi controller
$controller = new UserController($pdo);
$users = $controller->allUsers();

// Untuk edit dan hapus, gunakan parameter GET/POST sesuai kebutuhan
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manajemen Akun</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="sidebar.css" rel="stylesheet">
</head>
<body class="bg-light">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="container mt-5" style="margin-left: 220px;">
  <h2 class="mb-4">Manajemen Akun</h2>
  <div class="card mb-4 p-4">
    <form action="../routes.php?action=create_user" method="POST" class="row g-3">
      <div class="col-md-3">
        <input type="text" name="username" class="form-control" placeholder="Username" required>
      </div>
      <div class="col-md-3">
        <input type="password" name="password" class="form-control" placeholder="Password" required>
      </div>
      <div class="col-md-3">
        <select name="access_level_id" class="form-select" required>
          <option value="">Pilih Role</option>
          <option value="1">Staff</option>
          <option value="2">Kasub</option>
          <option value="3">Kabid</option>
          <option value="4">Super Admin</option>
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-primary">Tambah User</button>
      </div>
    </form>
  </div>

  <table class="table table-bordered table-striped shadow-sm bg-white">
    <thead class="table-dark">
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Role</th>
        <th>Deskripsi</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user): ?>
      <tr>
        <td><?= htmlspecialchars($user['id']) ?></td>
        <td><?= htmlspecialchars($user['username']) ?></td>
        <td><?= htmlspecialchars($user['access_name']) ?> (<?= htmlspecialchars($user['level']) ?>)</td>
        <td><?= htmlspecialchars($user['description']) ?></td>
        <td>
          <!-- Edit User -->
          <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editModal<?= $user['id'] ?>">Edit</button>
          <!-- Delete User -->
          <form action="../routes.php?action=delete_user&id=<?= $user['id'] ?>" method="POST" style="display:inline-block;">
            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Yakin hapus user ini?')">Hapus</button>
          </form>
        </td>
      </tr>
      <!-- Modal Edit User -->
      <div class="modal fade" id="editModal<?= $user['id'] ?>" tabindex="-1" aria-labelledby="editModalLabel<?= $user['id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
          <div class="modal-content">
            <form action="../routes.php?action=update_user&id=<?= $user['id'] ?>" method="POST">
              <div class="modal-header">
                <h5 class="modal-title" id="editModalLabel<?= $user['id'] ?>">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                <div class="mb-3">
                  <label>Username</label>
                  <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="mb-3">
                  <label>Password (isi jika ingin ganti)</label>
                  <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                  <label>Role</label>
                  <select name="access_level_id" class="form-select" required>
                    <option value="1" <?= $user['level']==1?'selected':'' ?>>Staff</option>
                    <option value="2" <?= $user['level']==2?'selected':'' ?>>Kasub</option>
                    <option value="3" <?= $user['level']==3?'selected':'' ?>>Kabid</option>
                    <option value="4" <?= $user['level']==4?'selected':'' ?>>Super Admin</option>
                  </select>
                </div>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success">Simpan Perubahan</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
