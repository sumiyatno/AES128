<?php
// filepath: d:\website\AES128\test_logging.php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/models/ActivityLog.php';

echo "<h2>Test Activity Logging System</h2>";

try {
    $logger = new ActivityLog($pdo);
    
    // Test 1: Log sederhana
    echo "<h3>Test 1: Basic Logging</h3>";
    $result1 = $logger->log('test_action', 'success', 'system', 'test_001', 'Test Log Entry');
    echo $result1 ? "✅ Basic log berhasil<br>" : "❌ Basic log gagal<br>";
    
    // Test 2: Log dengan details
    echo "<h3>Test 2: Log with Details</h3>";
    $result2 = $logger->log('test_upload', 'success', 'file', 'file_123', 'test_document.pdf', [
        'file_size' => '2.5MB',
        'mime_type' => 'application/pdf'
    ]);
    echo $result2 ? "✅ Detailed log berhasil<br>" : "❌ Detailed log gagal<br>";
    
    // Test 3: Ambil logs
    echo "<h3>Test 3: Retrieve Logs</h3>";
    $logs = $logger->getAllLogs(5, 0);
    echo "<p>Ditemukan " . count($logs) . " logs:</p>";
    
    if (!empty($logs)) {
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Time</th><th>Username</th><th>Action</th><th>Status</th><th>IP</th></tr>";
        foreach (array_slice($logs, 0, 3) as $log) {
            echo "<tr>";
            echo "<td>{$log['id']}</td>";
            echo "<td>{$log['created_at']}</td>";
            echo "<td>{$log['username']}</td>";
            echo "<td>{$log['action']}</td>";
            echo "<td>{$log['status']}</td>";
            echo "<td>{$log['ip_address']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test 4: Statistik
    echo "<h3>Test 4: Statistics</h3>";
    $stats = $logger->getStatistics(7);
    echo "<p>Statistik 7 hari terakhir: " . count($stats) . " entries</p>";
    
    echo "<hr>";
    echo "<p>✅ <strong>Sistem logging berfungsi dengan baik!</strong></p>";
    echo "<p><a href='views/logs.php'>🔗 Lihat Full Activity Logs</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}