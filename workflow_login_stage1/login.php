<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/audit.php';
audit_log($pdo, $_SESSION['user'] ?? null, 'page_view', 'login.php');

if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$oldUsername = '';
$googleError = (string)($_GET['google_error'] ?? '');
$googleErrors = [
    'not_configured' => 'ยังไม่ได้ตั้งค่า Google OAuth กรุณาตั้งค่า Client ID, Client Secret และ Redirect URI ก่อน',
    'cancelled' => 'ยกเลิกการเข้าสู่ระบบด้วย Google แล้ว',
    'state' => 'เซสชัน Google หมดอายุหรือไม่ถูกต้อง กรุณาลองใหม่',
    'email_unlinked' => 'อีเมล Google นี้ยังไม่ได้ผูกกับบัญชีในระบบ กรุณาติดต่อผู้ดูแลระบบ',
    'email_ambiguous' => 'พบหลายบัญชีที่ใช้อีเมลนี้ กรุณาให้ผู้ดูแลระบบตรวจสอบก่อน',
    'account_inactive' => 'บัญชีนี้ยังไม่สามารถเข้าใช้งานได้ กรุณาติดต่อผู้ดูแลระบบ',
    'google_mismatch' => 'บัญชีนี้ผูกกับ Google Account อื่นอยู่ กรุณาติดต่อผู้ดูแลระบบ',
    'failed' => 'เข้าสู่ระบบด้วย Google ไม่สำเร็จ กรุณาลองใหม่อีกครั้ง'
];
if (isset($googleErrors[$googleError])) $error = $googleErrors[$googleError];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'เซสชันไม่ถูกต้อง กรุณารีเฟรชหน้าเว็บ';
        audit_log($pdo, null, 'login_csrf_failed', 'login.php');
    } else {
                $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $oldUsername = $username;
        if ($username === '' || $password === '') {
            $error = 'กรุณากรอกอีเมล/ชื่อผู้ใช้และรหัสผ่านให้ครบถ้วน';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.password_hash, u.full_name, u.email, u.role_id, u.department_id, d.department_name, u.status, r.role_code, r.role_name FROM users u INNER JOIN roles r ON u.role_id = r.role_id LEFT JOIN departments d ON u.department_id = d.department_id WHERE u.username = :identity OR LOWER(u.email) = LOWER(:email) LIMIT 1");
                $stmt->execute(['identity' => $username, 'email' => $username]);
                $user = $stmt->fetch();
                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
                    audit_log($pdo, $user ?: null, 'login_failed', 'login.php');
                } elseif ($user['status'] !== 'active') {
                    $error = 'บัญชีของคุณยังไม่สามารถเข้าใช้งานได้ กรุณาติดต่อผู้ดูแลระบบ';
                    audit_log($pdo, $user, 'login_blocked', 'login.php');
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user'] = [
                        'user_id' => (int)$user['user_id'], 'username' => $user['username'],
                        'full_name' => $user['full_name'], 'email' => $user['email'],
                        'role_id' => (int)$user['role_id'], 'role_code' => $user['role_code'],
                        'role_name' => $user['role_name'], 'department_id' => $user['department_id'],
                        'department_name' => $user['department_name']
                    ];
                    $update = $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE user_id = :user_id');
                    $update->execute(['user_id' => $user['user_id']]);
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    audit_log($pdo, $_SESSION['user'], 'login_success', 'login.php');
                    header('Location: dashboard.php');
                    exit;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
                $error = 'ระบบขัดข้องชั่วคราว กรุณาตรวจสอบการเชื่อมต่อฐานข้อมูลแล้วลองใหม่';
            }
        }
    }
}
function e(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="th">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | Workflow Management</title>
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&family=Prompt:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=login-sunset-20261106-3">
</head>
<body>
<div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
<main class="page-shell"><div class="login-layout">
    <section class="brand-panel" aria-label="Workflow Management System">
        <div class="brand-top"><img class="university-logo" src="assets/images/naresuan-logo.png" alt="ตรามหาวิทยาลัยนเรศวร"><div><p class="brand-name">WORKFLOW <span>MANAGEMENT</span></p><p class="brand-caption">NARESUAN UNIVERSITY</p></div></div>
        <div class="brand-content"><div class="eyebrow"><span class="eyebrow-line"></span>WORK SMARTER TOGETHER</div><h1>จัดการงาน<br><span>อย่างเป็นระบบ</span></h1><p class="brand-description">เชื่อมต่อทุกทีมและทุกขั้นตอนการทำงานไว้ในที่เดียว ติดตามความคืบหน้าได้อย่างชัดเจน</p><div class="feature-list"><div class="feature-item"><div class="feature-icon">01</div><div><strong>จัดการงานได้ง่าย</strong><small>รวมงานและรายละเอียดไว้ในระบบเดียว</small></div></div><div class="feature-item"><div class="feature-icon">02</div><div><strong>ติดตามทุกความคืบหน้า</strong><small>เห็นสถานะงานได้ทันที</small></div></div><div class="feature-item"><div class="feature-icon">03</div><div><strong>ทำงานร่วมกันได้อย่างมั่นใจ</strong><small>ข้อมูลพร้อมใช้งานสำหรับทีมของคุณ</small></div></div></div></div>
        <div class="brand-footer"><span class="status-dot"></span>ระบบบริหารจัดการงานภายในมหาวิทยาลัย <span class="footer-version">WORKFLOW</span></div>
    </section>
    <section class="form-panel"><div class="form-topline"><span class="secure-label"><span class="lock-icon">▣</span> SECURE SIGN IN</span><span class="help-text">สำหรับบุคลากรและผู้ใช้งานในระบบ</span></div>
        <div class="form-content"><div class="mobile-brand"><img class="university-logo" src="assets/images/naresuan-logo.png" alt="ตรามหาวิทยาลัยนเรศวร"><strong>WORKFLOW <span>MANAGEMENT</span></strong></div><div class="welcome-icon"><span class="user-symbol">♙</span></div><p class="form-kicker">ยินดีต้อนรับกลับ</p><h2>เข้าสู่ระบบ</h2><p class="form-subtitle">กรอกข้อมูลบัญชีของคุณเพื่อดำเนินการต่อ</p>
            <?php if ($error !== ''): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form id="loginForm" method="post" action="login.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                <div class="field-group"><label for="username">อีเมล / ชื่อผู้ใช้ <span>*</span></label><div class="input-wrap"><span class="input-icon" aria-hidden="true">✉</span><input id="username" name="username" type="text" autocomplete="username" placeholder="กรอกอีเมลหรือชื่อผู้ใช้" value="<?= e($oldUsername) ?>" required autofocus></div></div>
                <div class="field-group"><div class="label-row"><label for="password">รหัสผ่าน <span>*</span></label></div><div class="input-wrap"><span class="input-icon" aria-hidden="true">▣</span><input id="password" name="password" type="password" autocomplete="current-password" placeholder="กรอกรหัสผ่าน" required><button class="toggle-password" id="togglePassword" type="button" aria-label="แสดงรหัสผ่าน"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div></div>
                <div class="options-row"><label class="remember" for="rememberUsername"><input id="rememberUsername" type="checkbox"><span class="custom-check" aria-hidden="true"></span><span>จดจำชื่อผู้ใช้</span></label><span class="forgot-note">ลืมรหัสผ่าน? ติดต่อผู้ดูแลระบบ</span></div><button class="submit-button" type="submit"><span>เข้าสู่ระบบ</span><span class="button-arrow" aria-hidden="true">→</span></button>
            </form><div class="login-or"><span>หรือเข้าสู่ระบบด้วย</span></div><a class="google-login-button" href="google_login.php?action=start"><span class="google-g" aria-hidden="true">G</span><span>เข้าสู่ระบบด้วย Google</span></a><p class="google-login-hint">ใช้ Google Account ที่ผูกอีเมลไว้กับบัญชีในระบบ</p><div class="form-divider">ระบบบริหารจัดการงาน</div><p class="security-note"><span class="shield">◆</span>ข้อมูลบัญชีของคุณได้รับการปกป้องอย่างปลอดภัย</p>
        </div><footer class="form-footer"><span>มหาวิทยาลัยนเรศวร</span><span>Workflow Management System</span></footer>
    </section>
</div></main><script src="assets/js/login.js" defer></script>
</body></html>
