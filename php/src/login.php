<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

require_once "config/database.php";

if (isset($_POST["login"])) {
    $email    = $_POST["email"];
    $password = $_POST["password"];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {
        $_SESSION["user_id"]         = $user["id"];
        $_SESSION["user_email"]      = $user["email"];
        $_SESSION["user"]            = $user["full_name"];
        $_SESSION["role"]            = $user["role"];
        $_SESSION["profile_picture"] = $user["profile_picture"];
        header("Location: index.php");
        exit;
    } else {
        $loginError = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<div class="auth-page auth-page--login">

    <!-- Left decorative panel -->
    <div class="auth-panel">
        <a href="index.php" class="auth-panel-logo">
            <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Logo">
        </a>
        <h1>กลับมาแล้ว<br>ยินดีต้อนรับ!</h1>
        <p>เข้าสู่ระบบเพื่อดูการจองและสิทธิพิเศษของคุณ</p>
        <div class="auth-panel-badges">
            <span><span class="material-symbols-outlined">calendar_month</span> ดูการจองได้ทันที</span>
            <span><span class="material-symbols-outlined">loyalty</span> สิทธิพิเศษสมาชิก</span>
        </div>
        <a href="index.php" class="auth-back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>

    <!-- Right form panel -->
    <div class="auth-form-side">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <a href="index.php" class="auth-form-back"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
                <h2>เข้าสู่ระบบ</h2>
                <p>ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
            </div>

            <?php if (isset($_GET["error"]) && $_GET["error"] === "login_required"): ?>
                <div class="alert alert-danger"><span class="material-symbols-outlined">warning</span> กรุณาเข้าสู่ระบบก่อนทำการจอง</div>
            <?php endif; ?>

            <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger"><span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>

            <form action="login.php" method="post" class="auth-form">
                <div class="field-group">
                    <label>อีเมล</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">mail</span>
                        <input type="email" name="email" placeholder="example@email.com" required>
                    </div>
                </div>
                <div class="field-group">
                    <label>รหัสผ่าน</label>
                    <div class="field-wrap password-wrapper">
                        <span class="field-icon material-symbols-outlined">lock</span>
                        <input type="password" name="password" id="password" placeholder="รหัสผ่านของคุณ" required>
                        <img src="image/hide.png" class="toggle-password" id="togglePassword" alt="toggle">
                    </div>
                </div>
                <button type="submit" name="login" class="auth-btn">เข้าสู่ระบบ</button>
            </form>

            <div class="auth-divider">หรือ</div>

            <a href="auth/google_login.php" class="btn-google">
                <img src="image/google-icon.svg" alt="Google">
                เข้าสู่ระบบด้วย Google
            </a>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("password");
    const toggleIcon    = document.getElementById("togglePassword");
    if (!passwordInput || !toggleIcon) return;
    toggleIcon.addEventListener("click", function () {
        passwordInput.type = passwordInput.type === "password" ? "text" : "password";
        toggleIcon.src = passwordInput.type === "password" ? "image/hide.png" : "image/view.png";
    });
});
</script>

</body>
</html>
