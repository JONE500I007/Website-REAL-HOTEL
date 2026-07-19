<?php
session_start();
require_once "config/database.php";

$rawToken = $_GET["token"] ?? '';
$hashedToken = $rawToken !== '' ? hash('sha256', $rawToken) : '';

$verified = false;

if ($hashedToken !== '') {
    $stmt = $conn->prepare("SELECT id FROM users WHERE verify_token = ? AND verify_token_expires_at > NOW()");
    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        $upd = $conn->prepare("UPDATE users SET email_verified_at = NOW(), verify_token = NULL, verify_token_expires_at = NULL WHERE id = ?");
        $upd->bind_param("i", $user["id"]);
        $upd->execute();
        $upd->close();
        $verified = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยืนยันอีเมล</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<div class="auth-page auth-page--login">
    <div class="auth-panel">
        <a href="index.php" class="auth-panel-logo">
            <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Logo">
        </a>
        <h1>ยืนยันอีเมล</h1>
        <p>ยืนยันตัวตนเพื่อความปลอดภัยของบัญชีคุณ</p>
        <a href="index.php" class="auth-back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <a href="index.php" class="auth-form-back"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
                <h2>ยืนยันอีเมล</h2>
            </div>

            <?php if ($verified): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    ยืนยันอีเมลเรียบร้อยแล้ว
                </div>
                <p><a href="<?= isset($_SESSION['user_id']) ? 'edit_profile.php' : 'login.php' ?>">
                    <?= isset($_SESSION['user_id']) ? 'กลับไปหน้าโปรไฟล์' : 'เข้าสู่ระบบ' ?>
                </a></p>
            <?php else: ?>
                <div class="alert alert-danger">
                    <span class="material-symbols-outlined">warning</span>
                    ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว
                </div>
                <p><a href="edit_profile.php">กลับไปหน้าโปรไฟล์เพื่อขอลิงก์ใหม่</a></p>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
