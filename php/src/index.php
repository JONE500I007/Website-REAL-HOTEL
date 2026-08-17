<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

$categories = $conn->query("SELECT * FROM hotel_categories WHERE show_on IN ('both', 'index') ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);
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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
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
                        html = `<div class="no-result"><span class="material-symbols-outlined">search</span> ไม่พบโรงแรมที่ตรงกับ "<strong>${query}</strong>"</div>`;
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
        <h1>ค้นหาโรงแรมทั่วไทย</h1>
        <p>พบโรงแรมที่เหมาะสมกับคุณในพื้นที่ที่คุณต้องการ</p>
        <form class="search-form" action="hotel.php" method="get">
            <div class="search-segment search-segment-main">
                <span class="search-segment-icon material-symbols-outlined">search</span>
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
                <span class="search-segment-icon material-symbols-outlined">calendar_month</span>
                <div class="search-segment-body">
                    <label>เช็คอิน</label>
                    <input type="date" id="searchCheckin" name="checkin">
                </div>
            </div>
            <div class="search-divider"></div>
            <div class="search-segment">
                <span class="search-segment-icon material-symbols-outlined">calendar_month</span>
                <div class="search-segment-body">
                    <label>เช็คเอาท์</label>
                    <input type="date" id="searchCheckout" name="checkout">
                </div>
            </div>
            <div class="search-divider"></div>
            <div class="search-segment search-segment-guests">
                <span class="search-segment-icon material-symbols-outlined">person</span>
                <div class="search-segment-body">
                    <label>ผู้เข้าพัก</label>
                    <input type="number" name="guests" min="1" value="2">
                </div>
            </div>
            <button type="submit">ค้นหา</button>
        </form>
    </div>
</div>

<?php foreach ($categories as $category): ?>
    <?php render_hotel_category($conn, (int) $category['id'], $category['title'], (bool) $category['is_auto']); ?>
<?php endforeach; ?>

<div class="image-slider">
    <div class="slider-controls">
        <button class="prev-btn"><span class="material-symbols-outlined">chevron_left</span></button>
        <button class="next-btn"><span class="material-symbols-outlined">chevron_right</span></button>
    </div>
    <div class="slider-container">
        <img src="image/The-Berkeley-Hotel.jpg" alt="Room 1" class="slider-image active">
        <img src="image/unnamed.jpg" alt="Room 2" class="slider-image">
        <img src="image/imagwdes.jpg" alt="Room 3" class="slider-image">
    </div>
</div>

<?php require_once "includes/footer.php"; ?>


<script>
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
