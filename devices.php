<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'serial_number' => trim($_POST['serial_number']),
            'model' => trim($_POST['model']),
            'brand' => trim($_POST['brand']),
            'supplier_id' => $_POST['supplier_id'],
            'purchase_date' => $_POST['purchase_date'],
            'warranty_months' => $_POST['warranty_months'],
            'purchase_price' => $_POST['purchase_price'],
            'notes' => trim($_POST['notes'])
        ];
        
        // حساب تاريخ انتهاء الكفالة
        $purchase = new DateTime($data['purchase_date']);
        $warranty = $purchase->add(new DateInterval('P' . $data['warranty_months'] . 'M'));
        $data['warranty_expiry_date'] = $warranty->format('Y-m-d');
        
        $oldData = null;
        $deviceId = null;
        
        if ($deviceModel->create($data)) {
            $deviceId = $pdo->lastInsertId();
            
            // تسجيل الحركة في المخزون
            $pdo->prepare("INSERT INTO stock_movements 
                (device_id, movement_type, from_status, to_status, quantity)
                VALUES (?, 'in', NULL, 'in_stock', 1)")
                ->execute([$deviceId]);
            
            // تسجيل في سجل النظام
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'devices', $deviceId, $oldData, $data);
            
            $_SESSION['success'] = 'تم إضافة الجهاز بنجاح';
            header('Location: devices.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء إضافة الجهاز';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $oldData = $deviceModel->getById($id);
        
        $data = [
            'model' => trim($_POST['model']),
            'brand' => trim($_POST['brand']),
            'supplier_id' => $_POST['supplier_id'],
            'purchase_date' => $_POST['purchase_date'],
            'warranty_months' => $_POST['warranty_months'],
            'warranty_expiry_date' => $_POST['warranty_expiry_date'],
            'purchase_price' => $_POST['purchase_price'],
            'notes' => trim($_POST['notes'])
        ];
        
        if ($deviceModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'devices', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث بيانات الجهاز بنجاح';
            header('Location: devices.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث البيانات';
        }
    } elseif ($action === 'sell') {
        $id = $_POST['id'];
        $oldData = $deviceModel->getById($id);
        
        if ($deviceModel->sell($id, $_POST['hospital_id'], $_POST['rep_id'] ?? null, 
            $_POST['sold_date'] ?? null, $_POST['sold_price'] ?? null)) {
            
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'devices', $id, $oldData, ['status' => 'sold']);
            $_SESSION['success'] = 'تم تسجيل البيع بنجاح';
            header('Location: devices.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تسجيل البيع';
        }
    } elseif ($action === 'return') {
        $id = $_POST['id'];
        $oldData = $deviceModel->getById($id);
        
        if ($deviceModel->returnToStock($id)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'devices', $id, $oldData, ['status' => 'in_stock']);
            $_SESSION['success'] = 'تم إرجاع الجهاز للمخزن بنجاح';
            header('Location: devices.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء عملية الإرجاع';
        }
    } elseif ($action === 'maintenance') {
        $id = $_POST['id'];
        $data = [
            'device_id' => $id,
            'hospital_id' => $_POST['hospital_id'],
            'assigned_rep_id' => $_POST['assigned_rep_id'],
            'problem_description' => trim($_POST['problem_description']),
            'reported_by' => $_SESSION['user_id']
        ];
        
        $maintModel = new Maintenance($pdo);
        if ($maintModel->create($data)) {
            // تحديث حالة الجهاز للصيانة
            $deviceModel->update($id, ['status' => 'maintenance']);
            
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'maintenance_logs', $pdo->lastInsertId(), null, $data);
            $_SESSION['success'] = 'تم تسجيل العطل بنجاح';
            header('Location: devices.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تسجيل العطل';
        }
    }
}

$suppliers = $supplierModel->getAll();
$hospitals = $hospitalModel->getAll();
$reps = $userModel->getReps();

if ($action === 'list') {
    $filters = [];
    
    if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
    if (!empty($_GET['model'])) $filters['model'] = $_GET['model'];
    if (!empty($_GET['serial'])) $filters['serial'] = $_GET['serial'];
    if (!empty($_GET['hospital_id'])) $filters['hospital_id'] = $_GET['hospital_id'];
    
    $devices = $deviceModel->getAll($filters);
} else {
    $device = null;
    if (isset($_GET['id'])) {
        $device = $deviceModel->getById($_GET['id']);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-warehouse"></i> إدارة المخزون</h2>
    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'storekeeper'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDeviceModal">
        <i class="fas fa-plus"></i> إضافة جهاز جديد
    </button>
    <?php endif; ?>
</div>

<!-- الفلاتر -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="serial" class="form-control" 
                    placeholder="الرقم التسلسلي" value="<?= htmlspecialchars($_GET['serial'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <input type="text" name="model" class="form-control" 
                    placeholder="الموديل" value="<?= htmlspecialchars($_GET['model'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">جميع الحالات</option>
                    <option value="in_stock" <?= ($_GET['status'] ?? '') == 'in_stock' ? 'selected' : '' ?>>في المخزن</option>
                    <option value="sold" <?= ($_GET['status'] ?? '') == 'sold' ? 'selected' : '' ?>>مباعة</option>
                    <option value="maintenance" <?= ($_GET['status'] ?? '') == 'maintenance' ? 'selected' : '' ?>>تحت الصيانة</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="hospital_id" class="form-select">
                    <option value="">جميع المشافي</option>
                    <?php foreach ($hospitals as $hosp): ?>
                    <option value="<?= $hosp['id'] ?>" <?= ($_GET['hospital_id'] ?? '') == $hosp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($hosp['name']) ?>
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

<!-- جدول الأجهزة -->
<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>الرقم التسلسلي</th>
                <th>الموديل</th>
                <th>العلامة التجارية</th>
                <th>المورد</th>
                <th>التاريخ</th>
                <th>الحالة</th>
                <th>الكفالة</th>
                <th>المشفى</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($devices) > 0): ?>
                <?php foreach ($devices as $dev): 
                    $warranty = getWarrantyStatus($dev['warranty_expiry_date']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($dev['serial_number']) ?></strong></td>
                    <td><?= htmlspecialchars($dev['model']) ?></td>
                    <td><?= htmlspecialchars($dev['brand']) ?></td>
                    <td><?= htmlspecialchars($dev['supplier_name']) ?></td>
                    <td><?= date('Y-m-d', strtotime($dev['purchase_date'])) ?></td>
                    <td>
                        <span class="badge bg-<?= $dev['status'] == 'sold' ? 'success' : ($dev['status'] == 'maintenance' ? 'warning' : 'info') ?>">
                            <?= $status_labels[$dev['status']] ?>
                        </span>
                    </td>
                    <td class="<?= $warranty['status'] == 'expired' ? 'warranty-expired' : ($warranty['status'] == 'expiring' ? 'warranty-expiring' : 'warranty-valid') ?>">
                        <?= $dev['warranty_expiry_date'] ?> (<?= $warranty['label'] ?>)
                    </td>
                    <td><?= $dev['hospital_name'] ?? '-' ?></td>
                    <td>
                        <div class="btn-group">
                            <a href="device_card.php?id=<?= $dev['id'] ?>" class="btn btn-sm btn-info" title="بطاقة الجهاز">
                                <i class="fas fa-id-card"></i>
                            </a>
                            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'storekeeper'): ?>
                            <button class="btn btn-sm btn-warning" onclick="showEditModal(<?= $dev['id'] ?>)" title="تعديل">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($dev['status'] == 'sold'): ?>
                            <form method="POST" style="display:inline" onsubmit="return confirm('هل أنت متأكد من الإرجاع؟')">
                                <input type="hidden" name="action" value="return">
                                <input type="hidden" name="id" value="<?= $dev['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-secondary" title="إرجاع للمخزن">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($_SESSION['role'] == 'rep' || $_SESSION['role'] == 'admin'): ?>
                            <button class="btn btn-sm btn-danger" onclick="showAddMaintenanceModal(<?= $dev['id'] ?>)" title="تسجيل عطل">
                                <i class="fas fa-tools"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center py-4">لا توجد أجهزة في النظام</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- مودال إضافة جهاز -->
<div class="modal fade" id="addDeviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة جهاز جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">الرقم التسلسلي <span class="text-danger">*</span></label>
                        <input type="text" name="serial_number" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">الموديل <span class="text-danger">*</span></label>
                            <input type="text" name="model" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">العلامة التجارية</label>
                            <input type="text" name="brand" class="form-control">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المورد</label>
                        <select name="supplier_id" class="form-select">
                            <option value="">بدون مورد</option>
                            <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['id'] ?>"><?= htmlspecialchars($sup['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">تاريخ الشراء <span class="text-danger">*</span></label>
                            <input type="date" name="purchase_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">مدة الكفالة (شهر) <span class="text-danger">*</span></label>
                            <input type="number" name="warranty_months" class="form-control" value="24" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">سعر الشراء</label>
                        <input type="number" name="purchase_price" class="form-control" step="0.01">
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>