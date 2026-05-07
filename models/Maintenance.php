<?php
// models/Maintenance.php
class Maintenance {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT ml.*, d.serial_number, d.model, 
            h.name as hospital_name, 
            u1.full_name as reported_by_name, 
            u2.full_name as rep_name 
            FROM maintenance_logs ml
            JOIN devices d ON ml.device_id = d.id
            LEFT JOIN hospitals h ON ml.hospital_id = h.id
            LEFT JOIN users u1 ON ml.reported_by = u1.id
            LEFT JOIN users u2 ON ml.assigned_rep_id = u2.id
            WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND ml.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['rep_id'])) {
            $sql .= " AND ml.assigned_rep_id = ?";
            $params[] = $filters['rep_id'];
        }
        
        if (!empty($filters['hospital_id'])) {
            $sql .= " AND ml.hospital_id = ?";
            $params[] = $filters['hospital_id'];
        }
        
        if (!empty($filters['device_id'])) {
            $sql .= " AND ml.device_id = ?";
            $params[] = $filters['device_id'];
        }
        
        $sql .= " ORDER BY ml.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT ml.*, d.serial_number, d.model, 
            h.name as hospital_name, 
            u1.full_name as reported_by_name, 
            u2.full_name as rep_name 
            FROM maintenance_logs ml
            JOIN devices d ON ml.device_id = d.id
            LEFT JOIN hospitals h ON ml.hospital_id = h.id
            LEFT JOIN users u1 ON ml.reported_by = u1.id
            LEFT JOIN users u2 ON ml.assigned_rep_id = u2.id
            WHERE ml.id = ?");
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO maintenance_logs 
            (device_id, hospital_id, reported_by, assigned_rep_id, 
            problem_description, status)
            VALUES (?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['device_id'],
            $data['hospital_id'],
            $data['reported_by'] ?? null,
            $data['assigned_rep_id'],
            $data['problem_description'],
            $data['status'] ?? 'pending'
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach (['status', 'assigned_rep_id', 'resolution_notes', 'resolved_date', 'start_date'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE maintenance_logs SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function getByDevice($device_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM maintenance_logs 
            WHERE device_id = ? ORDER BY created_at DESC");
        $stmt->execute([$device_id]);
        return $stmt->fetchAll();
    }
    
    public function getByRep($rep_id) {
        $stmt = $this->pdo->prepare("SELECT ml.*, d.serial_number, d.model, 
            h.name as hospital_name 
            FROM maintenance_logs ml
            JOIN devices d ON ml.device_id = d.id
            LEFT JOIN hospitals h ON ml.hospital_id = h.id
            WHERE ml.assigned_rep_id = ?
            ORDER BY ml.created_at DESC");
        
        $stmt->execute([$rep_id]);
        return $stmt->fetchAll();
    }
    
    public function getPerformanceStats() {
        $stmt = $this->pdo->query("SELECT 
            assigned_rep_id,
            u.full_name as rep_name,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            AVG(CASE WHEN status = 'resolved' THEN 
                TIMESTAMPDIFF(HOUR, reported_date, resolved_date)
            END) as avg_hours_to_resolve
            FROM maintenance_logs ml
            JOIN users u ON ml.assigned_rep_id = u.id
            GROUP BY assigned_rep_id
            ORDER BY total DESC");
        
        return $stmt->fetchAll();
    }
}