<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();
checkRole(['admin']);

$action = $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add') {
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'full_name' => trim($_POST['full_name']),
            'role' => $_POST['role'],
            'phone' => trim($_POST['phone']),
            'password' => $_POST['password'],
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        $oldData = null;
        if ($userModel->create($data)) {
            logAction($pdo, $_SESSION['user_id'], 'CREATE', 'users', $pdo->lastInsertId(), $oldData, $data);
            $_SESSION['success'] = 'تم إضافة المستخدم بنجاح';
            header('Location: users.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء إضافة المستخدم';
        }
    } elseif ($action === 'edit') {
        $id = $_POST['id'];
        $oldData = $userModel->getById($id);
        
        $data = [
            'username' => trim($_POST['username']),
            'email' => trim($_POST['email']),
            'full_name' => trim($_POST['full_name']),
            'role' => $_POST['role'],
            'phone' => trim($_POST['phone']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        if (!empty($_POST['password'])) {
            $data['password'] = $_POST['password'];
        }
        
        if ($userModel->update($id, $data)) {
            logAction($pdo, $_SESSION['user_id'], 'UPDATE', 'users', $id, $oldData, $data);
            $_SESSION['success'] = 'تم تحديث بيانات المستخدم بنجاح';
            header('Location: users.php');
            exit;
        } else {
            $_SESSION['error'] = 'حدث خطأ أثناء تحديث البيانات';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        $oldData = $userModel->getById($id);
        
        // لا يمكن حذف المستخدم الأخير من كل دور
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = ?");
        $stmt->execute([$user['role']]);
        $count = $stmt->fetchColumn();
        
        if ($count <= 1) {
            $_SESSION['error'] = 'لا يمكن حذف المستخدم الأخير من هذا الدور';
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$id])) {
                logAction($pdo, $_SESSION['user_id'], 'DELETE', 'users', $id, $oldData, null);
                $_SESSION['success'] = 'تم حذف المستخدم بنجاح';
                header('Location: users.php');
                exit;
            } else {
                $_SESSION['error'] = 'حدث خطأ أثناء الحذف';
            }
        }
    }
}

$users = $userModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-users"></i> إدارة المستخدمين</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-plus"></i> إضافة مستخدم
    </button>
</div>

<div class="row">
    <?php foreach ($users as $user): ?>
    <div class="col-md-6 col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="card-title mb-0"><?= htmlspecialchars($user['full_name']) ?></h5>
                        <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                    </div>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary" onclick="showEditUserModal(<?= $user['id'] ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" style="display:inline" 
                              onsubmit="return confirm('هل أنت متأكد من حذف هذا المستخدم؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $user['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                
                <p class="card-text">
                    <i class="fas fa-user-tag"></i> 
                    <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'rep' ? 'warning' : 'info') ?>">
                        <?= getRoleName($user['role']) ?>
                    </span><br>
                    <i class="fas fa-envelope"></i> 
                    <?= htmlspecialchars($user['email']) ?><br>
                    <?php if ($user['phone']): ?>
                    <i class="fas fa-phone"></i> 
                    <?= htmlspecialchars($user['phone']) ?><br>
                    <?php endif; ?>
                    <i class="fas fa-sign-in-alt"></i> 
                    آخر دخول: <?= $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'لم يقم بتسجيل الدخول' ?>
                </p>
                
                <div class="mt-3">
                    <span class="badge <?= $user['is_active'] ? 'bg-success' : 'bg-danger' ?>">
                        <?= $user['is_active'] ? 'نشط' : 'غير نشط' ?>
                    </span>
                    <?php if ($user['last_login_ip']): ?>
                    <br><small class="text-muted">آخر IP: <?= htmlspecialchars($user['last_login_ip']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- مودال إضافة مستخدم -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="?action=add">
                <div class="modal-header">
                    <h5 class="modal-title">إضافة مستخدم جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">اسم المستخدم <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">كلمة المرور <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">الاسم الكامل <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" required>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">الصلاحية <span class="text-danger">*</span></label>
                            <select name="role" class="form-select" required>
                                <option value="storekeeper">مسؤول مخزن</option>
                                <option value="rep">مندوب صيانة</option>
                                <option value="admin">مدير</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">حالة التفعيل</label>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="is_active" checked>
                                <label class="form-check-label">نشط</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" name="phone" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" name="email" class="form-control">
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
function showEditUserModal(id) {
    alert('وظيفة تعديل المستخدم قيد التطوير');
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>