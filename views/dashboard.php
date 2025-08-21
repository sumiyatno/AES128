<?php require_once __DIR__ . '/../controllers/FileController.php'; ?>
<?php
$controller = new FileController($pdo);
$files = $controller->dashboard();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard File</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
  <h2 class="mb-4">Dashboard File</h2>
  <a href="upload_form.php" class="btn btn-success mb-3">Upload File</a>
  <a href="create_label.php" class="btn btn-info mb-3">Buat Label</a>

  <table class="table table-bordered table-striped shadow-sm bg-white">
    <thead class="table-dark">
      <tr>
        <th>Nama File</th>
        <th>Label</th>
        <th>Tanggal Upload</th>
        <th>Download</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($files as $file): ?>
      <tr>
        <td><?= htmlspecialchars($file['decrypted_name']) ?></td>
        <td><?= htmlspecialchars($file['label_id']) ?></td>
        <td><?= htmlspecialchars($file['uploaded_at']) ?></td>
        <td>
          <?php if (!empty($file['restricted_password_hash'])): ?>
            <form action="../routes.php?action=download&id=<?= $file['id'] ?>" method="POST" class="d-flex">
              <input type="password" name="password" class="form-control form-control-sm me-2" placeholder="Password">
              <button type="submit" class="btn btn-sm btn-danger">Download</button>
            </form>
          <?php else: ?>
            <a href="../routes.php?action=download&id=<?= $file['id'] ?>" class="btn btn-sm btn-primary">Download</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
</body>
</html>
