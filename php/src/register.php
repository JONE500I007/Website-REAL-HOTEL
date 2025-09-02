<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัครสมาชิก</title>
    <link rel="stylesheet" href="style2.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <?php
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $full_name = $_POST["full_name"];
        $email = $_POST["email"];
        $phone_number = $_POST["phone_number"];
        $password = $_POST["password"];
        $passwordRepeat = $_POST["repeat_password"];

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $errors = array();

        if (empty($full_name) OR empty($email) OR empty($password) OR empty($passwordRepeat)) {
            array_push($errors, "กรุณากรอกข้อมูลให้ครบทุกช่อง");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            array_push($errors, "อีเมลไม่ถูกต้อง");
        }
        if (strlen($password) < 4) {
            array_push($errors, "รหัสผ่านต้องมีอย่างน้อย 4 ตัวอักษร");
        }
        if ($password !== $passwordRepeat) {
            array_push($errors, "รหัสผ่านไม่ตรงกัน");
        }

        require_once "database.php";
        $sql = "SELECT * FROM users WHERE email = '$email'";
        $result = mysqli_query($conn, $sql);
        $rowCount = mysqli_num_rows($result);
        if ($rowCount > 0) {
            array_push($errors, "อีเมลนี้ถูกใช้งานแล้ว");
        }

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                echo "<div class='alert alert-danger'>$error</div>";
            }
        } else {
            /*
            $defaultProfile = "default.jpg";
            $sql = "INSERT INTO users (full_name, email, phone_number, password, role, profile_picture) VALUES (?, ?, ?, ?, 'user', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssss", $full_name, $email, $phone_number, $passwordHash, $defaultProfile);
            */

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $sql = "INSERT INTO users (full_name, email, phone_number, password, role) VALUES (?, ?, ?, ?, 'user')";
            $stmt = $conn->prepare($sql);
            
            if ($stmt === false) {
                die("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("ssss", $full_name, $email, $phone_number, $passwordHash);

            if ($stmt->execute()) {
                echo "<div class='alert alert-success'>สมัครสมาชิกเรียบร้อยแล้ว</div>";
            } else {
                echo "<div class='alert alert-danger'>Something went wrong</div>";
                echo "<div>Error: " . $stmt->error . "</div>";
            }
        }
    }
    ?>
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Logo">
            </a>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="hotel.php">Hotel</a>
                <a href="contact.php">Contact</a>
            </nav>
            <div class="auth-links">
                <a href="register.php" class="btn-login">สมัครสมาชิก</a>
                <a href="login.php" class="btn-signup">เข้าสู่ระบบ</a>
            </div>
        </div>
    </header>
    
    <div class="form-container">
        <div class="form-card">
            <h2>สมัครสมาชิกผู้ใช้</h2>
            <form action="register.php" method="post">
                <input type="text" name="full_name" placeholder="ชื่อ - สกุล">
                <input type="email" name="email" placeholder="อีเมลของคุณ">
                <input type="text" name="phone_number" placeholder="เบอร์โทรของคุณ">
                <input type="password" name="password" placeholder="รหัสผ่าน">
                <input type="password" name="repeat_password" placeholder="ยืนยันรหัสผ่าน">
                <button type="submit" name="submit">สมัครสมาชิก</button>
            </form>
            <p>ได้สมัครสมาชิกแล้วใช่หรือไม่? <a href="login.php">คลิกที่นี่เพื่อเข้าสู่ระบบ</a></p>
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