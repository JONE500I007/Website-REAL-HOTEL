<?php
session_start();
require_once "database.php";

// check permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo "<div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

// updates hotel
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_hotel"])) {
    $id = intval($_POST["id"]);
    $hotel_name = $_POST["hotel_name"];
    $location = $_POST["location"];
    $price = $_POST["price"];
    $description = $_POST["description"];
    $facilities = $_POST["facilities"];
    $surrounding = $_POST["surrounding"];

    $sql = "UPDATE hotels 
            SET hotel_name=?, location=?, price=?, description=?, facilities=?, surrounding=? 
            WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssssi", $hotel_name, $location, $price, $description, $facilities, $surrounding, $id);
    $stmt->execute();
    $stmt->close();

    $msg = "อัปเดตข้อมูลโรงแรมเรียบร้อยแล้ว";
}

// retrieve all hotel data
$sql = "SELECT * FROM hotels ORDER BY id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการโรงแรม (Admin)</title>
    <link rel="stylesheet" href="style2.css?v=1.6">
</head>
<body>
    <div class="container">
        <h2 class="booking-title">จัดการโรงแรม</h2>
        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <table class="booking-list">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อโรงแรม</th>
                    <th>ที่ตั้ง</th>
                    <th>ราคา</th>
                    <th>รายละเอียด</th>
                    <th>สิ่งอำนวยความสะดวก</th>
                    <th>บริเวณโดยรอบ</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <form method="post">
                        <td><?= $row["id"] ?></td>
                        <td><input type="text" name="hotel_name" value="<?= htmlspecialchars($row["hotel_name"]) ?>"></td>
                        <td><input type="text" name="location" value="<?= htmlspecialchars($row["location"]) ?>"></td>
                        <td><input type="text" name="price" value="<?= htmlspecialchars($row["price"]) ?>"></td>
                        <td><input type="text" name="description" value="<?= htmlspecialchars($row["description"]) ?>"></td>
                        <td><input type="text" name="facilities" value="<?= htmlspecialchars($row["facilities"]) ?>"></td>
                        <td><input type="text" name="surrounding" value="<?= htmlspecialchars($row["surrounding"]) ?>"></td>
                        <td>
                            <input type="hidden" name="id" value="<?= $row["id"] ?>">
                            <button type="submit" name="update_hotel">บันทึก</button>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="booking-back">
            <a href="admin_manage.php">⬅ กลับเมนู Admin</a>
        </div>
    </div>
</body>
</html>