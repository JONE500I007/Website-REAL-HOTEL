<?php
session_start();
require_once "database.php";

// just check permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo "<div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

// update booking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_booking"])) {
    $id = intval($_POST["id"]);
    $first_name = $_POST["first_name"];
    $last_name = $_POST["last_name"];
    $email = $_POST["email"];
    $phone = $_POST["phone"];
    $country = $_POST["country"];
    $checkin = $_POST["checkin"];
    $checkout = $_POST["checkout"];
    $guests = intval($_POST["guests"]);
    $book_hotel_name = $_POST["book_hotel_name"];

    $sql = "UPDATE bookings 
            SET first_name=?, last_name=?, email=?, phone=?, country=?, checkin=?, checkout=?, guests=?, book_hotel_name=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssssi", $first_name, $last_name, $email, $phone, $country, $checkin, $checkout, $guests, $book_hotel_name, $id);
    $stmt->execute();
    $stmt->close();

    $msg = "อัปเดตข้อมูลการจองเรียบร้อยแล้ว";
}

// delete booking
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_booking"])) {
    $id = intval($_POST["id"]);

    $sql = "DELETE FROM bookings WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $msg = "ลบข้อมูลการจองเรียบร้อยแล้ว";
    } else {
        $msg = "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
    }
    $stmt->close();
}

// retrieve all booking information
$sql = "SELECT * FROM bookings ORDER BY id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการการจอง (Admin)</title>
    <link rel="stylesheet" href="style2.css?v=1.6">
</head>
<body>
    <div class="container">
        <h2 class="booking-title">จัดการการจอง</h2>
        <!--show msg dont or error-->
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <div style="overflow-x:auto;">
            <table class="booking-list">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อ</th>
                        <th>นามสกุล</th>
                        <th>อีเมล</th>
                        <th>โทรศัพท์</th>
                        <th>ประเทศ</th>
                        <th>Check-in</th>
                        <th>Check-out</th>
                        <th>Guests</th>
                        <th>โรงแรม</th>
                        <th>การจัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <form method="post">
                            <td><?= $row["id"] ?></td>
                            <td><input type="text" name="first_name" value="<?= htmlspecialchars($row["first_name"]) ?>"></td>
                            <td><input type="text" name="last_name" value="<?= htmlspecialchars($row["last_name"]) ?>"></td>
                            <td><input type="email" name="email" value="<?= htmlspecialchars($row["email"]) ?>"></td>
                            <td><input type="text" name="phone" value="<?= htmlspecialchars($row["phone"]) ?>"></td>
                            <td><input type="text" name="country" value="<?= htmlspecialchars($row["country"]) ?>"></td>
                            <td><input type="date" name="checkin" value="<?= htmlspecialchars($row["checkin"]) ?>"></td>
                            <td><input type="date" name="checkout" value="<?= htmlspecialchars($row["checkout"]) ?>"></td>
                            <td><input type="number" name="guests" min="1" value="<?= htmlspecialchars($row["guests"]) ?>"></td>
                            <td><input type="text" name="book_hotel_name" value="<?= htmlspecialchars($row["book_hotel_name"]) ?>"></td>
                            <td>
                                <input type="hidden" name="id" value="<?= $row["id"] ?>">
                                <button type="submit" name="update_booking">บันทึก</button>
                                <br><br>
                                <button type="submit" name="delete_booking" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบการจองนี้?');">ลบ</button>
                            </td>
                        </form>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    

        <div class="booking-back">
            <a href="admin_manage.php">⬅ กลับเมนู Admin</a>
        </div>
    </div>
</body>
</html>