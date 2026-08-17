<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if ($_SESSION["role"] !== "owner") {
    header("Location: index.php");
    exit;
}

$owner_id = $_SESSION["user_id"];

// Same hotel-picking rule as manage_hotels.php / dashboard_owner.php: one
// property at a time via ?hotel_id=, defaulting to the owner's first.
$stmt = $conn->prepare("SELECT id, hotel_name, province, price FROM hotels WHERE owner_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$ownerHotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$requestedHotelId = (int) ($_GET["hotel_id"] ?? 0);
$hotel = null;
foreach ($ownerHotels as $ownerHotel) {
    if ((int) $ownerHotel['id'] === $requestedHotelId) {
        $hotel = $ownerHotel;
        break;
    }
}
$hotel = $hotel ?: ($ownerHotels[0] ?? null);

// ---- Portfolio totals across every hotel this owner runs ----
$portfolio = ['hotels' => count($ownerHotels), 'bookings' => 0, 'revenue' => 0.0, 'pending' => 0];
if (!empty($ownerHotels)) {
    $stmt = $conn->prepare("
        SELECT COUNT(b.id) AS bookings,
               COALESCE(SUM(CASE WHEN b.payment_status = 'confirmed' THEN b.total_price ELSE 0 END), 0) AS revenue,
               COALESCE(SUM(b.payment_status = 'pending_verification'), 0) AS pending
        FROM bookings b
        JOIN hotels h ON h.id = b.hotel_id
        WHERE h.owner_id = ?
    ");
    $stmt->bind_param("i", $owner_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $portfolio['bookings'] = (int) $row['bookings'];
    $portfolio['revenue']  = (float) $row['revenue'];
    $portfolio['pending']  = (int) $row['pending'];
}

$stats       = ['total' => 0, 'confirmed' => 0, 'pending' => 0, 'rejected' => 0, 'revenue' => 0.0, 'guests' => 0];
$roomStats   = [];
$monthly     = [];
$staying     = 0;
$arrivals    = [];
$recent      = [];
$totalRooms  = 0;

if ($hotel) {
    $hotelId = (int) $hotel['id'];

    // ---- Headline numbers for the selected hotel ----
    $stmt = $conn->prepare("
        SELECT COUNT(*) AS total,
               COALESCE(SUM(payment_status = 'confirmed'), 0) AS confirmed,
               COALESCE(SUM(payment_status = 'pending_verification'), 0) AS pending,
               COALESCE(SUM(payment_status = 'rejected'), 0) AS rejected,
               COALESCE(SUM(CASE WHEN payment_status = 'confirmed' THEN total_price ELSE 0 END), 0) AS revenue,
               COALESCE(SUM(CASE WHEN payment_status <> 'rejected' THEN guests ELSE 0 END), 0) AS guests
        FROM bookings WHERE hotel_id = ?
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    $stats = array_map(fn($v) => is_numeric($v) ? $v + 0 : $v, $stmt->get_result()->fetch_assoc());
    $stmt->close();

    // ---- Per-room-type performance ----
    // A "night sold" is room-nights, not calendar nights, so a 3-night stay
    // counts as 3 — that's what makes revenue-per-room comparable.
    $stmt = $conn->prepare("
        SELECT rt.id, rt.room_name, rt.quantity, rt.price_per_night, rt.capacity,
               COUNT(b.id) AS bookings,
               COALESCE(SUM(CASE WHEN b.payment_status = 'confirmed' THEN b.total_price ELSE 0 END), 0) AS revenue,
               COALESCE(SUM(CASE WHEN b.payment_status <> 'rejected'
                                 THEN GREATEST(DATEDIFF(b.checkout, b.checkin), 0) ELSE 0 END), 0) AS nights,
               COALESCE(SUM(b.payment_status <> 'rejected'
                            AND b.owner_cleared = 0
                            AND DATE(b.checkin)  <= CURDATE()
                            AND DATE(b.checkout) >  CURDATE()), 0) AS occupied_now
        FROM room_types rt
        LEFT JOIN bookings b ON b.room_type_id = rt.id
        WHERE rt.hotel_id = ?
        GROUP BY rt.id, rt.room_name, rt.quantity, rt.price_per_night, rt.capacity
        ORDER BY revenue DESC, bookings DESC
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    $roomStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($roomStats as $room) {
        $totalRooms += (int) $room['quantity'];
        $staying    += (int) $room['occupied_now'];
    }

    // ---- Bookings per month, last 6 months ----
    $stmt = $conn->prepare("
        SELECT DATE_FORMAT(DATE(checkin), '%Y-%m') AS ym,
               COUNT(*) AS bookings,
               COALESCE(SUM(CASE WHEN payment_status = 'confirmed' THEN total_price ELSE 0 END), 0) AS revenue
        FROM bookings
        WHERE hotel_id = ?
          AND DATE(checkin) >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
        GROUP BY ym
        ORDER BY ym ASC
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $monthly[$row['ym']] = ['bookings' => (int) $row['bookings'], 'revenue' => (float) $row['revenue']];
    }
    $stmt->close();

    // ---- Check-ins in the next 7 days ----
    $stmt = $conn->prepare("
        SELECT b.first_name, b.last_name, b.checkin, b.checkout, b.guests,
               b.payment_status, rt.room_name
        FROM bookings b
        LEFT JOIN room_types rt ON rt.id = b.room_type_id
        WHERE b.hotel_id = ?
          AND b.payment_status <> 'rejected'
          AND DATE(b.checkin) >= CURDATE()
          AND DATE(b.checkin) <  DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ORDER BY DATE(b.checkin) ASC
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    $arrivals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // ---- Latest 5 bookings ----
    $stmt = $conn->prepare("
        SELECT b.id, b.first_name, b.last_name, b.checkin, b.checkout,
               b.total_price, b.payment_status, rt.room_name
        FROM bookings b
        LEFT JOIN room_types rt ON rt.id = b.room_type_id
        WHERE b.hotel_id = ?
        ORDER BY b.id DESC
        LIMIT 5
    ");
    $stmt->bind_param("i", $hotelId);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Fill in the months with no bookings so the chart shows a continuous
// 6-month run instead of silently skipping quiet months.
$monthChart = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime("-$i month"));
    $monthChart[$key] = $monthly[$key] ?? ['bookings' => 0, 'revenue' => 0.0];
}
$monthPeak = max(1, max(array_column($monthChart, 'bookings')));

$occupancyRate = $totalRooms > 0 ? round(($staying / $totalRooms) * 100) : 0;

$statusMeta = [
    'pending_verification' => ['label' => 'รอตรวจสอบ', 'class' => 'status-pending'],
    'confirmed'            => ['label' => 'ยืนยันแล้ว', 'class' => 'status-confirmed'],
    'rejected'             => ['label' => 'ปฏิเสธแล้ว', 'class' => 'status-rejected'],
];

$thaiMonths = ['01' => 'ม.ค.', '02' => 'ก.พ.', '03' => 'มี.ค.', '04' => 'เม.ย.', '05' => 'พ.ค.', '06' => 'มิ.ย.',
               '07' => 'ก.ค.', '08' => 'ส.ค.', '09' => 'ก.ย.', '10' => 'ต.ค.', '11' => 'พ.ย.', '12' => 'ธ.ค.'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดเจ้าของโรงแรม</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="admin-dashboard">
    <div class="admin-dashboard-header">
        <h1><span class="material-symbols-outlined">monitoring</span> แดชบอร์ดเจ้าของโรงแรม</h1>
        <p>ภาพรวมยอดจอง รายได้ และผลงานของห้องพักแต่ละประเภท</p>
    </div>

    <?php if (!$hotel): ?>
        <div class="board-empty">
            <div class="board-empty-icon material-symbols-outlined">hotel</div>
            <h3>ยังไม่มีโรงแรมของคุณ</h3>
            <p>เพิ่มโรงแรมก่อนเพื่อเริ่มรับการจองและดูสถิติ</p>
            <a href="manage_hotels.php" class="auth-btn" style="display:inline-block;width:auto;padding:12px 30px;text-decoration:none;">เพิ่มโรงแรม</a>
        </div>
    <?php else: ?>

        <?php if (count($ownerHotels) > 1): ?>
            <div class="owner-portfolio-bar">
                <div class="owner-portfolio-item">
                    <span class="owner-portfolio-value"><?= number_format($portfolio['hotels']) ?></span>
                    <span class="owner-portfolio-label">โรงแรมที่ดูแล</span>
                </div>
                <div class="owner-portfolio-item">
                    <span class="owner-portfolio-value"><?= number_format($portfolio['bookings']) ?></span>
                    <span class="owner-portfolio-label">ยอดจองรวมทุกโรงแรม</span>
                </div>
                <div class="owner-portfolio-item">
                    <span class="owner-portfolio-value">฿<?= number_format($portfolio['revenue'], 2) ?></span>
                    <span class="owner-portfolio-label">รายได้รวม (ยืนยันแล้ว)</span>
                </div>
                <div class="owner-portfolio-item<?= $portfolio['pending'] > 0 ? ' owner-portfolio-alert' : '' ?>">
                    <span class="owner-portfolio-value"><?= number_format($portfolio['pending']) ?></span>
                    <span class="owner-portfolio-label">รอตรวจสอบทั้งหมด</span>
                </div>
            </div>

            <div class="hotel-switcher">
                <span class="hotel-switcher-label">
                    <span class="material-symbols-outlined">apartment</span> เลือกโรงแรมที่ต้องการดูสถิติ
                </span>
                <div class="hotel-switcher-tabs">
                    <?php foreach ($ownerHotels as $ownerHotel): ?>
                        <a href="owner_dashboard.php?hotel_id=<?= (int) $ownerHotel['id'] ?>"
                           class="hotel-switcher-tab<?= (int) $ownerHotel['id'] === (int) $hotel['id'] ? ' active' : '' ?>">
                            <?= htmlspecialchars($ownerHotel['hotel_name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <h2 class="admin-section-title">
            <?= htmlspecialchars($hotel['hotel_name']) ?>
            <?= !empty($hotel['province']) ? '<span class="owner-section-sub">จ.' . htmlspecialchars($hotel['province']) . '</span>' : '' ?>
        </h2>

        <div class="admin-stats-grid">
            <div class="admin-stat-card">
                <span class="material-symbols-outlined admin-stat-icon">receipt_long</span>
                <div>
                    <div class="admin-stat-value"><?= number_format($stats['total']) ?></div>
                    <div class="admin-stat-label">ยอดจองทั้งหมด</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <span class="material-symbols-outlined admin-stat-icon">payments</span>
                <div>
                    <div class="admin-stat-value">฿<?= number_format($stats['revenue'], 2) ?></div>
                    <div class="admin-stat-label">รายได้ (ยืนยันแล้ว)</div>
                </div>
            </div>
            <div class="admin-stat-card">
                <span class="material-symbols-outlined admin-stat-icon">bed</span>
                <div>
                    <div class="admin-stat-value"><?= $staying ?> / <?= $totalRooms ?></div>
                    <div class="admin-stat-label">ห้องที่มีผู้พักวันนี้ (<?= $occupancyRate ?>%)</div>
                </div>
            </div>
            <div class="admin-stat-card<?= $stats['pending'] > 0 ? ' admin-stat-alert' : '' ?>">
                <span class="material-symbols-outlined admin-stat-icon">hourglass_top</span>
                <div>
                    <div class="admin-stat-value"><?= number_format($stats['pending']) ?></div>
                    <div class="admin-stat-label">รอตรวจสอบการชำระเงิน</div>
                </div>
            </div>
        </div>

        <div class="owner-status-breakdown">
            <span class="owner-chip status-confirmed">
                <span class="material-symbols-outlined">check_circle</span>
                ยืนยันแล้ว <?= number_format($stats['confirmed']) ?>
            </span>
            <span class="owner-chip status-pending">
                <span class="material-symbols-outlined">hourglass_top</span>
                รอตรวจสอบ <?= number_format($stats['pending']) ?>
            </span>
            <span class="owner-chip status-rejected">
                <span class="material-symbols-outlined">cancel</span>
                ปฏิเสธแล้ว <?= number_format($stats['rejected']) ?>
            </span>
            <span class="owner-chip">
                <span class="material-symbols-outlined">group</span>
                ผู้เข้าพักรวม <?= number_format($stats['guests']) ?> คน
            </span>
        </div>

        <h2 class="admin-section-title">ยอดจองย้อนหลัง 6 เดือน</h2>
        <div class="owner-chart-card">
            <div class="owner-chart">
                <?php foreach ($monthChart as $ym => $data):
                    [$year, $month] = explode('-', $ym);
                    $heightPct = round(($data['bookings'] / $monthPeak) * 100);
                ?>
                    <div class="owner-chart-col" title="<?= $data['bookings'] ?> การจอง · ฿<?= number_format($data['revenue'], 2) ?>">
                        <span class="owner-chart-count"><?= $data['bookings'] ?></span>
                        <div class="owner-chart-bar" style="height: <?= max($heightPct, 2) ?>%"></div>
                        <span class="owner-chart-month"><?= $thaiMonths[$month] ?> <?= substr($year, 2) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="owner-chart-hint">นับตามวันที่เช็คอินของการจอง · ชี้ที่แท่งเพื่อดูรายได้ของเดือนนั้น</p>
        </div>

        <h2 class="admin-section-title">ผลงานของห้องพักแต่ละประเภท</h2>
        <?php if (empty($roomStats)): ?>
            <div class="board-empty">
                <div class="board-empty-icon material-symbols-outlined">bed</div>
                <h3>ยังไม่มีห้องพัก</h3>
                <p>เพิ่มประเภทห้องพักก่อน ลูกค้าจึงจะจองได้</p>
                <a href="manage_hotels.php?hotel_id=<?= (int) $hotel['id'] ?>" class="auth-btn" style="display:inline-block;width:auto;padding:12px 30px;text-decoration:none;">จัดการห้องพัก</a>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="booking-list">
                    <thead>
                        <tr>
                            <th>ประเภทห้อง</th>
                            <th>จำนวนห้อง</th>
                            <th>ราคา/คืน</th>
                            <th>ยอดจอง</th>
                            <th>คืนที่ขายได้</th>
                            <th>รายได้</th>
                            <th>พักอยู่วันนี้</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($roomStats as $room):
                            $roomOccupancy = (int) $room['quantity'] > 0
                                ? round(((int) $room['occupied_now'] / (int) $room['quantity']) * 100)
                                : 0;
                        ?>
                        <tr>
                            <td data-label="ประเภทห้อง">
                                <strong><?= htmlspecialchars($room['room_name']) ?></strong>
                                <span class="owner-room-cap">สูงสุด <?= (int) $room['capacity'] ?> ท่าน</span>
                            </td>
                            <td data-label="จำนวนห้อง"><?= (int) $room['quantity'] ?></td>
                            <td data-label="ราคา/คืน">฿<?= number_format((float) $room['price_per_night'], 2) ?></td>
                            <td data-label="ยอดจอง"><?= number_format((int) $room['bookings']) ?></td>
                            <td data-label="คืนที่ขายได้"><?= number_format((int) $room['nights']) ?></td>
                            <td data-label="รายได้">฿<?= number_format((float) $room['revenue'], 2) ?></td>
                            <td data-label="พักอยู่วันนี้">
                                <div class="owner-occupancy">
                                    <div class="owner-occupancy-bar">
                                        <div class="owner-occupancy-fill" style="width: <?= $roomOccupancy ?>%"></div>
                                    </div>
                                    <span><?= (int) $room['occupied_now'] ?>/<?= (int) $room['quantity'] ?></span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="admin-section-title">ผู้เข้าพักที่กำลังจะมาถึง (7 วันข้างหน้า)</h2>
        <?php if (empty($arrivals)): ?>
            <p class="category-empty-hint">ยังไม่มีผู้เข้าพักที่จะเช็คอินภายใน 7 วันนี้</p>
        <?php else: ?>
            <div class="owner-arrival-list">
                <?php foreach ($arrivals as $arrival):
                    $meta = $statusMeta[$arrival['payment_status']] ?? $statusMeta['pending_verification'];
                ?>
                    <div class="owner-arrival-card">
                        <div class="owner-arrival-date">
                            <span class="owner-arrival-day"><?= date('d', strtotime($arrival['checkin'])) ?></span>
                            <span class="owner-arrival-month"><?= $thaiMonths[date('m', strtotime($arrival['checkin']))] ?></span>
                        </div>
                        <div class="owner-arrival-body">
                            <strong><?= htmlspecialchars($arrival['first_name'] . ' ' . $arrival['last_name']) ?></strong>
                            <span><?= htmlspecialchars($arrival['room_name'] ?? 'ไม่ระบุห้อง') ?> · <?= (int) $arrival['guests'] ?> ท่าน</span>
                            <span><?= htmlspecialchars($arrival['checkin']) ?> → <?= htmlspecialchars($arrival['checkout']) ?></span>
                        </div>
                        <span class="owner-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="admin-section-title">การจองล่าสุด</h2>
        <?php if (empty($recent)): ?>
            <p class="category-empty-hint">ยังไม่มีการจองเข้ามา</p>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="booking-list">
                    <thead>
                        <tr>
                            <th>ผู้จอง</th>
                            <th>ห้อง</th>
                            <th>เช็คอิน → เช็คเอาท์</th>
                            <th>ยอด</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent as $row):
                            $meta = $statusMeta[$row['payment_status']] ?? $statusMeta['pending_verification'];
                        ?>
                        <tr>
                            <td data-label="ผู้จอง"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                            <td data-label="ห้อง"><?= htmlspecialchars($row['room_name'] ?? 'ไม่ระบุ') ?></td>
                            <td data-label="เช็คอิน → เช็คเอาท์"><?= htmlspecialchars($row['checkin']) ?> → <?= htmlspecialchars($row['checkout']) ?></td>
                            <td data-label="ยอด">฿<?= number_format((float) $row['total_price'], 2) ?></td>
                            <td data-label="สถานะ"><span class="owner-chip <?= $meta['class'] ?>"><?= $meta['label'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <h2 class="admin-section-title">ทางลัด</h2>
        <div class="admin-menu-grid">
            <a href="dashboard_owner.php?hotel_id=<?= (int) $hotel['id'] ?>" class="admin-menu-card">
                <span class="material-symbols-outlined admin-menu-icon">fact_check</span>
                <div class="admin-menu-title">ตรวจสอบการจอง</div>
                <p class="admin-menu-desc">ดูสลิปการโอนเงินและยืนยัน/ปฏิเสธการชำระเงิน</p>
            </a>
            <a href="manage_hotels.php?hotel_id=<?= (int) $hotel['id'] ?>" class="admin-menu-card">
                <span class="material-symbols-outlined admin-menu-icon">edit_square</span>
                <div class="admin-menu-title">แก้ไขข้อมูลโรงแรม</div>
                <p class="admin-menu-desc">แก้ไขรายละเอียด รูปภาพ และประเภทห้องพัก</p>
            </a>
            <a href="hotel_detail.php?id=<?= (int) $hotel['id'] ?>" class="admin-menu-card">
                <span class="material-symbols-outlined admin-menu-icon">visibility</span>
                <div class="admin-menu-title">ดูหน้าโรงแรมของคุณ</div>
                <p class="admin-menu-desc">ดูหน้าโรงแรมแบบที่ผู้เข้าพักเห็น</p>
            </a>
        </div>

    <?php endif; ?>

    <div class="booking-back">
        <a href="index.php"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าหลัก</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
