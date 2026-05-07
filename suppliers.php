<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'name' => trim($_POST['name']),
            'contact_person' => trim($_POST['contact_person']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address' => trim($_POST['address']),
            'notes' => trim($_POST['notes'])
        ];
        
        $oldData = null;
        if ($supplierModel->create($data)) {
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'suppliers', $pdo->lastInsertId(), $oldData, $data);
            $_SESSION['success'] = 'تم إضافة المورد بنجاح';
            header('Location: suppliers.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء إضافة المورد';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $oldData = $supplierModel->getById($id);
        
        $data = [
            'name' => trim($_POST['name']),
            'contact_person' => trim($_POST['contact_person']),
            'phone' => trim($_POST['phone']),
            'email' => trim($_POST['email']),
            'address' => trim($_POST['address']),
            'notes' => trim($_POST['notes'])
        ];
        
        if ($supplierModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'suppliers', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث بيانات المورد بنجاح';
            header('Location: suppliers.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث البيانات';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $oldData = $supplierModel->getById($id);
        
        if ($supplierModel->canDelete($id)) {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            if ($stmt->execute([$id])) {
                logAction($pdo, $_SESSION['user_id'], 'DELETE', 'suppliers', $id, $oldData, null);
                $_SESSION['success'] = 'تم حذف المورد بنجاح';
                header('Location: suppliers.php');
                exit;
            } else {
                $_SESSION['error'] = 'حدث خطأ أثناء الحذف';
            }
        } else {
            $_SESSION['error'] = 'لا يمكن حذف هذا المورد لأنه مرتبط بأجهزة';
        }
    }
}

$suppliers = $supplierModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-truck"></i> إدارة الموردين</h2>
    <?php if ($_SESSION['role'] == 'admin'): ?>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fas fa-plus"></i> إضافة مورد
    </button>
    <?php endif; ?>
</div>

<div class="row">
    <?php foreach ($suppliers as $sup): 
        $deviceCount = $pdo->query("SELECT COUNT(*) FROM devices WHERE supplier_id = {$sup['id']}")->fetchColumn();
    ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="card-title mb-0"><?= htmlspecialchars($sup['name']) ?></h5>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="showEditSupplierModal(<?= $sup['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" style="display:inline" 
                              onsubmit="return confirm('هل أنت متأكد من حذف هذا المورد؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $sup['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                <?= $deviceCount > 0 ? 'disabled' : '' ?> title="<?= $deviceCount > 0 ? 'لا يمكن الحذف لأنه مرتبط بأجهزة' : '' ?>">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                
                <p class="card-text">
                    <?php if ($sup['contact_person']): ?>
                    <i class="fas fa-user"></i> 
                    <?= htmlspecialchars($sup['contact_person']) ?><br>
                    <?php endif; ?>
                    <?php if ($sup['phone']): ?>
                    <i class="fas fa-phone"></i> 
                    <?= htmlspecialchars($sup['phone']) ?><br>
                    <?php endif; ?>
                    <?php if ($sup['email']): ?>
                    <i class="fas fa-envelope"></i> 
                    <?= htmlspecialchars($sup['email']) ?><br>
                    <?php endif; ?>
                </p>
                
                <?php if ($sup['address']): ?>
                <hr>
                <p class="card-text small">
                    <i class="fas fa-map-marker-alt"></i> 
                    <?= nl2br(htmlspecialchars($sup['address'])) ?>
                </p>
                <?php endif; ?>
                
                <div class="mt-3">
                    <span class="badge bg-info">أجهزة: <?= $deviceCount ?></span>
                </div>
                
                <?php if ($sup['notes']): ?>
                <hr>
                <p class="card-text small text-muted">
                    <?= nl2br(htmlspecialchars($sup['notes'])) ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- مودال إضافة مورد -->
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مورد جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم المورد <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required>
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

<script>
function showEditSupplierModal(id) {
    alert('وظيفة تعديل المورد قيد التطوير');
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>