<?php
session_start();
require_once "config/database.php";
require_once "config/payment.php";
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

if (!$user) {
    session_destroy();
    header("Location: login.php?error=login_required");
    exit;
}

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

$pricePerNight = $room ? (float) $room["price_per_night"] : (float) ($hotel["price"] ?? 0);

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

    $slipFilename = null;
    if ($canBook) {
        $slipFilename = save_payment_slip($_FILES["slip"] ?? [], __DIR__ . "/uploads/slips/");
        if ($slipFilename === false) {
            $bookingError = "กรุณาแนบหลักฐานการโอนเงินเป็นไฟล์รูปภาพ (jpg/png) ขนาดไม่เกิน 5MB";
            $canBook = false;
        }
    }

    if ($canBook) {
        $nights = (new DateTime($checkin))->diff(new DateTime($checkout))->days;
        $totalPrice = round($pricePerNight * max($nights, 1), 2);

        $stmt = $conn->prepare("
            INSERT INTO bookings (first_name, last_name, email, phone, checkin, checkout, guests, hotel_id, room_type_id, book_hotel_name, total_price, payment_slip, payment_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_verification')
        ");
        $stmt->bind_param("ssssssiiisds", $first_name, $last_name, $email, $phone, $checkin, $checkout, $guests, $hotel_id, $room_type_id, $hotel["hotel_name"], $totalPrice, $slipFilename);

        if ($stmt->execute()) {
            echo "<script>alert('คุณได้ทำการจองโรงแรมเรียบร้อย เราจะตรวจสอบหลักฐานการชำระเงินและยืนยันการจองของท่านโดยเร็วที่สุด'); window.location.href = 'board.php';</script>";
            exit;
        } else {
            $bookingError = "เกิดข้อผิดพลาด: " . $stmt->error;
            unlink(__DIR__ . "/uploads/slips/" . $slipFilename);
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

            <form method="post" id="bookingForm" enctype="multipart/form-data">
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

                <div id="paymentModal" class="crop-modal" style="display:none">
                    <div class="crop-modal-box">
                        <div class="crop-modal-header">
                            <h3 style="flex:1; text-align:center;">ชำระเงินผ่าน PromptPay</h3>
                        </div>
                        <div class="crop-canvas-wrap qr-canvas-wrap" style="background:#fff; text-align:center;">
                            <div id="promptpayQr" style="display:inline-block;"></div>
                            <p style="margin:14px 0 4px; font-size:15px; color:#333;">
                                ยอดชำระ <strong id="paymentAmount">฿0.00</strong>
                            </p>
                            <p style="margin:0 0 10px; font-size:13px; color:#777;">
                                PromptPay: <?= htmlspecialchars(PROMPTPAY_NAME) ?> (<?= htmlspecialchars(PROMPTPAY_ID) ?>)
                            </p>
                        </div>
                        <div style="padding:0 22px 16px;">
                            <label for="slipInput">แนบหลักฐานการโอนเงิน (สลิป) *</label>
                            <input type="file" id="slipInput" name="slip" accept="image/png,image/jpeg">
                            <p id="slipError" style="color:#e05c5c; font-size:13px; display:none;">
                                กรุณาแนบไฟล์รูปภาพ (jpg/png)
                            </p>
                        </div>
                        <div class="crop-modal-footer">
                            <button type="button" id="paymentCancelBtn">ยกเลิก</button>
                            <button type="button" id="paymentConfirmBtn">ยืนยันการจอง</button>
                        </div>
                    </div>
                </div>
            </form>
        <?php else: ?>
            <h2>ไม่พบข้อมูลโรงแรม</h2>
        <?php endif; ?>

        <p><a href="index.php">กลับหน้าหลัก</a></p>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/qrcode.js"></script>
<script src="assets/js/promptpay.js"></script>
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

    // ---- Payment popup: QR + slip upload, shown on submit ----
    const pricePerNight   = <?= json_encode($pricePerNight) ?>;
    const promptpayId     = <?= json_encode(PROMPTPAY_ID) ?>;
    const bookingForm      = document.getElementById("bookingForm");
    const paymentModal     = document.getElementById("paymentModal");
    const paymentAmountEl  = document.getElementById("paymentAmount");
    const slipInput        = document.getElementById("slipInput");
    const slipError        = document.getElementById("slipError");
    const qrContainer      = document.getElementById("promptpayQr");
    let paymentConfirmed   = false;

    function currentTotal() {
        if (!checkinInput.value || !checkoutInput.value) return 0;
        const start = new Date(checkinInput.value);
        const end   = new Date(checkoutInput.value);
        const nights = Math.max(1, Math.round((end - start) / 86400000));
        return Math.round(pricePerNight * nights * 100) / 100;
    }

    bookingForm.addEventListener("submit", function (e) {
        if (paymentConfirmed) return; // second submit, triggered programmatically below
        e.preventDefault();

        const total = currentTotal();
        paymentAmountEl.textContent = "฿" + total.toLocaleString("th-TH", { minimumFractionDigits: 2 });
        renderPromptPayQR(qrContainer, promptpayId, total);
        slipInput.value = "";
        slipError.style.display = "none";
        paymentModal.style.display = "flex";
    });

    document.getElementById("paymentCancelBtn").addEventListener("click", () => paymentModal.style.display = "none");

    document.getElementById("paymentConfirmBtn").addEventListener("click", function () {
        if (!slipInput.files || slipInput.files.length === 0) {
            slipError.style.display = "block";
            return;
        }
        paymentConfirmed = true;
        bookingForm.requestSubmit();
    });
});
</script>

</body>
</html>
