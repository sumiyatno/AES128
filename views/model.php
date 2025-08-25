<?php include __DIR__ . '/sidebar.php'; ?> <!-- sudah diperbaiki -->

<?php
// File: views/model.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Rotate Master Key</title>
    <link href="sidebar.css" rel="stylesheet"> <!-- sudah diperbaiki -->
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 22px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            font-size: 14px;
            border-radius: 5px;
            border: 1px solid #ddd;
        }
        button {
            margin-top: 20px;
            padding: 12px 20px;
            background: #2d89ef;
            border: none;
            color: #fff;
            font-size: 14px;
            border-radius: 5px;
            cursor: pointer;
        }
        button:hover {
            background: #1b5cb8;
        }
        .alert {
            margin-top: 15px;
            padding: 12px;
            border-radius: 5px;
        }
        .success {
            background: #e0f7e9;
            color: #2d7a3f;
        }
        .error {
            background: #fdecea;
            color: #b32a2a;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Rotate Master Key</h1>
    <form method="post" action="../controllers/FileController.php?action=rotateKey">
        <label for="new_secret">Masukkan Master Secret Baru:</label>
        <input type="text" id="new_secret" name="new_secret" required>
        <button type="submit">Rotate Key</button>
    </form>

    <?php if (isset($_GET['status'])): ?>
        <?php if ($_GET['status'] === 'success'): ?>
            <div class="alert success">✅ Master Key berhasil di-rotate dan database diperbarui!</div>
        <?php elseif ($_GET['status'] === 'error'): ?>
            <div class="alert error">❌ Gagal melakukan rotasi key!</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
