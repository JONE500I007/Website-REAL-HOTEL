<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$msg = '';
$validPaymentStatuses = ['pending_verification', 'confirmed', 'rejected'];
$paymentStatusLabels  = [
    'pending_verification' => 'รอตรวจสอบ',
    'confirmed'             => 'ยืนยันแล้ว',
    'rejected'              => 'ปฏิเสธแล้ว',
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["update_booking"])) {
        $id              = (int) $_POST["id"];
        $first_name      = $_POST["first_name"];
        $last_name       = $_POST["last_name"];
        $email           = $_POST["email"];
        $phone           = $_POST["phone"];
        $checkin         = $_POST["checkin"];
        $checkout        = $_POST["checkout"];
        $guests          = (int) $_POST["guests"];
        $book_hotel_name = $_POST["book_hotel_name"];
        $payment_status  = in_array($_POST["payment_status"] ?? '', $validPaymentStatuses, true)
            ? $_POST["payment_status"]
            : 'pending_verification';

        $stmt = $conn->prepare("
            UPDATE bookings
            SET first_name=?, last_name=?, email=?, phone=?, checkin=?, checkout=?, guests=?, book_hotel_name=?, payment_status=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssissi", $first_name, $last_name, $email, $phone, $checkin, $checkout, $guests, $book_hotel_name, $payment_status, $id);
        $ok  = $stmt->execute();
        $msg = $ok ? "อัปเดตข้อมูลการจองเรียบร้อยแล้ว" : "เกิดข้อผิดพลาด: " . $stmt->error;
        $stmt->close();
    }

    if (isset($_POST["delete_booking"])) {
        $id   = (int) $_POST["id"];
        $stmt = $conn->prepare("DELETE FROM bookings WHERE id=?");
        $stmt->bind_param("i", $id);
        $msg = $stmt->execute() ? "ลบข้อมูลการจองเรียบร้อยแล้ว" : "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
        $stmt->close();
    }
}

$result = $conn->query("SELECT * FROM bookings ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง (Admin)</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="container">
    <h2 class="booking-title"><span class="material-symbols-outlined">receipt_long</span> จัดการการจอง</h2>

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
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Guests</th>
                    <th>โรงแรม</th>
                    <th>ยอดชำระ</th>
                    <th>สลิป</th>
                    <th>สถานะการชำระเงิน</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <form method="post">
                        <td data-label="ID"><?= $row["id"] ?></td>
                        <td data-label="ชื่อ"><input type="text" name="first_name" value="<?= htmlspecialchars($row["first_name"]) ?>"></td>
                        <td data-label="นามสกุล"><input type="text" name="last_name" value="<?= htmlspecialchars($row["last_name"]) ?>"></td>
                        <td data-label="อีเมล"><input type="email" name="email" value="<?= htmlspecialchars($row["email"]) ?>"></td>
                        <td data-label="โทรศัพท์"><input type="text" name="phone" value="<?= htmlspecialchars($row["phone"]) ?>"></td>
                        <td data-label="Check-in"><input type="date" name="checkin" value="<?= htmlspecialchars($row["checkin"]) ?>"></td>
                        <td data-label="Check-out"><input type="date" name="checkout" value="<?= htmlspecialchars($row["checkout"]) ?>"></td>
                        <td data-label="Guests"><input type="number" name="guests" min="1" value="<?= htmlspecialchars($row["guests"]) ?>"></td>
                        <td data-label="โรงแรม"><input type="text" name="book_hotel_name" value="<?= htmlspecialchars($row["book_hotel_name"]) ?>"></td>
                        <td data-label="ยอดชำระ">฿<?= number_format((float) $row["total_price"], 2) ?></td>
                        <td data-label="สลิป">
                            <?php if (!empty($row["payment_slip"])): ?>
                                <a href="uploads/slips/<?= urlencode($row["payment_slip"]) ?>" target="_blank" rel="noopener" class="btn-table-view" style="text-decoration:none;">
                                    <span class="material-symbols-outlined">receipt</span> ดูสลิป
                                </a>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td data-label="สถานะการชำระเงิน">
                            <select name="payment_status" class="styled-select">
                                <?php foreach ($validPaymentStatuses as $statusOption): ?>
                                    <option value="<?= $statusOption ?>" <?= $row["payment_status"] === $statusOption ? "selected" : "" ?>>
                                        <?= $paymentStatusLabels[$statusOption] ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td data-label="การจัดการ">
                            <input type="hidden" name="id" value="<?= $row["id"] ?>">
                            <div class="admin-actions-cell">
                                <button type="submit" name="update_booking" class="btn-table-save">
                                    <span class="material-symbols-outlined">save</span> บันทึก
                                </button>
                                <button type="submit" name="delete_booking" class="btn-table-delete"
                                        onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบการจองนี้?');">
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
