<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$userCount    = (int) $conn->query("SELECT COUNT(*) AS c FROM users")->fetch_assoc()["c"];
$hotelCount   = (int) $conn->query("SELECT COUNT(*) AS c FROM hotels")->fetch_assoc()["c"];
$bookingCount = (int) $conn->query("SELECT COUNT(*) AS c FROM bookings")->fetch_assoc()["c"];
$pendingCount = (int) $conn->query("SELECT COUNT(*) AS c FROM bookings WHERE payment_status = 'pending_verification'")->fetch_assoc()["c"];
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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="admin-dashboard">
    <div class="admin-dashboard-header">
        <h1><span class="material-symbols-outlined">shield_person</span> แผงควบคุมผู้ดูแลระบบ</h1>
        <p>ภาพรวมและเมนูจัดการระบบทั้งหมด</p>
    </div>

    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">group</span>
            <div>
                <div class="admin-stat-value"><?= number_format($userCount) ?></div>
                <div class="admin-stat-label">ผู้ใช้ทั้งหมด</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">hotel</span>
            <div>
                <div class="admin-stat-value"><?= number_format($hotelCount) ?></div>
                <div class="admin-stat-label">โรงแรมทั้งหมด</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">receipt_long</span>
            <div>
                <div class="admin-stat-value"><?= number_format($bookingCount) ?></div>
                <div class="admin-stat-label">การจองทั้งหมด</div>
            </div>
        </div>
        <div class="admin-stat-card<?= $pendingCount > 0 ? ' admin-stat-alert' : '' ?>">
            <span class="material-symbols-outlined admin-stat-icon">hourglass_top</span>
            <div>
                <div class="admin-stat-value"><?= number_format($pendingCount) ?></div>
                <div class="admin-stat-label">รอตรวจสอบการชำระเงิน</div>
            </div>
        </div>
    </div>

    <h2 class="admin-section-title">คุณต้องการจัดการอะไร?</h2>
    <div class="admin-menu-grid">
        <a href="admin_edit_users.php" class="admin-menu-card">
            <span class="material-symbols-outlined admin-menu-icon">group</span>
            <div class="admin-menu-title">จัดการผู้ใช้</div>
            <p class="admin-menu-desc">แก้ไขข้อมูล เปลี่ยนสิทธิ์ และลบบัญชีผู้ใช้</p>
        </a>
        <a href="admin_hotel_edit.php" class="admin-menu-card">
            <span class="material-symbols-outlined admin-menu-icon">hotel</span>
            <div class="admin-menu-title">จัดการโรงแรม</div>
            <p class="admin-menu-desc">แก้ไขข้อมูลโรงแรมและสิ่งอำนวยความสะดวก</p>
        </a>
        <a href="admin_edit_booking.php" class="admin-menu-card">
            <span class="material-symbols-outlined admin-menu-icon">receipt_long</span>
            <div class="admin-menu-title">จัดการการจองโรงแรม</div>
            <p class="admin-menu-desc">ตรวจสอบการจองและสถานะการชำระเงิน</p>
        </a>
    </div>

    <div class="booking-back">
        <a href="index.php"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
