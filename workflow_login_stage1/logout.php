<?php
declare(strict_types=1);
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    exit('คำขอไม่ถูกต้อง กรุณากลับไปยังหน้า Dashboard');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/audit.php';
if (isset($_SESSION['user'])) {
    audit_log($pdo, $_SESSION['user'], 'logout', 'logout.php');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php');
exit;
