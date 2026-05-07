<?php
// models/Supplier.php
class Supplier {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM suppliers ORDER BY name");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO suppliers 
            (name, contact_person, phone, email, address, notes)
            VALUES (?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['name'],
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
        
        foreach (['name', 'contact_person', 'phone', 'email', 'address', 'notes'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE suppliers SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function canDelete($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM devices 
            WHERE supplier_id = ?");
        $stmt->execute([$id]);
        
        return $stmt->fetchColumn() == 0;
    }
}