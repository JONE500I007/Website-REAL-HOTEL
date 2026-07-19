<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

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

$hotelAmenities = get_amenities_for_hotels($conn, [$hotel_id])[$hotel_id] ?? [];

$hasPin = !empty($hotel["latitude"]) && !empty($hotel["longitude"]);

$current_user_id = $_SESSION["user_id"] ?? null;
$isAdmin = ($_SESSION["role"] ?? '') === 'admin';
$reviewError = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["review_action"])) {
    if (!$current_user_id) {
        header("Location: login.php?error=login_required");
        exit;
    }

    $action = $_POST["review_action"];

    if ($action === "add_review") {
        $rating  = (int) ($_POST["rating"] ?? 0);
        $comment = trim($_POST["comment"] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = "กรุณาให้คะแนนระหว่าง 1-5 ดาว";
        } elseif ($comment === '') {
            $reviewError = "กรุณาเขียนความคิดเห็น";
        } else {
            $check = $conn->prepare("SELECT id FROM reviews WHERE hotel_id = ? AND user_id = ? AND parent_id IS NULL");
            $check->bind_param("ii", $hotel_id, $current_user_id);
            $check->execute();
            $alreadyReviewed = $check->get_result()->num_rows > 0;
            $check->close();

            if ($alreadyReviewed) {
                $reviewError = "คุณได้รีวิวโรงแรมนี้ไปแล้ว";
            } else {
                $ins = $conn->prepare("INSERT INTO reviews (hotel_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
                $ins->bind_param("iiis", $hotel_id, $current_user_id, $rating, $comment);
                $ins->execute();
                $ins->close();
                header("Location: hotel_detail.php?id=$hotel_id#reviews");
                exit;
            }
        }
    }

    if ($action === "edit_review") {
        $review_id = (int) ($_POST["review_id"] ?? 0);
        $comment   = trim($_POST["comment"] ?? '');
        $hasRating = isset($_POST["rating"]) && $_POST["rating"] !== '';
        $rating    = $hasRating ? (int) $_POST["rating"] : null;

        if ($comment === '') {
            $reviewError = "กรุณาเขียนความคิดเห็น";
        } elseif ($hasRating && ($rating < 1 || $rating > 5)) {
            $reviewError = "กรุณาให้คะแนนระหว่าง 1-5 ดาว";
        } else {
            if ($hasRating) {
                $upd = $conn->prepare("UPDATE reviews SET comment = ?, rating = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $upd->bind_param("siii", $comment, $rating, $review_id, $current_user_id);
            } else {
                $upd = $conn->prepare("UPDATE reviews SET comment = ?, updated_at = NOW() WHERE id = ? AND user_id = ?");
                $upd->bind_param("sii", $comment, $review_id, $current_user_id);
            }
            $upd->execute();
            $upd->close();
            header("Location: hotel_detail.php?id=$hotel_id#review-$review_id");
            exit;
        }
    }

    if ($action === "delete_review") {
        $review_id = (int) ($_POST["review_id"] ?? 0);

        if ($isAdmin) {
            $del = $conn->prepare("DELETE FROM reviews WHERE id = ?");
            $del->bind_param("i", $review_id);
        } else {
            $del = $conn->prepare("DELETE FROM reviews WHERE id = ? AND user_id = ?");
            $del->bind_param("ii", $review_id, $current_user_id);
        }
        $del->execute();
        if ($del->affected_rows > 0) {
            // A deleted top-level review takes its replies with it. Replies never
            // have children of their own (single-level replies only), so this is safe.
            $delReplies = $conn->prepare("DELETE FROM reviews WHERE parent_id = ?");
            $delReplies->bind_param("i", $review_id);
            $delReplies->execute();
            $delReplies->close();
        }
        $del->close();
        header("Location: hotel_detail.php?id=$hotel_id#reviews");
        exit;
    }

    if ($action === "add_reply") {
        $parent_id = (int) ($_POST["parent_id"] ?? 0);
        $comment   = trim($_POST["comment"] ?? '');

        $checkParent = $conn->prepare("SELECT id FROM reviews WHERE id = ? AND hotel_id = ? AND parent_id IS NULL");
        $checkParent->bind_param("ii", $parent_id, $hotel_id);
        $checkParent->execute();
        $parentExists = $checkParent->get_result()->num_rows > 0;
        $checkParent->close();

        if (!$parentExists) {
            $reviewError = "ไม่พบรีวิวที่ต้องการตอบกลับ";
        } elseif ($comment === '') {
            $reviewError = "กรุณาเขียนข้อความตอบกลับ";
        } else {
            $ins = $conn->prepare("INSERT INTO reviews (hotel_id, user_id, parent_id, comment) VALUES (?, ?, ?, ?)");
            $ins->bind_param("iiis", $hotel_id, $current_user_id, $parent_id, $comment);
            $ins->execute();
            $ins->close();
            header("Location: hotel_detail.php?id=$hotel_id#review-$parent_id");
            exit;
        }
    }
}

$avg_stmt = $conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS review_count FROM reviews WHERE hotel_id = ? AND parent_id IS NULL");
$avg_stmt->bind_param("i", $hotel_id);
$avg_stmt->execute();
$ratingSummary = $avg_stmt->get_result()->fetch_assoc();
$avg_stmt->close();
$avgRating   = $ratingSummary['avg_rating'] !== null ? round((float) $ratingSummary['avg_rating'], 1) : 0.0;
$reviewCount = (int) $ratingSummary['review_count'];

$top_stmt = $conn->prepare("
    SELECT r.*, u.full_name, u.profile_picture
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.hotel_id = ? AND r.parent_id IS NULL
    ORDER BY r.created_at DESC
");
$top_stmt->bind_param("i", $hotel_id);
$top_stmt->execute();
$topReviews = $top_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$top_stmt->close();

$reply_stmt = $conn->prepare("
    SELECT r.*, u.full_name, u.profile_picture
    FROM reviews r
    JOIN users u ON u.id = r.user_id
    WHERE r.hotel_id = ? AND r.parent_id IS NOT NULL
    ORDER BY r.created_at ASC
");
$reply_stmt->bind_param("i", $hotel_id);
$reply_stmt->execute();
$repliesByParent = [];
foreach ($reply_stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $reply) {
    $repliesByParent[$reply['parent_id']][] = $reply;
}
$reply_stmt->close();

$myReviewId = null;
if ($current_user_id) {
    foreach ($topReviews as $r) {
        if ((int) $r['user_id'] === (int) $current_user_id) {
            $myReviewId = (int) $r['id'];
            break;
        }
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
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
            <a href="#reviews" class="rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <span class="star<?= $i <= round($avgRating) ? ' star-filled' : '' ?>">★</span>
                <?php endfor; ?>
                <span class="star-rating"><?= $reviewCount > 0 ? $avgRating : 'ยังไม่มีรีวิว' ?></span>
                <?php if ($reviewCount > 0): ?>
                    <span class="review-count">(<?= $reviewCount ?> รีวิว)</span>
                <?php endif; ?>
            </a>
        </div>

        <div class="hotel-address">
            <p><?= htmlspecialchars($hotel["location"]) ?></p>
        </div>

        <?php if (!empty($hotelAmenities)): ?>
            <div class="hotel-detail-tags">
                <?php foreach ($hotelAmenities as $amenity): ?>
                    <span class="hotel-card-tag">
                        <span class="material-symbols-outlined"><?= htmlspecialchars($amenity['icon']) ?></span>
                        <?= htmlspecialchars($amenity['title']) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="hotel-gallery" data-images='<?= htmlspecialchars(json_encode($galleryImages), ENT_QUOTES) ?>'>
            <div class="main-image room-thumb-clickable" data-index="0" onclick="openImageGallery(this)">
                <img src="<?= htmlspecialchars($primary_image) ?>" alt="Hotel main image">
                <?php if (count($galleryImages) > 1): ?>
                    <span class="gallery-view-all-btn"><span class="material-symbols-outlined">photo_library</span> ดูรูปทั้งหมด</span>
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
                                <span class="room-image-count"><span class="material-symbols-outlined">photo_library</span> <?= count($room['images']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <h4><?= htmlspecialchars($room['room_name']) ?></h4>
                            <span class="capacity-badge"><span class="material-symbols-outlined">person</span> x <?= (int) $room['capacity'] ?></span>
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

        <div class="detail-box" id="reviews" style="margin-top:20px;">
            <h3><span class="material-symbols-outlined">rate_review</span> รีวิวจากผู้เข้าพัก (<?= $reviewCount ?>)</h3>

            <?php if (!empty($reviewError)): ?>
                <div class="alert alert-danger"><span class="material-symbols-outlined">warning</span> <?= htmlspecialchars($reviewError) ?></div>
            <?php endif; ?>

            <?php if (!$current_user_id): ?>
                <p class="review-login-hint">
                    <a href="login.php">เข้าสู่ระบบ</a> เพื่อเขียนรีวิวโรงแรมนี้
                </p>
            <?php elseif ($myReviewId === null): ?>
                <form method="post" class="review-form">
                    <input type="hidden" name="review_action" value="add_review">
                    <div class="star-picker">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="rate<?= $i ?>" value="<?= $i ?>" required>
                            <label for="rate<?= $i ?>">★</label>
                        <?php endfor; ?>
                    </div>
                    <textarea name="comment" placeholder="เล่าประสบการณ์การเข้าพักของคุณ..." rows="3" required></textarea>
                    <button type="submit" class="auth-btn" style="width:auto; padding:10px 26px;">
                        <span class="material-symbols-outlined">send</span> โพสต์รีวิว
                    </button>
                </form>
            <?php endif; ?>

            <div class="review-list">
                <?php foreach ($topReviews as $review): ?>
                    <?php $isMine = $current_user_id && (int) $review['user_id'] === (int) $current_user_id; ?>
                    <div class="review-card" id="review-<?= (int) $review['id'] ?>">
                        <div class="review-view" id="reviewView-<?= (int) $review['id'] ?>">
                            <div class="review-top">
                                <img class="review-avatar" src="<?= htmlspecialchars(resolve_upload_src($review['profile_picture'])) ?>" alt="">
                                <div>
                                    <div class="review-author"><?= htmlspecialchars($review['full_name']) ?></div>
                                    <div class="review-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star<?= $i <= (int) $review['rating'] ? ' star-filled' : '' ?>">★</span>
                                        <?php endfor; ?>
                                        <span class="review-date"><?= (new DateTime($review['created_at']))->format('d M Y') ?><?= $review['updated_at'] ? ' (แก้ไขแล้ว)' : '' ?></span>
                                    </div>
                                </div>
                            </div>
                            <p class="review-comment"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                            <div class="review-actions">
                                <?php if ($current_user_id): ?>
                                    <button type="button" class="review-link-btn" onclick="toggleReply(<?= (int) $review['id'] ?>)">
                                        <span class="material-symbols-outlined">reply</span> ตอบกลับ
                                    </button>
                                <?php endif; ?>
                                <?php if ($isMine): ?>
                                    <button type="button" class="review-link-btn" onclick="toggleEdit(<?= (int) $review['id'] ?>)">
                                        <span class="material-symbols-outlined">edit</span> แก้ไข
                                    </button>
                                <?php endif; ?>
                                <?php if ($isMine || $isAdmin): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="review_action" value="delete_review">
                                        <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                                        <button type="submit" class="review-link-btn review-link-danger"
                                                onclick="return confirm('ลบรีวิวนี้ใช่หรือไม่? คำตอบกลับทั้งหมดจะถูกลบไปด้วย');">
                                            <span class="material-symbols-outlined">delete</span> ลบ<?= (!$isMine && $isAdmin) ? ' (Admin)' : '' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($isMine): ?>
                        <form method="post" class="review-form review-edit-form" id="reviewEdit-<?= (int) $review['id'] ?>" style="display:none">
                            <input type="hidden" name="review_action" value="edit_review">
                            <input type="hidden" name="review_id" value="<?= (int) $review['id'] ?>">
                            <div class="star-picker">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="rating" id="editRate<?= (int) $review['id'] . '_' . $i ?>" value="<?= $i ?>" <?= $i === (int) $review['rating'] ? 'checked' : '' ?> required>
                                    <label for="editRate<?= (int) $review['id'] . '_' . $i ?>">★</label>
                                <?php endfor; ?>
                            </div>
                            <textarea name="comment" rows="3" required><?= htmlspecialchars($review['comment']) ?></textarea>
                            <div style="display:flex; gap:8px;">
                                <button type="submit" class="auth-btn" style="width:auto; padding:8px 20px;">บันทึก</button>
                                <button type="button" class="review-link-btn" onclick="toggleEdit(<?= (int) $review['id'] ?>)">ยกเลิก</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php if ($current_user_id): ?>
                        <form method="post" class="review-form review-reply-form" id="replyForm-<?= (int) $review['id'] ?>" style="display:none">
                            <input type="hidden" name="review_action" value="add_reply">
                            <input type="hidden" name="parent_id" value="<?= (int) $review['id'] ?>">
                            <textarea name="comment" placeholder="เขียนคำตอบกลับ..." rows="2" required></textarea>
                            <div style="display:flex; gap:8px;">
                                <button type="submit" class="auth-btn" style="width:auto; padding:8px 20px;">ส่งคำตอบกลับ</button>
                                <button type="button" class="review-link-btn" onclick="toggleReply(<?= (int) $review['id'] ?>)">ยกเลิก</button>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php if (!empty($repliesByParent[$review['id']])): ?>
                        <div class="review-replies">
                            <?php foreach ($repliesByParent[$review['id']] as $reply): ?>
                                <?php $replyIsMine = $current_user_id && (int) $reply['user_id'] === (int) $current_user_id; ?>
                                <div class="review-reply" id="review-<?= (int) $reply['id'] ?>">
                                    <div class="review-view" id="reviewView-<?= (int) $reply['id'] ?>">
                                        <div class="review-top">
                                            <img class="review-avatar review-avatar-sm" src="<?= htmlspecialchars(resolve_upload_src($reply['profile_picture'])) ?>" alt="">
                                            <div>
                                                <div class="review-author"><?= htmlspecialchars($reply['full_name']) ?></div>
                                                <span class="review-date"><?= (new DateTime($reply['created_at']))->format('d M Y') ?><?= $reply['updated_at'] ? ' (แก้ไขแล้ว)' : '' ?></span>
                                            </div>
                                        </div>
                                        <p class="review-comment"><?= nl2br(htmlspecialchars($reply['comment'])) ?></p>
                                        <?php if ($replyIsMine || $isAdmin): ?>
                                        <div class="review-actions">
                                            <?php if ($replyIsMine): ?>
                                            <button type="button" class="review-link-btn" onclick="toggleEdit(<?= (int) $reply['id'] ?>)">
                                                <span class="material-symbols-outlined">edit</span> แก้ไข
                                            </button>
                                            <?php endif; ?>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="review_action" value="delete_review">
                                                <input type="hidden" name="review_id" value="<?= (int) $reply['id'] ?>">
                                                <button type="submit" class="review-link-btn review-link-danger"
                                                        onclick="return confirm('ลบคำตอบกลับนี้ใช่หรือไม่?');">
                                                    <span class="material-symbols-outlined">delete</span> ลบ<?= (!$replyIsMine && $isAdmin) ? ' (Admin)' : '' ?>
                                                </button>
                                            </form>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($replyIsMine): ?>
                                    <form method="post" class="review-form review-edit-form" id="reviewEdit-<?= (int) $reply['id'] ?>" style="display:none">
                                        <input type="hidden" name="review_action" value="edit_review">
                                        <input type="hidden" name="review_id" value="<?= (int) $reply['id'] ?>">
                                        <textarea name="comment" rows="2" required><?= htmlspecialchars($reply['comment']) ?></textarea>
                                        <div style="display:flex; gap:8px;">
                                            <button type="submit" class="auth-btn" style="width:auto; padding:8px 20px;">บันทึก</button>
                                            <button type="button" class="review-link-btn" onclick="toggleEdit(<?= (int) $reply['id'] ?>)">ยกเลิก</button>
                                        </div>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($topReviews)): ?>
                    <p class="review-empty">ยังไม่มีรีวิวสำหรับโรงแรมนี้ เป็นคนแรกที่รีวิวเลย!</p>
                <?php endif; ?>
            </div>
        </div>

        <a href="hotel.php" class="back-link"><span class="material-symbols-outlined">arrow_back</span> กลับหน้าโรงแรม</a>
    </div>
</div>

<!-- Room image gallery lightbox -->
<div id="roomGalleryModal" class="gallery-lightbox" style="display:none">
    <button type="button" class="gallery-close" id="galleryClose"><span class="material-symbols-outlined">close</span></button>
    <button type="button" class="gallery-nav gallery-prev" id="galleryPrev"><span class="material-symbols-outlined">chevron_left</span></button>
    <img id="galleryImage" src="" alt="room photo">
    <button type="button" class="gallery-nav gallery-next" id="galleryNext"><span class="material-symbols-outlined">chevron_right</span></button>
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

function toggleEdit(reviewId) {
    var view = document.getElementById('reviewView-' + reviewId);
    var form = document.getElementById('reviewEdit-' + reviewId);
    var open = form.style.display !== 'none';
    form.style.display = open ? 'none' : 'block';
    view.style.display = open ? 'block' : 'none';
}

function toggleReply(reviewId) {
    var form = document.getElementById('replyForm-' + reviewId);
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
    if (form.style.display === 'block') {
        form.querySelector('textarea').focus();
    }
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
