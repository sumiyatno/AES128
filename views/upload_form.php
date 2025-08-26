<?php require_once __DIR__ . '/../models/Label.php'; ?>
<?php include __DIR__ . '/sidebar.php'; ?> <!-- sudah diperbaiki -->

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
  <link href="sidebar.css" rel="stylesheet"> <!-- sudah diperbaiki -->
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
      <select name="label_id" id="label_id" class="form-select" required>
        <option value="">-- Pilih Label --</option>
        <?php foreach ($labels as $label): ?>
          <option value="<?= $label['id'] ?>">
            <?= htmlspecialchars($label['name']) ?> (<?= $label['access_level'] ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

      
    <div class="mb-3">
      <label for="restricted_password" class="form-label">Password (untuk Restricted)</label>
      <input type="password" name="restricted_password" id="restricted_password" class="form-control">
      <div class="form-text">Kosongkan jika tidak menggunakan password khusus.</div>
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
</body>
</html>
