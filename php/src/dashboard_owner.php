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

// One property at a time, same as manage_hotels.php — ?hotel_id= picks it,
// falling back to the owner's first hotel.
$stmt = $conn->prepare("SELECT id, hotel_name FROM hotels WHERE owner_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$ownerHotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$requestedHotelId = (int) ($_POST["hotel_id"] ?? $_GET["hotel_id"] ?? 0);
$hotel = null;
foreach ($ownerHotels as $ownerHotel) {
    if ((int) $ownerHotel['id'] === $requestedHotelId) {
        $hotel = $ownerHotel;
        break;
    }
}
$hotel = $hotel ?: ($ownerHotels[0] ?? null);
$hotelQuery = $hotel ? "?hotel_id=" . (int) $hotel['id'] : "";

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

// "Clear" just hides a finished booking from the owner's active list
// (owner_cleared = 1) — it doesn't delete the row, so booking history,
// admin views, and availability checks elsewhere are unaffected.
if ($hotel && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["id"], $_POST["clear_action"])
    && in_array($_POST["clear_action"], ['clear', 'restore'], true)) {
    $id      = (int) $_POST["id"];
    $cleared = $_POST["clear_action"] === 'clear' ? 1 : 0;

    $stmt = $conn->prepare("UPDATE bookings SET owner_cleared = ? WHERE id = ? AND hotel_id = ?");
    $stmt->bind_param("iii", $cleared, $id, $hotel["id"]);
    $ok  = $stmt->execute();
    $msg = $ok ? ($cleared ? "เคลียร์รายการเรียบร้อยแล้ว" : "นำรายการกลับมาแสดงแล้ว") : "เกิดข้อผิดพลาด: " . $stmt->error;
    $stmt->close();
}

$viewCleared = isset($_GET["view"]) && $_GET["view"] === 'cleared';

$bookings = [];
$pendingCount = 0;
if ($hotel) {
    $stmt = $conn->prepare("
        SELECT b.id, b.first_name, b.last_name, b.email, b.phone,
               b.checkin, b.checkout, b.guests, b.total_price,
               b.payment_slip, b.payment_status, rt.room_name
        FROM bookings b
        LEFT JOIN room_types rt ON rt.id = b.room_type_id
        WHERE b.hotel_id = ? AND b.owner_cleared = ?
        ORDER BY b.id DESC
    ");
    $clearedFlag = $viewCleared ? 1 : 0;
    $stmt->bind_param("ii", $hotel["id"], $clearedFlag);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
        if (!$viewCleared && $row["payment_status"] === "pending_verification") {
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
        <div class="board-header-actions">
            <?php if ($hotel): ?>
                <a href="owner_dashboard.php<?= $hotelQuery ?>" class="board-back-btn"><span class="material-symbols-outlined">monitoring</span> ภาพรวมและสถิติ</a>
                <?php if ($viewCleared): ?>
                    <a href="dashboard_owner.php<?= $hotelQuery ?>" class="board-back-btn"><span class="material-symbols-outlined">arrow_back</span> กลับไปรายการที่ใช้งานอยู่</a>
                <?php else: ?>
                    <a href="dashboard_owner.php<?= $hotelQuery ?>&view=cleared" class="board-back-btn"><span class="material-symbols-outlined">archive</span> รายการที่เคลียร์แล้ว</a>
                <?php endif; ?>
            <?php endif; ?>
            <a href="index.php" class="board-back-btn board-btn-home"><span class="material-symbols-outlined">home</span> หน้าหลัก</a>
        </div>
    </div>

    <?php if (count($ownerHotels) > 1): ?>
        <div class="hotel-switcher">
            <span class="hotel-switcher-label">
                <span class="material-symbols-outlined">apartment</span> เลือกโรงแรมที่ต้องการดู
            </span>
            <div class="hotel-switcher-tabs">
                <?php foreach ($ownerHotels as $ownerHotel): ?>
                    <a href="dashboard_owner.php?hotel_id=<?= (int) $ownerHotel['id'] ?><?= $viewCleared ? '&view=cleared' : '' ?>"
                       class="hotel-switcher-tab<?= (int) $ownerHotel['id'] === (int) $hotel['id'] ? ' active' : '' ?>">
                        <?= htmlspecialchars($ownerHotel['hotel_name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

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
            <div class="board-empty-icon material-symbols-outlined"><?= $viewCleared ? 'archive' : 'inbox' ?></div>
            <h3><?= $viewCleared ? 'ยังไม่มีรายการที่เคลียร์' : 'ยังไม่มีการจอง' ?></h3>
            <p><?= $viewCleared ? 'รายการที่คุณกดเคลียร์แล้วจะมาอยู่ที่นี่' : 'เมื่อมีลูกค้าจองโรงแรมของคุณ รายการจะปรากฏที่นี่' ?></p>
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
                            <input type="hidden" name="hotel_id" value="<?= (int) $hotel["id"] ?>">
                            <input type="hidden" name="id" value="<?= (int) $row["id"] ?>">
                            <?php foreach ($statusMeta as $value => $meta): ?>
                                <button type="submit" name="payment_status" value="<?= $value ?>"
                                        class="status-btn <?= $meta['class'] ?><?= $row["payment_status"] === $value ? ' active' : '' ?>">
                                    <span class="material-symbols-outlined"><?= $meta['icon'] ?></span> <?= $meta['label'] ?>
                                </button>
                            <?php endforeach; ?>
                        </form>

                        <form method="post" class="owner-status-actions">
                            <input type="hidden" name="hotel_id" value="<?= (int) $hotel["id"] ?>">
                            <input type="hidden" name="id" value="<?= (int) $row["id"] ?>">
                            <?php if ($viewCleared): ?>
                                <input type="hidden" name="clear_action" value="restore">
                                <button type="submit" class="status-btn">
                                    <span class="material-symbols-outlined">undo</span> นำกลับมาแสดง
                                </button>
                            <?php else: ?>
                                <input type="hidden" name="clear_action" value="clear">
                                <button type="submit" class="status-btn"
                                        onclick="return confirm('เคลียร์รายการนี้ออกจากรายการที่ใช้งานอยู่ใช่หรือไม่? ยังดูย้อนหลังได้ที่ &quot;รายการที่เคลียร์แล้ว&quot;');">
                                    <span class="material-symbols-outlined">done_all</span> เคลียร์
                                </button>
                            <?php endif; ?>
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
