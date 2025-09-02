<?php
// filepath: d:\website\AES128\views\dashboard.php

require_once __DIR__ . '/../controllers/FileController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$controller = new FileController($pdo);
$userController = new UserController($pdo);
$role = $_SESSION['user_level'] ?? 1;

// Handle search dan filter
$searchTerm = $_GET['search'] ?? '';
$labelFilter = $_GET['label_filter'] ?? '';

// TAMBAHAN: Handle toggle encrypted untuk superadmin (override global lock)
$forceShowEncrypted = isset($_GET['show_encrypted']) && $_GET['show_encrypted'] === '1' && $role == 4;

// Gunakan search/filter jika ada parameter, atau dashboard biasa
if (!empty($searchTerm) || !empty($labelFilter)) {
    $files = $controller->searchAndFilter($searchTerm, $labelFilter, $forceShowEncrypted);
} else {
    $files = $controller->dashboard($forceShowEncrypted);
}

// Ambil data untuk dropdown filter
$labels = $controller->getAllLabels();
$stats = $controller->getDashboardStats();

// TAMBAHAN: Cek current lock status
$globalLocked = !empty($files) ? $files[0]['is_locked'] ?? false : false;
$globalMode = !empty($files) ? $files[0]['global_mode'] ?? 'normal' : 'normal';
?>

<?php include 'sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AES File Manager</title>
    <link href="sidebar.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 25px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        
        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }
        
        /* Search & Filter */
        .search-filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .search-filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr auto auto;
            gap: 15px;
            align-items: end;
        }
        
        .search-group {
            display: flex;
            flex-direction: column;
        }
        
        .search-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #333;
        }
        
        .search-group input,
        .search-group select {
            padding: 12px;
            border: 2px solid #e1e5e9;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .search-group input:focus,
        .search-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        /* Files table */
        .files-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .files-table th,
        .files-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e1e5e9;
        }
        
        .files-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .files-table tr:hover {
            background: #f5f5f5;
        }
        
        .file-name {
            font-weight: 600;
            color: #333;
        }
        
        .label-badge {
            background: #e9ecef;
            color: #ffffffff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .no-files {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }
        
        .no-files h3 {
            margin-bottom: 10px;
        }
        
        /* TAMBAHAN: Toggle switch untuk superadmin */
        .admin-controls {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: <?= $role == 4 ? 'block' : 'none' ?>;
        }
        
        .toggle-container {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: #667eea;
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
        
        .admin-info {
            background: #e8f4f8;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
            color: #0c5460;
            margin-top: 10px;
        }
        
        .encrypted-badge {
            background: #6f42c1;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 5px;
        }
        
        /* TAMBAHAN: Lock toggle styles */
        .lock-controls {
            background: <?= $globalLocked ? '#f8d7da' : '#d4edda' ?>;
            border: 1px solid <?= $globalLocked ? '#f5c6cb' : '#c3e6cb' ?>;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: <?= $role == 4 ? 'block' : ($globalLocked ? 'block' : 'none') ?>;
        }
        
        .lock-status {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        
        .lock-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .lock-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .lock-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .lock-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: <?= $globalLocked ? '#dc3545' : '#28a745' ?>;
            transition: .4s;
            border-radius: 34px;
        }
        
        .lock-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: <?= $globalLocked ? '30px' : '4px' ?>;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .lock-badge {
            background: <?= $globalLocked ? '#dc3545' : '#28a745' ?>;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .warning-text {
            color: #721c24;
            font-weight: bold;
        }
        
        .normal-text {
            color: #155724;
            font-weight: bold;
        }
        
        /* Admin override controls */
        .admin-override {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: <?= $role == 4 ? 'block' : 'none' ?>;
        }
        
        .override-controls {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .search-filter-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📂 File Dashboard</h1>
        
        <!-- TAMBAHAN: System Lock Controls -->
        <div class="lock-controls">
            <div class="lock-status">
                <div class="lock-info">
                    <strong>🔒 System Filename Lock:</strong>
                    <span class="lock-badge">
                        <?= $globalLocked ? '🔒 LOCKED' : '🔓 UNLOCKED' ?>
                    </span>
                    <span class="<?= $globalLocked ? 'warning-text' : 'normal-text' ?>">
                        <?= $globalLocked ? 'All filenames are encrypted for all users' : 'Filenames displayed normally' ?>
                    </span>
                </div>
                
                <?php if ($role == 4): ?>
                <div class="lock-controls-admin">
                    <label class="lock-switch">
                        <input type="checkbox" id="globalLockToggle" <?= $globalLocked ? 'checked' : '' ?>>
                        <span class="lock-slider"></span>
                    </label>
                    <span style="font-size: 14px; color: #6c757d;">
                        (Superadmin Control)
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($globalLocked && $role != 4): ?>
            <div style="margin-top: 15px; padding: 10px; background: #f8d7da; border-radius: 5px; color: #721c24;">
                ⚠️ <strong>Notice:</strong> Administrator has enabled filename encryption. All file names are currently hidden for security purposes.
            </div>
            <?php endif; ?>
        </div>
        
        <!-- TAMBAHAN: Admin Override Controls (hanya untuk superadmin) -->
        <?php if ($role == 4): ?>
        <div class="admin-override">
            <div class="override-controls">
                <strong>🔧 Admin Override:</strong>
                
                <a href="dashboard.php" class="btn btn-secondary">
                    📂 Respect Global Lock
                </a>
                
                <a href="dashboard.php?show_encrypted=1" class="btn btn-warning">
                    🔐 Force Encrypted View
                </a>
                
                <?php if ($forceShowEncrypted): ?>
                    <span class="lock-badge" style="background: #ffc107; color: #212529;">
                        ADMIN OVERRIDE ACTIVE
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?= number_format($stats['total_files']) ?></h3>
                <p>Total Files</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($stats['total_downloads']) ?></h3>
                <p>Total Downloads</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($stats['recent_uploads']) ?></h3>
                <p>Recent Uploads (7 days)</p>
            </div>
            <div class="stat-card">
                <h3><?= count($stats['files_by_label']) ?></h3>
                <p>Active Labels</p>
            </div>
        </div>
        
        <!-- Search & Filter Section -->
        <div class="search-filter-section">
            <form method="GET" action="" id="searchForm">
                <!-- TAMBAHAN: Hidden field untuk maintain override state -->
                <?php if ($role == 4 && $forceShowEncrypted): ?>
                    <input type="hidden" name="show_encrypted" value="1">
                <?php endif; ?>
                
                <div class="search-filter-grid">
                    <div class="search-group">
                        <label for="search">🔍 Search Files:</label>
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            placeholder="Enter filename..." 
                            value="<?= htmlspecialchars($searchTerm) ?>"
                        >
                    </div>
                    
                    <div class="search-group">
                        <label for="label_filter">🏷️ Label:</label>
                        <select name="label_filter" id="label_filter">
                            <option value="">All Labels</option>
                            <?php foreach ($labels as $label): ?>
                                <option value="<?= $label['id'] ?>" <?= $labelFilter == $label['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Search</button>
                    <a href="dashboard.php" class="btn btn-secondary">Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Files Table -->
        <?php if (!empty($files)): ?>
            <table class="files-table">
                <thead>
                    <tr>
                        <th>
                            File Name 
                            <?php if ($globalLocked): ?>
                                <span class="lock-badge">🔒 ENCRYPTED</span>
                            <?php endif; ?>
                            <?php if ($role == 4 && $forceShowEncrypted): ?>
                                <span class="lock-badge" style="background: #ffc107; color: #212529;">OVERRIDE</span>
                            <?php endif; ?>
                        </th>
                        <th>Label</th>
                        <th>Upload Date</th>
                        <th>Downloads</th>
                        <th>Access</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <?php 
                        // DEBUG: Check restricted status
                        $isRestricted = !empty($file['restricted_password_hash']) || !empty($file['is_restricted_file']);
                        error_log("File ID {$file['id']}: restricted_password_hash = " . ($file['restricted_password_hash'] ?? 'NULL') . ", is_restricted = " . ($isRestricted ? 'YES' : 'NO'));
                        ?>
                        <tr>
                            <td>
                                <div class="file-name">
                                    <?= htmlspecialchars($file['decrypted_name']) ?>
                                    <?php if ($isRestricted): ?>
                                        <span class="restricted-badge">🔒 RESTRICTED</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="label-badge" style="background-color: <?= htmlspecialchars($file['label_color'] ?? '#6c757d') ?>">
                                    <?= htmlspecialchars($file['label_name'] ?? 'No Label') ?>
                                </span>
                            </td>
                            <td>
                                <?= date('M d, Y H:i', strtotime($file['uploaded_at'])) ?>
                            </td>
                            <td>
                                <?= number_format($file['download_count'] ?? 0) ?>
                            </td>
                            <td>
                                <!-- ACCESS COLUMN - FIXED LOGIC -->
                                <?php if ($isRestricted): ?>
                                    <div class="password-group">
                                        <input 
                                            type="password" 
                                            class="password-input" 
                                            id="password_<?= $file['id'] ?>"
                                            placeholder="Enter password..."
                                            style="padding: 4px 8px; border: 2px solid #dc3545; border-radius: 4px; width: 100px; font-size: 11px;"
                                        >
                                        <button 
                                            type="button" 
                                            class="toggle-password-btn"
                                            onclick="togglePassword('password_<?= $file['id'] ?>')"
                                            style="margin-left: 3px; padding: 4px 6px; background: #6c757d; color: white; border: none; border-radius: 3px; font-size: 10px; cursor: pointer;"
                                            title="Show/Hide Password"
                                        >
                                            👁️
                                        </button>
                                    </div>
                                    <div style="font-size: 9px; color: #dc3545; margin-top: 1px; font-weight: bold;">
                                        🔒 Password Required
                                    </div>
                                <?php else: ?>
                                    <span style="color: #28a745; font-size: 11px; font-weight: bold;">📂 Public</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <!-- ACTION COLUMN - FIXED LOGIC -->
                                <?php if ($isRestricted): ?>
                                    <button 
                                        onclick="downloadWithPassword(<?= $file['id'] ?>)" 
                                        class="download-btn restricted-download-btn"
                                        id="download_btn_<?= $file['id'] ?>"
                                        style="background: #dc3545; border: 2px solid #dc3545; color: white; padding: 6px 12px; border-radius: 4px; font-size: 11px; cursor: pointer; font-weight: bold;"
                                    >
                                        🔒 Download
                                    </button>
                                <?php else: ?>
                                    <a href="../routes.php?action=download&id=<?= urlencode($file['id']) ?>" 
                                       class="download-btn"
                                       style="background: #007bff; border: 2px solid #007bff; color: white; padding: 6px 12px; border-radius: 4px; font-size: 11px; text-decoration: none; display: inline-block; font-weight: bold;">
                                        📥 Download
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 20px; color: #6c757d;">
                Found <?= count($files) ?> file(s)
                <?php if (!empty($searchTerm) || !empty($labelFilter)): ?>
                    matching your search criteria
                <?php endif; ?>
                <?php if ($globalLocked): ?>
                    <span class="lock-badge">🔒 SYSTEM LOCKED</span>
                <?php endif; ?>
            </p>
            
        <?php else: ?>
            <div class="no-files">
                <h3>📂 No files found</h3>
                <?php if (!empty($searchTerm) || !empty($labelFilter)): ?>
                    <p>Try adjusting your search criteria or <a href="dashboard.php">view all files</a></p>
                <?php else: ?>
                    <p>No files have been uploaded yet. <a href="upload_form.php">Upload your first file</a></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAMBAHAN: CSS untuk restricted file styling -->
    <style>
        .restricted-badge {
            background: #dc3545;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            margin-left: 8px;
            font-weight: bold;
        }
        
        .password-group {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        
        .password-input {
            border: 2px solid #dc3545 !important;
            transition: border-color 0.3s ease;
        }
        
        .password-input:focus {
            outline: none;
            border-color: #c82333 !important;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .password-input.valid {
            border-color: #28a745 !important;
        }
        
        .restricted-download-btn {
            transition: all 0.3s ease;
        }
        
        .restricted-download-btn:hover {
            background: #c82333 !important;
            border-color: #c82333;
            transform: translateY(-1px);
        }
        
        .restricted-download-btn:disabled {
            background: #6c757d !important;
            border-color: #6c757d;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .toggle-password-btn:hover {
            background: #5a6268 !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .files-table {
                font-size: 12px;
            }
            
            .password-input {
                width: 80px;
                font-size: 10px;
                padding: 3px 5px;
            }
            
            .download-btn {
                padding: 5px 8px;
                font-size: 10px;
            }
        }
    </style>

    <!-- TAMBAHAN: JavaScript untuk password handling dan download -->
    <script>
        // Function untuk toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '🙈';
                button.title = 'Hide Password';
            } else {
                input.type = 'password';
                button.innerHTML = '👁️';
                button.title = 'Show Password';
            }
        }
        
        // Function untuk download dengan password
        function downloadWithPassword(fileId) {
            const passwordInput = document.getElementById(`password_${fileId}`);
            const downloadBtn = document.getElementById(`download_btn_${fileId}`);
            const password = passwordInput.value.trim();
            
            // Validasi password input
            if (!password) {
                alert('❌ Please enter the password for this restricted file!');
                passwordInput.focus();
                passwordInput.style.borderColor = '#dc3545';
                passwordInput.style.boxShadow = '0 0 0 0.2rem rgba(220, 53, 69, 0.25)';
                return;
            }
            
            // Reset styling
            passwordInput.style.borderColor = '#28a745';
            passwordInput.style.boxShadow = '0 0 0 0.2rem rgba(40, 167, 69, 0.25)';
            
            // Disable button dan show loading
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '⏳ Processing...';
            
            // Create form dan submit untuk download
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../routes.php?action=download&id=' + encodeURIComponent(fileId);
            form.style.display = 'none';
            
            const passwordField = document.createElement('input');
            passwordField.type = 'hidden';
            passwordField.name = 'password';
            passwordField.value = password;
            
            form.appendChild(passwordField);
            document.body.appendChild(form);
            
            // Submit form
            form.submit();
            
            // Reset UI after delay
            setTimeout(() => {
                downloadBtn.disabled = false;
                downloadBtn.innerHTML = '🔒 Download';
                passwordInput.value = ''; // Clear password for security
                passwordInput.style.borderColor = '#dc3545';
                passwordInput.style.boxShadow = 'none';
                
                // Remove form
                if (document.body.contains(form)) {
                    document.body.removeChild(form);
                }
            }, 3000);
        }
        
        // Real-time password validation
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInputs = document.querySelectorAll('.password-input');
            
            passwordInputs.forEach(input => {
                input.addEventListener('input', function() {
                    const fileId = this.id.replace('password_', '');
                    const downloadBtn = document.getElementById(`download_btn_${fileId}`);
                    
                    if (this.value.trim().length > 0) {
                        this.classList.add('valid');
                        if (downloadBtn) {
                            downloadBtn.style.background = '#dc3545';
                            downloadBtn.innerHTML = '🔒 Download';
                        }
                    } else {
                        this.classList.remove('valid');
                        if (downloadBtn) {
                            downloadBtn.style.background = '#6c757d';
                            downloadBtn.innerHTML = '🔒 Password Required';
                        }
                    }
                });
                
                // Enter key untuk download
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        const fileId = this.id.replace('password_', '');
                        downloadWithPassword(fileId);
                    }
                });
            });
        });
    </script>

    <!-- EXISTING GLOBAL LOCK TOGGLE SCRIPT - TIDAK DIUBAH -->
    <?php if ($role == 4): ?>
    <script>
        document.getElementById('globalLockToggle').addEventListener('change', function() {
            const mode = this.checked ? 'encrypted' : 'normal';
            const lockText = this.checked ? 'lock' : 'unlock';
            
            if (confirm(`Are you sure you want to ${lockText} filename display for ALL USERS?`)) {
                // FIX: Perbaiki path ke routes.php
                fetch('../routes.php?action=toggle_filename_lock', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `mode=${mode}`
                })
                .then(response => {
                    // Check if response is ok before parsing JSON
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        this.checked = !this.checked; // Revert toggle
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Network error occurred: ' + error.message);
                    this.checked = !this.checked; // Revert toggle
                });
            } else {
                this.checked = !this.checked; // Revert toggle
            }
        });
        
        // TAMBAHAN: Check current lock status on page load
        fetch('../routes.php?action=get_filename_lock_status')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const toggle = document.getElementById('globalLockToggle');
                    if (toggle) {
                        toggle.checked = data.is_locked;
                    }
                } else {
                    console.log('Lock status check:', data.message);
                }
            })
            .catch(error => {
                console.log('Lock status check error:', error);
            });
    </script>
    <?php endif; ?>
</body>
</html>
