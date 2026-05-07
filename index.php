<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

// إحصائيات لوحة التحكم
$expiringDevices = count($deviceModel->getExpiringWarranty(30));
$pendingMaintenance = count($maintenanceModel->getAll(['status' => 'pending']));
$inProgressMaintenance = count($maintenanceModel->getAll(['status' => 'in_progress']));

$devices = $deviceModel->getAll();
$inStockCount = count(array_filter($devices, fn($d) => $d['status'] == 'in_stock'));
$soldCount = count(array_filter($devices, fn($d) => $d['status'] == 'sold'));
$maintenanceCount = count(array_filter($devices, fn($d) => $d['status'] == 'maintenance'));

// تنبيهات المخزون
$minmaxAlerts = $minMaxModel->getAlerts();

// الأعطال القادمة
$upcomingMaintenance = $maintenanceModel->getAll(['status' => 'pending']);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h2><i class="fas fa-tachometer-alt"></i> لوحة التحكم الرئيسية</h2>
        <p class="text-muted">مرحباً بك، <?= htmlspecialchars($_SESSION['full_name']) ?> - <?= $roles[$_SESSION['role']] ?></p>
    </div>
</div>

<!-- تنبيهات المخزون -->
<?php if (count($minmaxAlerts) > 0 && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'storekeeper')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-exclamation-triangle"></i> تنبيهات المخزون
            </div>
            <div class="card-body">
                <?php foreach ($minmaxAlerts as $alert): ?>
                <div class="alert alert-<?= $alert['type'] == 'min' ? 'danger' : 'warning' ?> alert-minmax">
                    <i class="fas fa-<?= $alert['type'] == 'min' ? 'exclamation-circle' : 'exclamation-triangle' ?>"></i>
                    <strong><?= htmlspecialchars($alert['model']) ?></strong>: <?= $alert['message'] ?>
                    <small class="float-end">الكمية الحالية: <?= $alert['current_qty'] ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- الإحصائيات -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card primary text-center">
            <h3 class="display-4"><?= $inStockCount ?></h3>
            <p class="mb-0">أجهزة في المخزون</p>
            <i class="fas fa-warehouse fa-2x mt-2"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card success text-center">
            <h3 class="display-4"><?= $soldCount ?></h3>
            <p class="mb-0">أجهزة مباعة</p>
            <i class="fas fa-shopping-cart fa-2x mt-2"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card warning text-center">
            <h3 class="display-4"><?= $maintenanceCount ?></h3>
            <p class="mb-0">أجهزة تحت الصيانة</p>
            <i class="fas fa-tools fa-2x mt-2"></i>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card danger text-center">
            <h3 class="display-4"><?= count($deviceModel->getExpiringWarranty(30)) ?></h3>
            <p class="mb-0">كفاليات تنتهي قريباً</p>
            <i class="fas fa-clock fa-2x mt-2"></i>
        </div>
    </div>
</div>

<div class="row">
    <!-- الأعطال القريبة -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <i class="fas fa-tools"></i> الأعطال المعلقة (<?= $pending ?>)
            </div>
            <div class="card-body">
                <?php if (count($upcomingMaintenance) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>الجهاز</th>
                                    <th>المشفى</th>
                                    <th>المندوب</th>
                                    <th>التاريخ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($upcomingMaintenance, 0, 5) as $maint): ?>
                                <tr>
                                    <td><?= htmlspecialchars($maint['serial_number']) ?></td>
                                    <td><?= htmlspecialchars($maint['hospital_name']) ?></td>
                                    <td><?= htmlspecialchars($maint['rep_name']) ?></td>
                                    <td><?= date('Y-m-d', strtotime($maint['reported_date'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted my-4">لا توجد أعطال معلقة حالياً</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- الأجهزة قريبة انتهاء الكفالة -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <i class="fas fa-clock"></i> كفاليات تنتهي خلال 30 يوماً
            </div>\n            <div class="card-body">
                <?php 
                $expiring = $deviceModel->getExpiringWarranty(30);
                if (count($expiring) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>الجهاز</th>
                                    <th>الموديل</th>
                                    <th>تاريخ الانتهاء</th>
                                    <th>المشفى</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($expiring as $dev): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dev['serial_number']) ?></td>
                                    <td><?= htmlspecialchars($dev['model']) ?></td>
                                    <td class="warranty-expiring"><?= $dev['warranty_expiry_date'] ?></td>
                                    <td><?= $dev['hospital_name'] ?? 'في المخزن' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted my-4">لا توجد أجهزة بكفاليات تنتهي قريباً</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- الأداء -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <i class="fas fa-chart-line"></i> تقرير الأداء السريع
            </div>
            <div class="card-body">
                <canvas id="performanceChart" height="200"></canvas>
            </div>
        </div>
        
        <?php if ($_SESSION['role'] == 'admin'): ?>
        <!-- أداء المندوبين -->
        <div class="card">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-user-tie"></i> أداء المندوبين
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>المندوب</th>
                                <th>إجمالي الأعطال</th>
                                <th>منتهية</th>
                                <th>قيد الانتظار</th>
                                <th>متوسط الوقت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $perf = $maintenanceModel->getPerformanceStats();
                            foreach ($perf as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['rep_name']) ?></td>
                                <td><?= $p['total'] ?></td>
                                <td><?= $p['resolved'] ?></td>
                                <td><span class="badge bg-warning"><?= $p['pending'] + $p['in_progress'] ?></span></td>
                                <td><?= $p['avg_hours_to_resolve'] ? round($p['avg_hours_to_resolve']) . ' ساعة' : 'N/A' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// رسم مخطط الأداء
const ctx = document.getElementById('performanceChart').getContext('2d');
new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['في المخزون', 'مباعة', 'تحت الصيانة'],
        datasets: [{
            data: [<?= $inStockCount ?>, <?= $soldCount ?>, <?= $maintenanceCount ?>],
            backgroundColor: ['#3498db', '#2ecc71', '#f39c12']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>