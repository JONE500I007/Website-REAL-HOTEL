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
";

$result = $conn->query($sql);
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

    <div class="hero-section">
        <div class="container">
            <h1>ค้นหาโรงแรมในอำเภอเมืองจังหวัดปัตตานี</h1>
            <p>พบโรงแรมที่เหมาะสมกับคุณในพื้นที่ที่คุณต้องการ</p>
            <form class="search-form" action="hotel.php" method="get">
                <input type="text" placeholder="ชื่อโรงแรมหรือสถานที่ใกล้เคียง">
                <input type="text" placeholder="เลือกประเภทโรงแรม">
                <button type="submit">ค้นหา</button>
            </form>
        </div>
    </div>

    <div class="popular-hotels">
        <div class="container">
            <h2 class="section-title">โรงแรมยอดนิยมในอำเภอเมือง ปัตตานี</h2>

            <div class="hotel-list-wrapper">
                <button class="scroll-btn left">⟨</button>
                    <div class="hotel-list">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="hotel-card">
                                <img src="<?= !empty($row["image_path"]) ? htmlspecialchars($row["image_path"]) : "uploads/hotels/noimage.jpg" ?>" 
                                alt="Hotel Image">

                                <div class="card-content">
                                    <h3><?= htmlspecialchars($row["hotel_name"]) ?></h3>
                                    <p><?= htmlspecialchars($row["location"]) ?></p>
                                    <p><?= htmlspecialchars($row["description"]) ?></p>
                                    <a href="hotel_detail.php?id=<?= $row["id"] ?>" class="btn-details">ดูรายละเอียด</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <button class="scroll-btn right">⟩</button>
            </div>    
        </div>
    </div>
    
    <!-- just my under1 -->

    <div class="image-slider">
        <div class="slider-controls">
            <button class="prev-btn">❮</button>
            <button class="next-btn">❯</button>
        </div>
        <div class="slider-container">
            <img src="image\The-Berkeley-Hotel.jpg" alt="Room 1" class="slider-image active">
            <img src="image\unnamed.jpg" alt="Room 2" class="slider-image">
            <img src="image\imagwdes.jpg" alt="Room 3" class="slider-image">
        </div>
    </div>

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
    const sliderContainer = document.querySelector('.slider-container');
    const prevBtn = document.querySelector('.prev-btn');
    const nextBtn = document.querySelector('.next-btn');
    const images = document.querySelectorAll('.slider-image');
    let currentIndex = 0;

    function updateSlider() {
        // just this code can make it slip (:
        const imageWidth = images[0].clientWidth;
        sliderContainer.style.transform = `translateX(${-currentIndex * imageWidth}px)`;
    }

    nextBtn.addEventListener('click', () => {
        currentIndex++;
        if (currentIndex >= images.length) {
            currentIndex = 0;
        }
        updateSlider();
    });

    prevBtn.addEventListener('click', () => {
        currentIndex--;
        if (currentIndex < 0) {
            currentIndex = images.length - 1;
        }
        updateSlider();
    });
</script>

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
document.querySelector(".scroll-btn.right").addEventListener("click", () => {
  document.querySelector(".hotel-list").scrollBy({ left: 400, behavior: "smooth" });
});

document.querySelector(".scroll-btn.left").addEventListener("click", () => {
  document.querySelector(".hotel-list").scrollBy({ left: -400, behavior: "smooth" });
});
</script>