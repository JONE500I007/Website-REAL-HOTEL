<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once "config/database.php";
require_once "includes/functions.php";

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$errors  = [];
$success = false;

function formatPhone(?string $raw): string {
    $raw = $raw ?? '';
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 10) return substr($d,0,3).'-'.substr($d,3,3).'-'.substr($d,6);
    if (strlen($d) === 9)  return substr($d,0,2).'-'.substr($d,2,3).'-'.substr($d,5);
    return $raw;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name    = trim($_POST["full_name"] ?? '');
    $email        = trim($_POST["email"] ?? '');
    $phone_number = trim($_POST["phone_number"] ?? '');
    $new_password = $_POST["new_password"] ?? '';

    if (empty($full_name) || empty($email) || empty($phone_number)) {
        $errors[] = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "รูปแบบอีเมลไม่ถูกต้อง";
    }
    // Strip dashes before storing, then validate digits
    $phone_digits = preg_replace('/\D/', '', $phone_number);
    if (!empty($phone_number) && (!preg_match('/^0\d{8,9}$/', $phone_digits))) {
        $errors[] = "เบอร์โทรศัพท์ไม่ถูกต้อง (ตัวอย่าง: 081-234-5678)";
    } else {
        $phone_number = $phone_digits; // store as digits only
    }
    if (!empty($new_password) && strlen($new_password) < 4) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
    }

    $profile_image = $user["profile_picture"];

    if (!empty($_POST["cropped_data"])) {
        $saved = save_cropped_image($_POST["cropped_data"], "uploads/", "profile_{$user_id}");
        if ($saved !== false) {
            $profile_image = $saved;
        } else {
            $errors[] = "ไม่สามารถบันทึกรูปภาพได้";
        }
    }

    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone_number=?, password=?, profile_picture=? WHERE id=?");
            $stmt->bind_param("sssssi", $full_name, $email, $phone_number, $hashed, $profile_image, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone_number=?, profile_picture=? WHERE id=?");
            $stmt->bind_param("ssssi", $full_name, $email, $phone_number, $profile_image, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION["user"]            = $full_name;
            $_SESSION["user_email"]      = $email;
            $_SESSION["profile_picture"] = $profile_image;
            $success = true;
            $user["full_name"]       = $full_name;
            $user["email"]           = $email;
            $user["phone_number"]    = $phone_number;
            $user["profile_picture"] = $profile_image;
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการอัปเดตข้อมูล";
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
    <title>แก้ไขโปรไฟล์</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="profile-page">
    <div class="profile-layout">

        <!-- Left: Avatar Card -->
        <div class="profile-avatar-card">
            <div class="avatar-wrap" id="avatarWrap" title="คลิกเพื่อเปลี่ยนรูป">
                <img id="currentAvatar"
                     src="<?= htmlspecialchars(resolve_upload_src($user['profile_picture'])) ?>"
                     alt="Profile">
                <div class="avatar-overlay">
                    <span>📷 เปลี่ยนรูป</span>
                </div>
            </div>
            <input type="file" id="fileInput" accept="image/*" style="display:none">
            <h3><?= htmlspecialchars($user['full_name']) ?></h3>
            <p class="profile-email"><?= htmlspecialchars($user['email']) ?></p>
            <span class="profile-role-badge"><?= htmlspecialchars($user['role'] ?? 'user') ?></span>
        </div>

        <!-- Right: Form -->
        <div class="profile-form-card">
            <div class="profile-form-header">
                <h2>แก้ไขข้อมูลส่วนตัว</h2>
                <a href="index.php" class="profile-back-link">← กลับหน้าหลัก</a>
            </div>

            <?php foreach ($errors as $error): ?>
                <div class="alert alert-danger">⚠ <?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
            <?php if ($success): ?>
                <div class="alert alert-success">✓ อัปเดตข้อมูลเรียบร้อยแล้ว!</div>
            <?php endif; ?>

            <form action="edit_profile.php" method="post" enctype="multipart/form-data" class="auth-form">
                <input type="hidden" name="cropped_data" id="croppedData">

                <div class="field-group">
                    <label>ชื่อ - สกุล</label>
                    <div class="field-wrap">
                        <span class="field-icon">👤</span>
                        <input type="text" name="full_name" placeholder="ชื่อ-สกุล"
                               value="<?= htmlspecialchars($user['full_name']) ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>อีเมล</label>
                    <div class="field-wrap">
                        <span class="field-icon">✉</span>
                        <input type="email" name="email" placeholder="อีเมล"
                               value="<?= htmlspecialchars($user['email']) ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>เบอร์โทรศัพท์</label>
                    <div class="field-wrap" id="phoneWrap">
                        <span class="field-icon">📱</span>
                        <input type="tel" name="phone_number" id="phoneInput"
                               inputmode="numeric"
                               autocomplete="tel"
                               placeholder="0XX-XXX-XXXX"
                               maxlength="12"
                               value="<?= htmlspecialchars(formatPhone($user['phone_number'])) ?>">
                        <span class="phone-status" id="phoneStatus"></span>
                    </div>
                    <span class="field-hint">กรอกเบอร์ 10 หลัก เช่น 081-234-5678</span>
                </div>
                <?php if (!empty($user['password'])): ?>
                <div class="field-group">
                    <label>รหัสผ่านใหม่ <span style="font-weight:400;color:#aaa;font-size:12px">(เว้นว่างไว้หากไม่เปลี่ยน)</span></label>
                    <div class="field-wrap password-wrapper">
                        <span class="field-icon">🔑</span>
                        <input type="password" name="new_password" id="new_password" placeholder="รหัสผ่านใหม่">
                        <img src="image/hide.png" class="toggle-password" id="togglePassword" alt="toggle">
                    </div>
                </div>
                <?php endif; ?>

                <button type="submit" class="auth-btn">💾 บันทึกการเปลี่ยนแปลง</button>
            </form>
        </div>
    </div>
</div>

<!-- Crop Modal -->
<div id="cropModal" class="crop-modal" style="display:none">
    <div class="crop-modal-box">
        <div class="crop-modal-header">
            <h3>✂️ ครอบตัดรูปโปรไฟล์</h3>
            <button class="crop-close-btn" id="cropClose">✕</button>
        </div>
        <div class="crop-canvas-wrap">
            <img id="cropImage" src="" alt="crop">
        </div>
        <div class="crop-modal-footer">
            <button class="crop-btn-cancel" id="cropCancel">ยกเลิก</button>
            <button class="crop-btn-confirm" id="cropConfirm">✓ ยืนยันการครอบ</button>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
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
            phoneStatus.textContent = '✓';
        } else {
            phoneWrap.classList.add('field-error');
            phoneStatus.textContent = '✗';
        }
    }

    if (phoneInput) {
        // Format on load if value exists
        if (phoneInput.value) {
            phoneInput.value = formatPhone(phoneInput.value);
            setPhoneState(validatePhone(phoneInput.value), false);
        }

        phoneInput.addEventListener('input', function (e) {
            const pos = this.selectionStart;
            const prev = this.value;
            const formatted = formatPhone(this.value);
            this.value = formatted;
            // Restore cursor position intelligently
            const added = formatted.length - prev.length;
            this.setSelectionRange(pos + added, pos + added);
            setPhoneState(validatePhone(formatted), formatted === '');
        });

        phoneInput.addEventListener('keydown', function (e) {
            // Allow: backspace, delete, tab, escape, arrows
            if ([8,9,27,46,37,38,39,40].includes(e.keyCode)) return;
            // Block non-numeric keys (except dash which we add automatically)
            if (e.key && !/[\d]/.test(e.key) && !e.ctrlKey && !e.metaKey) {
                e.preventDefault();
            }
        });

        phoneInput.addEventListener('blur', function () {
            setPhoneState(validatePhone(this.value), this.value === '');
        });
    }

    // Password toggle
    const pwInput = document.getElementById("new_password");
    const pwIcon  = document.getElementById("togglePassword");
    if (pwInput && pwIcon) {
        pwIcon.addEventListener("click", function () {
            pwInput.type = pwInput.type === "password" ? "text" : "password";
            pwIcon.src   = pwInput.type === "password" ? "image/hide.png" : "image/view.png";
        });
    }

    // Cropper setup
    const avatarWrap  = document.getElementById("avatarWrap");
    const fileInput   = document.getElementById("fileInput");
    const cropModal   = document.getElementById("cropModal");
    const cropImage   = document.getElementById("cropImage");
    const croppedData = document.getElementById("croppedData");
    const currentAvatar = document.getElementById("currentAvatar");
    let cropper = null;

    avatarWrap.addEventListener("click", () => fileInput.click());

    fileInput.addEventListener("change", function () {
        const file = this.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            cropImage.src = e.target.result;
            cropModal.style.display = "flex";
            if (cropper) { cropper.destroy(); cropper = null; }
            cropper = new Cropper(cropImage, {
                aspectRatio: 1,
                viewMode: 2,
                dragMode: "move",
                autoCropArea: 0.85,
                restore: false,
                guides: true,
                center: true,
                highlight: false,
                cropBoxMovable: true,
                cropBoxResizable: true,
                toggleDragModeOnDblclick: false,
            });
        };
        reader.readAsDataURL(file);
    });

    function closeModal() {
        cropModal.style.display = "none";
        if (cropper) { cropper.destroy(); cropper = null; }
        fileInput.value = "";
    }

    document.getElementById("cropClose").addEventListener("click", closeModal);
    document.getElementById("cropCancel").addEventListener("click", closeModal);

    document.getElementById("cropConfirm").addEventListener("click", function () {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: "high" });
        const dataURL = canvas.toDataURL("image/jpeg", 0.9);
        croppedData.value = dataURL;
        currentAvatar.src = dataURL;
        closeModal();
    });

    // Close on backdrop click
    cropModal.addEventListener("click", function (e) {
        if (e.target === cropModal) closeModal();
    });
});
</script>

</body>
</html>
