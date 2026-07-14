<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_email = $_SESSION["user_email"] ?? "";
$stmt = $conn->prepare("
    SELECT b.id, b.first_name, b.last_name, b.email, b.phone,
           b.checkin, b.checkout, b.guests, b.book_hotel_name,
           b.total_price, b.payment_status, rt.room_name
    FROM bookings b
    LEFT JOIN room_types rt ON rt.id = b.room_type_id
    WHERE b.email = ?
    ORDER BY b.id DESC
");
$stmt->bind_param("s", $user_email);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการจองโรงแรม</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="board-page">
    <div class="board-header">
        <div>
            <h1><span class="material-symbols-outlined">receipt_long</span> รายการจองของคุณ</h1>
            <p>ประวัติการจองโรงแรมทั้งหมดของ <?= htmlspecialchars($_SESSION['user']) ?></p>
        </div>
        <a href="index.php" class="board-back-btn"><span class="material-symbols-outlined">home</span> หน้าหลัก</a>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <div class="booking-cards">
            <?php
            $paymentStatusLabels = [
                'pending_verification' => ['label' => 'รอตรวจสอบการชำระเงิน', 'icon' => 'hourglass_top',  'color' => '#b8860b'],
                'confirmed'             => ['label' => 'ยืนยันการจองแล้ว',      'icon' => 'check_circle',   'color' => '#2e7d32'],
                'rejected'              => ['label' => 'การชำระเงินถูกปฏิเสธ',   'icon' => 'cancel',         'color' => '#c62828'],
            ];
            while ($row = $result->fetch_assoc()):
                $checkin  = $row['checkin']  ? new DateTime($row['checkin'])  : null;
                $checkout = $row['checkout'] ? new DateTime($row['checkout']) : null;
                $nights   = ($checkin && $checkout) ? $checkin->diff($checkout)->days : '-';
                $status   = $paymentStatusLabels[$row['payment_status']] ?? $paymentStatusLabels['pending_verification'];
            ?>
            <div class="booking-card">
                <div class="booking-card-top">
                    <div class="booking-hotel-name">
                        <span class="material-symbols-outlined">hotel</span> <?= htmlspecialchars($row['book_hotel_name'] ?? 'ไม่ระบุโรงแรม') ?>
                    </div>
                    <div class="booking-nights-badge"><?= $nights ?> คืน</div>
                </div>
                <div class="booking-card-body">
                    <div class="booking-payment-status">
                        <span class="payment-status-label" style="color:<?= $status['color'] ?>;">
                            <span class="material-symbols-outlined"><?= $status['icon'] ?></span> <?= $status['label'] ?>
                        </span>
                        <?php if ((float) $row['total_price'] > 0): ?>
                            <span class="payment-status-total">
                                ยอดรวม ฿<?= number_format((float) $row['total_price'], 2) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="booking-dates">
                        <div class="booking-date-box">
                            <span class="date-label">เช็คอิน</span>
                            <span class="date-value"><?= $checkin  ? $checkin->format('d M Y')  : '-' ?></span>
                        </div>
                        <div class="booking-date-arrow material-symbols-outlined">arrow_forward</div>
                        <div class="booking-date-box">
                            <span class="date-label">เช็คเอาท์</span>
                            <span class="date-value"><?= $checkout ? $checkout->format('d M Y') : '-' ?></span>
                        </div>
                    </div>

                    <div class="booking-info-grid">
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">person</span>
                            <div>
                                <span class="info-label">ผู้จอง</span>
                                <span class="info-value"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">mail</span>
                            <div>
                                <span class="info-label">อีเมล</span>
                                <span class="info-value"><?= htmlspecialchars($row['email']) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">call</span>
                            <div>
                                <span class="info-label">เบอร์โทร</span>
                                <span class="info-value"><?= htmlspecialchars($row['phone']) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">bed</span>
                            <div>
                                <span class="info-label">ห้องที่จอง</span>
                                <span class="info-value"><?= htmlspecialchars($row['room_name'] ?? 'ไม่ระบุห้อง') ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">group</span>
                            <div>
                                <span class="info-label">จำนวนผู้เข้าพัก</span>
                                <span class="info-value"><?= htmlspecialchars($row['guests']) ?> คน</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="board-empty">
            <div class="board-empty-icon material-symbols-outlined">hotel</div>
            <h3>ยังไม่มีการจองโรงแรม</h3>
            <p>เริ่มค้นหาและจองโรงแรมที่คุณชื่นชอบได้เลย</p>
            <a href="hotel.php" class="auth-btn" style="display:inline-block;width:auto;padding:12px 30px;text-decoration:none;">ค้นหาโรงแรม</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
