<?php
// models/Device.php
class Device {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll($filters = []) {
        $sql = "SELECT d.*, s.name as supplier_name, h.name as hospital_name, 
            u.full_name as rep_name FROM devices d
            LEFT JOIN suppliers s ON d.supplier_id = s.id
            LEFT JOIN hospitals h ON d.current_hospital_id = h.id
            LEFT JOIN users u ON h.rep_id = u.id
            WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['status'])) {
            $sql .= " AND d.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['model'])) {
            $sql .= " AND d.model LIKE ?";
            $params[] = '%' . $filters['model'] . '%';
        }
        
        if (!empty($filters['serial'])) {
            $sql .= " AND d.serial_number LIKE ?";
            $params[] = '%' . $filters['serial'] . '%';
        }
        
        if (!empty($filters['hospital_id'])) {
            $sql .= " AND d.current_hospital_id = ?";
            $params[] = $filters['hospital_id'];
        }
        
        if (!empty($filters['expiring_soon'])) {
            $date = date('Y-m-d', strtotime('+30 days'));
            $sql .= " AND d.warranty_expiry_date BETWEEN CURDATE() AND ?";
            $params[] = $date;
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT d.*, s.name as supplier_name, 
            s.phone as supplier_phone, h.name as hospital_name, 
            u.full_name as rep_name FROM devices d
            LEFT JOIN suppliers s ON d.supplier_id = s.id
            LEFT JOIN hospitals h ON d.current_hospital_id = h.id
            LEFT JOIN users u ON h.rep_id = u.id
            WHERE d.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getBySerial($serial) {
        $stmt = $this->pdo->prepare("SELECT * FROM devices WHERE serial_number = ?");
        $stmt->execute([$serial]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO devices 
            (serial_number, model, brand, supplier_id, purchase_date, 
            warranty_months, warranty_expiry_date, purchase_price, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'in_stock', ?)");
        
        return $stmt->execute([
            $data['serial_number'],
            $data['model'],
            $data['brand'],
            $data['supplier_id'],
            $data['purchase_date'],
            $data['warranty_months'],
            $data['warranty_expiry_date'],
            $data['purchase_price'],
            $data['notes'] ?? null
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach (['model', 'brand', 'supplier_id', 'purchase_date', 
            'warranty_months', 'warranty_expiry_date', 'purchase_price', 'status', 
            'current_hospital_id', 'sold_date', 'sold_price', 'notes'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE devices SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function sell($device_id, $hospital_id, $rep_id = null, $sold_date = null, $sold_price = null) {
        $this->pdo->beginTransaction();
        
        try {
            // تحديث حالة الجهاز
            $stmt = $this->pdo->prepare("UPDATE devices SET 
                status = 'sold', 
                current_hospital_id = ?, 
                sold_date = ?, 
                sold_price = ? 
                WHERE id = ?");
            
            $stmt->execute([$hospital_id, $sold_date ?? date('Y-m-d'), $sold_price, $device_id]);
            
            // تسجيل الحركة
            $stmt = $this->pdo->prepare("INSERT INTO stock_movements 
                (device_id, movement_type, from_status, to_status, hospital_id, rep_id, quantity)
                VALUES (?, 'sale', 'in_stock', 'sold', ?, ?, 1)");
            
            $stmt->execute([$device_id, $hospital_id, $rep_id]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
    
    public function returnToStock($device_id) {
        $stmt = $this->pdo->prepare("UPDATE devices SET 
            status = 'in_stock', 
            current_hospital_id = NULL, 
            sold_date = NULL 
            WHERE id = ?");
        
        if ($stmt->execute([$device_id])) {
            // تسجيل الحركة
            $stmt = $this->pdo->prepare("INSERT INTO stock_movements 
                (device_id, movement_type, from_status, to_status, quantity)
                VALUES (?, 'return', 'sold', 'in_stock', 1)");
            
            $stmt->execute([$device_id]);
            return true;
        }
        
        return false;
    }
    
    public function getMaintenanceLogs($device_id) {
        $stmt = $this->pdo->prepare("SELECT ml.*, d.serial_number, d.model, 
            h.name as hospital_name, u.full_name as rep_name 
            FROM maintenance_logs ml
            JOIN devices d ON ml.device_id = d.id
            LEFT JOIN hospitals h ON ml.hospital_id = h.id
            LEFT JOIN users u ON ml.assigned_rep_id = u.id
            WHERE ml.device_id = ?
            ORDER BY ml.created_at DESC");
        
        $stmt->execute([$device_id]);
        return $stmt->fetchAll();
    }
    
    public function getStockMovements($device_id) {
        $stmt = $this->pdo->prepare("SELECT sm.*, h.name as hospital_name, 
            u.full_name as rep_name 
            FROM stock_movements sm
            LEFT JOIN hospitals h ON sm.hospital_id = h.id
            LEFT JOIN users u ON sm.rep_id = u.id
            WHERE sm.device_id = ?
            ORDER BY sm.created_at DESC");
        
        $stmt->execute([$device_id]);
        return $stmt->fetchAll();
    }
    
    public function getStockSummary() {
        $stmt = $this->pdo->query("SELECT model, 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'in_stock' THEN 1 ELSE 0 END) as in_stock,
            SUM(CASE WHEN status = 'sold' THEN 1 ELSE 0 END) as sold,
            SUM(CASE WHEN status = 'maintenance' THEN 1 ELSE 0 END) as maintenance
            FROM devices 
            GROUP BY model");
        
        return $stmt->fetchAll();
    }
    
    public function getExpiringWarranty($days = 30) {
        $date = date('Y-m-d', strtotime("+$days days"));
        $stmt = $this->pdo->prepare("SELECT d.*, s.name as supplier_name, 
            h.name as hospital_name FROM devices d
            LEFT JOIN suppliers s ON d.supplier_id = s.id
            LEFT JOIN hospitals h ON d.current_hospital_id = h.id
            WHERE d.warranty_expiry_date BETWEEN CURDATE() AND ?
            AND d.status != 'maintenance'
            ORDER BY d.warranty_expiry_date ASC");
        
        $stmt->execute([$date]);
        return $stmt->fetchAll();
    }
}