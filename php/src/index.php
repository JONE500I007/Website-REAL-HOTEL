<?php
session_start();
require_once "config/database.php";

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
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function () {
    $("#hotelSearch").keyup(function () {
        let query = $(this).val();
        if (query.length > 1) {
            $.ajax({
                url: "search_hotel.php",
                method: "GET",
                data: { query: query },
                dataType: "json",
                success: function (data) {
                    let html = "";
                    if (data.length > 0) {
                        data.forEach(hotel => {
                            html += `<div onclick="window.location='hotel_detail.php?id=${hotel.id}'">
                                        <strong>${hotel.hotel_name}</strong>
                                        <span>${hotel.location} | ราคา: ${hotel.price} บาท</span>
                                     </div>`;
                        });
                    } else {
                        html = `<div class="no-result"><span>🔍</span> ไม่พบโรงแรมที่ตรงกับ "<strong>${query}</strong>"</div>`;
                    }
                    $("#searchResult").html(html).show();
                }
            });
        } else {
            $("#searchResult").hide();
        }
    });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const checkinInput  = document.getElementById("searchCheckin");
    const checkoutInput = document.getElementById("searchCheckout");

    const today = new Date().toISOString().split("T")[0];
    checkinInput.min = today;

    checkinInput.addEventListener("change", function () {
        if (!checkinInput.value) return;
        const nextDay = new Date(checkinInput.value);
        nextDay.setDate(nextDay.getDate() + 1);
        const minCheckout = nextDay.toISOString().split("T")[0];
        checkoutInput.min = minCheckout;
        if (checkoutInput.value && checkoutInput.value <= checkinInput.value) {
            checkoutInput.value = minCheckout;
        }
    });
});
</script>

<div class="hero-section">
    <div class="container">
        <h1>ค้นหาโรงแรมในอำเภอเมืองจังหวัดปัตตานี</h1>
        <p>พบโรงแรมที่เหมาะสมกับคุณในพื้นที่ที่คุณต้องการ</p>
        <form class="search-form" action="hotel.php" method="get">
            <div class="search-segment search-segment-main">
                <span class="search-segment-icon">🔍</span>
                <div class="search-segment-body">
                    <label>ค้นหา</label>
                    <div class="search-input-wrap">
                        <input type="text" id="hotelSearch" name="keyword" placeholder="ชื่อโรงแรมหรือสถานที่ใกล้เคียง">
                        <div id="searchResult"></div>
                    </div>
                </div>
            </div>
            <div class="search-divider"></div>
            <div class="search-segment">
                <span class="search-segment-icon">📅</span>
                <div class="search-segment-body">
                    <label>เช็คอิน</label>
                    <input type="date" id="searchCheckin" name="checkin">
                </div>
            </div>
            <div class="search-divider"></div>
            <div class="search-segment">
                <span class="search-segment-icon">📅</span>
                <div class="search-segment-body">
                    <label>เช็คเอาท์</label>
                    <input type="date" id="searchCheckout" name="checkout">
                </div>
            </div>
            <div class="search-divider"></div>
            <div class="search-segment search-segment-guests">
                <span class="search-segment-icon">👤</span>
                <div class="search-segment-body">
                    <label>ผู้เข้าพัก</label>
                    <input type="number" name="guests" min="1" value="2">
                </div>
            </div>
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

<div class="image-slider">
    <div class="slider-controls">
        <button class="prev-btn">❮</button>
        <button class="next-btn">❯</button>
    </div>
    <div class="slider-container">
        <img src="image/The-Berkeley-Hotel.jpg" alt="Room 1" class="slider-image active">
        <img src="image/unnamed.jpg" alt="Room 2" class="slider-image">
        <img src="image/imagwdes.jpg" alt="Room 3" class="slider-image">
    </div>
</div>

<?php require_once "includes/footer.php"; ?>


<script>
document.querySelector(".scroll-btn.right").addEventListener("click", () => {
    document.querySelector(".hotel-list").scrollBy({ left: 400, behavior: "smooth" });
});
document.querySelector(".scroll-btn.left").addEventListener("click", () => {
    document.querySelector(".hotel-list").scrollBy({ left: -400, behavior: "smooth" });
});
</script>
<script>
const sliderContainer = document.querySelector('.slider-container');
const images = document.querySelectorAll('.slider-image');
let currentIndex = 0;

function updateSlider() {
    const imageWidth = images[0].clientWidth;
    sliderContainer.style.transform = `translateX(${-currentIndex * imageWidth}px)`;
}

document.querySelector('.next-btn').addEventListener('click', () => {
    currentIndex = (currentIndex + 1) % images.length;
    updateSlider();
});
document.querySelector('.prev-btn').addEventListener('click', () => {
    currentIndex = (currentIndex - 1 + images.length) % images.length;
    updateSlider();
});
</script>

</body>
</html>
