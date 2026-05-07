<?php
// models/SystemLog.php
class SystemLog {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function log($user_id, $action_type, $table_name, $record_id, $old_data = null, $new_data = null) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $stmt = $this->pdo->prepare("INSERT INTO system_logs 
            (user_id, action_type, table_name, record_id, old_data, new_data, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $user_id,
            $action_type,
            $table_name,
            $record_id,
            $old_data ? json_encode($old_data) : null,
            $new_data ? json_encode($new_data) : null,
            $ip_address
        ]);
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT sl.*, u.username, u.full_name FROM system_logs sl
            LEFT JOIN users u ON sl.user_id = u.id
            WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND sl.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action_type'])) {
            $sql .= " AND sl.action_type = ?";
            $params[] = $filters['action_type'];
        }
        
        if (!empty($filters['table_name'])) {
            $sql .= " AND sl.table_name = ?";
            $params[] = $filters['table_name'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND sl.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND sl.created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY sl.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}