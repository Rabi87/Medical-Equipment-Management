<?php
// maintenance_card.php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$id = $_GET['id'] ?? 0;
$maint = $maintenanceModel->getById($id);

if (!$maint) {
    $_SESSION['error'] = 'سجل العطل غير موجود';
    header('Location: maintenance.php');
    exit;
}

$device = $deviceModel->getById($maint['device_id']);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل العطل - #<?= $maint['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="maintenance.php">إدارة الصيانة</a></li>
                <li class="breadcrumb-item active">تفاصيل العطل</li>
            </ol>
        </nav>
        
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h4 class="mb-0"><i class="fas fa-tools"></i> تفاصيل سجل العطل #<?= $maint['id'] ?></h4>
                    </div>
                    <div class="card-body">
                        <!-- معلومات الجهاز -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5><i class="fas fa-laptop-medical"></i> معلومات الجهاز</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>الرقم التسلسلي:</th>
                                        <td><strong><?= htmlspecialchars($device['serial_number']) ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th>الموديل:</th>
                                        <td><?= htmlspecialchars($device['model']) ?></td>
                                    </tr>
                                    <tr>
                                        <th>المشفى:</th>
                                        <td><?= htmlspecialchars($maint['hospital_name']) ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-info-circle"></i> حالة العطل</h5>
                                <table class="table table-sm">
                                    <tr>
                                        <th>تاريخ البلاغ:</th>
                                        <td><?= date('Y-m-d H:i', strtotime($maint['reported_date'])) ?></td>
                                    </tr>
                                    <tr>
                                        <th>الحالة:</th>
                                        <td>
                                            <span class="badge fs-5 p-2 bg-<?= $maint['status'] == 'resolved' ? 'success' : ($maint['status'] == 'in_progress' ? 'warning' : 'danger') ?>">
                                                <?= $maintenance_status[$maint['status']] ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>المندوب المسؤول:</th>
                                        <td><?= htmlspecialchars($maint['rep_name']) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <!-- وصف المشكلة -->
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-exclamation-triangle"></i> وصف المشكلة</h5>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($maint['problem_description'])) ?></p>
                        </div>
                        
                        <!-- تفاصيل الإصلاح -->
                        <?php if ($maint['resolution_notes']): ?>
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle"></i> تفاصيل الإصلاح</h5>
                            <p class="mb-2"><?= nl2br(htmlspecialchars($maint['resolution_notes'])) ?></p>
                            <small class="text-muted">
                                تم الإصلاح: <?= $maint['resolved_date'] ? date('Y-m-d H:i', strtotime($maint['resolved_date'])) : '-' ?>
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <!-- أزرار الإجراءات -->
                        <?php if ($maint['status'] != 'resolved' && ($_SESSION['role'] == 'admin' || ($_SESSION['role'] == 'rep' && $maint['assigned_rep_id'] == $_SESSION['user_id']))): ?>
                        <div class="card bg-light">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-cogs"></i> تحديث حالة العطل</h5>
                                <form method="POST" action="maintenance.php?action=update_status">
                                    <input type="hidden" name="id" value="<?= $maint['id'] ?>">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">الحالة الجديدة</label>\n                                            <select name="status" class="form-select" required>
                                                <option value="">اختر الحالة</option>
                                                <?php if ($maint['status'] != 'in_progress'): ?>
                                                <option value="in_progress">قيد الإصلاح</option>
                                                <?php endif; ?>
                                                <option value="resolved">تم الإصلاح</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">ملاحظات الإصلاح</label>
                                            <textarea name="resolution_notes" class="form-control" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-success mt-3">
                                        <i class="fas fa-save"></i> تحديث
                                    </button>
                                </form>
                            </div>
                        </div>
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