<?php
// rep_card.php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$id = $_GET['id'] ?? 0;

// جلب بيانات المندوب
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'rep'");
$stmt->execute([$id]);
$rep = $stmt->fetch();

if (!$rep) {
    $_SESSION['error'] = 'المندوب غير موجود';
    header('Location: users.php');
    exit;
}

// المشافي التي يغطيها
$hospitals = $hospitalModel->getByRep($id);

// الأعطال التي تم تعيينه لها
$maintenances = $maintenanceModel->getByRep($id);

// الأجهزة المباعة بواسطته
$stmt = $pdo->prepare("SELECT COUNT(*) FROM devices WHERE status = 'sold'");
$stmt->execute();
$soldCount = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقة المندوب - <?= htmlspecialchars($rep['full_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="users.php">إدارة المستخدمين</a></li>
                <li class="breadcrumb-item active">بطاقة المندوب</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- معلومات المندوب -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body text-center">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($rep['full_name']) ?>&background=007bff&color=fff&size=150" 
                             class="rounded-circle mb-3" width="120">
                        <h3><?= htmlspecialchars($rep['full_name']) ?></h3>
                        <p class="text-muted">مندوب صيانة</p>
                        
                        <hr>
                        
                        <div class="text-start mb-3">
                            <p><i class="fas fa-user"></i> اسم المستخدم: <strong><?= htmlspecialchars($rep['username']) ?></strong></p>
                            <p><i class="fas fa-envelope"></i> البريد: <?= htmlspecialchars($rep['email']) ?></p>
                            <?php if ($rep['phone']): ?>
                            <p><i class="fas fa-phone"></i> الهاتف: <?= htmlspecialchars($rep['phone']) ?></p>
                            <?php endif; ?>
                            <p><i class="fas fa-sign-in-alt"></i> آخر دخول: <?= $rep['last_login'] ? date('Y-m-d H:i', strtotime($rep['last_login'])) : 'لم يقم بتسجيل الدخول' ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <span class="badge bg-<?= $rep['is_active'] ? 'success' : 'danger' ?>">
                                <?= $rep['is_active'] ? 'نشط' : 'غير نشط' ?>
                            </span>
                        </div>
                        
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                        <button class="btn btn-outline-primary w-100" onclick="location.href='users.php'">
                            <i class="fas fa-edit"></i> تعديل بيانات المندوب
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- إحصائيات سريعة -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-chart-pie"></i> إحصائيات سريعة</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>المشافي:</span>
                            <strong><?= count($hospitals) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>الأعطال الإجمالية:</span>
                            <strong><?= count($maintenances) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>منجزة:</span>
                            <strong class="text-success"><?= count(array_filter($maintenances, fn($m) => $m['status'] == 'resolved')) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>قيد الانتظار:</span>
                            <strong class="text-warning"><?= count(array_filter($maintenances, fn($m) => $m['status'] == 'pending')) ?></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- تفاصيل العمل -->
            <div class="col-lg-8">
                <!-- المشافي التي يغطيها -->
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-hospital"></i> المشافي التي يغطيها (<?= count($hospitals) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($hospitals) > 0): ?>
                        <div class="row">
                            <?php foreach ($hospitals as $hosp): 
                                $hospDevices = $pdo->query("SELECT COUNT(*) FROM devices WHERE current_hospital_id = {$hosp['id']}")->fetchColumn();
                            ?>
                            <div class="col-md-6 mb-3">
                                <div class="card border-info">
                                    <div class="card-body">
                                        <h6><?= htmlspecialchars($hosp['name']) ?></h6>
                                        <p class="text-muted small mb-2"><?= htmlspecialchars($hosp['contact_person']) ?></p>
                                        <div class="d-flex justify-content-between">
                                            <span class="badge bg-light text-dark">أجهزة: <?= $hospDevices ?></span>
                                            <?php if ($hosp['phone']): ?>
                                            <small><i class="fas fa-phone"></i> <?= htmlspecialchars($hosp['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted py-4">لا توجد مشافي مخصصة لهذا المندوب بعد</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- سجل الأعطال -->
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0"><i class="fas fa-tools"></i> سجل الأعطال التي تمت معالجتها (<?= count($maintenances) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($maintenances) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>تاريخ البلاغ</th>
                                        <th>الجهاز</th>
                                        <th>المشفى</th>
                                        <th>الحالة</th>
                                        <th>التفاصيل</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($maintenances as $maint): ?>
                                    <tr>
                                        <td><?= date('Y-m-d', strtotime($maint['reported_date'])) ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($maint['serial_number']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($maint['model']) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($maint['hospital_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $maint['status'] == 'resolved' ? 'success' : ($maint['status'] == 'in_progress' ? 'warning' : 'danger') ?>">
                                                <?= $maintenance_status[$maint['status']] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="maintenance_card.php?id=<?= $maint['id'] ?>" class="btn btn-sm btn-outline-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-center text-muted py-4">لا توجد سجلات أعطال</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
<?php
$pdo = null;
?>