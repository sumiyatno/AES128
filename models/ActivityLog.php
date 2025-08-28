<?php
// filepath: d:\website\AES128\models\ActivityLog.php

class ActivityLog {
    private $pdo;

    public function __construct($pdo = null) {
        if ($pdo) {
            $this->pdo = $pdo;
        } else {
            require_once __DIR__ . '/../config/database.php';
            global $pdo;
            $this->pdo = $pdo;
        }
    }

    /**
     * Catat aktivitas baru
     */
    public function log($action, $status = 'success', $targetType = null, $targetId = null, $targetName = null, $details = null) {
        try {
            // FIX: Cek session dengan lebih aman
            $userId = null;
            $username = 'Anonymous';
            
            if (session_status() === PHP_SESSION_NONE) {
                // Hanya start session jika belum ada dan tidak ada output
                if (!headers_sent()) {
                    session_start();
                }
            }
            
            // Ambil info user dari session jika tersedia
            if (session_status() === PHP_SESSION_ACTIVE) {
                $userId = $_SESSION['user_id'] ?? null;
                $username = $_SESSION['username'] ?? 'Anonymous';
            }
            
            // Ambil IP address
            $ipAddress = $this->getClientIP();
            
            // Ambil user agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            // Convert details ke JSON jika array
            if (is_array($details)) {
                $details = json_encode($details, JSON_UNESCAPED_UNICODE);
            }

            $stmt = $this->pdo->prepare('
                INSERT INTO activity_logs 
                (user_id, username, action, target_type, target_id, target_name, details, ip_address, user_agent, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            return $stmt->execute([
                $userId,
                $username,
                $action,
                $targetType,
                $targetId,
                $targetName,
                $details,
                $ipAddress,
                $userAgent,
                $status
            ]);

        } catch (Exception $e) {
            // Jangan sampai error logging merusak aplikasi utama
            error_log("Failed to write activity log: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Ambil semua logs dengan pagination - FIX LIMIT/OFFSET
     */
    public function getAllLogs($limit = 50, $offset = 0, $filter = []) {
        $conditions = [];
        $params = [];

        // Filter berdasarkan action
        if (!empty($filter['action'])) {
            $conditions[] = "action = ?";
            $params[] = $filter['action'];
        }

        // Filter berdasarkan user
        if (!empty($filter['user_id'])) {
            $conditions[] = "user_id = ?";
            $params[] = $filter['user_id'];
        }

        // Filter berdasarkan status
        if (!empty($filter['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filter['status'];
        }

        // Filter berdasarkan tanggal
        if (!empty($filter['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $filter['date_from'] . ' 00:00:00';
        }

        if (!empty($filter['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $filter['date_to'] . ' 23:59:59';
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        // FIX: Gunakan integer langsung untuk LIMIT dan OFFSET
        $limit = (int)$limit;
        $offset = (int)$offset;

        $sql = "SELECT * FROM activity_logs 
                $whereClause 
                ORDER BY created_at DESC 
                LIMIT $limit OFFSET $offset";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Hitung total logs untuk pagination - FIX WHERE CLAUSE
     */
    public function countLogs($filter = []) {
        $conditions = [];
        $params = [];

        if (!empty($filter['action'])) {
            $conditions[] = "action = ?";
            $params[] = $filter['action'];
        }

        if (!empty($filter['user_id'])) {
            $conditions[] = "user_id = ?";
            $params[] = $filter['user_id'];
        }

        if (!empty($filter['status'])) {
            $conditions[] = "status = ?";
            $params[] = $filter['status'];
        }

        if (!empty($filter['date_from'])) {
            $conditions[] = "created_at >= ?";
            $params[] = $filter['date_from'] . ' 00:00:00';
        }

        if (!empty($filter['date_to'])) {
            $conditions[] = "created_at <= ?";
            $params[] = $filter['date_to'] . ' 23:59:59';
        }

        $whereClause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) as total FROM activity_logs $whereClause");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    }

    /**
     * Ambil statistik log - FIX LIMIT
     */
    public function getStatistics($days = 30) {
        $days = (int)$days;
        $stmt = $this->pdo->prepare('
            SELECT 
                action,
                status,
                COUNT(*) as count,
                DATE(created_at) as log_date
            FROM activity_logs 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY action, status, DATE(created_at)
            ORDER BY log_date DESC, action
        ');
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ambil logs user tertentu - FIX LIMIT
     */
    public function getUserLogs($userId, $limit = 20) {
        $limit = (int)$limit;
        $stmt = $this->pdo->prepare("
            SELECT * FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT $limit
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cleanup logs lama
     */
    public function cleanupOldLogs($days = 90) {
        $stmt = $this->pdo->prepare('
            DELETE FROM activity_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ');
        return $stmt->execute([$days]);
    }

    /**
     * Get real client IP address
     */
    private function getClientIP() {
        // Cek berbagai kemungkinan header untuk IP
        $ipHeaders = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validasi IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}