<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

$password = getenv('ADMIN_INITIAL_PASSWORD');
if (!is_string($password) || strlen($password) < 12) {
    fwrite(STDERR, "กำหนดตัวแปร ADMIN_INITIAL_PASSWORD ให้มีอย่างน้อย 12 ตัวอักษรก่อนเรียกสคริปต์นี้\n");
    exit(1);
}

require_once __DIR__ . '/config/database.php';
$username = trim($argv[1] ?? 'admin');
$fullName = trim($argv[2] ?? 'ผู้ดูแลระบบ');
if ($username === '' || $fullName === '') {
    fwrite(STDERR, "รูปแบบ: php create_admin.php [ชื่อผู้ใช้] [ชื่อแสดงผล]\n");
    exit(1);
}

try {
    $role = $pdo->query("SELECT role_id FROM roles WHERE role_code = 'ADMIN' LIMIT 1")->fetchColumn();
    if (!$role) {
        throw new RuntimeException('ไม่พบ role ADMIN กรุณานำเข้า database.sql ก่อน');
    }
    $stmt = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role_id, status) VALUES (:username, :password_hash, :full_name, :role_id, \'active\')');
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $fullName,
        'role_id' => $role,
    ]);
    fwrite(STDOUT, "สร้างบัญชีผู้ดูแลระบบสำเร็จ: {$username}\n");
} catch (Throwable $e) {
    error_log($e->getMessage());
    fwrite(STDERR, "สร้างบัญชีไม่สำเร็จ กรุณาตรวจสอบว่าฐานข้อมูลพร้อมและชื่อผู้ใช้ยังไม่ถูกใช้\n");
    exit(1);
} finally {
    putenv('ADMIN_INITIAL_PASSWORD');
}
