<?php
// models/MinMax.php
class MinMax {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM minmax_settings ORDER BY model");
        return $stmt->fetchAll();
    }
    
    public function getByModel($model) {
        $stmt = $this->pdo->prepare("SELECT * FROM minmax_settings WHERE model = ?");
        $stmt->execute([$model]);
        return $stmt->fetch();
    }
    
    public function create($data) {
        $stmt = $this->pdo->prepare("INSERT INTO minmax_settings 
            (model, min_qty, max_qty, notes)
            VALUES (?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['model'],
            $data['min_qty'],
            $data['max_qty'],
            $data['notes'] ?? null
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach (['model', 'min_qty', 'max_qty', 'notes'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE minmax_settings SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function getAlerts() {
        $sql = "SELECT m.*, 
            (SELECT COUNT(*) FROM devices d WHERE d.model = m.model AND d.status = 'in_stock') as current_qty
            FROM minmax_settings m";
        
        $stmt = $this->pdo->query($sql);
        $results = $stmt->fetchAll();
        
        $alerts = [];
        foreach ($results as $row) {
            if ($row['current_qty'] < $row['min_qty']) {
                $alerts[] = [
                    'model' => $row['model'],
                    'type' => 'min',
                    'current_qty' => $row['current_qty'],
                    'threshold' => $row['min_qty'],
                    'message' => "الكمية أقل من الحد الأدنى ({$row['current_qty']} < {$row['min_qty']})"
                ];
            } elseif ($row['current_qty'] > $row['max_qty']) {
                $alerts[] = [
                    'model' => $row['model'],
                    'type' => 'max',
                    'current_qty' => $row['current_qty'],
                    'threshold' => $row['max_qty'],
                    'message' => "الكمية تجاوزت الحد الأقصى ({$row['current_qty']} > {$row['max_qty']})"
                ];
            }
        }
        
        return $alerts;
    }
}