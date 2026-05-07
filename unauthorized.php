<?php
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="text-center">
        <i class="fas fa-exclamation-triangle fa-4x text-warning mb-4"></i>
        <h1 class="display-4 text-danger">عفواً، لا تملك صلاحية الوصول لهذه الصفحة</h1>
        <p class="lead text-muted">الصفحة التي تحاول الوصول إليها تتطلب صلاحيات أعلى من صلاحياتك الحالية</p>
        <hr class="my-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="alert alert-info">
                    <h4><i class="fas fa-info-circle"></i> معلومات حسابك</h4>
                    <p class="mb-1">الاسم: <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'غير معروف') ?></strong></p>
                    <p class="mb-1">الدور الحالي: <strong><?= getRoleName($_SESSION['role'] ?? '') ?></strong></p>
                    <p class="mb-0">الصلاحيات المتاحة: <?= $_SESSION['role'] == 'admin' ? 'الوصول الكامل' : ($_SESSION['role'] == 'rep' ? 'الصيانة والمبيعات' : 'إدارة المخزون') ?></p>
                </div>
            </div>
        </div>
        <div class="mt-5">
            <a href="index.php" class="btn btn-primary btn-lg me-2">
                <i class="fas fa-home"></i>
                العودة للوحة التحكم
            </a>
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <a href="users.php" class="btn btn-outline-primary btn-lg">
                <i class="fas fa-users"></i>
                إدارة المستخدمين
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>