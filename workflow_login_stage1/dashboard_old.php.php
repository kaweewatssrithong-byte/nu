
<?php
session_start();

// ตรวจสอบว่าผู้ใช้เข้าสู่ระบบแล้วหรือยัง
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Naresuan University</title>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Kanit', sans-serif;
            background: #f3f4f6;
            color: #292b30;
        }

        header {
            background: #25272c;
            color: white;
            padding: 18px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand {
            font-size: 20px;
            font-weight: 500;
        }

        .brand span {
            color: #f28a32;
        }

        main {
            max-width: 1100px;
            margin: 45px auto;
            padding: 20px;
        }

        .welcome {
            background: white;
            padding: 35px;
            border-radius: 16px;
            border-left: 5px solid #ed832b;
            box-shadow: 0 5px 25px rgba(0,0,0,.05);
        }

        h1 {
            margin-top: 0;
            font-weight: 500;
        }

        .role {
            display: inline-block;
            background: #fff0e2;
            color: #d66b16;
            padding: 6px 16px;
            border-radius: 20px;
        }

        .logout {
            background: #ed832b;
            color: white;
            text-decoration: none;
            padding: 9px 18px;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<header>
    <div class="brand">
        NARESUAN <span>UNIVERSITY</span>
    </div>

    <a class="logout" href="logout.php">ออกจากระบบ</a>
</header>

<main>
    <section class="welcome">
        <h1>
            ยินดีต้อนรับ
            <?= htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p>คุณเข้าสู่ระบบบริหารจัดการงานเรียบร้อยแล้ว</p>

        <p>
            Username:
            <?= htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p>
            ตำแหน่ง:
            <span class="role">
                <?= htmlspecialchars($user['role_name'], ENT_QUOTES, 'UTF-8') ?>
            </span>
        </p>

        <p>
            รหัสผู้ใช้งาน:
            <?= (int)$user['user_id'] ?>
        </p>
    </section>
</main>

</body>
</html>
