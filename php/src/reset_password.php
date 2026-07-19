<?php
session_start();
require_once "config/database.php";

$rawToken = $_GET["token"] ?? $_POST["token"] ?? '';
$hashedToken = $rawToken !== '' ? hash('sha256', $rawToken) : '';

$errors = [];
$success = false;
$validToken = false;

if ($hashedToken !== '') {
    $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expires_at > NOW()");
    $stmt->bind_param("s", $hashedToken);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $validToken = (bool) $user;
}

if ($validToken && $_SERVER["REQUEST_METHOD"] === "POST") {
    $password       = $_POST["password"] ?? '';
    $passwordRepeat = $_POST["repeat_password"] ?? '';

    if (strlen($password) < 4) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
    }
    if ($password !== $passwordRepeat) {
        $errors[] = "รหัสผ่านไม่ตรงกัน";
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
        $upd->bind_param("si", $hashed, $user["id"]);
        $upd->execute();
        $upd->close();
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งรหัสผ่านใหม่</title>
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
        <h1>ตั้งรหัสผ่าน<br>ใหม่ของคุณ</h1>
        <p>ตั้งรหัสผ่านใหม่ที่คาดเดายากเพื่อความปลอดภัยของบัญชี</p>
        <a href="index.php" class="auth-back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <a href="index.php" class="auth-form-back"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
                <h2>ตั้งรหัสผ่านใหม่</h2>
            </div>

            <?php if (!$validToken): ?>
                <div class="alert alert-danger">
                    <span class="material-symbols-outlined">warning</span>
                    ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว
                </div>
                <p><a href="forgot_password.php">ขอลิงก์ใหม่อีกครั้ง</a></p>
            <?php elseif ($success): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">check_circle</span>
                    ตั้งรหัสผ่านใหม่เรียบร้อยแล้ว
                </div>
                <p><a href="login.php">เข้าสู่ระบบด้วยรหัสผ่านใหม่</a></p>
            <?php else: ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($error) ?></div>
                <?php endforeach; ?>

                <form method="post" class="auth-form">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($rawToken) ?>">
                    <div class="field-group">
                        <label>รหัสผ่านใหม่</label>
                        <div class="field-wrap password-wrapper">
                            <span class="field-icon material-symbols-outlined">lock</span>
                            <input type="password" name="password" id="password" placeholder="อย่างน้อย 4 ตัวอักษร" required>
                            <img src="image/hide.png" class="toggle-password" id="togglePassword1" alt="toggle">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>ยืนยันรหัสผ่านใหม่</label>
                        <div class="field-wrap password-wrapper">
                            <span class="field-icon material-symbols-outlined">lock</span>
                            <input type="password" name="repeat_password" id="repeat_password" placeholder="พิมพ์รหัสผ่านอีกครั้ง" required>
                            <img src="image/hide.png" class="toggle-password" id="togglePassword2" alt="toggle">
                        </div>
                    </div>
                    <button type="submit" class="auth-btn">ตั้งรหัสผ่านใหม่</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    function setupToggle(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon  = document.getElementById(iconId);
        if (!input || !icon) return;
        icon.addEventListener("click", function () {
            input.type = input.type === "password" ? "text" : "password";
            icon.src   = input.type === "password" ? "image/hide.png" : "image/view.png";
        });
    }
    setupToggle("password", "togglePassword1");
    setupToggle("repeat_password", "togglePassword2");
});
</script>

</body>
</html>
