<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$msg = '';

// Swaps display_order with the adjacent row (optionally scoped to e.g. a
// category_id) to move a row up or down one position. $table/$scopeCol are
// always fixed literals passed from this file, never user input, so
// string-building the query is safe here.
function move_display_order(mysqli $conn, string $table, int $id, ?string $scopeCol, ?int $scopeVal, string $direction): void {
    $cmp = $direction === 'up' ? '<' : '>';
    $ord = $direction === 'up' ? 'DESC' : 'ASC';

    $cur = $conn->prepare("SELECT display_order FROM $table WHERE id = ?");
    $cur->bind_param("i", $id);
    $cur->execute();
    $currentRow = $cur->get_result()->fetch_assoc();
    $cur->close();
    if (!$currentRow) {
        return;
    }
    $currentOrder = (int) $currentRow['display_order'];

    if ($scopeCol !== null) {
        $adj = $conn->prepare("SELECT id, display_order FROM $table WHERE $scopeCol = ? AND display_order $cmp ? ORDER BY display_order $ord LIMIT 1");
        $adj->bind_param("ii", $scopeVal, $currentOrder);
    } else {
        $adj = $conn->prepare("SELECT id, display_order FROM $table WHERE display_order $cmp ? ORDER BY display_order $ord LIMIT 1");
        $adj->bind_param("i", $currentOrder);
    }
    $adj->execute();
    $adjacent = $adj->get_result()->fetch_assoc();
    $adj->close();
    if (!$adjacent) {
        return;
    }

    $upd1 = $conn->prepare("UPDATE $table SET display_order = ? WHERE id = ?");
    $upd1->bind_param("ii", $adjacent['display_order'], $id);
    $upd1->execute();
    $upd1->close();

    $upd2 = $conn->prepare("UPDATE $table SET display_order = ? WHERE id = ?");
    $upd2->bind_param("ii", $currentOrder, $adjacent['id']);
    $upd2->execute();
    $upd2->close();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? '';

    $allowedShowOn = ['both', 'index', 'hotel'];

    if ($action === "add_category") {
        $title   = trim($_POST["title"] ?? '');
        $showOn  = in_array($_POST["show_on"] ?? '', $allowedShowOn, true) ? $_POST["show_on"] : 'both';
        if ($title === '') {
            $msg = "กรุณากรอกชื่อหมวดหมู่";
        } else {
            $maxOrder = $conn->query("SELECT COALESCE(MAX(display_order), 0) AS m FROM hotel_categories")->fetch_assoc()['m'];
            $stmt = $conn->prepare("INSERT INTO hotel_categories (title, display_order, show_on) VALUES (?, ?, ?)");
            $nextOrder = (int) $maxOrder + 1;
            $stmt->bind_param("sis", $title, $nextOrder, $showOn);
            $stmt->execute();
            $stmt->close();
            $msg = "เพิ่มหมวดหมู่เรียบร้อยแล้ว";
        }
    }

    if ($action === "rename_category") {
        $id    = (int) $_POST["id"];
        $title = trim($_POST["title"] ?? '');
        if ($title !== '') {
            $stmt = $conn->prepare("UPDATE hotel_categories SET title = ? WHERE id = ?");
            $stmt->bind_param("si", $title, $id);
            $stmt->execute();
            $stmt->close();
            $msg = "บันทึกชื่อหมวดหมู่แล้ว";
        }
    }

    if ($action === "set_show_on") {
        $id     = (int) $_POST["id"];
        $showOn = in_array($_POST["show_on"] ?? '', $allowedShowOn, true) ? $_POST["show_on"] : 'both';
        $stmt = $conn->prepare("UPDATE hotel_categories SET show_on = ? WHERE id = ?");
        $stmt->bind_param("si", $showOn, $id);
        $stmt->execute();
        $stmt->close();
        $msg = "บันทึกการแสดงผลแล้ว";
    }

    if ($action === "delete_category") {
        $id = (int) $_POST["id"];
        $del = $conn->prepare("DELETE FROM hotel_category_items WHERE category_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
        $del2 = $conn->prepare("DELETE FROM hotel_categories WHERE id = ?");
        $del2->bind_param("i", $id);
        $del2->execute();
        $del2->close();
        $msg = "ลบหมวดหมู่เรียบร้อยแล้ว";
    }

    if ($action === "move_category") {
        $id = (int) $_POST["id"];
        move_display_order($conn, "hotel_categories", $id, null, null, $_POST["direction"] ?? '');
    }

    if ($action === "add_item") {
        $categoryId = (int) $_POST["category_id"];
        $hotelId    = (int) $_POST["hotel_id"];
        $isAutoCheck = $conn->prepare("SELECT is_auto FROM hotel_categories WHERE id = ?");
        $isAutoCheck->bind_param("i", $categoryId);
        $isAutoCheck->execute();
        $isAutoRow = $isAutoCheck->get_result()->fetch_assoc();
        $isAutoCheck->close();
        if ($hotelId > 0 && empty($isAutoRow['is_auto'])) {
            $maxOrder = $conn->prepare("SELECT COALESCE(MAX(display_order), 0) AS m FROM hotel_category_items WHERE category_id = ?");
            $maxOrder->bind_param("i", $categoryId);
            $maxOrder->execute();
            $nextOrder = (int) $maxOrder->get_result()->fetch_assoc()['m'] + 1;
            $maxOrder->close();

            // INSERT IGNORE: the UNIQUE(category_id, hotel_id) key silently
            // no-ops a duplicate add instead of erroring.
            $stmt = $conn->prepare("INSERT IGNORE INTO hotel_category_items (category_id, hotel_id, display_order) VALUES (?, ?, ?)");
            $stmt->bind_param("iii", $categoryId, $hotelId, $nextOrder);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($action === "remove_item") {
        $id = (int) $_POST["id"];
        $stmt = $conn->prepare("DELETE FROM hotel_category_items WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }

    if ($action === "move_item") {
        $id         = (int) $_POST["id"];
        $categoryId = (int) $_POST["category_id"];
        move_display_order($conn, "hotel_category_items", $id, "category_id", $categoryId, $_POST["direction"] ?? '');
    }
}

$categories = $conn->query("SELECT * FROM hotel_categories ORDER BY display_order ASC")->fetch_all(MYSQLI_ASSOC);

foreach ($categories as &$category) {
    if ($category['is_auto']) {
        // Auto category: membership is every hotel, always — nothing to
        // curate, so skip the manual items/available-hotels lookups.
        $stmt = $conn->prepare("
            SELECT h.id AS hotel_id, h.hotel_name,
                   (SELECT image_path FROM hotel_images WHERE hotel_id = h.id ORDER BY id ASC LIMIT 1) AS image_path
            FROM hotels h
            ORDER BY h.id DESC
        ");
        $stmt->execute();
        $category['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $category['availableHotels'] = [];
        continue;
    }

    $stmt = $conn->prepare("
        SELECT hci.id AS item_id, h.id AS hotel_id, h.hotel_name,
               (SELECT image_path FROM hotel_images WHERE hotel_id = h.id ORDER BY id ASC LIMIT 1) AS image_path
        FROM hotel_category_items hci
        JOIN hotels h ON h.id = hci.hotel_id
        WHERE hci.category_id = ?
        ORDER BY hci.display_order ASC
    ");
    $stmt->bind_param("i", $category['id']);
    $stmt->execute();
    $category['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $assignedIds = array_column($category['items'], 'hotel_id');
    if (!empty($assignedIds)) {
        $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
        $stmt = $conn->prepare("SELECT id, hotel_name FROM hotels WHERE id NOT IN ($placeholders) ORDER BY hotel_name ASC");
        $stmt->bind_param(str_repeat('i', count($assignedIds)), ...$assignedIds);
    } else {
        $stmt = $conn->prepare("SELECT id, hotel_name FROM hotels ORDER BY hotel_name ASC");
    }
    $stmt->execute();
    $category['availableHotels'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
unset($category);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหมวดหมู่โรงแรม (Admin)</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="container">
    <h2 class="booking-title"><span class="material-symbols-outlined">category</span> จัดการหมวดหมู่โรงแรม</h2>
    <p style="text-align:center; color:#888; margin-top:-10px;">
        หมวดหมู่เหล่านี้จะแสดงในหน้าแรกและหน้าค้นหาโรงแรม เลือกโรงแรมเข้าแต่ละหมวดเองได้อิสระ
    </p>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-success" style="max-width:700px; margin:16px auto;"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="category-admin-add">
        <form method="post">
            <input type="hidden" name="action" value="add_category">
            <input type="text" name="title" placeholder="ชื่อหมวดหมู่ใหม่ เช่น โรงแรมริมทะเล" required>
            <select name="show_on" class="styled-select category-show-on-select">
                <option value="both">แสดงทั้งสองหน้า</option>
                <option value="index">เฉพาะหน้าแรก</option>
                <option value="hotel">เฉพาะหน้าค้นหาโรงแรม</option>
            </select>
            <button type="submit" class="btn-table-save">
                <span class="material-symbols-outlined">add</span> เพิ่มหมวดหมู่
            </button>
        </form>
    </div>

    <div class="category-admin-list">
        <?php foreach ($categories as $index => $category): ?>
        <div class="category-admin-card">
            <div class="category-admin-top">
                <form method="post" class="category-title-form">
                    <input type="hidden" name="action" value="rename_category">
                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                    <input type="text" name="title" value="<?= htmlspecialchars($category['title']) ?>">
                    <button type="submit" class="btn-table-view">บันทึกชื่อ</button>
                </form>

                <form method="post" class="category-show-on-form">
                    <input type="hidden" name="action" value="set_show_on">
                    <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                    <select name="show_on" class="styled-select category-show-on-select" onchange="this.form.submit()">
                        <option value="both" <?= $category['show_on'] === 'both' ? 'selected' : '' ?>>แสดงทั้งสองหน้า</option>
                        <option value="index" <?= $category['show_on'] === 'index' ? 'selected' : '' ?>>เฉพาะหน้าแรก</option>
                        <option value="hotel" <?= $category['show_on'] === 'hotel' ? 'selected' : '' ?>>เฉพาะหน้าค้นหาโรงแรม</option>
                    </select>
                </form>

                <div class="category-admin-actions">
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="move_category">
                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <input type="hidden" name="direction" value="up">
                        <button type="submit" class="icon-btn" <?= $index === 0 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined">arrow_upward</span>
                        </button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="move_category">
                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <input type="hidden" name="direction" value="down">
                        <button type="submit" class="icon-btn" <?= $index === count($categories) - 1 ? 'disabled' : '' ?>>
                            <span class="material-symbols-outlined">arrow_downward</span>
                        </button>
                    </form>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="delete_category">
                        <input type="hidden" name="id" value="<?= (int) $category['id'] ?>">
                        <button type="submit" class="btn-table-delete"
                                onclick="return confirm('ลบหมวดหมู่ &quot;<?= htmlspecialchars($category['title'], ENT_QUOTES) ?>&quot; ใช่หรือไม่? โรงแรมในหมวดนี้จะไม่ถูกลบ แค่เอาออกจากหมวด');">
                            <span class="material-symbols-outlined">delete</span> ลบหมวดหมู่
                        </button>
                    </form>
                </div>
            </div>

            <?php if ($category['is_auto']): ?>
                <p class="category-empty-hint">หมวดนี้รวมโรงแรมทั้งหมดโดยอัตโนมัติ โรงแรมใหม่ที่ owner เพิ่มจะเข้ามาแสดงเองโดยไม่ต้องกดเพิ่ม</p>
            <?php endif; ?>

            <?php if (empty($category['items'])): ?>
                <p class="category-empty-hint">ยังไม่มีโรงแรมในหมวดนี้</p>
            <?php else: ?>
                <div class="category-item-grid">
                    <?php foreach ($category['items'] as $itemIndex => $item): ?>
                        <div class="category-item-card">
                            <img src="<?= !empty($item['image_path']) ? htmlspecialchars($item['image_path']) : 'uploads/hotels/noimage.jpg' ?>" alt="">
                            <div class="category-item-name"><?= htmlspecialchars($item['hotel_name']) ?></div>
                            <?php if (!$category['is_auto']): ?>
                                <div class="category-item-actions">
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="move_item">
                                        <input type="hidden" name="id" value="<?= (int) $item['item_id'] ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                        <input type="hidden" name="direction" value="up">
                                        <button type="submit" class="icon-btn-sm" <?= $itemIndex === 0 ? 'disabled' : '' ?>>
                                            <span class="material-symbols-outlined">arrow_back</span>
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="move_item">
                                        <input type="hidden" name="id" value="<?= (int) $item['item_id'] ?>">
                                        <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                                        <input type="hidden" name="direction" value="down">
                                        <button type="submit" class="icon-btn-sm" <?= $itemIndex === count($category['items']) - 1 ? 'disabled' : '' ?>>
                                            <span class="material-symbols-outlined">arrow_forward</span>
                                        </button>
                                    </form>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="action" value="remove_item">
                                        <input type="hidden" name="id" value="<?= (int) $item['item_id'] ?>">
                                        <button type="submit" class="icon-btn-sm icon-btn-danger">
                                            <span class="material-symbols-outlined">close</span>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!$category['is_auto'] && !empty($category['availableHotels'])): ?>
                <form method="post" class="category-add-item-form">
                    <input type="hidden" name="action" value="add_item">
                    <input type="hidden" name="category_id" value="<?= (int) $category['id'] ?>">
                    <select name="hotel_id" class="styled-select">
                        <option value="">เลือกโรงแรม...</option>
                        <?php foreach ($category['availableHotels'] as $hotel): ?>
                            <option value="<?= (int) $hotel['id'] ?>"><?= htmlspecialchars($hotel['hotel_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-table-save">
                        <span class="material-symbols-outlined">add</span> เพิ่มโรงแรม
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (empty($categories)): ?>
            <p class="category-empty-hint">ยังไม่มีหมวดหมู่ เริ่มสร้างหมวดแรกด้านบนได้เลย</p>
        <?php endif; ?>
    </div>

    <div class="booking-back">
        <a href="admin_manage.php"><span class="material-symbols-outlined">arrow_back</span> กลับเมนู Admin</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
