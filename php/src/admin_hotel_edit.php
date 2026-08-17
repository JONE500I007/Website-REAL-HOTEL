<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$msg = '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["update_hotel"])) {
        $id          = (int) $_POST["id"];
        $hotel_name  = $_POST["hotel_name"];
        $location    = $_POST["location"];
        $province    = in_array($_POST["province"] ?? '', thai_provinces(), true) ? $_POST["province"] : '';
        $price       = $_POST["price"];
        $description = $_POST["description"];
        $facilities  = $_POST["facilities"];
        $surrounding = $_POST["surrounding"];

        $stmt = $conn->prepare("
            UPDATE hotels SET hotel_name=?, location=?, province=?, price=?, description=?, facilities=?, surrounding=?
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi", $hotel_name, $location, $province, $price, $description, $facilities, $surrounding, $id);
        $stmt->execute();
        $stmt->close();
        $msg = "อัปเดตข้อมูลโรงแรมเรียบร้อยแล้ว";
    }

    if (isset($_POST["delete_hotel"])) {
        $id   = (int) $_POST["id"];
        $stmt = $conn->prepare("DELETE FROM hotels WHERE id=?");
        $stmt->bind_param("i", $id);
        $msg = $stmt->execute() ? "ลบโรงแรมเรียบร้อยแล้ว" : "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
        $stmt->close();
    }
}

$result = $conn->query("SELECT * FROM hotels ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการโรงแรม (Admin)</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="container">
    <h2 class="booking-title"><span class="material-symbols-outlined">hotel</span> จัดการโรงแรม</h2>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div style="overflow-x:auto;">
        <table class="booking-list">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อโรงแรม</th>
                    <th>ที่ตั้ง</th>
                    <th>จังหวัด</th>
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
                        <td data-label="ID"><?= $row["id"] ?></td>
                        <td data-label="ชื่อโรงแรม"><input type="text" name="hotel_name" value="<?= htmlspecialchars($row["hotel_name"]) ?>"></td>
                        <td data-label="ที่ตั้ง"><input type="text" name="location" value="<?= htmlspecialchars($row["location"]) ?>"></td>
                        <td data-label="จังหวัด">
                            <select name="province">
                                <option value="">-- ไม่ระบุ --</option>
                                <?php foreach (thai_provinces() as $provinceName): ?>
                                    <option value="<?= htmlspecialchars($provinceName) ?>"
                                        <?= ($row["province"] ?? '') === $provinceName ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($provinceName) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="ราคา"><input type="text" name="price" value="<?= htmlspecialchars($row["price"]) ?>"></td>
                        <td data-label="รายละเอียด"><input type="text" name="description" value="<?= htmlspecialchars($row["description"]) ?>"></td>
                        <td data-label="สิ่งอำนวยความสะดวก"><input type="text" name="facilities" value="<?= htmlspecialchars($row["facilities"]) ?>"></td>
                        <td data-label="บริเวณโดยรอบ"><input type="text" name="surrounding" value="<?= htmlspecialchars($row["surrounding"]) ?>"></td>
                        <td data-label="การจัดการ">
                            <input type="hidden" name="id" value="<?= $row["id"] ?>">
                            <div class="admin-actions-cell">
                                <button type="submit" name="update_hotel" class="btn-table-save">
                                    <span class="material-symbols-outlined">save</span> บันทึก
                                </button>
                                <button type="submit" name="delete_hotel" class="btn-table-delete"
                                        onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบโรงแรมนี้?');">
                                    <span class="material-symbols-outlined">delete</span> ลบ
                                </button>
                            </div>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="booking-back">
        <a href="admin_manage.php"><span class="material-symbols-outlined">arrow_back</span> กลับเมนู Admin</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
