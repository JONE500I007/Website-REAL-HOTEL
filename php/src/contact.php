<?php
session_start();
require_once "database.php";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="style2.css?v=1.5">
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
                <a href="login.php" class="btn-signup">เข้าสู่ระบบ</a>
            <?php else: ?>
                <div class="profile-menu">
                    <div class="profile-icon" onclick="toggleMenu()">
                        <img src="uploads/<?= $_SESSION["profile_picture"] ?? 'default.jpg' ?>" alt="Profile">
                        <span><?= $_SESSION["user"] ?></span>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="edit_profile.php">แก้ไขโปรไฟล์</a>
                        <?php if ($_SESSION["role"] === "owner" || $_SESSION["role"] === "admin"): ?>
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

                        <?php if ($_SESSION["role"] === "admin"): ?>
                            <a href="admin_manage.php">จัดการระบบ</a>
                        <?php endif; ?>

                        <a href="board.php">ดูการจองโรงแรม</a>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Contact Section -->
    <section class="contact-section">
        <div class="contact-card">
            <div class="contact-left">
                <img src="image/mydogin2.png" alt="Profile" class="profile-img">
            </div>
            <div class="contact-right">
                <h2>DEVOLROPER PROFILE</h2>
                <p><b>ชื่อ:</b> นาย ศรชิตพล เพ็งเอียด</p>
                <p><b>ชื่อเล่น:</b> เฟส</p>
                <p><b>อายุ:</b> 18 ปี</p>
                <p><b>วัน/เดือน/ปี เกิด:</b> 29 พฤษภาคม พ.ศ.2549</p>
                <p><b>สาขาวิชา:</b> เทคโนโลยีสารสนเทศ (IT)</p>
                <p><b>สถานศึกษา:</b> วิทยาลัยอาชีวศึกษาปัตตานี ระดับชั้น ปวส. ปีที่ 1/2</p>

                <h3>MY CONTACT</h3>
                <p>📘 Facebook: <a href="#">Face Kanchidpon</a></p>
                <p>📧 Email: <a href="mailto:khrabfes37@gmail.com">khrabfes37@gmail.com</a></p>
                <p>📱 Phone: <a href="tel:0646419643">064-641-9643</a></p>
            </div>
        </div>
    </section>

    <!-- Map Section -->
    <section class="map-section">
        <h3>สามารถติดต่อได้ที่วิทยาลัยอาชีวศึกษาปัตตานี</h3>
        <!--
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3923.283048469239!2d101.25324631433644!3d6.867547695030537!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x310959d47f0f3f3f%3A0x4cb37e9df7087dd!2z4Liq4LiW4Liy4Lih4Lin4Li04LiU4LmM4LmC4LiZ4Lit4LiH4LmC4LiU4Li04LmA4LiC4Lil4LiU4LmB4Lil4LmJ4Liy4LiB4LmE4LiB4LiX4Lia4LmJ4Lit4LiU4Lir4LmJ4Lih4LiH4Liy4Lij4Liw4Lia4Lij4Li54Lih4LiL4Lij4LmM!5e0!3m2!1sth!2sth!4v1705555555555!5m2!1sth!2sth"
            width="100%" height="350" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        -->

        <!--
        <iframe src="https://www.google.com/maps/place/วิทยาลัยอาชีวศึกษาปัตตานี/@6.8673407,101.2437005,18z/data=!4m9!1m2!7m1!2e1!3m5!1s0x31b3055fbf941a19:0xcbeff04afb42e175!8m2!3d6.8671223!4d101.2438078!16s%2Fg%2F11rjypdzk3?entry=ttu&g_ep=EgoyMDI1MDgyNS4wIKXMDSoASAFQAw%3D%3D"
            width="100%" height="350" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        -->
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3920.6185949937126!2d101.24161337595248!3d6.867340720903566!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31b3055fbf941a19%3A0xcbeff04afb42e175!2z4LmA4LiK4Li04LiB4LiK4Lit4Li04LiH4LiI4Li44LmM4LmA4LiB4Lij4Li04LiZ4Lij4Lih4Li04LiH4LiE4Li44LmI4Lih4LmB4Lil4Lix4LiB4Liy4Lin4LiB4Liy4Lij4LmM4LmB4Lil4Lij4Li0!5e0!3m2!1sth!2sth!4v1693552900000!5m2!1sth!2sth"
            width="100%" height="350" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
    </section>
    
    <!-- just my under1 -->

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