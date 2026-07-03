<?php
session_start();
require_once "config/database.php";

if (!isset($_GET["id"])) {
    header("Location: hotel.php");
    exit;
}

$hotel_id = (int) $_GET["id"];

$stmt = $conn->prepare("SELECT * FROM hotels WHERE id = ?");
$stmt->bind_param("i", $hotel_id);
$stmt->execute();
$hotel = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$hotel) {
    header("Location: hotel.php");
    exit;
}

$img_stmt = $conn->prepare("SELECT image_path FROM hotel_images WHERE hotel_id = ?");
$img_stmt->bind_param("i", $hotel_id);
$img_stmt->execute();
$all_images = $img_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$img_stmt->close();

$galleryImages    = !empty($all_images) ? array_column($all_images, 'image_path') : ['image/641151494.jpg'];
$primary_image    = $galleryImages[0];
$visible_thumbs   = array_slice($galleryImages, 1, 4);
$hidden_count     = count($galleryImages) - 1 - count($visible_thumbs);

$rt_stmt = $conn->prepare("SELECT * FROM room_types WHERE hotel_id = ? ORDER BY price_per_night ASC");
$rt_stmt->bind_param("i", $hotel_id);
$rt_stmt->execute();
$roomTypes = $rt_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$rt_stmt->close();

foreach ($roomTypes as &$room) {
    $ri = $conn->prepare("SELECT image_path FROM room_images WHERE room_type_id = ? ORDER BY id ASC");
    $ri->bind_param("i", $room["id"]);
    $ri->execute();
    $imgs = array_column($ri->get_result()->fetch_all(MYSQLI_ASSOC), 'image_path');
    $room["images"] = !empty($imgs) ? $imgs : ['image/641151494.jpg'];
    $ri->close();
}
unset($room);

$hasPin = !empty($hotel["latitude"]) && !empty($hotel["longitude"]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($hotel["hotel_name"]) ?></title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <?php if ($hasPin): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <?php endif; ?>
</head>
<body>

<?php require_once "includes/header.php"; ?>

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

        <div class="hotel-gallery" data-images='<?= htmlspecialchars(json_encode($galleryImages), ENT_QUOTES) ?>'>
            <div class="main-image room-thumb-clickable" data-index="0" onclick="openImageGallery(this)">
                <img src="<?= htmlspecialchars($primary_image) ?>" alt="Hotel main image">
                <?php if (count($galleryImages) > 1): ?>
                    <span class="gallery-view-all-btn">📷 ดูรูปทั้งหมด</span>
                <?php endif; ?>
            </div>
            <div class="thumbnail-images">
                <?php foreach ($visible_thumbs as $i => $imgPath): ?>
                    <div class="room-thumb-clickable" data-index="<?= $i + 1 ?>" onclick="openImageGallery(this)">
                        <img src="<?= htmlspecialchars($imgPath) ?>" alt="Hotel thumbnail">
                        <?php if ($hidden_count > 0 && $i === count($visible_thumbs) - 1): ?>
                            <span class="room-image-count">+<?= $hidden_count ?> รูปภาพ</span>
                        <?php endif; ?>
                    </div>
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
                        <?php foreach (explode(",", $hotel["facilities"]) as $facility): ?>
                            <li><?= htmlspecialchars(trim($facility)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($hasPin): ?>
                <div class="detail-box map-card">
                    <h3>ตำแหน่งที่ตั้ง</h3>
                    <div id="hotelMapView" class="leaflet-map-container"></div>
                </div>
                <?php endif; ?>
            </div>
            <div class="details-right">
                <?php if (empty($roomTypes)): ?>
                <div class="price-box">
                    <p class="price-label">ราคาต่อคืน</p>
                    <p class="price-amount">฿<?= htmlspecialchars($hotel["price"]) ?></p>
                    <a href="booking.php?hotel_id=<?= $hotel["id"] ?>" class="btn-booking">จองโรงแรม</a>
                </div>
                <?php else: ?>
                <div class="price-box">
                    <p class="price-label">เริ่มต้นที่</p>
                    <p class="price-amount">฿<?= htmlspecialchars($roomTypes[0]["price_per_night"]) ?></p>
                    <p class="price-label">ต่อคืน</p>
                </div>
                <?php endif; ?>
                <div class="location-box">
                    <h3>บริเวณโดยรอบ</h3>
                    <ul>
                        <?php foreach (explode(",", $hotel["surrounding"]) as $place): ?>
                            <li><?= htmlspecialchars(trim($place)) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (!empty($roomTypes)): ?>
        <div class="detail-box" style="margin-top:20px;">
            <h3>ห้องพัก</h3>
            <div class="room-type-grid">
                <?php foreach ($roomTypes as $room): ?>
                    <div class="room-type-card">
                        <div class="room-thumb-clickable" onclick="openImageGallery(this)" data-images='<?= htmlspecialchars(json_encode($room['images']), ENT_QUOTES) ?>'>
                            <img src="<?= htmlspecialchars($room['images'][0]) ?>" alt="<?= htmlspecialchars($room['room_name']) ?>">
                            <?php if (count($room['images']) > 1): ?>
                                <span class="room-image-count">🖼 <?= count($room['images']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <h4><?= htmlspecialchars($room['room_name']) ?></h4>
                            <span class="capacity-badge">👤 x <?= (int) $room['capacity'] ?></span>
                            <?php if (!empty($room['amenities'])): ?>
                            <p style="font-size:13px; opacity:0.85; margin:4px 0;">
                                <?= htmlspecialchars($room['amenities']) ?>
                            </p>
                            <?php endif; ?>
                            <span class="room-price">฿<?= htmlspecialchars($room['price_per_night']) ?> / คืน</span>
                            <div class="room-actions">
                                <a href="booking.php?hotel_id=<?= $hotel['id'] ?>&room_type_id=<?= $room['id'] ?>" class="btn-edit-room" style="background:#7b59a9;">จองห้องนี้</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <a href="hotel.php" class="back-link">← กลับหน้าโรงแรม</a>
    </div>
</div>

<!-- Room image gallery lightbox -->
<div id="roomGalleryModal" class="gallery-lightbox" style="display:none">
    <button type="button" class="gallery-close" id="galleryClose">✕</button>
    <button type="button" class="gallery-nav gallery-prev" id="galleryPrev">‹</button>
    <img id="galleryImage" src="" alt="room photo">
    <button type="button" class="gallery-nav gallery-next" id="galleryNext">›</button>
    <div class="gallery-counter" id="galleryCounter"></div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js"></script>
<script>
var galleryImages = [];
var galleryIndex = 0;

function openImageGallery(el) {
    var source = el.hasAttribute('data-images') ? el : el.closest('[data-images]');
    galleryImages = JSON.parse(source.dataset.images);
    galleryIndex = el.dataset.index ? parseInt(el.dataset.index, 10) : 0;
    showGalleryImage();
    document.getElementById('roomGalleryModal').style.display = 'flex';
}

function showGalleryImage() {
    document.getElementById('galleryImage').src = galleryImages[galleryIndex];
    document.getElementById('galleryCounter').textContent = (galleryIndex + 1) + ' / ' + galleryImages.length;
}

document.addEventListener('DOMContentLoaded', function () {
    var modal = document.getElementById('roomGalleryModal');

    document.getElementById('galleryClose').addEventListener('click', function () {
        modal.style.display = 'none';
    });
    document.getElementById('galleryPrev').addEventListener('click', function () {
        galleryIndex = (galleryIndex - 1 + galleryImages.length) % galleryImages.length;
        showGalleryImage();
    });
    document.getElementById('galleryNext').addEventListener('click', function () {
        galleryIndex = (galleryIndex + 1) % galleryImages.length;
        showGalleryImage();
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });
    document.addEventListener('keydown', function (e) {
        if (modal.style.display !== 'flex') return;
        if (e.key === 'Escape') modal.style.display = 'none';
        if (e.key === 'ArrowLeft') document.getElementById('galleryPrev').click();
        if (e.key === 'ArrowRight') document.getElementById('galleryNext').click();
    });
});
</script>
<?php if ($hasPin): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    var lat = <?= (float) $hotel["latitude"] ?>;
    var lng = <?= (float) $hotel["longitude"] ?>;
    var map = L.map('hotelMapView', { dragging: true, scrollWheelZoom: false }).setView([lat, lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);
    L.marker([lat, lng]).addTo(map);
});
</script>
<?php endif; ?>
</body>
</html>
