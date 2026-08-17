<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$keyword  = trim($_GET['q'] ?? '');
$selected = (int) ($_GET['hotel_id'] ?? 0);

// ---- System-wide totals ----
$totals = $conn->query("
    SELECT (SELECT COUNT(*) FROM hotels)                                        AS hotels,
           (SELECT COUNT(*) FROM users)                                         AS users,
           (SELECT COUNT(*) FROM users WHERE role = 'owner')                    AS owners,
           (SELECT COUNT(*) FROM room_types)                                    AS room_types,
           (SELECT COALESCE(SUM(quantity), 0) FROM room_types)                  AS rooms,
           (SELECT COUNT(*) FROM bookings)                                      AS bookings,
           (SELECT COUNT(*) FROM bookings WHERE payment_status = 'pending_verification') AS pending,
           (SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE payment_status = 'confirmed') AS revenue
")->fetch_assoc();

// ---- Hotel list, with the numbers an admin actually scans for ----
$listSql = "
    SELECT h.id, h.hotel_name, h.location, h.province, h.price,
           u.full_name AS owner_name, u.email AS owner_email,
           (SELECT image_path FROM hotel_images WHERE hotel_id = h.id ORDER BY id ASC LIMIT 1) AS image_path,
           (SELECT COUNT(*) FROM room_types WHERE hotel_id = h.id) AS room_types,
           (SELECT COALESCE(SUM(quantity), 0) FROM room_types WHERE hotel_id = h.id) AS rooms,
           (SELECT COUNT(*) FROM bookings WHERE hotel_id = h.id) AS bookings,
           (SELECT COUNT(*) FROM bookings WHERE hotel_id = h.id AND payment_status = 'pending_verification') AS pending,
           (SELECT COALESCE(SUM(total_price), 0) FROM bookings WHERE hotel_id = h.id AND payment_status = 'confirmed') AS revenue
    FROM hotels h
    LEFT JOIN users u ON u.id = h.owner_id
";
if ($keyword !== '') {
    $listSql .= " WHERE h.hotel_name LIKE ? OR h.location LIKE ? OR h.province LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?";
    $stmt = $conn->prepare($listSql . " ORDER BY h.id DESC");
    $like = "%$keyword%";
    $stmt->bind_param("sssss", $like, $like, $like, $like, $like);
    $stmt->execute();
    $hotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $hotels = $conn->query($listSql . " ORDER BY h.id DESC")->fetch_all(MYSQLI_ASSOC);
}

// ---- Drill-down: one hotel picked from the list ----
$detail = null;
$detailRooms = [];
$detailBookings = [];
$detailAmenities = [];
if ($selected > 0) {
    foreach ($hotels as $row) {
        if ((int) $row['id'] === $selected) {
            $detail = $row;
            break;
        }
    }

    if ($detail) {
        $stmt = $conn->prepare("
            SELECT rt.id, rt.room_name, rt.capacity, rt.price_per_night, rt.quantity,
                   COUNT(b.id) AS bookings,
                   COALESCE(SUM(CASE WHEN b.payment_status = 'confirmed' THEN b.total_price ELSE 0 END), 0) AS revenue
            FROM room_types rt
            LEFT JOIN bookings b ON b.room_type_id = rt.id
            WHERE rt.hotel_id = ?
            GROUP BY rt.id, rt.room_name, rt.capacity, rt.price_per_night, rt.quantity
            ORDER BY rt.id ASC
        ");
        $stmt->bind_param("i", $selected);
        $stmt->execute();
        $detailRooms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $stmt = $conn->prepare("
            SELECT b.id, b.first_name, b.last_name, b.email, b.checkin, b.checkout,
                   b.total_price, b.payment_status, rt.room_name
            FROM bookings b
            LEFT JOIN room_types rt ON rt.id = b.room_type_id
            WHERE b.hotel_id = ?
            ORDER BY b.id DESC
            LIMIT 8
        ");
        $stmt->bind_param("i", $selected);
        $stmt->execute();
        $detailBookings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $detailAmenities = get_amenities_for_hotels($conn, [$selected])[$selected] ?? [];
    }
}

$statusMeta = [
    'pending_verification' => ['label' => 'รอตรวจสอบ', 'class' => 'status-pending'],
    'confirmed'            => ['label' => 'ยืนยันแล้ว', 'class' => 'status-confirmed'],
    'rejected'             => ['label' => 'ปฏิเสธแล้ว', 'class' => 'status-rejected'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ดผู้ดูแลระบบ</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="admin-dashboard">
    <div class="admin-dashboard-header">
        <h1><span class="material-symbols-outlined">dashboard</span> แดชบอร์ดผู้ดูแลระบบ</h1>
        <p>ภาพรวมทั้งระบบ และรายชื่อโรงแรมทั้งหมด กดที่โรงแรมเพื่อดูรายละเอียด</p>
    </div>

    <div class="admin-stats-grid">
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">hotel</span>
            <div>
                <div class="admin-stat-value"><?= number_format($totals['hotels']) ?></div>
                <div class="admin-stat-label">โรงแรมทั้งหมด</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">bed</span>
            <div>
                <div class="admin-stat-value"><?= number_format($totals['rooms']) ?></div>
                <div class="admin-stat-label">ห้องพักรวม (<?= number_format($totals['room_types']) ?> ประเภท)</div>
            </div>
        </div>
        <div class="admin-stat-card">
            <span class="material-symbols-outlined admin-stat-icon">payments</span>
            <div>
                <div class="admin-stat-value">฿<?= number_format($totals['revenue'], 2) ?></div>
                <div class="admin-stat-label">รายได้รวม (ยืนยันแล้ว)</div>
            </div>
        </div>
        <div class="admin-stat-card<?= $totals['pending'] > 0 ? ' admin-stat-alert' : '' ?>">
            <span class="material-symbols-outlined admin-stat-icon">hourglass_top</span>
            <div>
                <div class="admin-stat-value"><?= number_format($totals['pending']) ?></div>
                <div class="admin-stat-label">รอตรวจสอบการชำระเงิน</div>
            </div>
        </div>
    </div>

    <div class="owner-portfolio-bar">
        <div class="owner-portfolio-item">
            <span class="owner-portfolio-value"><?= number_format($totals['users']) ?></span>
            <span class="owner-portfolio-label">ผู้ใช้ทั้งหมด</span>
        </div>
        <div class="owner-portfolio-item">
            <span class="owner-portfolio-value"><?= number_format($totals['owners']) ?></span>
            <span class="owner-portfolio-label">เจ้าของโรงแรม</span>
        </div>
        <div class="owner-portfolio-item">
            <span class="owner-portfolio-value"><?= number_format($totals['bookings']) ?></span>
            <span class="owner-portfolio-label">การจองทั้งหมด</span>
        </div>
        <div class="owner-portfolio-item">
            <span class="owner-portfolio-value"><?= number_format(count($hotels)) ?></span>
            <span class="owner-portfolio-label">โรงแรมที่แสดงอยู่</span>
        </div>
    </div>

    <?php if ($detail): ?>
        <!-- ---- Drill-down panel for the hotel picked from the list ---- -->
        <h2 class="admin-section-title">
            รายละเอียดโรงแรม
            <a href="admin_dashboard.php<?= $keyword !== '' ? '?q=' . urlencode($keyword) : '' ?>" class="admin-detail-close">
                <span class="material-symbols-outlined">close</span> ปิด
            </a>
        </h2>
        <div class="admin-detail-card">
            <div class="admin-detail-head">
                <img class="admin-detail-cover"
                     src="<?= !empty($detail['image_path']) ? htmlspecialchars($detail['image_path']) : 'uploads/hotels/noimage.jpg' ?>"
                     alt="<?= htmlspecialchars($detail['hotel_name']) ?>">
                <div class="admin-detail-info">
                    <h3><?= htmlspecialchars($detail['hotel_name']) ?></h3>
                    <p>
                        <span class="material-symbols-outlined">location_on</span>
                        <?= htmlspecialchars($detail['location']) ?><?= !empty($detail['province']) ? ' · จ.' . htmlspecialchars($detail['province']) : '' ?>
                    </p>
                    <p>
                        <span class="material-symbols-outlined">person</span>
                        เจ้าของ: <?= htmlspecialchars($detail['owner_name'] ?? 'ไม่พบเจ้าของ') ?>
                        <?= !empty($detail['owner_email']) ? '(' . htmlspecialchars($detail['owner_email']) . ')' : '' ?>
                    </p>
                    <div class="owner-status-breakdown" style="margin-top:10px;">
                        <span class="owner-chip"><span class="material-symbols-outlined">sell</span> เริ่มต้น ฿<?= htmlspecialchars($detail['price']) ?></span>
                        <span class="owner-chip"><span class="material-symbols-outlined">bed</span> <?= (int) $detail['rooms'] ?> ห้อง</span>
                        <span class="owner-chip"><span class="material-symbols-outlined">receipt_long</span> <?= (int) $detail['bookings'] ?> การจอง</span>
                        <span class="owner-chip"><span class="material-symbols-outlined">payments</span> ฿<?= number_format((float) $detail['revenue'], 2) ?></span>
                    </div>
                    <?php if (!empty($detailAmenities)): ?>
                        <div class="hotel-detail-tags" style="margin-top:12px;">
                            <?php foreach ($detailAmenities as $amenity): ?>
                                <span class="hotel-card-tag">
                                    <span class="material-symbols-outlined"><?= htmlspecialchars($amenity['icon']) ?></span>
                                    <?= htmlspecialchars($amenity['title']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="admin-detail-actions">
                        <a href="hotel_detail.php?id=<?= (int) $detail['id'] ?>" class="btn-table-view">
                            <span class="material-symbols-outlined">visibility</span> ดูหน้าโรงแรม
                        </a>
                        <a href="admin_hotel_edit.php" class="btn-table-save">
                            <span class="material-symbols-outlined">edit</span> แก้ไขข้อมูล
                        </a>
                        <a href="admin_edit_booking.php" class="btn-table-view">
                            <span class="material-symbols-outlined">receipt_long</span> จัดการการจอง
                        </a>
                    </div>
                </div>
            </div>

            <h4 class="admin-detail-subtitle">ประเภทห้องพัก (<?= count($detailRooms) ?>)</h4>
            <?php if (empty($detailRooms)): ?>
                <p class="category-empty-hint">โรงแรมนี้ยังไม่ได้เพิ่มประเภทห้องพัก</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="booking-list">
                        <thead>
                            <tr>
                                <th>ชื่อห้อง</th>
                                <th>ผู้เข้าพัก</th>
                                <th>ราคา/คืน</th>
                                <th>จำนวนห้อง</th>
                                <th>ยอดจอง</th>
                                <th>รายได้</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailRooms as $room): ?>
                            <tr>
                                <td data-label="ชื่อห้อง"><?= htmlspecialchars($room['room_name']) ?></td>
                                <td data-label="ผู้เข้าพัก"><?= (int) $room['capacity'] ?> ท่าน</td>
                                <td data-label="ราคา/คืน">฿<?= number_format((float) $room['price_per_night'], 2) ?></td>
                                <td data-label="จำนวนห้อง"><?= (int) $room['quantity'] ?></td>
                                <td data-label="ยอดจอง"><?= (int) $room['bookings'] ?></td>
                                <td data-label="รายได้">฿<?= number_format((float) $room['revenue'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <h4 class="admin-detail-subtitle">การจองล่าสุด</h4>
            <?php if (empty($detailBookings)): ?>
                <p class="category-empty-hint">ยังไม่มีการจองของโรงแรมนี้</p>
            <?php else: ?>
                <div style="overflow-x:auto;">
                    <table class="booking-list">
                        <thead>
                            <tr>
                                <th>ผู้จอง</th>
                                <th>อีเมล</th>
                                <th>ห้อง</th>
                                <th>เช็คอิน → เช็คเอาท์</th>
                                <th>ยอด</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detailBookings as $row):
                                $meta = $statusMeta[$row['payment_status']] ?? $statusMeta['pending_verification'];
                            ?>
                            <tr>
                                <td data-label="ผู้จอง"><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
                                <td data-label="อีเมล"><?= htmlspecialchars($row['email']) ?></td>
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
        </div>
    <?php endif; ?>

    <h2 class="admin-section-title">รายชื่อโรงแรมทั้งหมด</h2>

    <form method="get" action="admin_dashboard.php" class="admin-search-row">
        <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>"
               class="filter-text-input" placeholder="ค้นหาชื่อโรงแรม ที่ตั้ง จังหวัด หรือเจ้าของ">
        <button type="submit" class="btn-table-save"><span class="material-symbols-outlined">search</span> ค้นหา</button>
        <?php if ($keyword !== ''): ?>
            <a href="admin_dashboard.php" class="filter-clear-link">ล้างการค้นหา</a>
        <?php endif; ?>
    </form>

    <?php if (empty($hotels)): ?>
        <div class="board-empty">
            <div class="board-empty-icon material-symbols-outlined">search_off</div>
            <h3><?= $keyword !== '' ? 'ไม่พบโรงแรมที่ตรงกับการค้นหา' : 'ยังไม่มีโรงแรมในระบบ' ?></h3>
            <p><?= $keyword !== '' ? 'ลองใช้คำค้นอื่น หรือล้างการค้นหา' : 'เมื่อเจ้าของโรงแรมเพิ่มโรงแรม รายการจะปรากฏที่นี่' ?></p>
        </div>
    <?php else: ?>
        <div class="admin-hotel-list">
            <?php foreach ($hotels as $row): ?>
                <a href="admin_dashboard.php?hotel_id=<?= (int) $row['id'] ?><?= $keyword !== '' ? '&q=' . urlencode($keyword) : '' ?>"
                   class="admin-hotel-row<?= (int) $row['id'] === $selected ? ' active' : '' ?>">
                    <img class="admin-hotel-thumb"
                         src="<?= !empty($row['image_path']) ? htmlspecialchars($row['image_path']) : 'uploads/hotels/noimage.jpg' ?>"
                         alt="">
                    <div class="admin-hotel-main">
                        <div class="admin-hotel-name">
                            <?= htmlspecialchars($row['hotel_name']) ?>
                            <?php if ((int) $row['pending'] > 0): ?>
                                <span class="admin-hotel-badge"><?= (int) $row['pending'] ?> รอตรวจสอบ</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-hotel-meta">
                            <span><span class="material-symbols-outlined">location_on</span><?= htmlspecialchars($row['province'] ?: $row['location']) ?></span>
                            <span><span class="material-symbols-outlined">person</span><?= htmlspecialchars($row['owner_name'] ?? 'ไม่พบเจ้าของ') ?></span>
                        </div>
                    </div>
                    <div class="admin-hotel-figures">
                        <div><strong><?= (int) $row['rooms'] ?></strong><span>ห้อง</span></div>
                        <div><strong><?= (int) $row['bookings'] ?></strong><span>การจอง</span></div>
                        <div><strong>฿<?= number_format((float) $row['revenue'], 0) ?></strong><span>รายได้</span></div>
                    </div>
                    <span class="material-symbols-outlined admin-hotel-arrow">chevron_right</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="booking-back">
        <a href="admin_manage.php"><span class="material-symbols-outlined">arrow_back</span> กลับเมนู Admin</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
