<?php
session_start();
require_once "config/database.php";

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
                                <img src="<?= !empty($row["image_path"]) ? htmlspecialchars($row["image_path"]) : "uploads/hotels/noimage.jpg" ?>"
                                     alt="Hotel Image">
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

$keyword  = trim($_GET['keyword'] ?? '');
$hasSearch = $keyword !== '';

if ($hasSearch) {
    $search = "%" . $conn->real_escape_string($keyword) . "%";
}
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

<?php if ($hasSearch): ?>
    <?php
    $sql = "
        SELECT hotels.*, hotel_images.image_path
        FROM hotels
        LEFT JOIN (
            SELECT MIN(id) as id, hotel_id
            FROM hotel_images
            GROUP BY hotel_id
        ) AS first_images ON first_images.hotel_id = hotels.id
        LEFT JOIN hotel_images ON hotel_images.id = first_images.id
        WHERE hotels.hotel_name LIKE '$search' OR hotels.location LIKE '$search'
    ";
    $result = $conn->query($sql);
    ?>
    <div class="popular-hotels">
        <div class="container">
            <h2 class="section-title">ผลการค้นหาโรงแรม: "<?= htmlspecialchars($keyword) ?>"</h2>
            <?php if ($result && $result->num_rows > 0): ?>
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
                                    <p>ราคา: <?= htmlspecialchars($row["price"]) ?> บาท</p>
                                    <a href="hotel_detail.php?id=<?= $row["id"] ?>" class="btn-details">ดูรายละเอียด</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                    <button class="scroll-btn right">⟩</button>
                </div>
            <?php else: ?>
                <p>ไม่พบโรงแรมที่คุณค้นหา</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php
    showHotelsByCategory($conn, "โรงแรมราคาประหยัดในอำเภอเมือง ปัตตานี", "price < 499");
    showHotelsByCategory($conn, "โรงแรมที่แนะนำอำเภอเมือง ปัตตานี", "price >= 500 AND price < 999");
    showHotelsByCategory($conn, "โรงแรมหรูในอำเภอเมือง ปัตตานี", "price >= 1000");
    ?>
<?php endif; ?>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js"></script>
<script>
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
