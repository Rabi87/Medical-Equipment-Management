<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();
checkRole(['admin', 'storekeeper']);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'model' => trim($_POST['model']),
            'min_qty' => $_POST['min_qty'],
            'max_qty' => $_POST['max_qty'],
            'notes' => trim($_POST['notes'])
        ];
        
        $oldData = null;
        if ($minMaxModel->create($data)) {
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'minmax_settings', $pdo->lastInsertId(), $oldData, $data);
            $_SESSION['success'] = 'تم إضافة الإعداد بنجاح';
            header('Location: minmax.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء إضافة الإعداد';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $oldData = $minMaxModel->getById($id);
        
        $data = [
            'model' => trim($_POST['model']),
            'min_qty' => $_POST['min_qty'],
            'max_qty' => $_POST['max_qty'],
            'notes' => trim($_POST['notes'])
        ];
        
        if ($minMaxModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'minmax_settings', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث الإعداد بنجاح';
            header('Location: minmax.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث البيانات';
        }
    }
}

$minmaxSettings = $minMaxModel->getAll();
$alerts = $minMaxModel->getAlerts();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-chart-line"></i> إعدادات Min/Max للمخزون</h2>
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMinMaxModal">
        <i class="fas fa-plus"></i> إضافة إعداد جديد
    </button>
    <?php endif; ?>
</div>

<!-- تنبيهات المخزون -->
<?php if (count($alerts) > 0): ?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <h4><i class="fas fa-exclamation-triangle"></i> تنبيهات مخزون نشطة</h4>
    <?php foreach ($alerts as $alert): ?>
    <div class="alert alert-<?= $alert['type'] == 'min' ? 'danger' : 'warning' ?> d-flex align-items-center">
        <i class="fas fa-<?= $alert['type'] == 'min' ? 'exclamation-circle' : 'exclamation-triangle' ?> fa-lg me-2"></i>
        <div>
            <strong><?= htmlspecialchars($alert['model']) ?></strong>: <?= $alert['message'] ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- جدول الإعدادات -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">إعدادات Min/Max لكل موديل</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th>الموديل</th>
                    <th>الحد الأدنى</th>
                    <th>الحد الأقصى</th>
                    <th>الكمية الحالية</th>
                    <th>ملاحظات</th>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <th>الإجراءات</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($minmaxSettings as $setting): 
                    $currentQty = $pdo->query("SELECT COUNT(*) FROM devices WHERE model = '" . $setting['model'] . "' AND status = 'in_stock'")->fetchColumn();
                    $isAlert = $currentQty < $setting['min_qty'] || $currentQty > $setting['max_qty'];
                ?>
                <tr class="<?= $isAlert ? 'table-warning' : '' ?>">
                    <td><strong><?= htmlspecialchars($setting['model']) ?></strong></td>
                    <td><?= $setting['min_qty'] ?></td>
                    <td><?= $setting['max_qty'] ?></td>
                    <td class="<?= $isAlert ? 'fw-bold' : '' ?>">
                        <?= $currentQty ?>
                        <?php if ($currentQty < $setting['min_qty']): ?>
                            <span class="badge bg-danger">أقل من الحد</span>
                        <?php elseif ($currentQty > $setting['max_qty']): ?>
                            <span class="badge bg-warning">تجاوز الحد</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($setting['notes']) ?></td>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <td>
                        <button class="btn btn-sm btn-warning" onclick="showEditMinMaxModal(<?= $setting['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- مودال إضافة/تعديل إعداد -->
<div class="modal fade" id="addMinMaxModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة إعداد Min/Max جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الموديل <span class="text-danger">*</span></label>
                        <input type="text" name="model" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحد الأدنى <span class="text-danger">*</span></label>
                            <input type="number" name="min_qty" class="form-control" min="0" required value="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الحد الأقصى <span class="text-danger">*</span></label>
                            <input type="number" name="max_qty" class="form-control" min="1" required value="10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-primary">إضافة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showEditMinMaxModal(id) {
    alert('وظيفة تعديل الإعدادات قيد التطوير');
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>