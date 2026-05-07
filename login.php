<?php
// login.php
require_once __DIR__ . '/includes/header.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'يرجى إدخال اسم المستخدم وكلمة المرور';
    } else {
        $user = $userModel->authenticate($username, $password);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            logAction($pdo, $user['id'], 'LOGIN', 'users', $user['id'], null, ['ip' => $_SERVER['REMOTE_ADDR']]);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            logAction($pdo, null, 'LOGIN_FAILED', 'users', null, null, 
                ['username' => $username, 'ip' => $_SERVER['REMOTE_ADDR']]);
        }
    }
}

// إذا كان المستخدم مسجلاً بالفعل، توجيهه للوحة التحكم
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام إدارة شركة تجهيزات طبية</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            max-width: 400px;
            margin: 0 auto;
            border: none;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .login-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            text-align: center;
            padding: 30px 20px;
        }
        .login-card .card-body {
            padding: 30px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 10px 20px;
            font-weight: 500;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
                <div class="card login-card">
                    <div class="card-header">
                        <h3 class="mb-0">
                            <i class="fas fa-heartbeat me-2"></i>
                            نظام إدارة شركة تجهيزات طبية
                        </h3>
                        <p class="mb-0 mt-2 opacity-75">تسجيل الدخول</p>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="login.php">
                            <div class="mb-3">
                                <label for="username" class="form-label">
                                    <i class="fas fa-user me-1"></i>
                                    اسم المستخدم
                                </label>
                                <input type="text" class="form-control form-control-lg" 
                                    id="username" name="username" required autofocus
                                    placeholder="أدخل اسم المستخدم">
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>
                                    كلمة المرور
                                </label>
                                <input type="password" class="form-control form-control-lg" 
                                    id="password" name="password" required
                                    placeholder="أدخل كلمة المرور">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-sign-in-alt me-2"></i>
                                    تسجيل الدخول
                                </button>
                            </div>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center text-muted small">
                            <p class="mb-1">بيانات اختبار النظام:</p>
                            <p class="mb-0">
                                <strong>المدير:</strong> admin / admin<br>
                                <strong>المندوب:</strong> rep1 / rep1<br>
                                <strong>المخزن:</strong> store1 / store1
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center text-white mt-4">
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-shield-alt me-1"></i>
                        جميع الحقوق محفوظة © 2026
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
$pdo = null;
?>