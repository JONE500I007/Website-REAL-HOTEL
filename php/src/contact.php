<?php
session_start();
require_once "config/database.php";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<section class="contact-section">
    <div class="contact-card">
        <div class="contact-left">
            <img src="image/mydogin.png" alt="Profile" class="profile-img">
        </div>
        <div class="contact-right">
            <h2>DEVELOPER PROFILE</h2>
            <p><b>ชื่อ:</b> นาย อิงควัฒน์ คงใจมั่น</p>
            <p><b>ชื่อเล่น:</b> โซดา</p>
            <p><b>อายุ:</b> 22 ปี</p>
            <p><b>วัน/เดือน/ปี เกิด:</b> 6 สิงหาคม พ.ศ.2546</p>
            <p><b>สาขาวิชา:</b> เทคโนโลยีสารสนเทศ (IT)</p>
            <p><b>สถานศึกษา:</b> มหาวิทยาลัยราชภัฏเลย ปี 4</p>

            <h3>MY CONTACT</h3>
            <p><span class="material-symbols-outlined">link</span> Facebook: <a href="https://www.facebook.com/bennett.impact.2025/">Bennett Impact</a></p>
            <p><span class="material-symbols-outlined">mail</span> Email: <a href="Ingkawat2023Reals@gmail.com">Ingkawat2023Reals@gmail.com</a></p>
            <p><span class="material-symbols-outlined">call</span> Phone: <a href="tel:0973199931">097-319-9931</a></p>
        </div>
    </div>
</section>

<section class="map-section">
    <h3>สามารถติดต่อได้ที่มหาวิทยาลัยราชภัฏเลย</h3>
    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3804.350032431708!2d101.71874977516839!3d17.53851378337468!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31214835d001557f%3A0xd91f2a61d038e03c!2z4Lih4Lir4Liy4Lin4Li04LiX4Lii4Liy4Lil4Lix4Lii4Lij4Liy4LiK4Lig4Lix4LiP4LmA4Lil4LiiIExvZWkgUmFqYWJoYXQgVW5pdmVyc2l0eQ!5e0!3m2!1sth!2sth!4v1783074263962!5m2!1sth!2sth"
        width="100%" height="350" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
</section>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js"></script>
</body>
</html>
