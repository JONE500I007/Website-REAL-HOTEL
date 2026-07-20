<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

$keyword = trim($_GET['keyword'] ?? '');

// Bounds for the price slider. The floor always starts at 0 (typing/
// dragging below the cheapest current hotel should feel unrestricted, not
// clamped) and the ceiling pads 20% past the priciest hotel, rounded up to
// a clean number, with a sane minimum so the slider still has real room to
// drag even when there's barely any hotel data yet.
$priceMaxRow = $conn->query("SELECT MAX(CAST(price AS DECIMAL(10,2))) AS max_p FROM hotels")->fetch_assoc();
$priceFloor = 0;
$priceCeil  = max(5000, (int) (ceil((($priceMaxRow['max_p'] ?? 0) * 1.2) / 500) * 500));

$minPrice = ($_GET['min_price'] ?? '') !== '' ? (float) $_GET['min_price'] : null;
$maxPrice = ($_GET['max_price'] ?? '') !== '' ? (float) $_GET['max_price'] : null;

$selectedAmenityIds = array_values(array_unique(array_filter(array_map('intval', $_GET['amenities'] ?? []))));

$hasFilters = $keyword !== '' || $minPrice !== null || $maxPrice !== null || !empty($selectedAmenityIds);

$amenities = $conn->query("
    SELECT a.id, a.title, a.icon, COUNT(ha.hotel_id) AS hotel_count
    FROM amenities a
    LEFT JOIN hotel_amenities ha ON ha.amenity_id = a.id
    GROUP BY a.id, a.title, a.icon, a.display_order
    ORDER BY a.display_order ASC
")->fetch_all(MYSQLI_ASSOC);

if ($hasFilters) {
    $conditions = [];
    $params = [];
    $types = '';

    if ($keyword !== '') {
        $likeKeyword = "%$keyword%";
        $conditions[] = "(hotels.hotel_name LIKE ? OR hotels.location LIKE ?)";
        $params[] = $likeKeyword;
        $params[] = $likeKeyword;
        $types .= 'ss';
    }
    if ($minPrice !== null) {
        $conditions[] = "CAST(hotels.price AS DECIMAL(10,2)) >= ?";
        $params[] = $minPrice;
        $types .= 'd';
    }
    if ($maxPrice !== null) {
        $conditions[] = "CAST(hotels.price AS DECIMAL(10,2)) <= ?";
        $params[] = $maxPrice;
        $types .= 'd';
    }
    if (!empty($selectedAmenityIds)) {
        // Require the hotel to have ALL selected amenities, not just any one of them.
        $placeholders = implode(',', array_fill(0, count($selectedAmenityIds), '?'));
        $conditions[] = "hotels.id IN (
            SELECT hotel_id FROM hotel_amenities
            WHERE amenity_id IN ($placeholders)
            GROUP BY hotel_id
            HAVING COUNT(DISTINCT amenity_id) = ?
        )";
        foreach ($selectedAmenityIds as $amenityId) {
            $params[] = $amenityId;
            $types .= 'i';
        }
        $params[] = count($selectedAmenityIds);
        $types .= 'i';
    }

    $sql = "
        SELECT hotels.*, hotel_images.image_path
        FROM hotels
        LEFT JOIN (
            SELECT MIN(id) as id, hotel_id
            FROM hotel_images
            GROUP BY hotel_id
        ) AS first_images ON first_images.hotel_id = hotels.id
        LEFT JOIN hotel_images ON hotel_images.id = first_images.id
        " . (!empty($conditions) ? "WHERE " . implode(' AND ', $conditions) : "") . "
        ORDER BY hotels.id DESC
    ";
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $filteredHotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $amenitiesByHotel = get_amenities_for_hotels($conn, array_column($filteredHotels, 'id'));
} else {
    $categories = $conn->query("SELECT * FROM hotel_categories WHERE show_on IN ('both', 'hotel') ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);
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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="hotel-page-layout">
    <aside class="hotel-filter-sidebar">
        <form method="get" action="hotel.php" class="filter-form">
            <h3 class="filter-form-title"><span class="material-symbols-outlined">tune</span> จำกัดการค้นหาด้วย:</h3>

            <div class="filter-group">
                <label class="filter-group-label">ค้นหาชื่อโรงแรม/สถานที่</label>
                <input type="text" name="keyword" class="filter-text-input" value="<?= htmlspecialchars($keyword) ?>" placeholder="ชื่อโรงแรมหรือสถานที่ใกล้เคียง">
            </div>

            <div class="filter-group">
                <label class="filter-group-label">งบประมาณของท่าน (ต่อคืน)</label>
                <div class="price-input-row">
                    <div class="price-field-wrap">
                        <span class="price-field-label">เริ่มต้น</span>
                        <input type="number" name="min_price" id="minPriceField" class="price-field" value="<?= (int) ($minPrice ?? $priceFloor) ?>">
                    </div>
                    <span class="price-input-dash">—</span>
                    <div class="price-field-wrap">
                        <span class="price-field-label">สูงสุด</span>
                        <input type="number" name="max_price" id="maxPriceField" class="price-field" value="<?= (int) ($maxPrice ?? $priceCeil) ?>">
                    </div>
                </div>
                <div class="dual-range">
                    <div class="dual-range-track"></div>
                    <div class="dual-range-fill" id="rangeFill"></div>
                    <input type="range" id="minPriceRange" min="<?= $priceFloor ?>" max="<?= $priceCeil ?>" step="50" value="<?= (int) ($minPrice ?? $priceFloor) ?>">
                    <input type="range" id="maxPriceRange" min="<?= $priceFloor ?>" max="<?= $priceCeil ?>" step="50" value="<?= (int) ($maxPrice ?? $priceCeil) ?>">
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-group-label">เกณฑ์ค้นหายอดนิยม</label>
                <?php foreach ($amenities as $amenity): ?>
                    <label class="filter-checkbox">
                        <input type="checkbox" name="amenities[]" value="<?= (int) $amenity['id'] ?>" <?= in_array((int) $amenity['id'], $selectedAmenityIds, true) ? 'checked' : '' ?>>
                        <span class="material-symbols-outlined filter-checkbox-icon"><?= htmlspecialchars($amenity['icon']) ?></span>
                        <span class="filter-checkbox-label"><?= htmlspecialchars($amenity['title']) ?></span>
                        <span class="filter-count"><?= (int) $amenity['hotel_count'] ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="filter-submit-btn"><span class="material-symbols-outlined">search</span> ค้นหา</button>
            <?php if ($hasFilters): ?>
                <a href="hotel.php" class="filter-clear-link">ล้างตัวกรองทั้งหมด</a>
            <?php endif; ?>
        </form>
    </aside>

    <div class="hotel-page-content">
        <?php if ($hasFilters): ?>
            <h2 class="section-title filter-results-title">
                <?= $keyword !== '' ? 'ผลการค้นหาโรงแรม: "' . htmlspecialchars($keyword) . '"' : 'ผลการค้นหาโรงแรม' ?>
                <span class="filter-result-count">(<?= count($filteredHotels) ?> แห่ง)</span>
            </h2>
            <?php if (!empty($filteredHotels)): ?>
                <div class="hotel-filter-results-grid">
                    <?php foreach ($filteredHotels as $row): ?>
                        <?php render_hotel_card($row, $amenitiesByHotel[(int) $row['id']] ?? []); ?>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>ไม่พบโรงแรมที่ตรงกับตัวกรองที่เลือก</p>
            <?php endif; ?>
        <?php else: ?>
            <?php foreach ($categories as $category): ?>
                <?php render_hotel_category($conn, (int) $category['id'], $category['title'], (bool) $category['is_auto']); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    var minRange = document.getElementById("minPriceRange");
    var maxRange = document.getElementById("maxPriceRange");
    if (!minRange || !maxRange) return;

    var minField = document.getElementById("minPriceField");
    var maxField = document.getElementById("maxPriceField");
    var rangeFill = document.getElementById("rangeFill");
    var floor = parseInt(minRange.min, 10);
    var sliderMax = parseInt(minRange.max, 10);

    function clamp(val, lo, hi) {
        return Math.min(Math.max(val, lo), hi);
    }

    function paintFill(minVal, maxVal) {
        var span = sliderMax - floor || 1;
        var pctMin = ((minVal - floor) / span) * 100;
        var pctMax = ((maxVal - floor) / span) * 100;
        rangeFill.style.left = pctMin + "%";
        rangeFill.style.width = (pctMax - pctMin) + "%";

        // The two range inputs sit stacked exactly on top of each other, so
        // whichever has the lower z-index becomes unclickable once the
        // handles get close together — a click meant for it lands on the
        // other input instead (this was the "grabbing one moves the other"
        // bug). Keep whichever handle is in the right half on top, since
        // that's the one most likely being actively dragged when they meet.
        if (pctMin > 50) {
            minRange.style.zIndex = 2;
            maxRange.style.zIndex = 1;
        } else {
            minRange.style.zIndex = 1;
            maxRange.style.zIndex = 2;
        }
    }

    // Dragging a slider handle updates the matching number field. The
    // handles physically can't cross past each other on an <input
    // type="range">, so keeping them from swapping here is just mirroring
    // that same physical constraint into the paired number field.
    function fromRange(movedMin) {
        var minVal = parseInt(minRange.value, 10);
        var maxVal = parseInt(maxRange.value, 10);
        if (minVal > maxVal) {
            if (movedMin) {
                maxVal = minVal;
                maxRange.value = maxVal;
            } else {
                minVal = maxVal;
                minRange.value = minVal;
            }
        }
        minField.value = minVal;
        maxField.value = maxVal;
        paintFill(minVal, maxVal);
    }

    // Typing in a number field is intentionally unrestricted — no min/max
    // forced here, and no auto-swap if min ends up above max, since the
    // whole point is letting the user freely poke at numbers without the
    // UI fighting them. The slider handles (which do have hard bounds)
    // just clamp for their own visual position; the submitted field value
    // is always exactly what was typed.
    function fromField() {
        var minVal = parseInt(minField.value, 10);
        var maxVal = parseInt(maxField.value, 10);
        if (isNaN(minVal)) minVal = floor;
        if (isNaN(maxVal)) maxVal = sliderMax;

        var minClamped = clamp(minVal, floor, sliderMax);
        var maxClamped = clamp(maxVal, floor, sliderMax);
        minRange.value = minClamped;
        maxRange.value = maxClamped;
        paintFill(Math.min(minClamped, maxClamped), Math.max(minClamped, maxClamped));
    }

    minRange.addEventListener("input", function () { fromRange(true); });
    maxRange.addEventListener("input", function () { fromRange(false); });
    minField.addEventListener("input", fromField);
    maxField.addEventListener("input", fromField);

    paintFill(parseInt(minRange.value, 10), parseInt(maxRange.value, 10));
});
</script>

<?php require_once "includes/footer.php"; ?>

<script src="assets/js/navbar.js?v=<?= filemtime(__DIR__ . '/assets/js/navbar.js') ?>"></script>
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
