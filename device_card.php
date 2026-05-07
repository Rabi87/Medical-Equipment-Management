<?php
// device_card.php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$id = $_GET['id'] ?? 0;
$device = $deviceModel->getById($id);

if (!$device) {
    $_SESSION['error'] = 'الجهاز غير موجود';
    header('Location: devices.php');
    exit;
}

$warranty = getWarrantyStatus($device['warranty_expiry_date']);
$maintenanceLogs = $maintenanceModel->getByDevice($id);
$stockMovements = $deviceModel->getStockMovements($id);
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقة الجهاز - <?= htmlspecialchars($device['serial_number']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .card-header { background: #f8f9fa; border-bottom: 1px solid #e9ecef; }
        .nav-tabs .nav-link.active { background: #0d6efd; color: white; }
        .badge-status { font-size: 0.85rem; padding: 0.5em 0.75em; }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/includes/header.php'; ?>
    
    <div class="container-fluid mt-4">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php"><i class="fas fa-home"></i></a></li>
                <li class="breadcrumb-item"><a href="devices.php">إدارة المخزون</a></li>
                <li class="breadcrumb-item active">بطاقة الجهاز</li>
            </ol>
        </nav>
        
        <div class="row">
            <!-- معلومات الجهاز -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-id-card"></i> معلومات الجهاز</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="mb-3">
                            <i class="fas fa-laptop-medical fa-3x text-primary mb-3"></i>
                            <h4><?= htmlspecialchars($device['model']) ?></h4>
                            <p class="text-muted"><?= htmlspecialchars($device['brand']) ?></p>
                            <hr>
                            <h3 class="text-primary"><?= htmlspecialchars($device['serial_number']) ?></h3>
                            <small class="text-muted">الرقم التسلسلي</small>
                        </div>
                        
                        <div class="mb-3 text-start">
                            <p><i class="fas fa-calendar-day"></i> تاريخ الشراء: <?= date('Y-m-d', strtotime($device['purchase_date'])) ?></p>
                            <p><i class="fas fa-box"></i> المورد: <?= htmlspecialchars($device['supplier_name']) ?></p>
                            <?php if ($device['purchase_price']): ?>
                            <p><i class="fas fa-money-bill-wave"></i> سعر الشراء: <?= number_format($device['purchase_price'], 2) ?> ريال</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- حالة الجهاز -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-info-circle"></i> الحالة</h5>
                    </div>
                    <div class="card-body text-center">
                        <span class="badge badge-status bg-<?= $device['status'] == 'sold' ? 'success' : ($device['status'] == 'maintenance' ? 'warning' : 'info') ?> fs-5 p-3">
                            <i class="fas fa-<?= $device['status'] == 'sold' ? 'shopping-cart' : ($device['status'] == 'maintenance' ? 'tools' : 'warehouse') ?>"></i>
                            <?= $status_labels[$device['status']] ?>
                        </span>
                        
                        <?php if ($device['current_hospital_id']): ?>
                        <div class="mt-3">
                            <i class="fas fa-hospital text-primary"></i>
                            <strong><?= htmlspecialchars($device['hospital_name']) ?></strong>
                            <?php if ($device['sold_date']): ?>
                            <br><small class="text-muted">تم البيع: <?= date('Y-m-d', strtotime($device['sold_date'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- كفالة الجهاز -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt"></i> حالة الكفالة</h5>
                    </div>
                    <div class="card-body text-center">
                        <p class="display-6 <?= $warranty['status'] == 'expired' ? 'text-danger' : ($warranty['status'] == 'expiring' ? 'text-warning' : 'text-success') ?>">
                            <?= $device['warranty_expiry_date'] ?>
                        </p>
                        <p class="text-muted"><?= $warranty['label'] ?></p>
                        <?php if ($warranty['status'] != 'expired'): ?>
                        <div class="progress mt-3" style="height: 8px;">
                            <div class="progress-bar bg-<?= $warranty['status'] == 'expiring' ? 'warning' : 'success' ?>" 
                                 style="width: <?= min(100, ($warranty['days'] / 365) * 100) ?>%">
                                <?= $warranty['days'] ?> يوم متبقية
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- التبويبات -->
            <div class="col-lg-8">
                <ul class="nav nav-tabs" id="deviceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="details-tab" data-bs-toggle="tab" data-bs-target="#details" type="button" role="tab">
                            <i class="fas fa-list"></i> التفاصيل
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button" role="tab">
                            <i class="fas fa-tools"></i> الصيانة (<span class="badge bg-warning"><?= count($maintenanceLogs) ?></span>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements" type="button" role="tab">
                            <i class="fas fa-exchange-alt"></i> حركة المخزون
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content mt-4">
                    <!-- تفاصيل الجهاز -->
                    <div class="tab-pane fade show active" id="details" role="tabpanel">
                        <div class="card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-building"></i> معلومات المورد</h5>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>اسم المورد:</th>
                                                <td><?= htmlspecialchars($device['supplier_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>تاريخ الشراء:</th>
                                                <td><?= date('Y-m-d', strtotime($device['purchase_date'])) ?></td>
                                            </tr>
                                            <tr>
                                                <th>مدة الكفالة:</th>
                                                <td><?= $device['warranty_months'] ?> شهر</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h5><i class="fas fa-hospital"></i> معلومات المشفى</h5>
                                        <?php if ($device['current_hospital_id']): ?>
                                        <table class="table table-sm">
                                            <tr>
                                                <th>اسم المشفى:</th>
                                                <td><?= htmlspecialchars($device['hospital_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <th>تاريخ البيع:</th>
                                                <td><?= $device['sold_date'] ? date('Y-m-d', strtotime($device['sold_date'])) : '-' ?></td>
                                            </tr>
                                            <tr>
                                                <th>سعر البيع:</th>
                                                <td><?= $device['sold_price'] ? number_format($device['sold_price'], 2) . ' ريال' : '-' ?></td>
                                            </tr>
                                        </table>
                                        <?php else: ?>
                                        <p class="text-muted">الجهاز حالياً في المخزن ولم يتم بيعه بعد</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($device['notes']): ?>
                                <div class="mt-4">
                                    <h5><i class="fas fa-sticky-note"></i> ملاحظات</h5>
                                    <p class="bg-light p-3 rounded"><?= nl2br(htmlspecialchars($device['notes'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- سجل الصيانة -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-tools"></i> سجل الصيانة والأعطال</h5>
                                <?php if ($_SESSION['role'] == 'rep' || $_SESSION['role'] == 'admin'): ?>
                                <button class="btn btn-sm btn-danger" onclick="location.href='maintenance.php?action=add&device_id=<?= $device['id'] ?>' + (<?= $device['current_hospital_id'] ? '&hospital_id=' . $device['current_hospital_id'] : '' ?>)">
                                    <i class="fas fa-plus"></i> تسجيل عطل جديد
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (count($maintenanceLogs) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>تاريخ البلاغ</th>
                                                <th>الوصف</th>
                                                <th>المندوب</th>
                                                <th>الحالة</th>
                                                <th>تفاصيل الإصلاح</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($maintenanceLogs as $log): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i', strtotime($log['reported_date'])) ?></td>
                                                <td><?= htmlspecialchars(substr($log['problem_description'], 0, 100)) ?>...</td>
                                                <td><?= htmlspecialchars($log['rep_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-<?= $log['status'] == 'resolved' ? 'success' : ($log['status'] == 'in_progress' ? 'warning' : 'danger') ?>">
                                                        <?= $maintenance_status[$log['status']] ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($log['resolution_notes']): ?>
                                                    <small class="text-success">
                                                        <i class="fas fa-check-circle"></i> 
                                                        <?= htmlspecialchars($log['resolution_notes']) ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            تم الإصلاح: <?= $log['resolved_date'] ? date('Y-m-d', strtotime($log['resolved_date'])) : '-' ?>
                                                        </small>
                                                    </small>
                                                    <?php else: ?>
                                                    <small class="text-muted">لا توجد تفاصيل إصلاح بعد</small>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php else: ?>
                                <p class="text-center text-muted py-4">لا توجد سجلات صيانة لهذا الجهاز</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- حركة المخزون -->
                    <div class="tab-pane fade" id="movements" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-exchange-alt"></i> سجل حركة المخزون</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>التاريخ</th>
                                                <th>نوع الحركة</th>
                                                <th>المخزن</th>
                                                <th>المندوب</th>
                                                <th>المشفى</th>
                                                <th>الكمية</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($stockMovements) > 0): ?>
                                                <?php foreach ($stockMovements as $movement): ?>
                                                <tr>
                                                    <td><?= date('Y-m-d H:i', strtotime($movement['created_at'])) ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $movement['movement_type'] == 'sale' ? 'success' : ($movement['movement_type'] == 'in' ? 'primary' : 'info') ?>">
                                                            <?= $movement['movement_type'] == 'in' ? 'استيراد' : ($movement['movement_type'] == 'sale' ? 'بيع' : ($movement['movement_type'] == 'return' ? 'إرجاع' : 'تعديل')) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $movement['from_status'] ? $status_labels[$movement['from_status']] : '-' ?> → <?= $status_labels[$movement['to_status']] ?></td>
                                                    <td><?= $movement['rep_name'] ?? '-' ?></td>
                                                    <td><?= $movement['hospital_name'] ?? '-' ?></td>
                                                    <td><?= $movement['quantity'] ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4">لا توجد حركات مخزون</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
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