<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();
checkRole(['admin', 'rep', 'storekeeper']);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'device_id' => $_POST['device_id'],
            'hospital_id' => $_POST['hospital_id'],
            'reported_by' => $_SESSION['user_id'],
            'assigned_rep_id' => $_POST['assigned_rep_id'] ?? $_SESSION['user_id'],
            'problem_description' => trim($_POST['problem_description']),
            'status' => 'pending'
        ];
        
        $oldData = null;
        if ($maintenanceModel->create($data)) {
            // تحديث حالة الجهاز للصيانة
            $deviceModel->update($_POST['device_id'], ['status' => 'maintenance']);
            
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'maintenance_logs', $pdo->lastInsertId(), $oldData, $data);
            $_SESSION['success'] = 'تم تسجيل العطل بنجاح';
            header('Location: maintenance.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تسجيل العطل';
        }
    } elseif ($action === 'update_status') {
        $id = $_POST['id'];
        $oldData = $maintenanceModel->getById($id);
        
        $data = [
            'status' => $_POST['status'],
            'resolution_notes' => $_POST['resolution_notes'] ?? null,
            'resolved_date' => ($_POST['status'] == 'resolved') ? date('Y-m-d H:i:s') : null
        ];
        
        // إذا تم الإصلاح، إرجاع الجهاز للمخزن
        if ($_POST['status'] == 'resolved') {
            $maint = $maintenanceModel->getById($id);
            $deviceModel->update($maint['device_id'], ['status' => 'in_stock', 'current_hospital_id' => null]);
        }
        
        if ($maintenanceModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'maintenance_logs', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث حالة العطل بنجاح';
            header('Location: maintenance.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث الحالة';
        }
    }
}

$filters = [];
if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
if (!empty($_GET['device_id'])) $filters['device_id'] = $_GET['device_id'];

// تحديد الأعطال المعروضة بناءً على الصلاحية
if ($_SESSION['role'] == 'rep') {
    $maintenances = $maintenanceModel->getByRep($_SESSION['user_id']);
    $allMaintenances = $maintenanceModel->getAll($filters);
    // تصفية الأعطال التي لا يغطيها هذا المندوب
    $allMaintenances = array_filter($allMaintenances, function($m) {
        return $m['assigned_rep_id'] == $_SESSION['user_id'];
    });
} else {
    $allMaintenances = $maintenanceModel->getAll($filters);
    $maintenances = $allMaintenances;
}

$devices = $deviceModel->getAll(['status' => 'sold']);
$reps = $userModel->getReps();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-tools"></i> إدارة الصيانة والأعطال</h2>
    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'rep'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
        <i class="fas fa-plus"></i> تسجيل عطل جديد
    </button>
    <?php endif; ?>
</div>

<!-- الفلاتر -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    <option value="pending" <?= ($_GET['status'] ?? '') == 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                    <option value="in_progress" <?= ($_GET['status'] ?? '') == 'in_progress' ? 'selected' : '' ?>>قيد الإصلاح</option>
                    <option value="resolved" <?= ($_GET['status'] ?? '') == 'resolved' ? 'selected' : '' ?>>تم الإصلاح</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="device_id" class="form-select">
                    <option value="">جميع الأجهزة</option>
                    <?php foreach ($devices as $dev): ?>
                    <option value="<?= $dev['id'] ?>" <?= ($_GET['device_id'] ?? '') == $dev['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($dev['serial_number']) ?> - <?= htmlspecialchars($dev['model']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i> تصفية</button>
            </div>
        </form>
    </div>
</div>

<!-- جدول الأعطال -->
<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>الرقم</th>
                <th>الجهاز</th>
                <th>المشفى</th>
                <th>المندوب</th>
                <th>تاريخ البلاغ</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($allMaintenances) > 0): ?>
                <?php foreach ($allMaintenances as $maint): ?>
                <tr>
                    <td><?= $maint['id'] ?></td>
                    <td>
                        <strong><?= htmlspecialchars($maint['serial_number']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($maint['model']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($maint['hospital_name']) ?></td>
                    <td><?= htmlspecialchars($maint['rep_name']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($maint['reported_date'])) ?></td>
                    <td>
                        <span class="badge bg-<?= $maint['status'] == 'resolved' ? 'success' : ($maint['status'] == 'in_progress' ? 'warning' : 'danger') ?>">
                            <?= $maintenance_status[$maint['status']] ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="maintenance_card.php?id=<?= $maint['id'] ?>" class="btn btn-sm btn-info" title="التفاصيل">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if ($maint['status'] != 'resolved' && ($_SESSION['role'] == 'admin' || ($_SESSION['role'] == 'rep' && $maint['assigned_rep_id'] == $_SESSION['user_id']))): ?>
                            <button class="btn btn-sm btn-warning" onclick="showUpdateStatusModal(<?= $maint['id'] ?>)" title="تحديث الحالة">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center py-4">لا توجد أعطال مسجلة</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- مودال تسجيل عطل -->
<div class="modal fade" id="addMaintenanceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">تسجيل عطل جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الجهاز <span class="text-danger">*</span></label>
                        <select name="device_id" class="form-select" required onchange="updateHospitalInfo()">
                            <option value="">اختر الجهاز</option>
                            <?php foreach ($devices as $dev): ?>
                            <option value="<?= $dev['id'] ?>" data-hospital="<?= $dev['hospital_id'] ?? '' ?>">
                                <?= htmlspecialchars($dev['serial_number']) ?> - <?= htmlspecialchars($dev['model']) ?>
                                (<?= $dev['hospital_name'] ?? 'في المخزن' ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المشفى</label>
                        <select name="hospital_id" class="form-select" required>
                            <option value="">اختر المشفى</option>
                            <?php foreach ($hospitals as $hosp): ?>
                            <option value="<?= $hosp['id'] ?>"><?= htmlspecialchars($hosp['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المندوب المسؤول</label>
                        <select name="assigned_rep_id" class="form-select">
                            <option value="<?= $_SESSION['user_id'] ?>">أنا (<?= htmlspecialchars($_SESSION['full_name']) ?>)</option>
                            <?php foreach ($reps as $rep): ?>
                                <?php if ($rep['id'] != $_SESSION['user_id']): ?>
                                <option value="<?= $rep['id'] ?>"><?= htmlspecialchars($rep['full_name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">وصف المشكلة <span class="text-danger">*</span></label>
                        <textarea name="problem_description" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تسجيل العطل</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- مودال تحديث حالة العطل -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=update_status">
                <input type="hidden" name="id" id="update_status_id">
                <div class="modal-header">
                    <h5 class="modal-title">تحديث حالة العطل</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الحالة <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <option value="in_progress">قيد الإصلاح</option>
                            <option value="resolved">تم الإصلاح</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات الإصلاح</label>
                        <textarea name="resolution_notes" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">تحديث</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateHospitalInfo() {
    const select = document.querySelector('select[name="device_id"]');
    const hospitalSelect = document.querySelector('select[name="hospital_id"]');
    const selectedOption = select.options[select.selectedIndex];
    const hospitalId = selectedOption.dataset.hospital;
    
    if (hospitalId) {
        hospitalSelect.value = hospitalId;
    }
}

function showUpdateStatusModal(id) {
    document.getElementById('update_status_id').value = id;
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>