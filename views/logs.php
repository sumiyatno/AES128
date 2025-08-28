<?php
// filepath: d:\website\AES128\views\logs.php

// Start session jika belum
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek pembatasan akses
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/LogController.php';
require_once __DIR__ . '/../config/database.php';

$userController = new UserController($pdo);
$role = $_SESSION['user_level'] ?? 1;

// Hanya Super Admin yang bisa akses logs
if (!$userController->canAccessFeature($role, 'admin')) {
    die("Access denied! Hanya Super Admin yang dapat melihat logs.");
}

$logController = new LogController($pdo);

// Handle filter dan pagination
$filter = [
    'action' => $_GET['action_filter'] ?? '',
    'status' => $_GET['status_filter'] ?? '',
    'user_id' => $_GET['user_filter'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
];

$page = (int)($_GET['page'] ?? 1);
$perPage = 25;

// Ambil data logs
$logData = $logController->dashboard($filter, $page, $perPage);
$logs = $logData['logs'];
$pagination = $logData['pagination'];

// Ambil statistik
$statistics = $logController->getStatistics();
?>

<?php include __DIR__ . '/sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Activity Logs - Admin Panel</title>
    <link href="sidebar.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f8f8f8;
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
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
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
        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .filter-group select,
        .filter-group input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .table-container {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .status-success {
            color: #28a745;
            font-weight: bold;
        }
        .status-failed {
            color: #dc3545;
            font-weight: bold;
        }
        .status-error {
            color: #fd7e14;
            font-weight: bold;
        }
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 20px 0;
        }
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination .current {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Activity Logs</h1>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <?php
            $totalLogs = array_sum(array_column($statistics, 'count'));
            $todayLogs = count(array_filter($statistics, function($stat) {
                return $stat['log_date'] === date('Y-m-d');
            }));
            $errorLogs = array_sum(array_column(array_filter($statistics, function($stat) {
                return $stat['status'] === 'failed' || $stat['status'] === 'error';
            }), 'count'));
            ?>
            <div class="stat-card">
                <h3><?= number_format($totalLogs) ?></h3>
                <p>Total Logs (30 days)</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($todayLogs) ?></h3>
                <p>Today's Activities</p>
            </div>
            <div class="stat-card">
                <h3><?= number_format($errorLogs) ?></h3>
                <p>Failed/Error Actions</p>
            </div>
            <div class="stat-card">
                <h3><?= count(array_unique(array_column($statistics, 'action'))) ?></h3>
                <p>Unique Actions</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Action:</label>
                        <select name="action_filter">
                            <option value="">All Actions</option>
                            <option value="login" <?= $filter['action'] === 'login' ? 'selected' : '' ?>>Login</option>
                            <option value="logout" <?= $filter['action'] === 'logout' ? 'selected' : '' ?>>Logout</option>
                            <option value="upload" <?= $filter['action'] === 'upload' ? 'selected' : '' ?>>Upload</option>
                            <option value="download" <?= $filter['action'] === 'download' ? 'selected' : '' ?>>Download</option>
                            <option value="rotate_key" <?= $filter['action'] === 'rotate_key' ? 'selected' : '' ?>>Rotate Key</option>
                            <option value="create_user" <?= $filter['action'] === 'create_user' ? 'selected' : '' ?>>Create User</option>
                            <option value="delete_user" <?= $filter['action'] === 'delete_user' ? 'selected' : '' ?>>Delete User</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>Status:</label>
                        <select name="status_filter">
                            <option value="">All Status</option>
                            <option value="success" <?= $filter['status'] === 'success' ? 'selected' : '' ?>>Success</option>
                            <option value="failed" <?= $filter['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="error" <?= $filter['status'] === 'error' ? 'selected' : '' ?>>Error</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label>From Date:</label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filter['date_from']) ?>">
                    </div>

                    <div class="filter-group">
                        <label>To Date:</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filter['date_to']) ?>">
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">🔍 Filter</button>
                    </div>

                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <a href="?" class="btn btn-secondary">🔄 Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Export Button -->
        <div style="margin-bottom: 20px;">
            <a href="../routes.php?action=export_logs<?= http_build_query($filter) ? '&' . http_build_query($filter) : '' ?>" 
               class="btn btn-success">📥 Export to CSV</a>
        </div>

        <!-- Logs Table -->
        <div class="table-container">
            <?php if (!empty($logs)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Target</th>
                            <th>Status</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= htmlspecialchars($log['id']) ?></td>
                                <td><?= date('M d, H:i:s', strtotime($log['created_at'])) ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($log['username']) ?></strong>
                                    <?php if ($log['user_id']): ?>
                                        <small>(ID: <?= $log['user_id'] ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span style="background: #e9ecef; padding: 3px 8px; border-radius: 12px; font-size: 12px;">
                                        <?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($log['target_name']): ?>
                                        <strong><?= htmlspecialchars($log['target_name']) ?></strong><br>
                                        <small><?= htmlspecialchars($log['target_type']) ?>: <?= htmlspecialchars($log['target_id']) ?></small>
                                    <?php else: ?>
                                        <em>N/A</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-<?= $log['status'] ?>">
                                        <?= ucfirst($log['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td>
                                    <?php if ($log['details']): ?>
                                        <details>
                                            <summary>View</summary>
                                            <pre style="font-size: 11px; max-width: 200px; overflow: auto;"><?= htmlspecialchars($log['details']) ?></pre>
                                        </details>
                                    <?php else: ?>
                                        <em>No details</em>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] - 1])) ?>">« Previous</a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $pagination['current_page'] - 2); $i <= min($pagination['total_pages'], $pagination['current_page'] + 2); $i++): ?>
                        <?php if ($i === $pagination['current_page']): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pagination['current_page'] + 1])) ?>">Next »</a>
                    <?php endif; ?>
                </div>

                <p style="text-align: center; color: #666; font-size: 14px;">
                    Showing <?= count($logs) ?> of <?= number_format($pagination['total_records']) ?> total records
                </p>

            <?php else: ?>
                <div class="no-data">
                    <h3>📝 No logs found</h3>
                    <p>No activity logs match your current filters.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>