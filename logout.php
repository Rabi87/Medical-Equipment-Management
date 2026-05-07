<?php
require_once __DIR__ . '/includes/header.php';

session_unset();
session_destroy();

logAction($pdo, $_SESSION['user_id'] ?? null, 'LOGOUT', 'users', $_SESSION['user_id'] ?? null, null, null);

header('Location: login.php');
exit;
?>