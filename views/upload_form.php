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
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="sidebar.css" rel="stylesheet">
  <style>
    /* Enhanced Form Styling */
    .upload-container {
      max-width: 900px;
      margin: 0 auto;
    }
    
    .upload-card {
      border: none;
      box-shadow: 0 0 20px rgba(0,0,0,0.1);
      border-radius: 15px;
      overflow: hidden;
    }
    
    .upload-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      text-align: center;
    }
    
    .upload-body {
      padding: 40px;
      background: #f8f9fa;
    }
    
    .form-group {
      margin-bottom: 25px;
    }
    
    .form-label {
      font-weight: 600;
      color: #495057;
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    
    .form-control, .form-select {
      border: 2px solid #e9ecef;
      border-radius: 10px;
      padding: 12px 15px;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    
    .file-input-wrapper {
      position: relative;
      overflow: hidden;
      background: white;
      border: 2px dashed #dee2e6;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      transition: all 0.3s ease;
    }
    
    .file-input-wrapper:hover {
      border-color: #667eea;
      background: #f8f9ff;
    }
    
    .file-input-wrapper.has-file {
      border-color: #28a745;
      background: #f8fff9;
    }
    
    .file-preview-container {
      border: 2px dashed #dee2e6;
      border-radius: 15px;
      padding: 25px;
      text-align: center;
      margin: 20px 0;
      background: white;
      min-height: 120px;
      display: none;
      transition: all 0.3s ease;
    }
    
    .file-preview-container.active {
      display: block;
      border-color: #667eea;
      background: linear-gradient(135deg, #f8f9ff 0%, #e7f1ff 100%);
    }
    
    .file-info {
      background: white;
      border: 1px solid #e9ecef;
      border-radius: 10px;
      padding: 20px;
      margin: 15px 0;
      box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .preview-iframe {
      width: 100%;
      height: 400px;
      border: 1px solid #dee2e6;
      border-radius: 10px;
      margin-top: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .file-type-restrictions {
      background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
      border: 1px solid #ffeaa7;
      border-radius: 15px;
      padding: 20px;
      margin: 20px 0;
      font-size: 0.9em;
    }
    
    .restrictions-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      margin-top: 15px;
    }
    
    @media (max-width: 768px) {
      .restrictions-grid {
        grid-template-columns: 1fr;
        gap: 15px;
      }
    }
    
    .allowed-files {
      color: #198754;
      font-weight: 600;
    }
    
    .forbidden-files {
      color: #dc3545;
      font-weight: 600;
    }
    
    .btn-upload {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      border-radius: 10px;
      padding: 12px 30px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-upload:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }
    
    .btn-upload:disabled {
      background: #6c757d;
      transform: none;
      box-shadow: none;
    }
    
    .btn-secondary {
      border-radius: 10px;
      padding: 12px 30px;
      font-weight: 600;
    }
    
    /* Form Row Layouts */
    .form-row {
      display: grid;
      gap: 20px;
      margin-bottom: 25px;
    }
    
    .form-row.two-cols {
      grid-template-columns: 1fr 1fr;
    }
    
    @media (max-width: 768px) {
      .form-row.two-cols {
        grid-template-columns: 1fr;
      }
    }
    
    /* Success Alert Animation */
    #uploadSuccessAlert {
      animation: slideInDown 0.5s ease-out;
      border-radius: 15px;
      border: none;
      box-shadow: 0 5px 20px rgba(40, 167, 69, 0.3);
    }
    
    @keyframes slideInDown {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Preview Styles */
    .document-preview-container {
      background: white;
      padding: 25px;
      border-radius: 15px;
      border: 1px solid #e9ecef;
      box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    }
    
    .file-validation-status .badge {
      margin-right: 8px;
      margin-bottom: 8px;
      padding: 8px 12px;
      border-radius: 8px;
    }
    
    .text-preview-header {
      background: linear-gradient(135deg, #e9ecef 0%, #f8f9fa 100%);
      padding: 15px;
      border-radius: 10px 10px 0 0;
      border: 1px solid #dee2e6;
      border-bottom: none;
    }
    
    .text-content-preview pre {
      margin: 0;
      border-radius: 0 0 10px 10px;
    }
    
    .preview-iframe {
      border: 1px solid #dee2e6;
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    /* Password Field Animation */
    #password-field {
      transition: all 0.3s ease;
    }
    
    /* Character Counter */
    .char-counter {
      font-size: 12px;
      text-align: right;
      margin-top: 5px;
      color: #6c757d;
    }
  </style>
</head>
<body class="bg-light">
<div class="container-fluid">
  <div class="upload-container mt-4">
    <!-- Header -->
    <div class="upload-card">
      <div class="upload-header">
        <h2 class="mb-2"><i class="fas fa-cloud-upload-alt me-2"></i>Upload File</h2>
        <p class="mb-0 opacity-75">Upload dan enkripsi file Anda dengan aman</p>
      </div>

      <div class="upload-body">
        <!-- Upload Form -->
        <form id="uploadForm" action="../routes.php?action=upload" method="POST" enctype="multipart/form-data">
          
          <!-- File Selection -->
          <div class="form-group">
            <label for="file" class="form-label">
              <i class="fas fa-file-upload"></i>Pilih File
            </label>
            <div class="file-input-wrapper" id="fileInputWrapper">
              <input type="file" class="form-control" id="file" name="file" required 
                     accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.rtf">
              <div class="mt-2">
                <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                <p class="mb-1"><strong>Klik untuk memilih file</strong></p>
                <small class="text-muted">atau drag & drop file di sini</small>
              </div>
            </div>
            <div class="form-text mt-2">
              <i class="fas fa-eye me-1"></i>
              <strong>Preview tersedia:</strong> File akan ditampilkan preview sebelum upload untuk memastikan file yang benar.
            </div>
          </div>

          <!-- File Preview Container -->
          <div id="filePreviewContainer" class="file-preview-container">
            <div id="fileInfo" class="file-info" style="display: none;">
              <div class="row">
                <div class="col-md-6">
                  <strong><i class="fas fa-file me-1"></i>Nama File:</strong> <span id="fileName"></span><br>
                  <strong><i class="fas fa-weight me-1"></i>Ukuran:</strong> <span id="fileSize"></span><br>
                  <strong><i class="fas fa-file-code me-1"></i>Tipe:</strong> <span id="fileType"></span>
                </div>
                <div class="col-md-6">
                  <strong><i class="fas fa-eye me-1"></i>Dapat di-preview:</strong> <span id="canPreview"></span><br>
                  <strong><i class="fas fa-check-circle me-1"></i>Status:</strong> <span id="fileStatus" class="badge"></span>
                </div>
              </div>
            </div>
            
            <div id="previewContent">
              <div id="previewLoading" style="display: none;">
                <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                <p class="mt-3">Memuat preview file...</p>
              </div>
              <div id="previewError" style="display: none;">
                <i class="fas fa-exclamation-triangle fa-2x text-warning"></i>
                <p class="mt-2">Preview tidak tersedia untuk tipe file ini</p>
              </div>
              <iframe id="previewIframe" class="preview-iframe" style="display: none;"></iframe>
              <img id="previewImage" style="max-width: 100%; max-height: 400px; display: none; border-radius: 10px;" alt="Image Preview">
              <div id="previewText" style="display: none; text-align: left; background: white; padding: 15px; border-radius: 10px; max-height: 300px; overflow-y: auto;"></div>
            </div>
          </div>

          <!-- Form Fields Grid -->
          <div class="form-row two-cols">
            <!-- Label Selection -->
            <div class="form-group">
              <label for="label_id" class="form-label">
                <i class="fas fa-tags"></i>Pilih Label
              </label>
              <select name="label_id" id="label_id" class="form-select" required onchange="togglePasswordField()">
                <option value="">-- Pilih Label --</option>
                <?php foreach ($labels as $label): ?>
                  <option value="<?= $label['id'] ?>" data-access-level="<?= $label['access_level'] ?>">
                    <?= htmlspecialchars($label['name']) ?> (<?= $label['access_level'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Access Level -->
            <div class="form-group">
              <label for="access_level_id" class="form-label">
                <i class="fas fa-shield-alt"></i>Akses Level
              </label>
              <select name="access_level_id" id="access_level_id" class="form-select" required>
                <option value="">-- Pilih Level Akses --</option>
                <option value="1">Level 1 - Basic</option>
                <option value="2">Level 2 - Standard</option>
                <option value="3">Level 3 - Advanced</option>
                <option value="4">Level 4 - Admin</option>
              </select>
              <div class="form-text">Pilih level akses file sesuai yang boleh mengakses.</div>
            </div>
          </div>

          <!-- Password Field (Hidden by default) -->
          <div class="form-group" id="password-field" style="display: none;">
            <label for="restricted_password" class="form-label">
              <i class="fas fa-lock"></i>Password untuk File Restricted
            </label>
            <input type="password" name="restricted_password" id="restricted_password" class="form-control" 
                   placeholder="Masukkan password untuk file restricted">
            <div class="form-text">
              <i class="fas fa-exclamation-triangle text-warning me-1"></i>
              <strong>Label "Restricted" dipilih - Password wajib diisi!</strong><br>
              Password ini akan dienkripsi dengan Argon2 dan diperlukan untuk download.
            </div>
          </div>

          <!-- File Description -->
          <div class="form-group">
            <label for="file_description" class="form-label">
              <i class="fas fa-edit"></i>Deskripsi File <span class="text-muted">(Opsional)</span>
            </label>
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
              • Baris 2+: <strong>Deskripsi:</strong> [detail file, tujuan, catatan, dll]
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="form-group">
            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
              <a href="dashboard.php" class="btn btn-secondary me-md-2">
                <i class="fas fa-arrow-left me-1"></i>Kembali
              </a>
              <button type="submit" class="btn btn-primary btn-upload" id="submitBtn" disabled>
                <i class="fas fa-upload me-1"></i>Upload File
              </button>
            </div>
          </div>

        </form>
      </div>
    </div>
  </div>
</div>

<script>
// JavaScript code
const FILE_RESTRICTIONS = {
    allowedExtensions: [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'rtf'
    ],
    forbiddenExtensions: [
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg',
        'txt',
        'mp4', 'avi', 'mov', 'wmv', 'flv', 'mkv', 'webm', '3gp',
        'mp3', 'wav', 'flac', 'ogg', 'aac', 'wma', 'm4a',
        'exe', 'bat', 'com', 'cmd', 'scr', 'msi', 'deb', 'rpm',
        'js', 'vbs', 'ps1', 'sh'
    ],
    maxSize: 10 * 1024 * 1024,
    previewableTypes: {
        documents: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'],
        images: [],
        text: ['csv', 'rtf']
    }
};

function validateFile(file) {
    const fileName = file.name.toLowerCase();
    const fileExtension = fileName.split('.').pop();
    const fileSize = file.size;
    
    if (FILE_RESTRICTIONS.forbiddenExtensions.includes(fileExtension)) {
        return {
            valid: false,
            error: `Tipe file .${fileExtension.toUpperCase()} tidak diizinkan. File ${fileExtension === 'mp3' || fileExtension === 'mp4' ? 'audio/video' : 'executable'} dilarang untuk keamanan.`
        };
    }
    
    if (!FILE_RESTRICTIONS.allowedExtensions.includes(fileExtension)) {
        return {
            valid: false,
            error: `Tipe file .${fileExtension.toUpperCase()} tidak diizinkan. Hanya file dokumen, gambar, dan text yang diperbolehkan.`
        };
    }
    
    if (fileSize > FILE_RESTRICTIONS.maxSize) {
        const maxSizeMB = FILE_RESTRICTIONS.maxSize / (1024 * 1024);
        const fileSizeMB = (fileSize / (1024 * 1024)).toFixed(2);
        return {
            valid: false,
            error: `Ukuran file terlalu besar (${fileSizeMB}MB). Maksimal ${maxSizeMB}MB.`
        };
    }
    
    return { valid: true };
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function getFileExtension(fileName) {
    return fileName.toLowerCase().split('.').pop();
}

function canPreviewFile(extension) {
    const previewable = FILE_RESTRICTIONS.previewableTypes;
    return previewable.documents.includes(extension) || 
           previewable.images.includes(extension) || 
           previewable.text.includes(extension);
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function showFilePreview(file) {
    const container = document.getElementById('filePreviewContainer');
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    const fileType = document.getElementById('fileType');
    const canPreview = document.getElementById('canPreview');
    const fileStatus = document.getElementById('fileStatus');
    
    resetPreviewContent();
    
    container.classList.add('active');
    fileInfo.style.display = 'block';
    
    const extension = getFileExtension(file.name);
    fileName.textContent = file.name;
    fileSize.textContent = formatFileSize(file.size);
    fileType.textContent = file.type || 'application/octet-stream';
    
    const validation = validateFile(file);
    if (!validation.valid) {
        canPreview.textContent = 'Tidak';
        fileStatus.textContent = 'File Ditolak';
        fileStatus.className = 'badge bg-danger';
        
        document.getElementById('previewError').style.display = 'block';
        document.getElementById('previewError').innerHTML = `
            <i class="fas fa-times-circle fa-2x text-danger"></i>
            <p class="mt-2 text-danger"><strong>File Ditolak:</strong><br>${validation.error}</p>
        `;
        
        document.getElementById('submitBtn').disabled = true;
        return;
    }
    
    fileStatus.textContent = 'File Valid';
    fileStatus.className = 'badge bg-success';
    document.getElementById('submitBtn').disabled = false;
    
    const isPreviewable = canPreviewFile(extension);
    canPreview.textContent = isPreviewable ? 'Ya' : 'Tidak';
    
    if (isPreviewable) {
        showPreviewContent(file, extension);
    } else {
        document.getElementById('previewError').style.display = 'block';
        document.getElementById('previewError').innerHTML = `
            <i class="fas fa-file fa-2x text-secondary"></i>
            <p class="mt-2">Preview tidak tersedia untuk tipe file ini, tapi file valid untuk diupload.</p>
        `;
    }
}

function resetPreviewContent() {
    document.getElementById('previewLoading').style.display = 'none';
    document.getElementById('previewError').style.display = 'none';
    document.getElementById('previewIframe').style.display = 'none';
    document.getElementById('previewImage').style.display = 'none';
    document.getElementById('previewText').style.display = 'none';
}

function showPreviewContent(file, extension) {
    const previewable = FILE_RESTRICTIONS.previewableTypes;
    
    document.getElementById('previewLoading').style.display = 'block';
    
    setTimeout(() => {
        document.getElementById('previewLoading').style.display = 'none';
        
        if (previewable.images.includes(extension)) {
            showImagePreview(file);
        } else if (previewable.text.includes(extension)) {
            showTextPreview(file);
        } else if (previewable.documents.includes(extension)) {
            showDocumentPreviewBeforeUpload(file);
        }
    }, 500);
}

function showDocumentPreviewBeforeUpload(file) {
    const previewError = document.getElementById('previewError');
    const fileName = file.name;
    const fileSize = formatFileSize(file.size);
    const extension = getFileExtension(file.name);
    
    if (extension === 'pdf') {
        showPdfPreviewBeforeUpload(file);
        return;
    }
    
    const officeIcons = {
        'doc': 'fas fa-file-word text-primary',
        'docx': 'fas fa-file-word text-primary', 
        'xls': 'fas fa-file-excel text-success',
        'xlsx': 'fas fa-file-excel text-success',
        'ppt': 'fas fa-file-powerpoint text-danger',
        'pptx': 'fas fa-file-powerpoint text-danger'
    };
    
    const iconClass = officeIcons[extension] || 'fas fa-file-alt text-secondary';
    
    previewError.innerHTML = `
        <div class="document-preview-container">
            <div class="row">
                <div class="col-md-4 text-center">
                    <i class="${iconClass}" style="font-size: 4rem;"></i>
                    <h5 class="mt-2">${extension.toUpperCase()} Document</h5>
                </div>
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">📄 Detail File</h6>
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td><strong>Nama:</strong></td>
                                    <td>${fileName}</td>
                                </tr>
                                <tr>
                                    <td><strong>Ukuran:</strong></td>
                                    <td>${fileSize}</td>
                                </tr>
                                <tr>
                                    <td><strong>Tipe:</strong></td>
                                    <td>${file.type}</td>
                                </tr>
                                <tr>
                                    <td><strong>Terakhir Diubah:</strong></td>
                                    <td>${new Date(file.lastModified).toLocaleString('id-ID')}</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info mt-3">
                <i class="fas fa-info-circle"></i>
                <strong>File Siap untuk Upload!</strong><br>
                <small>File ${extension.toUpperCase()} telah dipilih dan dapat diupload. Setelah upload, file akan dienkripsi dan bisa diakses melalui sistem.</small>
            </div>
            
            <div class="file-validation-status mt-2">
                <span class="badge bg-success">✓ File Valid</span>
                <span class="badge bg-info">✓ Tipe Didukung</span>
                <span class="badge bg-primary">✓ Ukuran OK</span>
            </div>
        </div>
    `;
    previewError.style.display = 'block';
}

function showPdfPreviewBeforeUpload(file) {
    const reader = new FileReader();
    reader.onload = function(e) {
        const pdfData = e.target.result;
        
        const previewIframe = document.getElementById('previewIframe');
        previewIframe.src = pdfData;
        previewIframe.style.display = 'block';
        previewIframe.style.height = '500px';
        
        const previewError = document.getElementById('previewError');
        previewError.innerHTML = `
            <div class="alert alert-success mt-3">
                <i class="fas fa-check-circle"></i>
                <strong>PDF Preview Berhasil!</strong><br>
                <small>File PDF "${file.name}" dapat dipreviewed di atas. Pastikan ini adalah file yang benar sebelum upload.</small>
            </div>
        `;
        previewError.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function showImagePreview(file) {
    if (file.size > 5 * 1024 * 1024) {
        document.getElementById('previewError').innerHTML = `
            <i class="fas fa-image fa-2x text-info"></i>
            <div class="alert alert-warning mt-2">
                <strong>Gambar Terlalu Besar untuk Preview</strong><br>
                File: ${file.name}<br>
                Ukuran: ${formatFileSize(file.size)}<br>
                <small>Gambar akan ditampilkan setelah upload selesai.</small>
            </div>
        `;
        document.getElementById('previewError').style.display = 'block';
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewImage = document.getElementById('previewImage');
        previewImage.src = e.target.result;
        previewImage.style.display = 'block';
        previewImage.style.maxHeight = '400px';
        previewImage.style.maxWidth = '100%';
        previewImage.style.borderRadius = '8px';
        previewImage.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        
        const previewError = document.getElementById('previewError');
        previewError.innerHTML = `
            <div class="alert alert-success mt-3">
                <i class="fas fa-check-circle"></i>
                <strong>Preview Gambar Berhasil!</strong><br>
                <small>Gambar "${file.name}" ditampilkan di atas. File siap untuk diupload.</small>
            </div>
        `;
        previewError.style.display = 'block';
    };
    reader.readAsDataURL(file);
}

function showTextPreview(file) {
    if (file.size > 1024 * 1024) {
        document.getElementById('previewError').innerHTML = `
            <i class="fas fa-file-alt fa-2x text-info"></i>
            <div class="alert alert-warning mt-2">
                <strong>File Text Terlalu Besar untuk Preview</strong><br>
                File: ${file.name}<br>
                Ukuran: ${formatFileSize(file.size)}<br>
                <small>Konten akan tersedia setelah upload selesai.</small>
            </div>
        `;
        document.getElementById('previewError').style.display = 'block';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const previewText = document.getElementById('previewText');
        let content = e.target.result;
        
        const maxPreviewLength = 3000;
        let truncated = false;
        if (content.length > maxPreviewLength) {
            content = content.substring(0, maxPreviewLength);
            truncated = true;
        }
        
        const lines = content.split('\n').length;
        const words = content.trim().split(/\s+/).length;
        const chars = content.length;
        
        previewText.innerHTML = `
            <div class="text-preview-header mb-2">
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            Lines: ${lines} | Words: ${words} | Characters: ${chars}
                        </small>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted">
                            <i class="fas fa-file-alt"></i>
                            ${file.name} (${formatFileSize(file.size)})
                        </small>
                    </div>
                </div>
            </div>
            <div class="text-content-preview">
                <pre style="white-space: pre-wrap; font-family: 'Courier New', monospace; font-size: 0.9em; background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #dee2e6; max-height: 350px; overflow-y: auto;">${escapeHtml(content)}${truncated ? '\n\n... (preview dipotong, konten lengkap akan tersedia setelah upload)' : ''}</pre>
            </div>
            <div class="alert alert-success mt-2">
                <i class="fas fa-check-circle"></i>
                <strong>Text Preview Berhasil!</strong><br>
                <small>Konten file text ditampilkan di atas. Pastikan ini adalah file yang benar.</small>
            </div>
        `;
        previewText.style.display = 'block';
    };
    
    reader.readAsText(file, 'UTF-8');
}

document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const labelSelect = document.getElementById('label_id');
    const passwordInput = document.getElementById('restricted_password');
    const fileInput = document.getElementById('file');
    
    if (!fileInput.files.length) {
        alert('Pilih file terlebih dahulu!');
        return;
    }
    
    const file = fileInput.files[0];
    const validation = validateFile(file);
    if (!validation.valid) {
        alert('File tidak valid: ' + validation.error);
        return;
    }
    
    const selectedOption = labelSelect.options[labelSelect.selectedIndex];
    const accessLevel = selectedOption.getAttribute('data-access-level');
    
    if (accessLevel === 'restricted' && !passwordInput.value.trim()) {
        alert('Password wajib diisi untuk file dengan label Restricted!');
        passwordInput.focus();
        return;
    }
    
    const submitBtn = document.getElementById('submitBtn');
    const originalBtnText = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    
    const formData = new FormData(this);
    
    fetch(this.action, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        
        if (data.success) {
            showSimpleUploadSuccess(data.file_id, data.filename);
        } else {
            alert('Upload gagal: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Upload error:', error);
        
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
        
        alert('Terjadi error saat upload file: ' + error.message);
    });
});

function showSimpleUploadSuccess(fileId, fileName) {
    const successHtml = `
        <div class="alert alert-success alert-dismissible fade show" role="alert" id="uploadSuccessAlert">
            <h5><i class="fas fa-check-circle text-success"></i> Upload Berhasil!</h5>
            <p><strong>File:</strong> ${fileName}</p>
            <p><strong>File ID:</strong> #${fileId}</p>
            <p><strong>Status:</strong> File berhasil diupload dan dienkripsi</p>
            
            <div class="mt-3">
                <a href="my_files.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-folder"></i> Lihat File di File Manager
                </a>
                <a href="../download.php?id=${fileId}" class="btn btn-success btn-sm ms-2">
                    <i class="fas fa-download"></i> Download File
                </a>
                <button onclick="location.reload()" class="btn btn-outline-secondary btn-sm ms-2">
                    <i class="fas fa-plus"></i> Upload File Lagi
                </button>
            </div>
            
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    
    const existingAlert = document.getElementById('uploadSuccessAlert');
    if (existingAlert) {
        existingAlert.remove();
    }
    
    const container = document.querySelector('.upload-container');
    container.insertAdjacentHTML('afterbegin', successHtml);
    
    document.getElementById('uploadForm').reset();
    document.getElementById('filePreviewContainer').classList.remove('active');
    document.getElementById('submitBtn').disabled = true;
    
    container.scrollIntoView({ behavior: 'smooth' });
}

document.getElementById('file').addEventListener('change', function() {
    const fileInput = this;
    const descriptionTextarea = document.getElementById('file_description');
    const wrapper = document.getElementById('fileInputWrapper');
    
    if (fileInput.files.length > 0) {
        const file = fileInput.files[0];
        
        // Update file input wrapper appearance
        wrapper.classList.add('has-file');
        
        showFilePreview(file);
        
        const fileName = file.name;
        const fileNameWithoutExt = fileName.replace(/\.[^/.]+$/, "");
        
        if (!descriptionTextarea.value.trim()) {
            descriptionTextarea.value = `Nama File: ${fileNameWithoutExt}\nDeskripsi: `;
            
            setTimeout(() => {
                descriptionTextarea.focus();
                descriptionTextarea.setSelectionRange(descriptionTextarea.value.length, descriptionTextarea.value.length);
            }, 100);
        }
    } else {
        wrapper.classList.remove('has-file');
        const container = document.getElementById('filePreviewContainer');
        container.classList.remove('active');
        document.getElementById('submitBtn').disabled = true;
    }
});

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
        passwordInput.value = '';
    }
}

document.getElementById('file_description').addEventListener('input', function() {
    const maxLength = 1000;
    const currentLength = this.value.length;
    const remaining = maxLength - currentLength;
    
    let counter = document.getElementById('description-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'description-counter';
        counter.className = 'char-counter';
        this.parentNode.appendChild(counter);
    }
    
    counter.textContent = `${currentLength}/1000 karakter`;
    counter.style.color = remaining < 50 ? '#dc3545' : '#6c757d';
    
    if (currentLength > maxLength) {
        this.value = this.value.substring(0, maxLength);
    }
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
