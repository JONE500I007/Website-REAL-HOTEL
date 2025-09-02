<?php
session_start();
require_once "database.php";

if (!isset($_GET["id"])) {
    echo "ไม่พบโรงแรม";
    exit;
}

$hotel_id = $_GET["id"];
$sql = "SELECT * FROM hotels WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$result = $stmt->get_result();
$hotel = $result->fetch_assoc();

if (!$hotel) {
    echo "ไม่พบโรงแรม";
    exit;
}

$img_sql = "SELECT image_path FROM hotel_images WHERE hotel_id = ?";
$img_stmt = $conn->prepare($img_sql);
$img_stmt->bind_param("i", $hotel_id);
$img_stmt->execute();
$images = $img_stmt->get_result();


$all_images = $images->fetch_all(MYSQLI_ASSOC);
$primary_image = $all_images[0]['image_path'] ?? 'image/641151494.jpg';
array_shift($all_images);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel["hotel_name"]) ?></title>
    <link rel="stylesheet" href="style2.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="logo">
                <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Logo">
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
                            <?php if ($_SESSION["role"] === "owner"): ?>
                                <a href="manage_hotels.php">แก้ไขโรงแรม</a>
                            <?php endif; ?>
                            <a href="logout.php">ออกจากระบบ</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="main-content">
        <div class="container">
            <div class="hotel-header">
                <h1><?= htmlspecialchars($hotel["hotel_name"]) ?></h1>
                <div class="rating">
                    <span class="star">★</span><span class="star">★</span><span class="star">★</span>
                    <span class="star-rating">4.5</span>
                </div>
            </div>

            <div class="hotel-address">
                <p><?= htmlspecialchars($hotel["location"]) ?></p>
            </div>

            <div class="hotel-gallery">
                <?php
                $img_sql = "SELECT image_path FROM hotel_images WHERE hotel_id = ?";
                $img_stmt = $conn->prepare($img_sql);
                $img_stmt->bind_param("i", $hotel_id);
                $img_stmt->execute();
                $images = $img_stmt->get_result();

                $all_images = $images->fetch_all(MYSQLI_ASSOC);
                $primary_image = 'image/641151494.jpg';
                if (!empty($all_images)) {
                    $primary_image = $all_images[0]['image_path'];
                    array_shift($all_images); // เอารูปหลักออก
                }
                ?>
                <div class="main-image">
                    <img src="<?= htmlspecialchars($primary_image) ?>" alt="Hotel main image">
                </div>
                <div class="thumbnail-images">
                    <?php foreach ($all_images as $img): ?>
                        <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="Hotel thumbnail">
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="hotel-details-section">
                <div class="details-left">
                    <div class="detail-box">
                        <h3>ไฮไลท์</h3>
                        <p><?= htmlspecialchars($hotel["description"]) ?></p>
                    </div>
                    <div class="detail-box">
                        <h3>สิ่งอำนวยความสะดวก</h3>
                        <ul>
                            <li>ฟรี Wi-Fi</li>
                            <li>ที่จอดรถฟรี</li>
                            <li>สระว่ายน้ำ</li>
                        </ul>
                    </div>
                </div>
                <div class="details-right">
                    <div class="price-box">
                        <p class="price-label">ราคาต่อคืน</p>
                        <p class="price-amount">฿<?= htmlspecialchars($hotel["price"]) ?></p>
                        <a href="#" class="btn-booking">เลือกห้องพัก</a>
                    </div>
                    <div class="location-box">
                        <h3>บริเวณโดยรอบ</h3>
                        <ul>
                            <li>มหาวิทยาลัยสงขลานครินทร์ วิทยาเขตปัตตานี (1.2 กม.)</li>
                            <li>ตลาดนัด (0.5 กม.)</li>
                        </ul>
                    </div>
                </div>
            </div>
            <a href="index.php" class="back-link">← กลับหน้าหลัก</a>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
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
</body>
</html>