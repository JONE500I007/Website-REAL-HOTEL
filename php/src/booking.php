<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=login_required");
    exit;
}

$hotel_id = (int) ($_GET["hotel_id"] ?? 0);

$stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// A room type may be requested via GET or (to survive a re-render after a
// validation error) via the hidden POST field.
$room_type_id = (int) ($_GET["room_type_id"] ?? $_POST["room_type_id"] ?? 0);
$room = null;
if ($room_type_id > 0 && $hotel) {
    $stmt = $conn->prepare("SELECT * FROM room_types WHERE id = ? AND hotel_id = ?");
    $stmt->bind_param("ii", $room_type_id, $hotel_id);
    $stmt->execute();
    $room = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
if (!$room) {
    $room_type_id = null; // invalid/mismatched room -> fall back to legacy hotel-only booking
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $hotel) {
    $first_name = $_POST["first_name"];
    $last_name  = $_POST["last_name"];
    $email      = $_POST["email"];
    $phone      = $_POST["phone"];
    $checkin    = $_POST["checkin"];
    $checkout   = $_POST["checkout"];
    $guests     = (int) $_POST["guests"];

    $canBook = true;
    if ($checkout <= $checkin) {
        $bookingError = "วันที่เช็คเอาท์ต้องอยู่หลังวันที่เช็คอิน";
        $canBook = false;
    } elseif ($room_type_id) {
        $available = get_room_availability($conn, $room_type_id, $checkin, $checkout);
        if ($available <= 0) {
            $bookingError = "ขออภัย ไม่มีห้องว่างในช่วงวันที่ท่านเลือกแล้ว กรุณาเลือกวันที่อื่น";
            $canBook = false;
        }
    }

    if ($canBook) {
        $stmt = $conn->prepare("
            INSERT INTO bookings (first_name, last_name, email, phone, checkin, checkout, guests, hotel_id, room_type_id, book_hotel_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssiiis", $first_name, $last_name, $email, $phone, $checkin, $checkout, $guests, $hotel_id, $room_type_id, $hotel["hotel_name"]);

        if ($stmt->execute()) {
            echo "<script>alert('คุณได้ทำการจองโรงแรมเรียบร้อย'); window.location.href = 'board.php';</script>";
            exit;
        } else {
            $bookingError = "เกิดข้อผิดพลาด: " . $stmt->error;
        }
        $stmt->close();
    }
}

$name_parts = explode(" ", $user["full_name"] ?? "", 2);
$first_name = $name_parts[0] ?? "";
$last_name  = $name_parts[1] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จองโรงแรม</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="form-container">
    <div class="form-card">
        <?php if ($hotel): ?>
            <h2>จองโรงแรม: <?= htmlspecialchars($hotel["hotel_name"]) ?></h2>

            <?php if ($room): ?>
                <p style="color:#5d3f8c; font-weight:600;">
                    ห้อง: <?= htmlspecialchars($room["room_name"]) ?>
                    · สูงสุด <?= (int) $room["capacity"] ?> ท่าน
                    · ฿<?= htmlspecialchars($room["price_per_night"]) ?>/คืน
                </p>
            <?php endif; ?>

            <?php if (!empty($bookingError)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($bookingError) ?></div>
            <?php endif; ?>

            <form method="post">
                <?php if ($room_type_id): ?>
                    <input type="hidden" name="room_type_id" value="<?= (int) $room_type_id ?>">
                <?php endif; ?>
                <input type="hidden" name="hotel_id" value="<?= (int) $hotel_id ?>">
                <h3>ผู้เข้าพักหลัก</h3>
                <p style="color:red; font-size:14px;">* จำเป็นต้องระบุ</p>

                <div style="display:flex; gap:10px;">
                    <input type="text" name="first_name" placeholder="ชื่อ *"
                           value="<?= htmlspecialchars($first_name) ?>" required>
                    <input type="text" name="last_name" placeholder="นามสกุล *"
                           value="<?= htmlspecialchars($last_name) ?>" required>
                </div>

                <input type="email" name="email" placeholder="อีเมล *"
                       value="<?= htmlspecialchars($user["email"]) ?>" required>
                <small>กรุณาตรวจสอบว่าอีเมลของท่านถูกต้องหรือไม่ เราจะส่งใบยืนยันการจองไปที่อีเมลนี้</small>

                <input type="text" name="phone" placeholder="หมายเลขโทรศัพท์ *"
                       value="<?= htmlspecialchars($user["phone_number"] ?? '') ?>" required>

                <hr style="margin:20px 0;">

                <label for="checkin">วันที่เช็คอิน</label>
                <input type="date" id="checkin" name="checkin" required>

                <label for="checkout">วันที่เช็คเอาท์</label>
                <input type="date" id="checkout" name="checkout" required>

                <?php if ($room): ?>
                    <div id="availabilityNote" class="availability-note"></div>
                <?php endif; ?>

                <label for="guests">จำนวนผู้เข้าพัก</label>
                <input type="number" id="guests" name="guests" min="1"
                       <?= $room ? 'max="' . (int) $room["capacity"] . '"' : '' ?> required>

                <button type="submit" id="bookingSubmitBtn">ยืนยันการจอง</button>
            </form>
        <?php else: ?>
            <h2>ไม่พบข้อมูลโรงแรม</h2>
        <?php endif; ?>

        <p><a href="index.php">กลับหน้าหลัก</a></p>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    // ---- Keep checkout strictly after checkin ----
    const checkinInput  = document.getElementById("checkin");
    const checkoutInput = document.getElementById("checkout");

    const today = new Date().toISOString().split("T")[0];
    checkinInput.min = today;

    function syncCheckoutMin() {
        if (!checkinInput.value) return;
        const nextDay = new Date(checkinInput.value);
        nextDay.setDate(nextDay.getDate() + 1);
        const minCheckout = nextDay.toISOString().split("T")[0];
        checkoutInput.min = minCheckout;
        if (checkoutInput.value && checkoutInput.value <= checkinInput.value) {
            checkoutInput.value = minCheckout;
        }
    }

    syncCheckoutMin();
    checkinInput.addEventListener("change", syncCheckoutMin);

    <?php if ($room): ?>
    const note           = document.getElementById("availabilityNote");
    const submitBtn      = document.getElementById("bookingSubmitBtn");

    function checkAvailability() {
        if (!checkinInput.value || !checkoutInput.value) return;
        fetch(`check_availability.php?room_type_id=<?= (int) $room_type_id ?>&checkin=${checkinInput.value}&checkout=${checkoutInput.value}`)
            .then(res => res.json())
            .then(data => {
                if (data.available > 0) {
                    note.textContent = `เหลือ ${data.available} ห้อง`;
                    note.className = "availability-note availability-ok";
                    submitBtn.disabled = false;
                } else {
                    note.textContent = "ห้องเต็มในช่วงวันที่เลือก กรุณาเลือกวันที่อื่น";
                    note.className = "availability-note availability-warn";
                    submitBtn.disabled = true;
                }
            })
            .catch(() => { note.textContent = ""; submitBtn.disabled = false; });
    }

    checkinInput.addEventListener("change", checkAvailability);
    checkoutInput.addEventListener("change", checkAvailability);
    <?php endif; ?>
});
</script>

</body>
</html>
