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

// Cek apakah user punya akses ke upload
if (!$userController->canAccessFeature($role, 'upload')) {
    http_response_code(403);
    echo "❌ Anda tidak punya akses ke fitur ini.";
    exit;
}
?>

<?php require_once __DIR__ . '/../models/Label.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?>

<?php
$labelModel = new Label($pdo);
$labels = $labelModel->all();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Upload File</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="sidebar.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h2 class="mb-4">Upload File</h2>

  <form action="../routes.php?action=upload" method="POST" enctype="multipart/form-data" class="card p-4 shadow-sm">
    <div class="mb-3">
      <label for="file" class="form-label">Pilih File</label>
      <input type="file" class="form-control" id="file" name="file" required>
    </div>

    <div class="mb-3">
      <label for="label_id" class="form-label">Pilih Label</label>
      <select name="label_id" id="label_id" class="form-select" required onchange="togglePasswordField()">
        <option value="">-- Pilih Label --</option>
        <?php foreach ($labels as $label): ?>
          <option value="<?= $label['id'] ?>" data-access-level="<?= $label['access_level'] ?>">
            <?= htmlspecialchars($label['name']) ?> (<?= $label['access_level'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- FIXED: Password field hanya muncul jika label = restricted -->
    <div class="mb-3" id="password-field" style="display: none;">
      <label for="restricted_password" class="form-label">🔒 Password untuk File Restricted</label>
      <input type="password" name="restricted_password" id="restricted_password" class="form-control">
      <div class="form-text">
        <strong>Label "Restricted" dipilih - Password wajib diisi!</strong><br>
        Password ini akan dienkripsi dengan Argon2 dan diperlukan untuk download.
      </div>
    </div>

    <div class="mb-3">
      <label for="access_level_id" class="form-label">Akses File (Role)</label>
      <select name="access_level_id" id="access_level_id" class="form-select" required>
        <option value="">-- Pilih Level Akses --</option>
        <option value="1">Staff/General</option>
        <option value="2">Kepala Sub Bidang</option>
        <option value="3">Kepala Bidang</option>
        <option value="4">Super Admin</option>
      </select>
      <div class="form-text">Pilih level akses file sesuai role yang boleh mengakses.</div>
    </div>

    <button type="submit" class="btn btn-primary">Upload</button>
    <a href="dashboard.php" class="btn btn-secondary">Kembali</a>
  </form>
</div>

<script>
function togglePasswordField() {
    const labelSelect = document.getElementById('label_id');
    const passwordField = document.getElementById('password-field');
    const passwordInput = document.getElementById('restricted_password');
    
    const selectedOption = labelSelect.options[labelSelect.selectedIndex];
    const accessLevel = selectedOption.getAttribute('data-access-level');
    
    if (accessLevel === 'restricted') {
        passwordField.style.display = 'block';
        passwordInput.required = true;
    } else {
        passwordField.style.display = 'none';
        passwordInput.required = false;
        passwordInput.value = ''; // Clear password jika tidak restricted
    }
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    const labelSelect = document.getElementById('label_id');
    const passwordInput = document.getElementById('restricted_password');
    
    const selectedOption = labelSelect.options[labelSelect.selectedIndex];
    const accessLevel = selectedOption.getAttribute('data-access-level');
    
    if (accessLevel === 'restricted' && !passwordInput.value.trim()) {
        e.preventDefault();
        alert('Password wajib diisi untuk file dengan label Restricted!');
        passwordInput.focus();
    }
});
</script>
</body>
</html>
