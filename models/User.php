<?php
// models/User.php
class User {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function authenticate($username, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE username = ? AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // تحديث آخر دخول
            $stmt = $this->pdo->prepare("UPDATE users SET 
                last_login = NOW(), 
                last_login_ip = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                $user['id']
            ]);
            
            return $user;
        }
        
        return false;
    }
    
    public function getAll() {
        $stmt = $this->pdo->query("SELECT * FROM users ORDER BY role, full_name");
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function getReps() {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE role = 'rep' AND is_active = 1");
        return $stmt->fetchAll();
    }
    
    public function getStorekeepers() {
        $stmt = $this->pdo->query("SELECT * FROM users WHERE role = 'storekeeper' AND is_active = 1");
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        
        $stmt = $this->pdo->prepare("INSERT INTO users 
            (username, email, password, full_name, role, phone, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        return $stmt->execute([
            $data['username'],
            $data['email'],
            $hashedPassword,
            $data['full_name'],
            $data['role'],
            $data['phone'] ?? null,
            $data['is_active'] ?? 1
        ]);
    }
    
    public function update($id, $data) {
        $fields = [];
        $params = [];
        
        foreach (['username', 'email', 'full_name', 'role', 'phone', 'is_active'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        if (empty($fields)) return true;
        
        $params[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        return $this->pdo->prepare($sql)->execute($params);
    }
    
    public function changePassword($id, $oldPassword, $newPassword) {
        $user = $this->getById($id);
        
        if (!$user || !password_verify($oldPassword, $user['password'])) {
            return false;
        }
        
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        
        $stmt = $this->pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        return $stmt->execute([$hashedPassword, $id]);
    }
}