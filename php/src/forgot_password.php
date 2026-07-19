<?php
session_start();
require_once "config/database.php";
require_once "config/resend.php";
require_once "includes/functions.php";

$sent = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? '');

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $conn->prepare("SELECT id, full_name, password FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Only accounts with a password can reset one — Google-only accounts
        // have no local password to change. Either way the response below is
        // identical, so this never reveals whether the email is registered.
        if ($user && $user["password"] !== null) {
            [$rawToken, $hashedToken] = generate_secure_token();

            $upd = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expires_at = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            $upd->bind_param("si", $hashedToken, $user["id"]);
            $upd->execute();
            $upd->close();

            $resetLink = APP_URL . "/reset_password.php?token=" . urlencode($rawToken);
            $html = "
                <p>สวัสดีคุณ " . htmlspecialchars($user['full_name']) . "</p>
                <p>เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชี JustHottel ของคุณ คลิกลิงก์ด้านล่างเพื่อตั้งรหัสผ่านใหม่ (ลิงก์นี้ใช้ได้ 1 ชั่วโมง):</p>
                <p><a href=\"{$resetLink}\" style=\"background:#7b59a9;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;display:inline-block;\">ตั้งรหัสผ่านใหม่</a></p>
                <p>หากคุณไม่ได้ทำรายการนี้ ไม่ต้องดำเนินการใดๆ รหัสผ่านของคุณจะไม่ถูกเปลี่ยน</p>
            ";
            send_email_via_resend($email, "รีเซ็ตรหัสผ่าน JustHottel", $html);
        }
    }

    // Always show success — confirming/denying an email exists is a user
    // enumeration leak.
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน</title>
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
        <h1>ลืมรหัสผ่าน<br>ไม่ต้องกังวล</h1>
        <p>กรอกอีเมลที่ใช้สมัคร แล้วเราจะส่งลิงก์ตั้งรหัสผ่านใหม่ให้คุณ</p>
        <a href="index.php" class="auth-back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>

    <div class="auth-form-side">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <a href="index.php" class="auth-form-back"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
                <h2>ลืมรหัสผ่าน</h2>
                <p>นึกรหัสผ่านออกแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
            </div>

            <?php if ($sent): ?>
                <div class="alert alert-success">
                    <span class="material-symbols-outlined">mail</span>
                    หากอีเมลนี้มีอยู่ในระบบ เราได้ส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้แล้ว กรุณาตรวจสอบกล่องจดหมาย (และโฟลเดอร์สแปม)
                </div>
            <?php else: ?>
                <form action="forgot_password.php" method="post" class="auth-form">
                    <div class="field-group">
                        <label>อีเมล</label>
                        <div class="field-wrap">
                            <span class="field-icon material-symbols-outlined">mail</span>
                            <input type="email" name="email" placeholder="example@email.com" required>
                        </div>
                    </div>
                    <button type="submit" class="auth-btn">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
