<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/audit.php';

// อนุญาตให้ผู้ดูแลระบบที่ล็อกอินอยู่เท่านั้น
if (
    !isset($_SESSION['user']) ||
    ($_SESSION['user']['role_code'] ?? '') !== 'ADMIN'
) {
    http_response_code(403);
    exit('ไม่มีสิทธิ์เข้าถึงหน้านี้');
}
audit_log($pdo, $_SESSION['user'], 'page_view', 'reset_admin_password.php');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('คำขอไม่ถูกต้อง กรุณากลับไปลองใหม่');
    }

    $password = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!is_string($password) || !is_string($confirm)) {
        $error = 'ข้อมูลไม่ถูกต้อง';

    } elseif (strlen($password) < 12) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 12 ตัวอักษร';

    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';

    } else {

        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                UPDATE users
                SET password_hash = :password_hash
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                'password_hash' => $hash,
                'user_id' => $_SESSION['user']['user_id']
            ]);

            if ($stmt->rowCount() === 1) {
                audit_log($pdo, $_SESSION['user'], 'password_changed', 'reset_admin_password.php');
                $message = 'เปลี่ยนรหัสผ่าน ADMIN สำเร็จแล้ว';
            } else {
                // ตรวจสอบกรณี Hash ใหม่เหมือนเดิมหรือไม่พบแถว
                $check = $pdo->prepare("
                    SELECT password_hash
                    FROM users
                    WHERE user_id = :user_id
                ");
                $check->execute(['user_id' => $_SESSION['user']['user_id']]);
                $savedHash = $check->fetchColumn();

                if (
                    is_string($savedHash) &&
                    password_verify($password, $savedHash)
                ) {
                    $message = 'รหัสผ่านนี้ใช้งานได้แล้ว';
                } else {
                    $error = 'ไม่พบบัญชี ADMIN ที่ต้องการแก้ไข';
                }
            }

        } catch (PDOException $e) {
            error_log($e->getMessage());
            $error = 'ไม่สามารถเปลี่ยนรหัสผ่านได้';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Admin Password</title>
<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f6f8;
    display: grid;
    place-items: center;
    min-height: 100vh;
}
.container {
    background: white;
    width: min(400px, 90%);
    padding: 28px;
    border-radius: 14px;
    box-shadow: 0 5px 25px #0001;
}
input, button {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    margin-top: 10px;
}
button {
    background: #e97827;
    color: white;
    border: 0;
    cursor: pointer;
}
.message { color: green; }
.error { color: red; }
</style>
</head>
<body>
<div class="container">
    <h2>เปลี่ยนรหัสผ่าน ADMIN</h2>

    <?php if ($message): ?>
        <p class="message"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <label>รหัสผ่านใหม่</label>
        <input type="password" name="new_password"
               minlength="12" required>

        <label>ยืนยันรหัสผ่านใหม่</label>
        <input type="password" name="confirm_password"
               minlength="12" required>

        <button type="submit">บันทึกรหัสผ่านใหม่</button>
    </form>
</div>
</body>
</html>
