<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
    exit;
}

$errors = [];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once "config/database.php";

    $full_name      = trim($_POST["full_name"] ?? '');
    $email          = trim($_POST["email"] ?? '');
    $phone_number   = trim($_POST["phone_number"] ?? '');
    $password       = $_POST["password"] ?? '';
    $passwordRepeat = $_POST["repeat_password"] ?? '';

    if (empty($full_name) || empty($email) || empty($password) || empty($passwordRepeat)) {
        $errors[] = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "อีเมลไม่ถูกต้อง";
    }
    $phone_digits = preg_replace('/\D/', '', $phone_number);
    if (empty($phone_number) || !preg_match('/^0\d{8,9}$/', $phone_digits)) {
        $errors[] = "เบอร์โทรศัพท์ไม่ถูกต้อง (ตัวอย่าง: 081-234-5678)";
    } else {
        $phone_number = $phone_digits;
    }
    if (strlen($password) < 4) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
    }
    if ($password !== $passwordRepeat) {
        $errors[] = "รหัสผ่านไม่ตรงกัน";
    }

    if (empty($errors)) {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $errors[] = "อีเมลนี้ถูกใช้งานแล้ว";
        }
        $check->close();
    }

    if (empty($errors)) {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone_number, password, role) VALUES (?, ?, ?, ?, 'user')");
        $stmt->bind_param("ssss", $full_name, $email, $phone_number, $passwordHash);

        if ($stmt->execute()) {
            $success = true;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในระบบ กรุณาลองใหม่อีกครั้ง";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<div class="auth-page">

    <!-- Left decorative panel -->
    <div class="auth-panel">
        <a href="index.php" class="auth-panel-logo">
            <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Logo">
        </a>
        <h1>ยินดีต้อนรับ<br>สู่ JustHottel</h1>
        <p>สร้างบัญชีเพื่อค้นหาและจองโรงแรมที่ดีที่สุดในจังหวัดปัตตานี</p>
        <div class="auth-panel-badges">
            <span><span class="material-symbols-outlined">hotel</span> โรงแรมคัดสรร</span>
            <span><span class="material-symbols-outlined">star</span> รีวิวจริง</span>
            <span><span class="material-symbols-outlined">lock</span> ปลอดภัย</span>
        </div>
        <a href="index.php" class="auth-back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>

    <!-- Right form panel -->
    <div class="auth-form-side">
        <div class="auth-form-box">
            <div class="auth-form-header">
                <a href="index.php" class="auth-form-back"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
                <h2>สมัครสมาชิก</h2>
                <p>มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger"><span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>

            <?php if ($success): ?>
                <div class="alert alert-success"><span class="material-symbols-outlined">check_circle</span> สมัครสมาชิกเรียบร้อยแล้ว! <a href="login.php">เข้าสู่ระบบเลย <span class="material-symbols-outlined">arrow_forward</span></a></div>
            <?php else: ?>
                <form action="register.php" method="post" class="auth-form">
                    <div class="field-group">
                        <label>ชื่อ - สกุล</label>
                        <div class="field-wrap">
                            <span class="field-icon material-symbols-outlined">person</span>
                            <input type="text" name="full_name" placeholder="กรอกชื่อ-สกุล"
                                   value="<?= htmlspecialchars($_POST["full_name"] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>อีเมล</label>
                        <div class="field-wrap">
                            <span class="field-icon material-symbols-outlined">mail</span>
                            <input type="email" name="email" placeholder="example@email.com"
                                   value="<?= htmlspecialchars($_POST["email"] ?? '') ?>">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>เบอร์โทรศัพท์</label>
                        <div class="field-wrap" id="phoneWrap">
                            <span class="field-icon material-symbols-outlined">call</span>
                            <input type="tel" name="phone_number" id="phoneInput"
                                   inputmode="numeric"
                                   autocomplete="tel"
                                   placeholder="0XX-XXX-XXXX"
                                   maxlength="12"
                                   value="<?= htmlspecialchars($_POST["phone_number"] ?? '') ?>">
                            <span class="phone-status material-symbols-outlined" id="phoneStatus"></span>
                        </div>
                        <span class="field-hint">กรอกเบอร์ 10 หลัก เช่น 081-234-5678</span>
                    </div>
                    <div class="field-group">
                        <label>รหัสผ่าน</label>
                        <div class="field-wrap password-wrapper">
                            <span class="field-icon material-symbols-outlined">lock</span>
                            <input type="password" name="password" id="password" placeholder="อย่างน้อย 4 ตัวอักษร">
                            <img src="image/hide.png" class="toggle-password" id="togglePassword1" alt="toggle">
                        </div>
                    </div>
                    <div class="field-group">
                        <label>ยืนยันรหัสผ่าน</label>
                        <div class="field-wrap password-wrapper">
                            <span class="field-icon material-symbols-outlined">lock</span>
                            <input type="password" name="repeat_password" id="repeat_password" placeholder="พิมพ์รหัสผ่านอีกครั้ง">
                            <img src="image/hide.png" class="toggle-password" id="togglePassword2" alt="toggle">
                        </div>
                    </div>
                    <button type="submit" name="submit" class="auth-btn">สมัครสมาชิก</button>
                </form>

                <div class="auth-divider">หรือ</div>

                <a href="auth/google_login.php" class="btn-google">
                    <img src="image/google-icon.svg" alt="Google">
                    สมัครสมาชิกด้วย Google
                </a>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    // Password toggles
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

    // Phone auto-format
    const phoneInput  = document.getElementById("phoneInput");
    const phoneWrap   = document.getElementById("phoneWrap");
    const phoneStatus = document.getElementById("phoneStatus");

    function formatPhone(val) {
        const d = val.replace(/\D/g, '').slice(0, 10);
        if (d.length <= 3) return d;
        if (d.length <= 6) return d.slice(0,3) + '-' + d.slice(3);
        return d.slice(0,3) + '-' + d.slice(3,6) + '-' + d.slice(6);
    }

    function validatePhone(val) {
        const d = val.replace(/\D/g, '');
        return d.length >= 9 && d.length <= 10 && d.charAt(0) === '0';
    }

    function setPhoneState(valid, empty) {
        phoneWrap.classList.remove('field-valid', 'field-error');
        phoneStatus.textContent = '';
        if (empty) return;
        if (valid) {
            phoneWrap.classList.add('field-valid');
            phoneStatus.textContent = 'check_circle';
        } else {
            phoneWrap.classList.add('field-error');
            phoneStatus.textContent = 'cancel';
        }
    }

    if (phoneInput) {
        phoneInput.addEventListener('input', function () {
            const pos = this.selectionStart;
            const prev = this.value;
            const formatted = formatPhone(this.value);
            this.value = formatted;
            const added = formatted.length - prev.length;
            this.setSelectionRange(pos + added, pos + added);
            setPhoneState(validatePhone(formatted), formatted === '');
        });

        phoneInput.addEventListener('keydown', function (e) {
            if ([8,9,27,46,37,38,39,40].includes(e.keyCode)) return;
            if (e.key && !/[\d]/.test(e.key) && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
            }
        });

        phoneInput.addEventListener('blur', function () {
            setPhoneState(validatePhone(this.value), this.value === '');
        });
    }
});
</script>

</body>
</html>
