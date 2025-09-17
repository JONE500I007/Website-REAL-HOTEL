<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    // Redirect or report that you are not logged in
    header("Location: login.php");
    exit;
}

require_once "database.php";
$user_email = $_SESSION["user_email"];

// load databases
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user["id"];
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = $_SESSION["user_id"];
    $full_name = $_POST["full_name"] ?? '';
    $email = $_POST["email"] ?? '';
    $phone_number = $_POST["phone_number"] ?? '';
    $new_password = $_POST["new_password"] ?? '';
    $errors = [];

    if (empty($full_name) || empty($email) || empty($phone_number)) {
        $errors[] = "กรุณากรอกข้อมูลให้ครบทุกช่อง";
    }

    if (!empty($new_password) && strlen($new_password) < 4) {
        $errors[] = "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร";
    }

    $profile_image = $user["profile_picture"];
    $ext = "";
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir);
        }

        $profile_image_name = $_FILES["profile_picture"]["name"];

        //Check file extension
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        //$ext = pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION);
        $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
        $target_file = $target_dir . $new_filename;
        $target_file = iconv("UTF-8", "UTF-8//IGNORE", $target_file);

        if (in_array($ext, $allowed_extensions)) {
            // new name file
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            $target_file = $target_dir . $new_filename;

            if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
                $profile_image = $new_filename; 
                $_SESSION["profile_picture"] = $profile_image;
            } else {
                $errors[] = "ไม่สามารถอัปโหลดรูปภาพได้";
            }
        } else {
            $errors[] = "ไม่รองรับนามสกุลไฟล์นี้ (อนุญาตเฉพาะ JPG, JPEG, PNG, GIF)";
        }
    }


    if (count($errors) === 0) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, password = ?, profile_picture = ? WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssss", $full_name, $email, $phone_number, $hashed_password, $profile_image, $user_email);
        } else {
            $sql = "UPDATE users SET full_name = ?, email = ?, phone_number = ?, profile_picture = ? WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $full_name, $email, $phone_number, $profile_image, $user_email);
        }

        if ($stmt->execute()) {
            $_SESSION["user"] = $full_name;
            $_SESSION["user_email"] = $email;
            /*
            $target_dir = "uploads/";
            $new_filename = "profile_" . $user_id . "_" . time() . "." . $ext;
            */

            echo "<div class='alert alert-success'>อัปเดตข้อมูลเรียบร้อยแล้ว</div>";
            $user["full_name"] = $full_name;
            $user["email"] = $email;
            $user["phone_number"] = $phone_number;
        } else {
            echo "<div class='alert alert-danger'>เกิดข้อผิดพลาดในการอัปเดตข้อมูล</div>";
        }
        $stmt->close();
    } else {
        foreach ($errors as $error) {
            echo "<div class='alert alert-danger'>$error</div>";
        }
    }
}

$user_id = $_SESSION["user_id"];
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_id = $user["id"];
$user = $result->fetch_assoc();
?>

<?php if (isset($_GET["success"])): ?>
    <p style="color: green;">โปรไฟล์ของคุณถูกอัปเดตเรียบร้อยแล้ว</p>
<?php endif; ?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรไฟล์</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="style2.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="logo">
                <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Logo">
            </div>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="hotel.php">Hotel</a>
                <a href="contact.php">Contact</a>
            </nav>
            <div class="auth-links">
            <?php if (!isset($_SESSION["user"])): ?>
                <a href="register.php" class="btn-signup">สมัครสมาชิก</a>
                <a href="login.php" class="btn-login">เข้าสู่ระบบ</a>
            <?php else: ?>
                <div class="profile-menu">
                    <div class="profile-icon" onclick="toggleMenu()">
                        <img src="uploads/<?= $_SESSION["profile_picture"] ?? 'default.jpg' ?>" alt="Profile">
                        <span><?= $_SESSION["user"] ?></span>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="edit_profile.php">แก้ไขโปรไฟล์</a>
                        <?php if ($_SESSION["role"] === "owner"): ?>
                            <?php
                                $owner_id = $_SESSION["user_id"];
                                $check_sql = "SELECT id FROM hotels WHERE owner_id = ?";
                                $check_stmt = $conn->prepare($check_sql);
                                $check_stmt->bind_param("i", $owner_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                $hasHotel = $check_result->num_rows > 0;
                                $check_stmt->close();
                            ?>
                            <a href="manage_hotels.php">
                                <?= $hasHotel ? "แก้ไขโรงแรม" : "เพิ่มโรงแรม" ?>
                            </a>
                        <?php endif; ?>
                        <a href="board.php">ดูการจองโรงแรม</a>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <div class="form-card">
            <h2>แก้ไขโปรไฟล์</h2>
            <form action="edit_profile.php" method="post" enctype="multipart/form-data">
                <input type="text" name="full_name" placeholder="ชื่อ - สกุล" value="<?= htmlspecialchars($user["full_name"]) ?>">
                <input type="text" name="email" placeholder="อีเมล" value="<?= htmlspecialchars($user["email"]) ?>">
                <input type="text" name="phone_number" placeholder="เบอร์โทรของคุณ" value="<?= htmlspecialchars($user["phone_number"]) ?>">
                <!--
                <input type="password" name="new_password" placeholder="รหัสผ่านใหม่ (ไม่ต้องกรอกหากไม่ต้องการเปลี่ยน)">
                        -->
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" placeholder="รหัสผ่านใหม่ (ไม่ต้องกรอกหากไม่ต้องการเปลี่ยน)">
                    <img src="image/hide.png" class="toggle-password" id="togglePassword" alt="toggle">
                </div>

                <!--
                <input type="file" name="profile_picture" accept="image/*">
                -->
                <div class="form-group">
                    <label for="profile_picture">รูปโปรไฟล์ใหม่ (เลือกเฉพาะ .jpg, .png):</label>
                    <input type="file" name="profile_picture" class="form-control">
                </div>

                <button type="submit">บันทึกการเปลี่ยนแปลง</button>
            </form>
            <p><a href="index.php">กลับหน้าหลัก</a></p>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
                </div>
                <p>© 2025 PNVC, นายครรชิดพล เพ็งเอียด</p>
                <div class="social-icons">
                    <a href="https://x.com/FGgez777"><img src="image\twwokX.png" alt="Twitter_X"></a>
                    <a href="https://www.instagram.com/face.2339/?igsh=dWh6eGtmbjVpanRt"><img src="image\insatagem.png" alt="Instagram"></a>
                    <a href="https://www.facebook.com/face.pengeid/"><img src="image\fackbookicon.png" alt="Facebook"></a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

<script>
function toggleMenu() {
    const menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}
document.addEventListener('click', function(event) {
    const menu = document.getElementById("dropdownMenu");
    const profileIcon = document.querySelector('.profile-icon');
    if (menu && !profileIcon.contains(event.target)) {
        menu.style.display = "none";
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const passwordInput = document.getElementById("new_password");
    const toggleIcon = document.getElementById("togglePassword");

    toggleIcon.addEventListener("click", function() {
        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            toggleIcon.src = "image/view.png";
        } else {
            passwordInput.type = "password";
            toggleIcon.src = "image/hide.png";
        }
    });
});
</script>