<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$export_type = $_GET['export_type'] ?? '';

if ($export_type === 'csv' || $export_type === 'pdf') {
    // إعدادات التصدير
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // رأس الأعمدة
    fputcsv($output, ['النوع', 'المبلغ', 'الكمية', 'التاريخ']);
    
    // إجمالي الأجهزة المباعة
    $sold_devices = $deviceModel->getAll(['status' => 'sold']);
    foreach ($sold_devices as $dev) {
        fputcsv($output, [
            'جهاز مباع',
            $dev['sold_price'] ?? 0,
            1,
            $dev['sold_date']
        ]);
    }
    
    fclose($output);
    exit;
}

// إحصائيات التقارير
$stockSummary = $deviceModel->getStockSummary();
$expiringWarranty = $deviceModel->getExpiringWarranty(30);
$performanceStats = $maintenanceModel->getPerformanceStats();

// الأجهزة الأكثر تعرضاً للأعطال
$mostProblematicDevices = $pdo->query("SELECT d.serial_number, d.model, COUNT(*) as fault_count
    FROM maintenance_logs ml
    JOIN devices d ON ml.device_id = d.id
    GROUP BY d.id
    ORDER BY fault_count DESC
    LIMIT 5")->fetchAll();

// إجماليات عامة
$totalDevices = count($deviceModel->getAll());
$totalSold = count($deviceModel->getAll(['status' => 'sold']));
$totalInStock = count($deviceModel->getAll(['status' => 'in_stock']));
$totalMaintenance = count($deviceModel->getAll(['status' => 'maintenance']));
$totalReportedIssues = count($maintenanceModel->getAll());
$totalResolvedIssues = count($maintenanceModel->getAll(['status' => 'resolved']));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</h2>
    <div class="btn-group">
        <a href="?export_type=csv" class="btn btn-success">
            <i class="fas fa-file-csv"></i> تصدير CSV
        </a>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> طباعة
        </button>
    </div>
</div>

<!-- الإحصائيات العامة -->
<div class="row mb-4">
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-primary"><?= $totalDevices ?></div>
                <div class="text-muted small">إجمالي الأجهزة</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-success"><?= $totalInStock ?></div>
                <div class="text-muted small">في المخزن</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-info"><?= $totalSold ?></div>
                <div class="text-muted small">مباعة</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-warning"><?= $totalMaintenance ?></div>
                <div class="text-muted small">تحت الصيانة</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-danger"><?= $totalReportedIssues ?></div>
                <div class="text-muted small">الأعطال المبلغ عنها</div>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6 mb-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="display-6 text-success"><?= $totalResolvedIssues ?></div>
                <div class="text-muted small">الأعطال المنجزة</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- تقرير المخزون -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-warehouse"></i> تقرير حالة المخزون</h5>
            </div>
            <div class="card-body">
                <canvas id="stockChart" height="250"></canvas>
                <hr>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>الموديل</th>
                            <th>الكمية</th>
                            <th>نسبة التغطية</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockSummary as $item): 
                            $total = $item['total'];
                            $inStock = $item['in_stock'];
                            $percentage = $total > 0 ? round(($inStock / $total) * 100) : 0;
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($item['model']) ?></td>
                            <td><?= $total ?></td>
                            <td>
                                <div class="progress" style="height: 10px;">
                                    <div class="progress-bar bg-<?= $percentage < 50 ? 'danger' : ($percentage < 80 ? 'warning' : 'success') ?>" 
                                         style="width: <?= $percentage ?>%">
                                        <?= $percentage ?>%
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- أداء المندوبين -->
    <div class="col-lg-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-user-tie"></i> تقرير أداء المندوبين</h5>
            </div>
            <div class="card-body">
                <canvas id="repChart" height="250"></canvas>
                <hr>
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>المندوب</th>
                            <th>إجمالي الأعطال</th>
                            <th>المنجزة</th>
                            <th>قيد الانتظار</th>
                            <th>متوسط الوقت</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($performanceStats as $stat): ?>
                        <tr>
                            <td><?= htmlspecialchars($stat['rep_name']) ?></td>
                            <td><?= $stat['total'] ?></td>
                            <td class="text-success"><?= $stat['resolved'] ?></td>
                            <td class="text-warning"><?= $stat['pending'] + $stat['in_progress'] ?></td>
                            <td><?= $stat['avg_hours_to_resolve'] ? round($stat['avg_hours_to_resolve']) . ' ساعة' : 'N/A' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- الأجهزة الأكثر تعرضاً للأعطال -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> الأجهزة الأكثر تعرضاً للأعطال (Top 5)</h5>
            </div>
            <div class="card-body">
                <canvas id="faultChart" height="200"></canvas>
                <hr>
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الترتيب</th>
                            <th>الجهاز</th>
                            <th>الموديل</th>
                            <th>عدد الأعطال</th>
                            <th>آخر عطل</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mostProblematicDevices as $index => $dev): 
                            $lastFault = $pdo->query("SELECT resolved_date FROM maintenance_logs WHERE device_id = {$dev['device_id']} ORDER BY created_at DESC LIMIT 1")->fetchColumn();
                        ?>
                        <tr>
                            <td><span class="badge bg-danger"><?= $index + 1 ?></span></td>
                            <td><strong><?= htmlspecialchars($dev['serial_number']) ?></strong></td>
                            <td><?= htmlspecialchars($dev['model']) ?></td>
                            <td><span class="badge bg-danger"><?= $dev['fault_count'] ?></span></td>
                            <td><?= $lastFault ? date('Y-m-d', strtotime($lastFault)) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- كفاليات تنتهي قريباً -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-clock"></i> أجهزة بكفاليات تنتهي خلال 30 يوماً</h5>
            </div>
            <div class="card-body">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>الرقم التسلسلي</th>
                            <th>الموديل</th>
                            <th>تاريخ انتهاء الكفالة</th>
                            <th>المشفى</th>
                            <th>الوقت المتبقي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiringWarranty as $dev): 
                            $daysLeft = ceil((strtotime($dev['warranty_expiry_date']) - time()) / (60 * 60 * 24));
                        ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($dev['serial_number']) ?></strong></td>
                            <td><?= htmlspecialchars($dev['model']) ?></td>
                            <td class="warranty-expiring"><?= $dev['warranty_expiry_date'] ?></td>
                            <td><?= $dev['hospital_name'] ?? 'في المخزون' ?></td>
                            <td class="text-danger"><strong><?= $daysLeft ?> يوم</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// مخطط حالة المخزون
const stockCtx = document.getElementById('stockChart').getContext('2d');
new Chart(stockCtx, {
    type: 'doughnut',
    data: {
        labels: ['في المخزن', 'مباعة', 'تحت الصيانة'],
        datasets: [{
            data: [<?= $totalInStock ?>, <?= $totalSold ?>, <?= $totalMaintenance ?>],
            backgroundColor: ['#28a745', '#17a2b8', '#ffc107']
        }]
    },
    options: {
        responsive: true
    }
});

// مخطط أداء المندوبين
const repCtx = document.getElementById('repChart').getContext('2d');
new Chart(repCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($performanceStats as $s): ?>"<?= htmlspecialchars($s['rep_name']) ?>",<?php endforeach; ?>],
        datasets: [{
            label: 'تم الإصلاح',
            data: [<?php foreach ($performanceStats as $s): ?><?= $s['resolved'] ?>,<?php endforeach; ?>],
            backgroundColor: '#28a745'
        }, {
            label: 'قيد الانتظار/الإصلاح',
            data: [<?php foreach ($performanceStats as $s): ?><?= $s['pending'] + $s['in_progress'] ?>,<?php endforeach; ?>],
            backgroundColor: '#ffc107'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});

// مخطط الأجهزة المشكلة
const faultCtx = document.getElementById('faultChart').getContext('2d');
new Chart(faultCtx, {
    type: 'bar',
    data: {
        labels: [<?php foreach ($mostProblematicDevices as $d): ?>"<?= htmlspecialchars($d['serial_number']) ?>",<?php endforeach; ?>],
        datasets: [{
            label: 'عدد الأعطال',
            data: [<?php foreach ($mostProblematicDevices as $d): ?><?= $d['fault_count'] ?>,<?php endforeach; ?>],
            backgroundColor: '#dc3545'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>