<?php
session_start();
require_once "config/database.php";
require_once "includes/functions.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

if (!in_array($_SESSION["role"], ["owner", "admin"])) {
    echo "<div style='color:red; font-weight:bold;'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

$owner_id = $_SESSION["user_id"];

// An owner can run more than one property, so this page works on ONE hotel
// at a time — picked by ?hotel_id= (or the hidden field a form posts back),
// defaulting to their first. ?hotel_id=new opens a blank "add hotel" form.
$stmt = $conn->prepare("SELECT * FROM hotels WHERE owner_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$ownerHotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$blankHotel = [
    "hotel_name" => "", "location" => "", "province" => "", "latitude" => null, "longitude" => null,
    "price" => "", "description" => "", "facilities" => "", "surrounding" => "", "type" => ""
];

$requestedHotelId = $_POST["hotel_id"] ?? $_GET["hotel_id"] ?? null;
$addingNewHotel   = $requestedHotelId === 'new';

$hotel = $blankHotel;
if (!$addingNewHotel) {
    $requestedHotelId = (int) $requestedHotelId;
    foreach ($ownerHotels as $ownerHotel) {
        // Falling back to the first hotel keeps a stale/foreign ?hotel_id=
        // from silently editing someone else's property.
        if ($requestedHotelId === 0 || (int) $ownerHotel['id'] === $requestedHotelId) {
            $hotel = $ownerHotel;
            break;
        }
    }
    if (empty($hotel['id']) && !empty($ownerHotels)) {
        $hotel = $ownerHotels[0];
    }
}

$hasHotel = !empty($hotel['id']);
// Every redirect and form on this page carries the active hotel, otherwise
// saving a room would bounce the owner back to their first hotel.
$hotelQuery = $hasHotel ? "?hotel_id=" . (int) $hotel['id'] : "";

$allAmenities    = $conn->query("SELECT id, title, icon FROM amenities ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);
$validAmenityIds = array_map('intval', array_column($allAmenities, 'id'));

// ---- Delete hotel ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_hotel_id"])) {
    $delete_id = (int) $_POST["delete_hotel_id"];

    $check = $conn->prepare("SELECT id FROM hotels WHERE id = ? AND owner_id = ?");
    $check->bind_param("ii", $delete_id, $owner_id);
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        $del = $conn->prepare("DELETE FROM hotels WHERE id = ? AND owner_id = ?");
        $del->bind_param("ii", $delete_id, $owner_id);
        $del->execute();
        $del->close();

        $delImg = $conn->prepare("DELETE FROM hotel_images WHERE hotel_id = ?");
        $delImg->bind_param("i", $delete_id);
        $delImg->execute();
        $delImg->close();

        echo "<script>alert('ลบโรงแรมเรียบร้อยแล้ว'); window.location.href='manage_hotels.php';</script>";
        exit;
    } else {
        echo "<script>alert('ไม่พบข้อมูลโรงแรมนี้ หรือคุณไม่มีสิทธิ์ลบ');</script>";
    }
    $check->close();
}

// ---- Save hotel (insert or update) ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["hotel_name"])) {
    $hotel_name  = trim($_POST["hotel_name"] ?? '');
    $location    = trim($_POST["location"] ?? '');
    // Only accept a province that's actually on the canonical list, so a
    // tampered form can't write a value the filter dropdown can never match.
    $province    = in_array($_POST["province"] ?? '', thai_provinces(), true) ? $_POST["province"] : '';
    $latitude    = ($_POST["latitude"] ?? '') !== '' ? (float) $_POST["latitude"] : null;
    $longitude   = ($_POST["longitude"] ?? '') !== '' ? (float) $_POST["longitude"] : null;
    $price       = trim($_POST["price"] ?? '');
    $description = trim($_POST["description"] ?? '');
    $facilities  = trim($_POST["facilities"] ?? '');
    $surrounding = trim($_POST["surrounding"] ?? '');
    $type        = trim($_POST["type"] ?? '');

    if (!empty($hotel_name) && !empty($location) && !empty($price)) {
        if ($hasHotel) {
            // Scoped by id AND owner_id: id alone would let a tampered form
            // edit another owner's hotel, owner_id alone would rewrite every
            // hotel this owner has.
            $stmt = $conn->prepare("UPDATE hotels SET hotel_name=?, location=?, province=?, latitude=?, longitude=?, price=?, description=?, facilities=?, surrounding=?, type=? WHERE id=? AND owner_id=?");
            $stmt->bind_param("sssddsssssii", $hotel_name, $location, $province, $latitude, $longitude, $price, $description, $facilities, $surrounding, $type, $hotel["id"], $owner_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO hotels (hotel_name, location, province, latitude, longitude, price, description, facilities, surrounding, type, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssddsssssi", $hotel_name, $location, $province, $latitude, $longitude, $price, $description, $facilities, $surrounding, $type, $owner_id);
        }

        if ($stmt->execute()) {
            $hotel_id = $hasHotel ? $hotel["id"] : $conn->insert_id;

            $selectedAmenityIds = array_values(array_intersect(array_map('intval', $_POST["amenities"] ?? []), $validAmenityIds));
            $delAmenities = $conn->prepare("DELETE FROM hotel_amenities WHERE hotel_id = ?");
            $delAmenities->bind_param("i", $hotel_id);
            $delAmenities->execute();
            $delAmenities->close();
            if (!empty($selectedAmenityIds)) {
                $insAmenity = $conn->prepare("INSERT INTO hotel_amenities (hotel_id, amenity_id) VALUES (?, ?)");
                foreach ($selectedAmenityIds as $amenityId) {
                    $insAmenity->bind_param("ii", $hotel_id, $amenityId);
                    $insAmenity->execute();
                }
                $insAmenity->close();
            }

            if (!empty($_POST["cropped_hotel_images_json"])) {
                $croppedImages = json_decode($_POST["cropped_hotel_images_json"], true) ?: [];
                $destDir = __DIR__ . "/uploads/hotels/";
                $relDir  = "uploads/hotels/";

                foreach ($croppedImages as $dataUrl) {
                    $filename = save_cropped_image($dataUrl, $destDir, "hotel");
                    if ($filename !== false) {
                        $rel = $relDir . $filename;
                        $img = $conn->prepare("INSERT INTO hotel_images (hotel_id, image_path) VALUES (?, ?)");
                        $img->bind_param("is", $hotel_id, $rel);
                        $img->execute();
                        $img->close();
                    }
                }
            }

            header("Location: manage_hotels.php?hotel_id=" . (int) $hotel_id . "&success=1");
            exit;
        } else {
            $saveError = "เกิดข้อผิดพลาด: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $saveError = "กรุณากรอกข้อมูลให้ครบ (ชื่อโรงแรม, ที่ตั้ง, ราคา)";
    }
}

// ---- Save room type (insert or update) ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["room_name"]) && $hasHotel) {
    $room_type_id   = (int) ($_POST["room_type_id"] ?? 0);
    $room_name      = trim($_POST["room_name"] ?? '');
    $capacity       = (int) ($_POST["capacity"] ?? 0);
    $price_per_night = trim($_POST["price_per_night"] ?? '');
    $quantity       = (int) ($_POST["quantity"] ?? 0);
    $room_description = trim($_POST["room_description"] ?? '');
    $amenities      = trim($_POST["amenities"] ?? '');

    if (!empty($room_name) && $capacity > 0 && $price_per_night !== '' && $quantity > 0) {
        if ($room_type_id > 0) {
            // Verify this room type actually belongs to this owner's hotel
            $own = $conn->prepare("SELECT rt.id FROM room_types rt JOIN hotels h ON h.id = rt.hotel_id WHERE rt.id = ? AND h.owner_id = ?");
            $own->bind_param("ii", $room_type_id, $owner_id);
            $own->execute();
            $owns = $own->get_result()->num_rows > 0;
            $own->close();

            if ($owns) {
                $stmt = $conn->prepare("UPDATE room_types SET room_name=?, capacity=?, price_per_night=?, quantity=?, description=?, amenities=? WHERE id=?");
                $stmt->bind_param("sidsssi", $room_name, $capacity, $price_per_night, $quantity, $room_description, $amenities, $room_type_id);
                $stmt->execute();
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("INSERT INTO room_types (hotel_id, room_name, capacity, price_per_night, quantity, description, amenities) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isidsss", $hotel["id"], $room_name, $capacity, $price_per_night, $quantity, $room_description, $amenities);
            $stmt->execute();
            $room_type_id = $conn->insert_id;
            $stmt->close();
        }

        if ($room_type_id > 0 && !empty($_POST["cropped_images_json"])) {
            $croppedImages = json_decode($_POST["cropped_images_json"], true) ?: [];
            $destDir = __DIR__ . "/uploads/rooms/";
            $relDir  = "uploads/rooms/";

            foreach ($croppedImages as $dataUrl) {
                $filename = save_cropped_image($dataUrl, $destDir, "room");
                if ($filename !== false) {
                    $rel = $relDir . $filename;
                    $img = $conn->prepare("INSERT INTO room_images (room_type_id, image_path) VALUES (?, ?)");
                    $img->bind_param("is", $room_type_id, $rel);
                    $img->execute();
                    $img->close();
                }
            }
        }

        header("Location: manage_hotels.php{$hotelQuery}" . ($hotelQuery === '' ? '?' : '&') . "room_success=1");
        exit;
    } else {
        $roomError = "กรุณากรอกข้อมูลห้องพักให้ครบ (ชื่อห้อง, จำนวนผู้เข้าพัก, ราคา, จำนวนห้อง)";
    }
}

// ---- Delete room type ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_room_id"])) {
    $del_room_id = (int) $_POST["delete_room_id"];

    $own = $conn->prepare("SELECT rt.id FROM room_types rt JOIN hotels h ON h.id = rt.hotel_id WHERE rt.id = ? AND h.owner_id = ?");
    $own->bind_param("ii", $del_room_id, $owner_id);
    $own->execute();
    $owns = $own->get_result()->num_rows > 0;
    $own->close();

    if ($owns) {
        $imgs = $conn->prepare("SELECT image_path FROM room_images WHERE room_type_id = ?");
        $imgs->bind_param("i", $del_room_id);
        $imgs->execute();
        $imgRows = $imgs->get_result()->fetch_all(MYSQLI_ASSOC);
        $imgs->close();

        foreach ($imgRows as $row) {
            $path = __DIR__ . "/" . $row['image_path'];
            if (is_file($path)) {
                unlink($path);
            }
        }

        $del = $conn->prepare("DELETE FROM room_types WHERE id = ?");
        $del->bind_param("i", $del_room_id);
        $del->execute();
        $del->close();
    }

    header("Location: manage_hotels.php{$hotelQuery}");
    exit;
}

// ---- Delete a single room image ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_room_image_id"])) {
    $del_image_id = (int) $_POST["delete_room_image_id"];
    $edit_room_after = (int) ($_POST["edit_room_after"] ?? 0);

    $own = $conn->prepare("
        SELECT ri.id, ri.image_path
        FROM room_images ri
        JOIN room_types rt ON rt.id = ri.room_type_id
        JOIN hotels h ON h.id = rt.hotel_id
        WHERE ri.id = ? AND h.owner_id = ?
    ");
    $own->bind_param("ii", $del_image_id, $owner_id);
    $own->execute();
    $imgRow = $own->get_result()->fetch_assoc();
    $own->close();

    if ($imgRow) {
        $path = __DIR__ . "/" . $imgRow['image_path'];
        if (is_file($path)) {
            unlink($path);
        }
        $del = $conn->prepare("DELETE FROM room_images WHERE id = ?");
        $del->bind_param("i", $del_image_id);
        $del->execute();
        $del->close();
    }

    header("Location: manage_hotels.php{$hotelQuery}"
        . ($edit_room_after > 0 ? ($hotelQuery === '' ? '?' : '&') . "edit_room={$edit_room_after}" : ""));
    exit;
}

// ---- Delete a single hotel image ----
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_hotel_image_id"]) && $hasHotel) {
    $del_himg_id = (int) $_POST["delete_hotel_image_id"];

    $own = $conn->prepare("SELECT id, image_path FROM hotel_images WHERE id = ? AND hotel_id = ?");
    $own->bind_param("ii", $del_himg_id, $hotel["id"]);
    $own->execute();
    $imgRow = $own->get_result()->fetch_assoc();
    $own->close();

    if ($imgRow) {
        $path = __DIR__ . "/" . $imgRow['image_path'];
        if (is_file($path)) {
            unlink($path);
        }
        $del = $conn->prepare("DELETE FROM hotel_images WHERE id = ?");
        $del->bind_param("i", $del_himg_id);
        $del->execute();
        $del->close();
    }

    header("Location: manage_hotels.php{$hotelQuery}");
    exit;
}

// ---- Load this hotel's selected amenity tags ----
$selectedAmenityIds = [];
if ($hasHotel) {
    $sa = $conn->prepare("SELECT amenity_id FROM hotel_amenities WHERE hotel_id = ?");
    $sa->bind_param("i", $hotel["id"]);
    $sa->execute();
    $selectedAmenityIds = array_map('intval', array_column($sa->get_result()->fetch_all(MYSQLI_ASSOC), 'amenity_id'));
    $sa->close();
}

// ---- Load existing hotel images for display ----
$hotelImages = [];
if ($hasHotel) {
    $hi = $conn->prepare("SELECT id, image_path FROM hotel_images WHERE hotel_id = ? ORDER BY id ASC");
    $hi->bind_param("i", $hotel["id"]);
    $hi->execute();
    $hotelImages = $hi->get_result()->fetch_all(MYSQLI_ASSOC);
    $hi->close();
}

// ---- Load room types for display ----
$roomTypes = [];
if ($hasHotel) {
    $rt = $conn->prepare("SELECT * FROM room_types WHERE hotel_id = ? ORDER BY id DESC");
    $rt->bind_param("i", $hotel["id"]);
    $rt->execute();
    $roomTypes = $rt->get_result()->fetch_all(MYSQLI_ASSOC);
    $rt->close();

    foreach ($roomTypes as &$room) {
        $ri = $conn->prepare("SELECT id, image_path FROM room_images WHERE room_type_id = ? ORDER BY id ASC");
        $ri->bind_param("i", $room["id"]);
        $ri->execute();
        $room["images"] = $ri->get_result()->fetch_all(MYSQLI_ASSOC);
        $ri->close();
    }
    unset($room);
}

// ---- Editing an existing room type? ----
$editingRoom = null;
if (isset($_GET["edit_room"])) {
    $edit_id = (int) $_GET["edit_room"];
    foreach ($roomTypes as $room) {
        if ((int) $room["id"] === $edit_id) {
            $editingRoom = $room;
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
    <title>เพิ่มหรือแก้ไขโรงแรม</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="wide-page-container">
    <div class="wide-form-card">
        <h2>โรงแรมของคุณ</h2>

        <?php if (!empty($ownerHotels)): ?>
            <div class="hotel-switcher">
                <span class="hotel-switcher-label">
                    <span class="material-symbols-outlined">apartment</span> โรงแรมที่คุณดูแล (<?= count($ownerHotels) ?>)
                </span>
                <div class="hotel-switcher-tabs">
                    <?php foreach ($ownerHotels as $ownerHotel): ?>
                        <a href="manage_hotels.php?hotel_id=<?= (int) $ownerHotel['id'] ?>"
                           class="hotel-switcher-tab<?= (!$addingNewHotel && (int) $ownerHotel['id'] === (int) ($hotel['id'] ?? 0)) ? ' active' : '' ?>">
                            <?= htmlspecialchars($ownerHotel['hotel_name']) ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="manage_hotels.php?hotel_id=new"
                       class="hotel-switcher-tab hotel-switcher-add<?= $addingNewHotel ? ' active' : '' ?>">
                        <span class="material-symbols-outlined">add</span> เพิ่มโรงแรมใหม่
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET["success"])): ?>
            <div class="alert alert-success">บันทึกโรงแรมเรียบร้อยแล้ว</div>
        <?php endif; ?>
        <?php if (isset($_GET["room_success"])): ?>
            <div class="alert alert-success">บันทึกห้องพักเรียบร้อยแล้ว</div>
        <?php endif; ?>
        <?php if (!empty($saveError)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($saveError) ?></div>
        <?php endif; ?>

        <form action="manage_hotels.php" method="post" enctype="multipart/form-data">
            <input type="hidden" name="hotel_id" value="<?= $addingNewHotel ? 'new' : (int) ($hotel['id'] ?? 0) ?>">
            <div class="field-row">
                <div class="field-group">
                    <label>ชื่อโรงแรม</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">hotel</span>
                        <input type="text" name="hotel_name" placeholder="ชื่อโรงแรม"
                               value="<?= htmlspecialchars($hotel["hotel_name"]) ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>ราคาต่อคืน (บาท)</label>
                    <div class="field-wrap">
                        <span class="field-icon">฿</span>
                        <input type="text" name="price" placeholder="ราคาต่อคืน (บาท)"
                               value="<?= htmlspecialchars($hotel["price"]) ?>">
                    </div>
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>ที่ตั้ง</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">location_on</span>
                        <input type="text" name="location" id="locationInput" placeholder="ที่ตั้ง"
                               value="<?= htmlspecialchars($hotel["location"]) ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>จังหวัด</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">map</span>
                        <select name="province" class="styled-select">
                            <option value="">-- เลือกจังหวัด --</option>
                            <?php foreach (thai_provinces_by_region() as $region => $provinceNames): ?>
                                <optgroup label="<?= htmlspecialchars($region) ?>">
                                    <?php foreach ($provinceNames as $provinceName): ?>
                                        <option value="<?= htmlspecialchars($provinceName) ?>"
                                            <?= ($hotel["province"] ?? '') === $provinceName ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($provinceName) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <span class="field-hint">ใช้สำหรับให้ผู้เข้าพักกรองหาโรงแรมตามจังหวัด</span>
                </div>
            </div>

            <div class="map-card">
                <div class="map-search-row">
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">search</span>
                        <input type="text" id="mapSearch" placeholder="พิมพ์ชื่อสถานที่หรือที่อยู่ เช่น สยามพารากอน กรุงเทพ">
                    </div>
                </div>
                <span class="field-hint">
                    1) พิมพ์ชื่อสถานที่หรือที่อยู่ในช่องด้านบน แล้วกด Enter เพื่อค้นหา<br>
                    2) แผนที่จะเลื่อนไปตำแหน่งที่พบและปักหมุดให้อัตโนมัติ<br>
                    3) คลิกหรือลากหมุดบนแผนที่เพื่อปรับตำแหน่งให้ตรงจุดจริง
                </span>
                <div id="hotelMap" class="leaflet-map-container"></div>
                <input type="hidden" name="latitude" id="latitudeInput" value="<?= htmlspecialchars($hotel["latitude"] ?? '') ?>">
                <input type="hidden" name="longitude" id="longitudeInput" value="<?= htmlspecialchars($hotel["longitude"] ?? '') ?>">
            </div>

            <div class="field-group">
                <label>รายละเอียด</label>
                <div class="field-wrap">
                    <span class="field-icon material-symbols-outlined">description</span>
                    <input type="text" name="description" placeholder="รายละเอียด"
                           value="<?= htmlspecialchars($hotel["description"]) ?>">
                </div>
            </div>
            <div class="field-group">
                <label>สิ่งอำนวยความสะดวก (คั่นด้วย ,)</label>
                <div class="field-wrap">
                    <span class="field-icon material-symbols-outlined">room_service</span>
                    <input type="text" name="facilities" placeholder="สิ่งอำนวยความสะดวก (คั่นด้วย ,)"
                           value="<?= htmlspecialchars($hotel["facilities"]) ?>">
                </div>
            </div>
            <div class="field-group">
                <label>สิ่งอำนวยความสะดวกเด่น (ใช้เป็นตัวกรองค้นหา)</label>
                <div class="amenity-tab-list">
                    <?php foreach ($allAmenities as $amenity): ?>
                        <label class="amenity-tab">
                            <input type="checkbox" name="amenities[]" value="<?= (int) $amenity['id'] ?>" <?= in_array((int) $amenity['id'], $selectedAmenityIds, true) ? 'checked' : '' ?>>
                            <span class="material-symbols-outlined"><?= htmlspecialchars($amenity['icon']) ?></span>
                            <?= htmlspecialchars($amenity['title']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="field-group">
                <label>บริเวณโดยรอบ (คั่นด้วย ,)</label>
                <div class="field-wrap">
                    <span class="field-icon material-symbols-outlined">map</span>
                    <input type="text" name="surrounding" placeholder="บริเวณโดยรอบ (คั่นด้วย ,)"
                           value="<?= htmlspecialchars($hotel["surrounding"]) ?>">
                </div>
            </div>
            <div class="field-group">
                <label>รูปภาพโรงแรม</label>
                <?php if (!empty($hotelImages)): ?>
                <div class="room-thumb-strip" style="margin-bottom:12px;">
                    <?php foreach ($hotelImages as $img): ?>
                        <div class="room-thumb">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="hotel image">
                            <button type="submit" form="delHotelImg<?= (int) $img['id'] ?>"
                                    class="room-thumb-remove">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="room-upload-dropzone" id="hotelDropzone"><span class="material-symbols-outlined">add_photo_alternate</span> คลิกเพื่อเลือกรูปภาพ (เลือกได้หลายรูป พร้อมครอบตัด)</div>
                <input type="file" id="hotelFileInput" accept="image/*" multiple style="display:none">
                <div class="room-thumb-strip" id="hotelThumbStrip"></div>
                <input type="hidden" name="cropped_hotel_images_json" id="croppedHotelImagesJson">
            </div>

            <button type="submit" class="auth-btn"><?= $hasHotel ? "บันทึกข้อมูล" : "เพิ่มโรงแรม" ?></button>
        </form>

        <?php foreach ($hotelImages as $img): ?>
        <form method="post" id="delHotelImg<?= (int) $img['id'] ?>" data-confirm="ลบรูปนี้ใช่หรือไม่?">
            <input type="hidden" name="hotel_id" value="<?= (int) ($hotel['id'] ?? 0) ?>">
            <input type="hidden" name="delete_hotel_image_id" value="<?= (int) $img['id'] ?>">
        </form>
        <?php endforeach; ?>

        <form method="POST" action="manage_hotels.php" data-confirm="คุณแน่ใจหรือไม่ว่าต้องการลบโรงแรมนี้?">
            <?php if ($hasHotel): ?>
                <input type="hidden" name="delete_hotel_id" value="<?= (int) $hotel['id'] ?>">
                <button type="submit" class="btn btn-danger" style="margin-top:10px;">ลบโรงแรม</button>
            <?php endif; ?>
        </form>

        <?php if ($hasHotel): ?>
        <h3 class="section-title">ห้องพัก</h3>

        <?php if (!empty($roomError)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($roomError) ?></div>
        <?php endif; ?>

        <?php if (!empty($roomTypes)): ?>
        <div class="room-type-grid" style="margin-bottom:28px;">
            <?php foreach ($roomTypes as $room):
                $isEditingThis = $editingRoom && (int) $editingRoom['id'] === (int) $room['id'];
            ?>
                <div class="room-type-card<?= $isEditingThis ? ' editing-room' : '' ?>">
                    <?php if ($isEditingThis): ?>
                        <span class="editing-badge"><span class="material-symbols-outlined">edit</span> กำลังแก้ไข</span>
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars($room['images'][0]['image_path'] ?? 'image/641151494.jpg') ?>" alt="<?= htmlspecialchars($room['room_name']) ?>">
                    <div class="card-content">
                        <h4><?= htmlspecialchars($room['room_name']) ?></h4>
                        <span class="capacity-badge"><span class="material-symbols-outlined">person</span> x <?= (int) $room['capacity'] ?></span>
                        <span>เหลือ <?= (int) $room['quantity'] ?> ห้อง</span>
                        <span class="room-price">฿<?= htmlspecialchars($room['price_per_night']) ?> / คืน</span>
                        <div class="room-actions">
                            <a href="manage_hotels.php?hotel_id=<?= (int) $hotel['id'] ?>&edit_room=<?= (int) $room['id'] ?>" class="btn-edit-room">แก้ไข</a>
                            <form method="post" style="flex:1;" data-confirm="ลบห้องพัก &quot;<?= htmlspecialchars($room['room_name'], ENT_QUOTES) ?>&quot; ใช่หรือไม่?">
                                <input type="hidden" name="hotel_id" value="<?= (int) $hotel['id'] ?>">
                                <input type="hidden" name="delete_room_id" value="<?= (int) $room['id'] ?>">
                                <button type="submit" class="btn-delete-room">ลบ</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h3 class="section-title" id="roomFormAnchor">
            <?= $editingRoom ? "แก้ไขห้องพัก: " . htmlspecialchars($editingRoom['room_name']) : "เพิ่มห้องพัก" ?>
        </h3>
        <form method="post" id="roomForm" class="<?= $editingRoom ? 'editing-room-form' : '' ?>">
            <input type="hidden" name="hotel_id" value="<?= (int) $hotel['id'] ?>">
            <input type="hidden" name="room_type_id" value="<?= $editingRoom ? (int) $editingRoom['id'] : '' ?>">

            <div class="field-row">
                <div class="field-group">
                    <label>ชื่อห้อง</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">bed</span>
                        <input type="text" name="room_name" placeholder="เช่น ห้องมาตรฐาน เตียงคู่"
                               value="<?= htmlspecialchars($editingRoom['room_name'] ?? '') ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>จำนวนผู้เข้าพักสูงสุด</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">person</span>
                        <input type="number" name="capacity" min="1" placeholder="เช่น 2"
                               value="<?= htmlspecialchars($editingRoom['capacity'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>ราคาต่อคืน (บาท)</label>
                    <div class="field-wrap">
                        <span class="field-icon">฿</span>
                        <input type="number" step="0.01" name="price_per_night" placeholder="เช่น 1200"
                               value="<?= htmlspecialchars($editingRoom['price_per_night'] ?? '') ?>">
                    </div>
                </div>
                <div class="field-group">
                    <label>จำนวนห้องทั้งหมด</label>
                    <div class="field-wrap">
                        <span class="field-icon material-symbols-outlined">numbers</span>
                        <input type="number" name="quantity" min="1" placeholder="เช่น 5"
                               value="<?= htmlspecialchars($editingRoom['quantity'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <div class="field-group">
                <label>รายละเอียดห้อง</label>
                <div class="field-wrap">
                    <span class="field-icon material-symbols-outlined">description</span>
                    <input type="text" name="room_description" placeholder="รายละเอียดห้องพัก"
                           value="<?= htmlspecialchars($editingRoom['description'] ?? '') ?>">
                </div>
            </div>
            <div class="field-group">
                <label>สิ่งอำนวยความสะดวกในห้อง (คั่นด้วย ,)</label>
                <div class="field-wrap">
                    <span class="field-icon material-symbols-outlined">room_service</span>
                    <input type="text" name="amenities" placeholder="เช่น แอร์, ทีวี, ตู้เย็น"
                           value="<?= htmlspecialchars($editingRoom['amenities'] ?? '') ?>">
                </div>
            </div>

            <?php if ($editingRoom && !empty($editingRoom['images'])): ?>
            <div class="field-group">
                <label>รูปภาพปัจจุบัน</label>
                <div class="room-thumb-strip">
                    <?php foreach ($editingRoom['images'] as $img): ?>
                        <div class="room-thumb">
                            <img src="<?= htmlspecialchars($img['image_path']) ?>" alt="room image">
                            <button type="submit" form="delRoomImg<?= (int) $img['id'] ?>"
                                    class="room-thumb-remove">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="field-group">
                <label>เพิ่มรูปภาพห้องพัก (เลือกได้หลายรูป พร้อมครอบตัด)</label>
                <div class="room-upload-dropzone" id="roomDropzone"><span class="material-symbols-outlined">add_photo_alternate</span> คลิกเพื่อเลือกรูปภาพ</div>
                <input type="file" id="roomFileInput" accept="image/*" multiple style="display:none">
                <div class="room-thumb-strip" id="roomThumbStrip"></div>
                <input type="hidden" name="cropped_images_json" id="croppedImagesJson">
            </div>

            <button type="submit" class="auth-btn"><?= $editingRoom ? "บันทึกห้องพัก" : "เพิ่มห้องพัก" ?></button>
            <?php if ($editingRoom): ?>
                <a href="manage_hotels.php?hotel_id=<?= (int) $hotel['id'] ?>" class="profile-back-link" style="display:block; margin-top:10px;">ยกเลิกการแก้ไข</a>
            <?php endif; ?>
        </form>

        <?php if ($editingRoom): foreach ($editingRoom['images'] as $img): ?>
        <form method="post" id="delRoomImg<?= (int) $img['id'] ?>" data-confirm="ลบรูปนี้ใช่หรือไม่?">
            <input type="hidden" name="hotel_id" value="<?= (int) $hotel['id'] ?>">
            <input type="hidden" name="delete_room_image_id" value="<?= (int) $img['id'] ?>">
            <input type="hidden" name="edit_room_after" value="<?= (int) $editingRoom['id'] ?>">
        </form>
        <?php endforeach; endif; ?>
        <?php endif; ?>

        <p><a href="index.php">กลับหน้าหลัก</a></p>
    </div>
</div>

<!-- Shared image crop modal (used for both hotel and room photos) -->
<div id="imageCropModal" class="crop-modal" style="display:none">
    <div class="crop-modal-box">
        <div class="crop-modal-header">
            <h3><span class="material-symbols-outlined">crop</span> ครอบตัดรูปภาพ</h3>
            <button type="button" class="crop-close-btn" id="cropClose2"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="crop-canvas-wrap">
            <img id="cropImage2" src="" alt="crop">
        </div>
        <div class="crop-resolution-row">
            <label for="cropResolution">ขนาดรูปภาพที่ต้องการ:</label>
            <select id="cropResolution">
                <option value="1280x720" selected>720p (1280x720)</option>
                <option value="1600x900">900p (1600x900)</option>
                <option value="1920x1080">1080p (1920x1080)</option>
                <option value="2560x1440">1440p (2560x1440)</option>
            </select>
        </div>
        <div class="crop-modal-footer">
            <button type="button" class="crop-btn-cancel" id="cropCancel2">ข้ามรูปนี้</button>
            <button type="button" class="crop-btn-confirm" id="cropConfirm2"><span class="material-symbols-outlined">check</span> ยืนยันการครอบ</button>
        </div>
    </div>
</div>

<!-- Shared confirm-delete modal (replaces native browser confirm()) -->
<div id="confirmModal" class="crop-modal" style="display:none">
    <div class="crop-modal-box confirm-modal-box">
        <div class="crop-modal-header">
            <h3>ยืนยันการลบ</h3>
        </div>
        <div class="confirm-modal-message" id="confirmMessage"></div>
        <div class="crop-modal-footer">
            <button type="button" class="crop-btn-cancel" id="confirmCancelBtn">ยกเลิก</button>
            <button type="button" class="crop-btn-confirm confirm-btn-danger" id="confirmOkBtn">ลบ</button>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    <?php if ($editingRoom): ?>
    // Jump straight to the edit form so it's obvious which room is being edited,
    // instead of leaving the owner to scroll down and guess.
    var roomAnchor = document.getElementById('roomFormAnchor');
    if (roomAnchor) roomAnchor.scrollIntoView({ behavior: 'smooth', block: 'start' });
    <?php endif; ?>

    // ---- Leaflet map ----
    var startLat = <?= $hotel["latitude"] ? (float) $hotel["latitude"] : 6.8692 ?>;
    var startLng = <?= $hotel["longitude"] ? (float) $hotel["longitude"] : 101.2504 ?>;
    var hasPin   = <?= ($hotel["latitude"] && $hotel["longitude"]) ? "true" : "false" ?>;

    var map = L.map('hotelMap').setView([startLat, startLng], hasPin ? 15 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var marker = L.marker([startLat, startLng], { draggable: true }).addTo(map);

    var latInput = document.getElementById('latitudeInput');
    var lngInput = document.getElementById('longitudeInput');

    function setPin(lat, lng) {
        marker.setLatLng([lat, lng]);
        latInput.value = lat;
        lngInput.value = lng;
    }

    if (hasPin) setPin(startLat, startLng);

    marker.on('dragend', function () {
        var pos = marker.getLatLng();
        setPin(pos.lat, pos.lng);
    });

    map.on('click', function (e) {
        setPin(e.latlng.lat, e.latlng.lng);
    });

    // Nominatim address search (OpenStreetMap free geocoder — fine at low volume,
    // avoid heavy/production-scale use per their usage policy).
    var mapSearch = document.getElementById('mapSearch');
    mapSearch.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var q = mapSearch.value.trim();
        if (!q) return;
        fetch('https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(q))
            .then(res => res.json())
            .then(results => {
                if (!results.length) { alert('ไม่พบสถานที่นี้'); return; }
                var lat = parseFloat(results[0].lat);
                var lng = parseFloat(results[0].lon);
                map.setView([lat, lng], 15);
                setPin(lat, lng);
            })
            .catch(() => alert('ค้นหาที่อยู่ไม่สำเร็จ'));
    });

    // ---- Shared crop modal + resolution selector, used by both the hotel and
    // room image uploaders below. Only one queue can be "active" at a time. ----
    var cropModal      = document.getElementById('imageCropModal');
    var cropImageEl    = document.getElementById('cropImage2');
    var cropResolution = document.getElementById('cropResolution');
    var cropper        = null;
    var activeUploader  = null;

    function makeImageUploader(dropzoneId, fileInputId, thumbStripId, jsonInputId) {
        var dropzone   = document.getElementById(dropzoneId);
        var fileInput  = document.getElementById(fileInputId);
        var thumbStrip = document.getElementById(thumbStripId);
        var jsonInput  = document.getElementById(jsonInputId);
        if (!dropzone) return null;

        var pendingFiles = [];
        var croppedResults = [];

        dropzone.addEventListener('click', () => fileInput.click());

        fileInput.addEventListener('change', function () {
            pendingFiles = Array.from(this.files);
            activeUploader = uploader;
            processNextFile();
            fileInput.value = "";
        });

        function processNextFile() {
            if (pendingFiles.length === 0) {
                renderThumbStrip();
                return;
            }
            var file = pendingFiles.shift();
            var reader = new FileReader();
            reader.onload = function (e) {
                cropModal.style.display = "flex";
                if (cropper) { cropper.destroy(); cropper = null; }

                // Wait for the image to actually finish loading before handing it to
                // Cropper.js — instantiating immediately after setting .src races the
                // image decode and reliably breaks on the 2nd+ image reusing this <img>.
                cropImageEl.onload = function () {
                    cropper = new Cropper(cropImageEl, {
                        aspectRatio: 16 / 9,
                        viewMode: 2,
                        dragMode: "move",
                        autoCropArea: 0.9,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                    });
                };
                cropImageEl.src = e.target.result;
            };
            reader.readAsDataURL(file);
        }

        function renderThumbStrip() {
            thumbStrip.innerHTML = "";
            croppedResults.forEach(function (dataUrl, index) {
                var div = document.createElement('div');
                div.className = 'room-thumb';
                var img = document.createElement('img');
                img.src = dataUrl;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'room-thumb-remove';
                btn.textContent = '×';
                btn.addEventListener('click', function () {
                    croppedResults.splice(index, 1);
                    renderThumbStrip();
                });
                div.appendChild(img);
                div.appendChild(btn);
                thumbStrip.appendChild(div);
            });
            jsonInput.value = JSON.stringify(croppedResults);
        }

        var uploader = {
            processNextFile: processNextFile,
            pushResult: function (dataUrl) { croppedResults.push(dataUrl); },
        };
        return uploader;
    }

    var hotelUploader = makeImageUploader('hotelDropzone', 'hotelFileInput', 'hotelThumbStrip', 'croppedHotelImagesJson');
    var roomUploader  = makeImageUploader('roomDropzone', 'roomFileInput', 'roomThumbStrip', 'croppedImagesJson');

    function closeCropModal() {
        cropModal.style.display = "none";
        if (cropper) { cropper.destroy(); cropper = null; }
    }

    document.getElementById('cropClose2').addEventListener('click', function () {
        closeCropModal();
        if (activeUploader) activeUploader.processNextFile();
    });
    document.getElementById('cropCancel2').addEventListener('click', function () {
        closeCropModal();
        if (activeUploader) activeUploader.processNextFile();
    });
    document.getElementById('cropConfirm2').addEventListener('click', function () {
        if (!cropper) return;
        var res = cropResolution.value.split('x');
        var canvas = cropper.getCroppedCanvas({
            width: parseInt(res[0], 10),
            height: parseInt(res[1], 10),
            imageSmoothingQuality: "high",
        });
        if (activeUploader) activeUploader.pushResult(canvas.toDataURL('image/jpeg', 0.9));
        closeCropModal();
        if (activeUploader) activeUploader.processNextFile();
    });

    // ---- Custom confirm-delete modal (replaces native confirm()) ----
    var confirmModal     = document.getElementById('confirmModal');
    var confirmMessageEl = document.getElementById('confirmMessage');
    var confirmOkBtn     = document.getElementById('confirmOkBtn');
    var confirmCancelBtn = document.getElementById('confirmCancelBtn');
    var pendingConfirmForm = null;

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (form.dataset.confirm) {
            e.preventDefault();
            pendingConfirmForm = form;
            confirmMessageEl.textContent = form.dataset.confirm;
            confirmModal.style.display = 'flex';
        }
    });

    function closeConfirmModal() {
        confirmModal.style.display = 'none';
        pendingConfirmForm = null;
    }

    confirmCancelBtn.addEventListener('click', closeConfirmModal);
    confirmModal.addEventListener('click', function (e) {
        if (e.target === confirmModal) closeConfirmModal();
    });
    confirmOkBtn.addEventListener('click', function () {
        var form = pendingConfirmForm;
        closeConfirmModal();
        if (form) form.submit();
    });
});
</script>

</body>
</html>
