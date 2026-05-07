<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
// includes/header.php
require_once __DIR__ . '/../config/database.php';

// تحميل النماذج
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Device.php';
require_once __DIR__ . '/../models/Hospital.php';
require_once __DIR__ . '/../models/Supplier.php';
require_once __DIR__ . '/../models/Maintenance.php';
require_once __DIR__ . '/../models/MinMax.php';
require_once __DIR__ . '/../models/SystemLog.php';

// تهيئة النماذج
$userModel = new User($pdo);
$deviceModel = new Device($pdo);
$hospitalModel = new Hospital($pdo);
$supplierModel = new Supplier($pdo);
$maintenanceModel = new Maintenance($pdo);
$minMaxModel = new MinMax($pdo);
$systemLogModel = new SystemLog($pdo);

// جلب تنبيهات المخزون
$minmaxAlerts = [];
if (isset($_SESSION['user_id'])) {
    $minmaxAlerts = $minMaxModel->getAlerts();
}

// جلب تعريفات الصلاحيات
$roles = [
    'admin' => 'مدير',
    'rep' => 'مندوب صيانة',
    'storekeeper' => 'مسؤول مخزن'
];

$status_labels = [
    'in_stock' => 'في المخزن',
    'sold' => 'مباع',
    'maintenance' => 'تحت الصيانة'
];

$maintenance_status = [
    'pending' => 'قيد الانتظار',
    'in_progress' => 'قيد الإصلاح',
    'resolved' => 'تم الإصلاح'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة شركة تجهيزات طبية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        .sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            padding: 10px 20px;
            display: block;
            transition: all 0.3s;
        }
        .sidebar a:hover, .sidebar a.active {
            background: rgba(255,255,255,0.1);
            border-left: 4px solid #3498db;
        }
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            font-weight: bold;
        }
        .stat-card {
            padding: 20px;
            border-radius: 10px;
            color: white;
            margin-bottom: 20px;
        }
        .stat-card.primary { background: #3498db; }
        .stat-card.success { background: #2ecc71; }
        .stat-card.warning { background: #f39c12; }
        .stat-card.danger { background: #e74c3c; }
        .alert-minmax {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
        }
        .warranty-expired { color: #dc3545; font-weight: bold; }
        .warranty-expiring { color: #fd7e14; font-weight: bold; }
        .warranty-valid { color: #28a745; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- الشريط الجانبي -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <h3 class="text-center mb-4">
                        <i class="fas fa-heartbeat"></i>
                        <br><small>نظام التجهيزات الطبية</small>
                    </h3>
                    
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="text-center mb-4">
                        <img src="https://ui-avatars.com/api/?name=<?= $_SESSION['full_name'] ?>&background=3498db&color=fff" 
                             class="rounded-circle mb-2" width="60">
                        <h6><?= htmlspecialchars($_SESSION['full_name']) ?></h6>
                        <small class="text-muted"><?= $roles[$_SESSION['role']] ?></small>
                    </div>
                    
                    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i> لوحة التحكم
                    </a>
                    
                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'storekeeper'): ?>
                    <a href="devices.php" class="<?= basename($_SERVER['PHP_SELF']) == 'devices.php' ? 'active' : '' ?>">
                        <i class="fas fa-warehouse"></i> إدارة المخزون
                    </a>
                    
                    <a href="stock_movements.php" class="<?= basename($_SERVER['PHP_SELF']) == 'stock_movements.php' ? 'active' : '' ?>">
                        <i class="fas fa-exchange-alt"></i> حركة المخزون
                    </a>
                    
                    <a href="minmax.php" class="<?= basename($_SERVER['PHP_SELF']) == 'minmax.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-line"></i> إعدادات Min/Max
                        <?php if (count($minmaxAlerts) > 0): ?>
                            <span class="badge bg-danger float-end"><?= count($minmaxAlerts) ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    
                    <a href="maintenance.php" class="<?= basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'active' : '' ?>">
                        <i class="fas fa-tools"></i> إدارة الصيانة
                        <?php
                        $pending = $maintenanceModel->getAll(['status' => 'pending']);
                        if (count($pending) > 0): ?>
                            <span class="badge bg-warning float-end"><?= count($pending) ?></span>
                        <?php endif; ?>
                    </a>
                    
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="hospitals.php" class="<?= basename($_SERVER['PHP_SELF']) == 'hospitals.php' ? 'active' : '' ?>">
                        <i class="fas fa-hospital"></i> إدارة المشافي
                    </a>
                    
                    <a href="suppliers.php" class="<?= basename($_SERVER['PHP_SELF']) == 'suppliers.php' ? 'active' : '' ?>">
                        <i class="fas fa-truck"></i> إدارة الموردين
                    </a>
                    
                    <a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>">
                        <i class="fas fa-users"></i> إدارة المستخدمين
                    </a>
                    
                    <a href="system_logs.php" class="<?= basename($_SERVER['PHP_SELF']) == 'system_logs.php' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i> سجل الحركات
                    </a>
                    <?php endif; ?>
                    
                    <a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>">
                        <i class="fas fa-chart-bar"></i> التقارير
                    </a>
                    
                    <a href="logout.php" class="text-danger">
                        <i class="fas fa-sign-out-alt"></i> تسجيل الخروج
                    </a>
                    
                    <?php else: ?>
                    <p class="text-center">برجاء تسجيل الدخول</p>
                    <?php endif; ?>
                </div>
            </nav>
            
            <!-- المحتوى الرئيسي -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
<?php