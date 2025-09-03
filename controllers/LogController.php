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
    try {
        // Validate filter parameters
        $validatedFilter = $this->validateExportFilter($filter);
        
        // Get logs with filter
        $logs = $this->logModel->getAllLogs(10000, 0, $validatedFilter); // Max 10k records
        
        // Start output buffering untuk capture CSV
        ob_start();
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for proper Excel UTF-8 handling
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
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
                $log['username'] ?: 'Unknown',
                $log['action'],
                $log['target_type'] ?: 'N/A',
                $log['target_id'] ?: 'N/A',
                $log['target_name'] ?: 'N/A',
                $log['status'],
                $log['ip_address'] ?: 'N/A',
                substr($log['user_agent'] ?: 'N/A', 0, 100), // Limit user agent length
                $log['details'] ? substr($log['details'], 0, 200) : 'N/A', // Limit details length
                $log['created_at']
            ]);
        }
        
        fclose($output);
        
        // Get CSV content
        $csvContent = ob_get_clean();
        
        // Log the export action
        $this->writeLog('export_logs', 'success', 'logs', null, 'CSV Export', [
            'filter' => $validatedFilter,
            'records_exported' => count($logs)
        ]);
        
        return $csvContent;
        
    } catch (Exception $e) {
        error_log("CSV Export error: " . $e->getMessage());
        
        // Log the failed export
        $this->writeLog('export_logs', 'failed', 'logs', null, 'CSV Export Failed', [
            'error' => $e->getMessage(),
            'filter' => $filter ?? []
        ]);
        
        throw new Exception('Export failed: ' . $e->getMessage());
    }
}

/**
 * Validate and sanitize export filter parameters
 */
private function validateExportFilter($filter) {
    $validatedFilter = [];
    
    // Validate action filter
    if (!empty($filter['action'])) {
        $allowedActions = [
            'login', 'logout', 'upload', 'download', 'rotate_key', 
            'create_user', 'delete_user', 'update_user', 'file_delete', 
            'file_update', 'export_logs', 'create_label', 'soft_delete',           // Soft delete file
            'hard_delete',           // Hard delete file  
            'permanent_delete',      // Permanent delete from database
            'file_restore',          // Restore deleted file
            'file_update',           // Update file metadata
            'export_my_files',       // Export user's files to CSV
            'view_file_details',     // View file details
            'bulk_delete',           // Bulk delete files
            'bulk_permanent_delete', // Bulk permanent delete
                
        ];
        if (in_array($filter['action'], $allowedActions)) {
            $validatedFilter['action'] = $filter['action'];
        }
    }
    
    // Validate status filter
    if (!empty($filter['status'])) {
        $allowedStatuses = ['success', 'failed', 'error'];
        if (in_array($filter['status'], $allowedStatuses)) {
            $validatedFilter['status'] = $filter['status'];
        }
    }
    
    // Validate user_id filter
    if (!empty($filter['user_id']) && is_numeric($filter['user_id'])) {
        $validatedFilter['user_id'] = (int)$filter['user_id'];
    }
    
    // Validate date filters
    if (!empty($filter['date_from'])) {
        $dateFrom = DateTime::createFromFormat('Y-m-d', $filter['date_from']);
        if ($dateFrom !== false) {
            $validatedFilter['date_from'] = $filter['date_from'];
        }
    }
    
    if (!empty($filter['date_to'])) {
        $dateTo = DateTime::createFromFormat('Y-m-d', $filter['date_to']);
        if ($dateTo !== false) {
            $validatedFilter['date_to'] = $filter['date_to'];
        }
    }
    
    return $validatedFilter;
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
    /**
     * NEW METHOD: Get File Manager activity statistics
     */
    public function getFileManagerStats($days = 30) {
        try {
            $sql = "
                SELECT 
                    action,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM activity_logs 
                WHERE action IN (
                    'soft_delete', 'hard_delete', 'permanent_delete', 
                    'file_restore', 'file_update', 'export_my_files',
                    'view_file_details', 'bulk_delete', 'bulk_permanent_delete'
                )
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY action, DATE(created_at)
                ORDER BY created_at DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get file manager stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW METHOD: Get most active file managers
     */
    public function getMostActiveFileManagers($limit = 10, $days = 30) {
        try {
            $sql = "
                SELECT 
                    al.user_id,
                    u.username,
                    COUNT(*) as activity_count,
                    COUNT(CASE WHEN al.action = 'permanent_delete' THEN 1 END) as permanent_deletes,
                    COUNT(CASE WHEN al.action = 'file_restore' THEN 1 END) as restores,
                    COUNT(CASE WHEN al.action = 'file_update' THEN 1 END) as updates
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.action IN (
                    'soft_delete', 'hard_delete', 'permanent_delete', 
                    'file_restore', 'file_update', 'export_my_files'
                )
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY al.user_id, u.username
                ORDER BY activity_count DESC
                LIMIT ?
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days, $limit]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get most active file managers error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * NEW METHOD: Get file deletion trends
     */
    public function getFileDeletionTrends($days = 30) {
        try {
            $sql = "
                SELECT 
                    DATE(created_at) as date,
                    COUNT(CASE WHEN action = 'soft_delete' THEN 1 END) as soft_deletes,
                    COUNT(CASE WHEN action = 'hard_delete' THEN 1 END) as hard_deletes,
                    COUNT(CASE WHEN action = 'permanent_delete' THEN 1 END) as permanent_deletes,
                    COUNT(CASE WHEN action = 'file_restore' THEN 1 END) as restores
                FROM activity_logs
                WHERE action IN ('soft_delete', 'hard_delete', 'permanent_delete', 'file_restore')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY DATE(created_at)
                ORDER BY date DESC
            ";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$days]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Get file deletion trends error: " . $e->getMessage());
            return [];
        }
    }
}
?>
