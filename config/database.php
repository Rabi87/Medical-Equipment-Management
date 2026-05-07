<?php
ob_start();
// config/database.php
session_start();

// إعدادات قاعدة البيانات
define('DB_HOST', 'mysql');
define('DB_PORT', '3306');
define('DB_NAME', 'medical_equipment_db');
define('DB_USER', 'app_user');
define('DB_PASS', 'secure_password_123');
define('DB_CHARSET', 'utf8mb4');

// عنوان الموقع
define('BASE_URL', 'http://localhost:8080/care-devices');

// إعدادات البريد الإلكتروني (غير مفعل في الإصدار الأول)
define('EMAIL_NOTIFICATIONS', false);

// توقيت النظام
date_default_timezone_set('Asia/Riyadh');

try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );
} catch (PDOException $e) {
    error_log("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
    die("عذراً، يرجى المحاولة لاحقاً.");
}

/**
 * تسجيل حركة في سجل النظام
 */
function logAction($pdo, $user_id, $action_type, $table_name, $record_id, $old_data = null, $new_data = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $stmt = $pdo->prepare("INSERT INTO system_logs 
        (user_id, action_type, table_name, record_id, old_data, new_data, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $stmt->execute([
        $user_id,
        $action_type,
        $table_name,
        $record_id,
        $old_data ? json_encode($old_data) : null,
        $new_data ? json_encode($new_data) : null,
        $ip_address
    ]);
}

/**
 * التحقق من تسجيل الدخول
 */
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * التحقق من صلاحيات المستخدم
 */
function checkRole($required_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], (array)$required_roles)) {
        header('Location: ' . BASE_URL . '/unauthorized.php');
        exit;
    }
}

/**
 * الحصول على اسم الصلاحية
 */
function getRoleName($role) {
    $roles = [
        'admin' => 'مدير',
        'rep' => 'مندوب صيانة',
        'storekeeper' => 'مسؤول مخزن'
    ];
    return $roles[$role] ?? $role;
}

/**
 * التحقق من انتهاء كفالة الجهاز
 */
function getWarrantyStatus($expiry_date) {
    $today = date('Y-m-d');
    $expiry = new DateTime($expiry_date);
    $now = new DateTime($today);
    $diff = $now->diff($expiry)->days;
    
    if ($expiry < $now) {
        return ['status' => 'expired', 'days' => -$diff, 'label' => 'منتهية'];
    } elseif ($diff <= 30) {
        return ['status' => 'expiring', 'days' => $diff, 'label' => 'تنتهي قريباً'];
    } else {
        return ['status' => 'valid', 'days' => $diff, 'label' => 'سارية'];
    }
}

/**
 * التحقق من تنبيهات المخزون (min/max)
 */
function checkMinMaxAlerts($pdo, $model) {
    $stmt = $pdo->prepare("SELECT * FROM minmax_settings WHERE model = ?");
    $stmt->execute([$model]);
    $settings = $stmt->fetch();
    
    if (!$settings) return null;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM devices 
        WHERE model = ? AND status = 'in_stock'");
    $stmt->execute([$model]);
    $current = $stmt->fetch()['count'];
    
    if ($current < $settings['min_qty']) {
        return ['type' => 'min', 'message' => "الكمية أقل من الحد الأدنى ({$current} < {$settings['min_qty']})"];
    } elseif ($current > $settings['max_qty']) {
        return ['type' => 'max', 'message' => "الكمية تجاوزت الحد الأقصى ({$current} > {$settings['max_qty']})"];
    }
    
    return ['type' => 'ok', 'message' => 'الكمية ضمن النطاق الطبيعي'];
}

/**
 * الحصول على قائمة المشافي للمندوب
 */
function getRepHospitals($pdo, $rep_id) {
    $stmt = $pdo->prepare("SELECT * FROM hospitals WHERE rep_id = ?");
    $stmt->execute([$rep_id]);
    return $stmt->fetchAll();
}

/**
 * التحقق من صلاحية المستخدم لجهاز معين
 */
function canAccessDevice($pdo, $device_id, $user_id, $user_role) {
    if ($user_role === 'admin') return true;
    
    $stmt = $pdo->prepare("SELECT d.*, h.rep_id FROM devices d 
        LEFT JOIN hospitals h ON d.current_hospital_id = h.id 
        WHERE d.id = ?");
    $stmt->execute([$device_id]);
    $device = $stmt->fetch();
    
    if (!$device) return false;
    
    if ($user_role === 'storekeeper') {
        return true;
    } elseif ($user_role === 'rep') {
        if ($device['current_hospital_id']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM hospitals WHERE id = ? AND rep_id = ?");
            $stmt->execute([$device['current_hospital_id'], $user_id]);
            return $stmt->fetchColumn() > 0;
        }
        return false;
    }
    
    return false;
}
ob_end_flush();
