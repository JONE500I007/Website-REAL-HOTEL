<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Management</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="container">
    <h2 class="booking-title">เมนูจัดการระบบ (Admin)</h2>

    <div class="form-container" style="min-height:auto;">
        <div class="form-card" style="max-width:600px;">
            <h3>คุณต้องการจัดการอะไร?</h3>
            <div style="display:flex; flex-direction:column; gap:15px; margin-top:20px;">
                <a href="admin_edit_users.php" class="btn-details">จัดการผู้ใช้</a>
                <a href="admin_hotel_edit.php" class="btn-details">จัดการโรงแรม</a>
                <a href="admin_edit_booking.php" class="btn-details">จัดการการจองโรงแรม</a>
            </div>
        </div>
    </div>

    <div class="booking-back">
        <a href="index.php">⬅ กลับหน้าหลัก</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js"></script>
</body>
</html>
