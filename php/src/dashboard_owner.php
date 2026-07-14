<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "owner") {
    header("Location: index.php");
    exit;
}

$owner_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT id, hotel_name FROM hotels WHERE owner_id = ?");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();
$stmt->close();

$msg = '';
$validPaymentStatuses = ['pending_verification', 'confirmed', 'rejected'];

if ($hotel && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"], $_POST["payment_status"])
    && in_array($_POST["payment_status"], $validPaymentStatuses, true)) {
    $id             = (int) $_POST["id"];
    $payment_status = $_POST["payment_status"];

    // hotel_id=? keeps an owner from updating another hotel's booking by
    // tampering with the hidden id field.
    $stmt = $conn->prepare("UPDATE bookings SET payment_status = ? WHERE id = ? AND hotel_id = ?");
    $stmt->bind_param("sii", $payment_status, $id, $hotel["id"]);
    $ok  = $stmt->execute();
    $msg = $ok ? "อัปเดตสถานะการชำระเงินเรียบร้อยแล้ว" : "เกิดข้อผิดพลาด: " . $stmt->error;
    $stmt->close();
}

$bookings = [];
$pendingCount = 0;
if ($hotel) {
    $stmt = $conn->prepare("
        SELECT b.id, b.first_name, b.last_name, b.email, b.phone,
               b.checkin, b.checkout, b.guests, b.total_price,
               b.payment_slip, b.payment_status, rt.room_name
        FROM bookings b
        LEFT JOIN room_types rt ON rt.id = b.room_type_id
        WHERE b.hotel_id = ?
        ORDER BY b.id DESC
    ");
    $stmt->bind_param("i", $hotel["id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if ($row["payment_status"] === "pending_verification") {
            $pendingCount++;
        }
    }
    $stmt->close();
}

$statusMeta = [
    'pending_verification' => ['label' => 'รอตรวจสอบ', 'icon' => 'hourglass_top',  'class' => 'status-pending'],
    'confirmed'             => ['label' => 'ยืนยันแล้ว', 'icon' => 'check_circle',   'class' => 'status-confirmed'],
    'rejected'              => ['label' => 'ปฏิเสธแล้ว', 'icon' => 'cancel',         'class' => 'status-rejected'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการการจอง (Owner)</title>
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
            <h1><span class="material-symbols-outlined">fact_check</span> ตรวจสอบการจอง<?= $hotel ? ' — ' . htmlspecialchars($hotel["hotel_name"]) : '' ?></h1>
            <p><?= $hotel ? 'รายการจองและหลักฐานการชำระเงินของโรงแรมคุณ' : 'คุณยังไม่มีโรงแรมในระบบ' ?></p>
            <?php if ($pendingCount > 0): ?>
                <span class="owner-pending-pill">รอตรวจสอบ <?= $pendingCount ?></span>
            <?php endif; ?>
        </div>
        <a href="index.php" class="board-back-btn"><span class="material-symbols-outlined">home</span> หน้าหลัก</a>
    </div>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if (!$hotel): ?>
        <div class="board-empty">
            <div class="board-empty-icon material-symbols-outlined">hotel</div>
            <h3>ยังไม่มีโรงแรมของคุณ</h3>
            <p>เพิ่มโรงแรมก่อนเพื่อเริ่มรับการจอง</p>
            <a href="manage_hotels.php" class="auth-btn" style="display:inline-block;width:auto;padding:12px 30px;text-decoration:none;">เพิ่มโรงแรม</a>
        </div>
    <?php elseif (empty($bookings)): ?>
        <div class="board-empty">
            <div class="board-empty-icon material-symbols-outlined">inbox</div>
            <h3>ยังไม่มีการจอง</h3>
            <p>เมื่อมีลูกค้าจองโรงแรมของคุณ รายการจะปรากฏที่นี่</p>
        </div>
    <?php else: ?>
        <div class="booking-cards">
            <?php foreach ($bookings as $row):
                $status = $statusMeta[$row["payment_status"]] ?? $statusMeta['pending_verification'];
            ?>
            <div class="booking-card">
                <div class="booking-card-top">
                    <div class="booking-hotel-name">
                        <span class="material-symbols-outlined">person</span> <?= htmlspecialchars($row["first_name"] . ' ' . $row["last_name"]) ?>
                    </div>
                    <div class="booking-nights-badge owner-status-badge <?= $status['class'] ?>">
                        <span class="material-symbols-outlined"><?= $status['icon'] ?></span> <?= $status['label'] ?>
                    </div>
                </div>

                <div class="booking-card-body">
                    <div class="booking-info-grid">
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">bed</span>
                            <div>
                                <span class="info-label">ห้อง</span>
                                <span class="info-value"><?= htmlspecialchars($row["room_name"] ?? 'ไม่ระบุ') ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">calendar_month</span>
                            <div>
                                <span class="info-label">เช็คอิน → เช็คเอาท์</span>
                                <span class="info-value"><?= htmlspecialchars($row["checkin"]) ?> → <?= htmlspecialchars($row["checkout"]) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">mail</span>
                            <div>
                                <span class="info-label">อีเมล</span>
                                <span class="info-value"><?= htmlspecialchars($row["email"]) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">call</span>
                            <div>
                                <span class="info-label">โทรศัพท์</span>
                                <span class="info-value"><?= htmlspecialchars($row["phone"]) ?></span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">group</span>
                            <div>
                                <span class="info-label">ผู้เข้าพัก</span>
                                <span class="info-value"><?= (int) $row["guests"] ?> คน</span>
                            </div>
                        </div>
                        <div class="booking-info-item">
                            <span class="info-icon material-symbols-outlined">payments</span>
                            <div>
                                <span class="info-label">ยอดชำระ</span>
                                <span class="info-value">฿<?= number_format((float) $row["total_price"], 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="owner-slip-row">
                        <?php if (!empty($row["payment_slip"])): ?>
                            <a href="uploads/slips/<?= urlencode($row["payment_slip"]) ?>" target="_blank" rel="noopener" class="slip-thumb-wrap">
                                <img class="slip-thumb" src="uploads/slips/<?= urlencode($row["payment_slip"]) ?>" alt="สลิปการโอนเงิน">
                            </a>
                        <?php else: ?>
                            <div class="slip-thumb-wrap slip-thumb-placeholder">ไม่มีสลิป</div>
                        <?php endif; ?>

                        <form method="post" class="owner-status-actions">
                            <input type="hidden" name="id" value="<?= (int) $row["id"] ?>">
                            <?php foreach ($statusMeta as $value => $meta): ?>
                                <button type="submit" name="payment_status" value="<?= $value ?>"
                                        class="status-btn <?= $meta['class'] ?><?= $row["payment_status"] === $value ? ' active' : '' ?>">
                                    <span class="material-symbols-outlined"><?= $meta['icon'] ?></span> <?= $meta['label'] ?>
                                </button>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
