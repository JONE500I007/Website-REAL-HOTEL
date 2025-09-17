<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    $_SESSION["error"] = "กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน";
    header("Location: login.php");
    exit;
}

// Retrieve all booking information form databsese
$sql = "SELECT b.id, b.first_name, b.last_name, b.email, b.phone, b.country, 
               b.checkin, b.checkout, b.guests, b.book_hotel_name AS hotel_name
        FROM bookings b
        ORDER BY b.id DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการจองโรงแรม</title>
    <link rel="stylesheet" href="style2.css?v=1.6">
</head>
<body>

    <h2 class="booking-title">📋 รายการจองโรงแรมของคุณ</h2>

    <table class="booking-list">
        <thead>
            <tr>
                <th>ชื่อผู้จอง</th>
                <th>อีเมล</th>
                <th>เบอร์โทร</th>
                <th>ประเทศ</th>
                <th>โรงแรม</th>
                <th>เช็คอิน</th>
                <th>เช็คเอาท์</th>
                <th>จำนวนผู้เข้าพัก</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row["first_name"] . " " . $row["last_name"]) ?></td>
                        <td><?= htmlspecialchars($row["email"]) ?></td>
                        <td><?= htmlspecialchars($row["phone"]) ?></td>
                        <td><?= htmlspecialchars($row["country"]) ?></td>
                        <td><?= htmlspecialchars($row["hotel_name"] ?? "ไม่ระบุ") ?></td>
                        <td><?= htmlspecialchars($row["checkin"]) ?></td>
                        <td><?= htmlspecialchars($row["checkout"]) ?></td>
                        <td><?= htmlspecialchars($row["guests"]) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8">ยังไม่มีการจอง</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="booking-back">
        <a href="index.php" class="btn-signup">กลับหน้าหลัก</a>
    </div>

</body>
</html>