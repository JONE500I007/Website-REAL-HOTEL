<?php
session_start();
require_once "database.php";

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
    LIMIT 6
";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel</title>
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
            <a href="hotel.php" class="active">Hotel</a>
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
                            <a href="manage_hotels.php">จัดการโรงแรม</a>
                        <?php endif; ?>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<div class="container">

    <?php
    // ฟังก์ชันดึงโรงแรมตามหมวดหมู่
    function showHotelsByCategory($conn, $categoryName, $condition = "1=1") {
        echo "<h2>$categoryName</h2>";
        echo '<div class="hotel-list">';
        
        $sql = "SELECT * FROM hotels WHERE $condition LIMIT 6"; 
        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo '<div class="hotel-card">';
                echo '<img src="' . 
                        (!empty($row["image_path"]) 
                            ? htmlspecialchars($row["image_path"]) 
                            : "uploads/hotels/noimage.jpg") . 
                        '" alt="Hotel Image">';
                echo '<h3>' . htmlspecialchars($row["hotel_name"]) . '</h3>';
                echo '<p>ที่ตั้ง: ' . htmlspecialchars($row["location"]) . '</p>';
                echo '<p>ราคา: ' . htmlspecialchars($row["price"]) . ' บาท</p>';
                echo '<p>' . htmlspecialchars($row["description"]) . '</p>';
                echo '<a href="hotel_detail.php?id=<?= $row["id"] ?>" class="btn-details">ดูรายละเอียด</a>';
                echo '</div>';
            }
        } else {
            echo "<p>ยังไม่มีโรงแรมในหมวดนี้</p>";
        }
        echo "</div>";
    }

    // ตัวอย่างการแบ่งหมวดหมู่
    showHotelsByCategory($conn, "โรงแรมราคาประหยัดในอำเภอเมือง ปัตตานี", "price < 1000");
    showHotelsByCategory($conn, "โรงแรมหรูในอำเภอเมือง ปัตตานี", "price >= 1000 AND price < 3000");
    showHotelsByCategory($conn, "โรงแรมครอบครัวในอำเภอเมือง ปัตตานี", "price >= 3000");
    ?>

</div>

<footer class="footer">
    <div class="container">
        <div class="footer-content">
            <div class="footer-logo">
                <img src="image/hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
            </div>
            <p>© 2025 PNVC, นายครรชิดพล เพ็งเอียด</p>
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
