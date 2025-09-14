<?php
session_start();
if (isset($_SESSION["user"])) {
    header("Location: index.php");
}

require_once "database.php";
if (isset($_POST["login"])) {
    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if ($user && password_verify($password, $user["password"])) {
        
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["user"] = $user["full_name"];
        $_SESSION["role"] = $user["role"];
        $_SESSION["profile_picture"] = $user["profile_picture"];
        header("Location: index.php");
        exit;
    } else {
        echo "<div class='alert alert-danger'>Invalid email or password.</div>";
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
    <link rel="stylesheet" href="style2.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
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
                <a href="register.php" class="btn-signup">สมัครสมาชิก</a>
                <a href="login.php" class="btn-login">เข้าสู่ระบบ</a>
            </div>
        </div>
    </header>
    
    <div class="form-container">
        <div class="form-card">
            <h2>เข้าสู่ระบบ</h2>
            <form action="login.php" method="post">
                <input type="email" name="email" placeholder="อีเมล">
                <input type="password" name="password" placeholder="รหัสผ่าน">
                <!--
                <p class="admin-link">สำหรับ เจ้าของโรงแรม <a href="#">คลิกที่นี่</a></p>
                -->
                <button type="submit" name="login">เข้าสู่ระบบ</button>
            </form>
            <p>สมัครสมาชิกแล้วหรือยัง? <a href="register.php">คลิกที่นี่เพื่อสมัครสมาชิก</a></p>
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