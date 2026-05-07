<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'name' => trim($_POST['name']),
            'rep_id' => !empty($_POST['rep_id']) ? $_POST['rep_id'] : null,
            'contact_person' => trim($_POST['contact_person']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address' => trim($_POST['address']),
            'notes' => trim($_POST['notes'])
        ];
        
        $oldData = null;
        $newId = null;
        
        if ($hospitalModel->create($data)) {
            $newId = $pdo->lastInsertId();
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'hospitals', $newId, $oldData, $data);
            $_SESSION['success'] = 'تم إضافة المشفى بنجاح';
            header('Location: hospitals.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء إضافة المشفى';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $oldData = $hospitalModel->getById($id);
        
        $data = [
            'name' => trim($_POST['name']),
            'rep_id' => !empty($_POST['rep_id']) ? $_POST['rep_id'] : null,
            'contact_person' => trim($_POST['contact_person']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address' => trim($_POST['address']),
            'notes' => trim($_POST['notes'])
        ];
        
        if ($hospitalModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'hospitals', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث بيانات المشفى بنجاح';
            header('Location: hospitals.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث البيانات';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $oldData = $hospitalModel->getById($id);
        
        if ($hospitalModel->canDelete($id)) {
            $stmt = $pdo->prepare("DELETE FROM hospitals WHERE id = ?");
            if ($stmt->execute([$id])) {
                logAction($pdo, $_SESSION['user_id'], 'DELETE', 'hospitals', $id, $oldData, null);
                $_SESSION['success'] = 'تم حذف المشفى بنجاح';
                header('Location: hospitals.php');
                exit;
            } else {
                $_SESSION['error'] = 'حدث خطأ أثناء الحذف';
            }
        } else {
            $_SESSION['error'] = 'لا يمكن حذف هذا المشفى لأنه مرتبط بأجهزة أو أعطال';
        }
    }
}

$hospitals = $hospitalModel->getAll();
$reps = $userModel->getReps();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-hospital"></i> إدارة المشافي</h2>
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHospitalModal">
        <i class="fas fa-plus"></i> إضافة مشفى
    </button>
    <?php endif; ?>
</div>

<div class="row">
    <?php foreach ($hospitals as $hosp): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0"><?= htmlspecialchars($hosp['name']) ?></h5>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="showEditHospitalModal(<?= $hosp['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline" 
                              onsubmit="return confirm('هل أنت متأكد من حذف هذا المشفى؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $hosp['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <p class="card-text">
                    <i class="fas fa-user-md text-primary"></i> 
                    <?= htmlspecialchars($hosp['contact_person']) ?><br>
                    <i class="fas fa-phone text-primary"></i> 
                    <?= htmlspecialchars($hosp['phone']) ?><br>
                    <?php if ($hosp['email']): ?>
                    <i class="fas fa-envelope text-primary"></i> 
                    <?= htmlspecialchars($hosp['email']) ?><br>
                    <?php endif; ?>
                    <?php if ($hosp['rep_name']): ?>
                    <i class="fas fa-user-tie text-primary"></i> 
                    المندوب: <?= htmlspecialchars($hosp['rep_name']) ?>
                    <?php endif; ?>
                </p>
                
                <?php if ($hosp['address']): ?>
                <hr>
                <p class="card-text small">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?= nl2br(htmlspecialchars($hosp['address'])) ?>
                </p>
                <?php endif; ?>
                
                <?php 
                $devices = $deviceModel->getAll(['hospital_id' => $hosp['id']]);
                $pendingMaint = $maintenanceModel->getAll(['hospital_id' => $hosp['id'], 'status' => 'pending']);
                ?>
                
                <div class="mt-3">
                    <span class="badge bg-info">أجهزة: <?= count($devices) ?></span>
                    <?php if (count($pendingMaint) > 0): ?>
                    <span class="badge bg-warning">أعطال: <?= count($pendingMaint) ?></span>
                    <?php endif; ?>
                </div>
                
                <a href="devices.php?hospital_id=<?= $hosp['id'] ?>" class="btn btn-sm btn-outline-primary w-100 mt-2">
                    <i class="fas fa-eye"></i> عرض الأجهزة
                </a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($_SESSION['role'] == 'admin'): ?>
<!-- مودال إضافة مشفى -->
<div class="modal fade" id="addHospitalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مشفى جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم المشفى <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">المندوب المسؤول</label>
                        <select name="rep_id" class="form-select">
                            <option value="">بدون مندوب</option>
                            <?php foreach ($reps as $rep): ?>
                            <option value="<?= $rep['id'] ?>"><?= htmlspecialchars($rep['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الجهة المسؤولة</label>
                        <input type="text" name="contact_person" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">العنوان</label>
                        <textarea name="address" class="form-control"></textarea>
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
<?php endif; ?>

<script>
// دالة عرض مودال التعديل
function showEditHospitalModal(id) {
    // يمكن توسيعها لجلب بيانات المشفى وتعبئة المودال
    alert('وظيفة تعديل المشفى قيد التطوير');
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>