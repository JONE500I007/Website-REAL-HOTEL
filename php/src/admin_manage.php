<?php
session_start();
require_once "database.php";

// just check permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo "<div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Management</title>
    <link rel="stylesheet" href="style2.css?v=1.6">
</head>
<body>
    <div class="container">
        <h2 class="booking-title">เมนูจัดการระบบ (Admin)</h2>

        <div class="form-container" style="min-height:auto;">
            <div class="form-card" style="max-width:600px;">
                <h3>คุณต้องการจัดการอะไร?</h3>
                <div style="display:flex; flex-direction:column; gap:15px; margin-top:20px;">
                    <a href="admin_edit_users.php" class="btn-details">จัดการผู้ใช้</a>
                    <a href="admin_hotel_edit.php" class="btn-details">จัดการโรงแรม</a>
                    <a href="admin_edit_booking.php" class="btn-details">จัดการ การจองโรงแรม</a>
                </div>
            </div>
        </div>

        <div class="booking-back">
            <a href="index.php">⬅ กลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>