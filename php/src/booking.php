<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php?error=login_required");
    exit;
}

// load databases
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION["user_id"]);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$user_id = $user["id"];
$stmt->close();


$hotel_id = $_GET["hotel_id"] ?? 0;
// Retrieve hotel information
$sql = "SELECT * FROM hotels WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$result = $stmt->get_result();
$hotel = $result->fetch_assoc();

/*
$hotel_id = isset($_GET["hotel_id"]) ? (int) $_GET["hotel_id"] : 0;
// Retrieve hotel information
$hotel = null;
if ($hotel_id > 0) {
    $sql = "SELECT * FROM hotels WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hotel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $hotel = $result->fetch_assoc();
}
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = $_POST["first_name"];
    $last_name  = $_POST["last_name"];
    $email      = $_POST["email"];
    $phone      = $_POST["phone"];
    $country    = $_POST["country"];
    $checkin    = $_POST["checkin"];
    $checkout   = $_POST["checkout"];
    $guests     = $_POST["guests"];

    $sql = "INSERT INTO bookings (first_name, last_name, email, phone, country, checkin, checkout, guests, book_hotel_name)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sssssssis", $first_name, $last_name, $email, $phone, $country, $checkin, $checkout, $guests, $hotel["hotel_name"]);

    if ($stmt->execute()) {
        echo "<script>
                alert('คุณได้ทำการจองโรงแรมเรียบร้อย');
                window.location.href = 'index.php';
              </script>";
        exit;
    } else {
        echo "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $stmt->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ติดต่อเรา</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="style2.css?v=1.6">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

    <header class="navbar">
        <div class="container">
            <div class="logo">
                <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Logo">
            </div>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="hotel.php">Hotel</a>
                <a href="contact.php">Contact</a>
            </nav>
            <div class="auth-links">
            <?php if (!isset($_SESSION["user"])): ?>
                <a href="register.php" class="btn-signup">สมัครสมาชิก</a>
                <a href="login.php" class="btn-signup">เข้าสู่ระบบ</a>
            <?php else: ?>
                <div class="profile-menu">
                    <div class="profile-icon" onclick="toggleMenu()">
                        <img src="uploads/<?= $_SESSION["profile_picture"] ?? 'default.jpg' ?>" alt="Profile">
                        <span><?= $_SESSION["user"] ?></span>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="edit_profile.php">แก้ไขโปรไฟล์</a>
                        <?php if ($_SESSION["role"] === "owner" || $_SESSION["role"] === "admin"): ?>
                            <?php
                                $owner_id = $_SESSION["user_id"];
                                $check_sql = "SELECT id FROM hotels WHERE owner_id = ?";
                                $check_stmt = $conn->prepare($check_sql);
                                $check_stmt->bind_param("i", $owner_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                $hasHotel = $check_result->num_rows > 0;
                                $check_stmt->close();
                            ?>
                            <a href="manage_hotels.php">
                                <?= $hasHotel ? "แก้ไขโรงแรม" : "เพิ่มโรงแรม" ?>
                            </a>
                        <?php endif; ?>

                        <?php if ($_SESSION["role"] === "admin"): ?>
                            <a href="admin_manage.php">จัดการระบบ</a>
                        <?php endif; ?>
                        
                        <a href="board.php">ดูการจองโรงแรม</a>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <div class="form-card">
            <?php if ($hotel): ?>
                <h2>จองโรงแรม: <?= htmlspecialchars($hotel["hotel_name"]) ?></h2>
                <form method="post">
                    <h3>ผู้เข้าพักหลัก</h3>
                    <p style="color:red; font-size:14px;">* จำเป็นต้องระบุ</p>

                    <?php
                    $full_name = $user["full_name"] ?? "";
                    $name_parts = explode(" ", $full_name, 2);
                    $first_name = $name_parts[0] ?? "";
                    $last_name  = $name_parts[1] ?? "";
                    ?>
                    <div style="display:flex; gap:10px;">
                        <input type="text" name="first_name" placeholder="ชื่อ *" 
                            value="<?= htmlspecialchars($first_name) ?>" required>
                        <input type="text" name="last_name" placeholder="นามสกุล *" 
                            value="<?= htmlspecialchars($last_name) ?>" required>
                    </div>

                    <input type="email" name="email" placeholder="อีเมล *" value="<?= htmlspecialchars($user["email"]) ?>" required>
                    <small>กรุณาตรวจสอบว่าอีเมลของท่านถูกต้องหรือไม่ เราจะส่งใบยืนยันการจองไปที่อีเมลนี้</small>

                    <input type="text" name="phone" placeholder="หมายเลขโทรศัพท์ (จำเป็น)" value="<?= htmlspecialchars($user["phone_number"]) ?>" required>

                    <div class="custom-select" id="countrySelect">
                        <div class="custom-select-trigger">-- เลือกประเทศ --</div>
                        <div class="custom-options">
                            <span class="custom-option" data-value="ไทย">ไทย</span>
                            <span class="custom-option" data-value="สหรัฐอเมริกา">สหรัฐอเมริกา</span>
                            <span class="custom-option" data-value="ญี่ปุ่น">ญี่ปุ่น</span>
                            <span class="custom-option" data-value="เกาหลีใต้">เกาหลีใต้</span>
                            <span class="custom-option" data-value="สิงคโปร์">สิงคโปร์</span>
                        </div>
                    </div>
                    <input type="hidden" name="country" id="country">

                    <hr style="margin:20px 0;">

                    <label for="checkin">วันที่เช็คอิน</label>
                    <input type="date" id="checkin" name="checkin" required>

                    <label for="checkout">วันที่เช็คเอาท์</label>
                    <input type="date" id="checkout" name="checkout" required>

                    <label for="guests">จำนวนผู้เข้าพัก</label>
                    <input type="number" id="guests" name="guests" min="1" required>

                    <button type="submit">ยืนยันการจอง</button>
                </form>
            <?php else: ?>
                <h2>ไม่พบข้อมูลโรงแรม</h2>
            <?php endif; ?>

            <p><a href="index.php">กลับหน้าหลัก</a></p>
        </div>
    </div>
    
    <!-- just my under1 -->

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
                </div>
                <p>© 2025 PNVC, นายครรชิดพล เพ็งเอียด</p>
                <div class="social-icons">
                    <a href="https://x.com/FGgez777"><img src="image\twwokX.png" alt="Twitter_X"></a>
                    <a href="https://www.instagram.com/face.2339/?igsh=dWh6eGtmbjVpanRt"><img src="image\insatagem.png" alt="Instagram"></a>
                    <a href="https://www.facebook.com/face.pengeid/"><img src="image\fackbookicon.png" alt="Facebook"></a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>

<script>
function toggleMenu() {
    const menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}
document.addEventListener('click', function(event) {
    const menu = document.getElementById("dropdownMenu");
    const profileIcon = document.querySelector('.profile-icon');
    if (menu && !profileIcon.contains(event.target)) {
        menu.style.display = "none";
    }
});
</script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
    const select = document.querySelector(".custom-select");
    const trigger = select.querySelector(".custom-select-trigger");
    const options = select.querySelectorAll(".custom-option");
    const hiddenInput = document.getElementById("country");

    trigger.addEventListener("click", () => {
        select.classList.toggle("open");
    });

    options.forEach(option => {
        option.addEventListener("click", () => {
            trigger.textContent = option.textContent;
            hiddenInput.value = option.dataset.value;
            select.classList.remove("open");
            options.forEach(o => o.classList.remove("selected"));
            option.classList.add("selected");
        });
    });

    document.addEventListener("click", (e) => {
        if (!select.contains(e.target)) {
            select.classList.remove("open");
        }
    });
});
</script>