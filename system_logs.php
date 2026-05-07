<?php
require_once __DIR__ . '/includes/header.php';
checkAuth();

$filters = [];
if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
if (!empty($_GET['action_type'])) $filters['action_type'] = $_GET['action_type'];
if (!empty($_GET['table_name'])) $filters['table_name'] = $_GET['table_name'];
if (!empty($_GET['start_date'])) $filters['start_date'] = $_GET['start_date'];
if (!empty($_GET['end_date'])) $filters['end_date'] = $_GET['end_date'];

$logs = $systemLogModel->getAll($filters);
$usersList = $userModel->getAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-history"></i> سجل الحركات النظام</h2>
</div>

<!-- الفلاتر -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="user_id" class="form-select">
                    <option value="">جميع المستخدمين</option>
                    <?php foreach ($usersList as $usr): ?>
                    <option value="<?= $usr['id'] ?>" <?= ($_GET['user_id'] ?? '') == $usr['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($usr['full_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="action_type" class="form-select">
                    <option value="">جميع الإجراءات</option>
                    <option value="CREATE" <?= ($_GET['action_type'] ?? '') == 'CREATE' ? 'selected' : '' ?>>إنشاء</option>
                    <option value="UPDATE" <?= ($_GET['action_type'] ?? '') == 'UPDATE' ? 'selected' : '' ?>>تحديث</option>
                    <option value="DELETE" <?= ($_GET['action_type'] ?? '') == 'DELETE' ? 'selected' : '' ?>>حذف</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="table_name" class="form-select">
                    <option value="">جميع الجداول</option>
                    <option value="users" <?= ($_GET['table_name'] ?? '') == 'users' ? 'selected' : '' ?>>المستخدمين</option>
                    <option value="devices" <?= ($_GET['table_name'] ?? '') == 'devices' ? 'selected' : '' ?>>الأجهزة</option>
                    <option value="hospitals" <?= ($_GET['table_name'] ?? '') == 'hospitals' ? 'selected' : '' ?>>المشافي</option>
                    <option value="suppliers" <?= ($_GET['table_name'] ?? '') == 'suppliers' ? 'selected' : '' ?>>الموردين</option>
                    <option value="maintenance_logs" <?= ($_GET['table_name'] ?? '') == 'maintenance_logs' ? 'selected' : '' ?>>الصيانة</option>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" name="start_date" class="form-control" 
                    value="<?= htmlspecialchars($_GET['start_date'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="end_date" class="form-control" 
                    value="<?= htmlspecialchars($_GET['end_date'] ?? '') ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter"></i></button>
            </div>
        </form>
    </div>
</div>

<!-- جدول السجل -->
<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table-dark">
            <tr>
                <th>التاريخ والوقت</th>
                <th>المستخدم</th>
                <th>نوع العملية</th>
                <th>الجدول</th>
                <th>معرف السجل</th>
                <th>عنوان IP</th>
                <th>التفاصيل</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($logs) > 0): ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><?= htmlspecialchars($log['username'] ?? 'نظام') ?></td>
                    <td>
                        <span class="badge bg-<?= $log['action_type'] == 'DELETE' ? 'danger' : ($log['action_type'] == 'UPDATE' ? 'warning' : 'success') ?>">
                            <?= $log['action_type'] == 'CREATE' ? 'إنشاء' : ($log['action_type'] == 'UPDATE' ? 'تحديث' : 'حذف') ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($log['table_name']) ?></td>
                    <td><?= $log['record_id'] ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td>
                        <button class="btn btn-sm btn-outline-info" onclick="showLogDetails(<?= htmlspecialchars(json_encode($log)) ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center py-4">لا توجد سجلات</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- مودال عرض تفاصيل السجل -->
<div class="modal fade" id="logDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">تفاصيل السجل</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="logDetailsContent">
            </div>
        </div>
    </div>
</div>

<script>
function showLogDetails(log) {
    let oldDataHtml = log.old_data ? `<pre>${JSON.stringify(JSON.parse(log.old_data), null, 2)}</pre>` : '<p class="text-muted">لا توجد بيانات سابقة</p>';
    let newDataHtml = log.new_data ? `<pre>${JSON.stringify(JSON.parse(log.new_data), null, 2)}</pre>` : '<p class="text-muted">لا توجد بيانات جديدة</p>';
    
    let content = `
        <div class="row">
            <div class="col-md-6">
                <h6>البيانات القديمة:</h6>
                ${oldDataHtml}
            </div>
            <div class="col-md-6">
                <h6>البيانات الجديدة:</h6>
                ${newDataHtml}
            </div>
        </div>
        <div class="mt-3">
            <h6>معلومات إضافية:</h6>
            <ul class="list-unstyled">
                <li><strong>المستخدم:</strong> ${log.username || 'نظام'}</li>
                <li><strong>عنوان IP:</strong> ${log.ip_address}</li>
                <li><strong>التاريخ:</strong> ${log.created_at}</li>
                <li><strong>الجدول:</strong> ${log.table_name}</li>
                <li><strong>معرف السجل:</strong> ${log.record_id}</li>
            </ul>
        </div>
    `;
    
    document.getElementById('logDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('logDetailsModal')).show();
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>