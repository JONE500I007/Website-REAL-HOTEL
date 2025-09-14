<?php
session_start();
require_once "database.php";

// ฟังก์ชันแสดงโรงแรมตามหมวดหมู่
function showHotelsByCategory($conn, $title, $condition) {
    $sql = "
        SELECT hotels.*, hotel_images.image_path
        FROM hotels
        LEFT JOIN (
            SELECT MIN(id) as id, hotel_id
            FROM hotel_images
            GROUP BY hotel_id
        ) AS first_images ON first_images.hotel_id = hotels.id
        LEFT JOIN hotel_images ON hotel_images.id = first_images.id
        WHERE $condition
    ";
    $result = $conn->query($sql);
    ?>
    <div class="popular-hotels">
        <div class="container">
            <h2 class="section-title"><?= htmlspecialchars($title) ?></h2>
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="hotel-list-wrapper">
                    <button class="scroll-btn left">⟨</button>
                    <div class="hotel-list">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="hotel-card">
                                <img src="<?= !empty($row["image_path"]) ? htmlspecialchars($row["image_path"]) : "uploads/hotels/noimage.jpg" ?>" alt="Hotel Image">
                                <div class="card-content">
                                    <h3><?= htmlspecialchars($row["hotel_name"]) ?></h3>
                                    <p><?= htmlspecialchars($row["location"]) ?></p>
                                    <p><?= htmlspecialchars($row["description"]) ?></p>
                                    <p>ราคา: <?= htmlspecialchars($row["price"]) ?> บาท</p>
                                    <a href="hotel_detail.php?id=<?= $row["id"] ?>" class="btn-details">ดูรายละเอียด</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <button class="scroll-btn right">⟩</button>
                </div>
            <?php else: ?>
                <p>ยังไม่มีโรงแรมในหมวดนี้</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ---------------------------
// 🔍 ส่วนการค้นหา
// ---------------------------
$conditions = [];

if (!empty($_GET['keyword'])) {
    $keyword = $conn->real_escape_string($_GET['keyword']);
    $conditions[] = "(hotels.hotel_name LIKE '%$keyword%' OR hotels.location LIKE '%$keyword%')";
}

if (!empty($_GET['type'])) {
    $type = $conn->real_escape_string($_GET['type']);
    $conditions[] = "hotels.type LIKE '%$type%'";
}

$where = "";
if (count($conditions) > 0) {
    $where = "WHERE " . implode(" AND ", $conditions);
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ค้นหาโรงแรม</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="style2.css?v=1.3">
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

<?php
// ---------------------------
// ถ้ามีการค้นหา → แสดงผลลัพธ์ค้นหา
// ---------------------------
if ($where !== "") {
    $sql = "
        SELECT hotels.*, hotel_images.image_path
        FROM hotels
        LEFT JOIN (
            SELECT MIN(id) as id, hotel_id
            FROM hotel_images
            GROUP BY hotel_id
        ) AS first_images ON first_images.hotel_id = hotels.id
        LEFT JOIN hotel_images ON hotel_images.id = first_images.id
        $where
    ";
    $result = $conn->query($sql);

    echo '<div class="popular-hotels"><div class="container">';
    echo '<h2 class="section-title">ผลการค้นหาโรงแรม</h2>';

    if ($result && $result->num_rows > 0) {
        echo '<div class="hotel-list-wrapper">';
        echo '<button class="scroll-btn left">⟨</button>';
        echo '<div class="hotel-list">';
        while ($row = $result->fetch_assoc()) {
            ?>
            <div class="hotel-card">
                <img src="<?= !empty($row["image_path"]) ? htmlspecialchars($row["image_path"]) : "uploads/hotels/noimage.jpg" ?>" alt="Hotel Image">
                <div class="card-content">
                    <h3><?= htmlspecialchars($row["hotel_name"]) ?></h3>
                    <p><?= htmlspecialchars($row["location"]) ?></p>
                    <p><?= htmlspecialchars($row["description"]) ?></p>
                    <p>ราคา: <?= htmlspecialchars($row["price"]) ?> บาท</p>
                    <a href="hotel_detail.php?id=<?= $row["id"] ?>" class="btn-details">ดูรายละเอียด</a>
                </div>
            </div>
            <?php
        }
        echo '</div><button class="scroll-btn right">⟩</button></div>';
    } else {
        echo "<p>ไม่พบโรงแรมที่คุณค้นหา</p>";
    }

    echo '</div></div>';

} else {
    // ---------------------------
    // ถ้าไม่มีการค้นหา → แสดงหมวดหมู่ราคา
    // ---------------------------
    showHotelsByCategory($conn, "โรงแรมราคาประหยัดในอำเภอเมือง ปัตตานี", "price < 1000");
    showHotelsByCategory($conn, "โรงแรมครอบครัวในอำเภอเมือง ปัตตานี", "price >= 1000 AND price < 4000");
    showHotelsByCategory($conn, "โรงแรมหรูในอำเภอเมือง ปัตตานี", "price >= 4000");
}
?>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
            </div>
            <p>© 2025 PNVC, นายครรชิดพล เพ็งเอียด</p>
            <div class="social-icons">
                <a href="https://x.com/FGgez777"><img src="image/twwokX.png" alt="Twitter_X"></a>
                <a href="https://www.instagram.com/face.2339/?igsh=dWh6eGtmbjVpanRt"><img src="image/insatagem.png" alt="Instagram"></a>
                <a href="https://www.facebook.com/face.pengeid/"><img src="image/fackbookicon.png" alt="Facebook"></a>
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

// ปุ่มเลื่อนการ์ด
document.addEventListener("DOMContentLoaded", () => {
    document.querySelectorAll(".scroll-btn.right").forEach(btn => {
        btn.addEventListener("click", () => {
            btn.closest(".hotel-list-wrapper").querySelector(".hotel-list")
               .scrollBy({ left: 400, behavior: "smooth" });
        });
    });
    document.querySelectorAll(".scroll-btn.left").forEach(btn => {
        btn.addEventListener("click", () => {
            btn.closest(".hotel-list-wrapper").querySelector(".hotel-list")
               .scrollBy({ left: -400, behavior: "smooth" });
        });
    });
});
</script>

</body>
</html>