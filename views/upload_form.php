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
      <label for="file_description" class="form-label">📝 Deskripsi File</label>
      <textarea 
        class="form-control" 
        id="file_description" 
        name="file_description" 
        rows="4" 
        placeholder="Contoh:&#10;Nama File: Laporan Keuangan Q3&#10;Deskripsi: Laporan keuangan triwulan ketiga tahun 2025, berisi analisis pendapatan dan pengeluaran departemen..."
        style="resize: vertical;"
      ></textarea>
      <div class="form-text">
        <strong>Format yang disarankan:</strong><br>
        • Baris 1: <strong>Nama File:</strong> [nama yang mudah diingat]<br>
        • Baris 2+: <strong>Deskripsi:</strong> [detail file, tujuan, catatan, dll]<br>
        • Field ini opsional, boleh dikosongkan
      </div>
    </div>
    
    <div class="mb-3">
      <label for="access_level_id" class="form-label">Akses Level</label>
      <select name="access_level_id" id="access_level_id" class="form-select" required>
        <option value="">-- Pilih Level Akses --</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
      </select>
      <div class="form-text">Pilih level akses file sesuai yang boleh mengakses.</div>
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
document.getElementById('file').addEventListener('change', function() {
    const fileInput = this;
    const descriptionTextarea = document.getElementById('file_description');
    
    if (fileInput.files.length > 0) {
        const fileName = fileInput.files[0].name;
        const fileNameWithoutExt = fileName.replace(/\.[^/.]+$/, ""); // Remove extension
        
        // Auto-suggest format jika textarea kosong
        if (!descriptionTextarea.value.trim()) {
            descriptionTextarea.value = `Nama File: ${fileNameWithoutExt}\nDeskripsi: `;
            
            // Set cursor ke akhir untuk user mulai mengetik deskripsi
            setTimeout(() => {
                descriptionTextarea.focus();
                descriptionTextarea.setSelectionRange(descriptionTextarea.value.length, descriptionTextarea.value.length);
            }, 100);
        }
    }
});

// Character counter untuk deskripsi
document.getElementById('file_description').addEventListener('input', function() {
    const maxLength = 1000;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    // Buat atau update counter element
    let counter = document.getElementById('description-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'description-counter';
        counter.style.cssText = 'font-size: 12px; margin-top: 5px; text-align: right;';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/1000 karakter`;
    counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
    
    // Limit input
    if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>
</body>
</html>
