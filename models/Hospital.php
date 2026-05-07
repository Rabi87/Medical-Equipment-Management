<?php
// models/Hospital.php
class Hospital {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll($rep_id = null) {
        $sql = "SELECT h.*, u.full_name as rep_name FROM hospitals h
            LEFT JOIN users u ON h.rep_id = u.id
            WHERE 1=1";
        
        $params = [];
        
        if ($rep_id !== null) {
            $sql .= " AND h.rep_id = ?";
            $params[] = $rep_id;
        }
        
        $sql .= " ORDER BY h.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT h.*, u.full_name as rep_name 
            FROM hospitals h
            LEFT JOIN users u ON h.rep_id = u.id
            WHERE h.id = ?");
        
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO hospitals 
            (name, rep_id, contact_person, phone, email, address, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['name'],
            $data['rep_id'] ?? null,
            $data['contact_person'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['notes'] ?? null
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach (['name', 'rep_id', 'contact_person', 'phone', 
            'email', 'address', 'notes'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE hospitals SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function canDelete($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices 
            WHERE current_hospital_id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->fetchColumn() > 0) return false;
        
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM maintenance_logs 
            WHERE hospital_id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetchColumn() == 0;
    }
}