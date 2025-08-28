<?php
// filepath: d:\website\AES128\controllers\LogController.php

require_once __DIR__ . '/../models/ActivityLog.php';

class LogController {
    private $pdo;
    private $logModel;

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->logModel = new ActivityLog($pdo);
    }

    /**
     * Tampilkan dashboard logs untuk admin
     */
    public function dashboard($filter = [], $page = 1, $perPage = 50) {
        $offset = ($page - 1) * $perPage;
        
        $logs = $this->logModel->getAllLogs($perPage, $offset, $filter);
        $totalLogs = $this->logModel->countLogs($filter);
        $totalPages = ceil($totalLogs / $perPage);

        return [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_records' => $totalLogs,
                'per_page' => $perPage
            ]
        ];
    }

    /**
     * Ekspor logs ke CSV
     */
    public function exportToCSV($filter = []) {
        $logs = $this->logModel->getAllLogs(10000, 0, $filter); // Max 10k records

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_H-i-s') . '.csv"');

        $output = fopen('php://output', 'w');
        
        // CSV Header
        fputcsv($output, [
            'ID', 'User ID', 'Username', 'Action', 'Target Type', 'Target ID', 
            'Target Name', 'Status', 'IP Address', 'User Agent', 'Details', 'Created At'
        ]);

        // CSV Data
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['id'],
                $log['user_id'] ?: 'N/A',
                $log['username'],
                $log['action'],
                $log['target_type'] ?: 'N/A',
                $log['target_id'] ?: 'N/A',
                $log['target_name'] ?: 'N/A',
                $log['status'],
                $log['ip_address'],
                $log['user_agent'],
                $log['details'] ?: 'N/A',
                $log['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Ambil statistik untuk dashboard
     */
    public function getStatistics() {
        return $this->logModel->getStatistics(30);
    }

    /**
     * Manual log helper function
     */
    public function writeLog($action, $status = 'success', $targetType = null, $targetId = null, $targetName = null, $details = null) {
        return $this->logModel->log($action, $status, $targetType, $targetId, $targetName, $details);
    }
}