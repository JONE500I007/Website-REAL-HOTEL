<?php

function load_env(string $path): void {
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value);
        // Allow "KEY=quoted value" without keeping the quote characters themselves.
        if (strlen($value) >= 2 && $value[0] === $value[-1] && in_array($value[0], ['"', "'"], true)) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function resolve_upload_src(?string $path, string $subdir = ''): string {
    $path = $path ?: 'default.jpg';
    return str_starts_with($path, 'http') ? $path : "uploads/$subdir$path";
}

function save_cropped_image(string $dataUrl, string $destDir, string $prefix): string|false {
    $data = preg_replace('/^data:image\/\w+;base64,/', '', $dataUrl);
    $decoded = base64_decode($data);
    if ($decoded === false) {
        return false;
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    $filename = uniqid($prefix . '_') . '.jpg';
    if (!file_put_contents($destDir . $filename, $decoded)) {
        return false;
    }

    return $filename;
}

// Bulk-fetches amenity tags for a batch of hotels in one query (instead of
// one query per hotel-card), keyed by hotel_id.
function get_amenities_for_hotels(mysqli $conn, array $hotelIds): array {
    $hotelIds = array_values(array_unique(array_filter(array_map('intval', $hotelIds))));
    if (empty($hotelIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($hotelIds), '?'));
    $stmt = $conn->prepare("
        SELECT ha.hotel_id, a.id, a.title, a.icon
        FROM hotel_amenities ha
        JOIN amenities a ON a.id = ha.amenity_id
        WHERE ha.hotel_id IN ($placeholders)
        ORDER BY a.display_order ASC
    ");
    $stmt->bind_param(str_repeat('i', count($hotelIds)), ...$hotelIds);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $byHotel = [];
    foreach ($rows as $row) {
        $byHotel[(int) $row['hotel_id']][] = ['id' => (int) $row['id'], 'title' => $row['title'], 'icon' => $row['icon']];
    }
    return $byHotel;
}

// Renders one hotel-card (used by render_hotel_category() and by hotel.php's
// filtered results grid) so the markup — including amenity tag badges —
// can't drift between the different places hotels are listed.
function render_hotel_card(array $hotel, array $amenities = []): void {
    ?>
    <div class="hotel-card">
        <img src="<?= !empty($hotel["image_path"]) ? htmlspecialchars($hotel["image_path"]) : "uploads/hotels/noimage.jpg" ?>"
             alt="Hotel Image">
        <div class="card-content">
            <h3><?= htmlspecialchars($hotel["hotel_name"]) ?></h3>
            <p><?= htmlspecialchars($hotel["location"]) ?></p>
            <?php if (!empty($amenities)): ?>
                <div class="hotel-card-tags">
                    <?php foreach ($amenities as $amenity): ?>
                        <span class="hotel-card-tag">
                            <span class="material-symbols-outlined"><?= htmlspecialchars($amenity['icon']) ?></span>
                            <?= htmlspecialchars($amenity['title']) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <p><?= htmlspecialchars($hotel["description"]) ?></p>
            <p>ราคา: <?= htmlspecialchars($hotel["price"]) ?> บาท</p>
            <a href="hotel_detail.php?id=<?= (int) $hotel["id"] ?>" class="btn-details">ดูรายละเอียด</a>
        </div>
    </div>
    <?php
}

// Renders one hotel category section (used on both index.php and hotel.php
// so the "which hotels show under which heading" logic and markup can't
// drift between the two pages). Admin-curated categories list only the
// hotels manually assigned via hotel_category_items; an "auto" category
// (is_auto = 1) instead always lists every hotel, so owner-added hotels
// show up without an admin having to assign them.
function render_hotel_category(mysqli $conn, int $categoryId, string $title, bool $isAuto = false): void {
    if ($isAuto) {
        $stmt = $conn->prepare("
            SELECT hotels.*, hotel_images.image_path
            FROM hotels
            LEFT JOIN (
                SELECT MIN(id) as id, hotel_id
                FROM hotel_images
                GROUP BY hotel_id
            ) AS first_images ON first_images.hotel_id = hotels.id
            LEFT JOIN hotel_images ON hotel_images.id = first_images.id
            ORDER BY hotels.id DESC
        ");
    } else {
        $stmt = $conn->prepare("
            SELECT hotels.*, hotel_images.image_path
            FROM hotel_category_items hci
            JOIN hotels ON hotels.id = hci.hotel_id
            LEFT JOIN (
                SELECT MIN(id) as id, hotel_id
                FROM hotel_images
                GROUP BY hotel_id
            ) AS first_images ON first_images.hotel_id = hotels.id
            LEFT JOIN hotel_images ON hotel_images.id = first_images.id
            WHERE hci.category_id = ?
            ORDER BY hci.display_order ASC
        ");
        $stmt->bind_param("i", $categoryId);
    }
    $stmt->execute();
    $hotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $amenitiesByHotel = get_amenities_for_hotels($conn, array_column($hotels, 'id'));
    ?>
    <div class="popular-hotels">
        <div class="container">
            <h2 class="section-title"><?= htmlspecialchars($title) ?></h2>
            <?php if (!empty($hotels)): ?>
                <div class="hotel-list-wrapper">
                    <button class="scroll-btn left"><span class="material-symbols-outlined">chevron_left</span></button>
                    <div class="hotel-list">
                        <?php foreach ($hotels as $row): ?>
                            <?php render_hotel_card($row, $amenitiesByHotel[(int) $row['id']] ?? []); ?>
                        <?php endforeach; ?>
                    </div>
                    <button class="scroll-btn right"><span class="material-symbols-outlined">chevron_right</span></button>
                </div>
            <?php else: ?>
                <p>ยังไม่มีโรงแรมในหมวดนี้</p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function get_room_availability(mysqli $conn, int $room_type_id, string $checkin, string $checkout): int {
    $stmt = $conn->prepare("
        SELECT rt.quantity - COUNT(b.id) AS available
        FROM room_types rt
        LEFT JOIN bookings b
          ON b.room_type_id = rt.id
         AND b.checkin < ?
         AND b.checkout > ?
         AND b.payment_status <> 'rejected'
         AND b.owner_cleared = 0
        WHERE rt.id = ?
        GROUP BY rt.id, rt.quantity
    ");
    $stmt->bind_param("ssi", $checkout, $checkin, $room_type_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int) $row['available'] : 0;
}

// Validates and stores an uploaded payment slip image. Unlike
// save_cropped_image(), the input here is a raw $_FILES entry, so we check
// upload_err, a real MIME/dimension sniff via getimagesize(), and a size cap
// before writing anything to disk.
function save_payment_slip(array $file, string $destDir): string|false {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return false;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    $info = getimagesize($file['tmp_name']);
    $allowedMime = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    if ($info === false || !isset($allowedMime[$info['mime']])) {
        return false;
    }

    if (!is_dir($destDir)) {
        mkdir($destDir, 0777, true);
    }

    $filename = uniqid('slip_') . '.' . $allowedMime[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) {
        return false;
    }

    return $filename;
}

// Returns [rawToken, hashedToken]. The raw token goes out in the emailed
// link; only the hash is stored, so a DB leak alone can't be used to reset
// an account (same idea as never storing plaintext passwords).
function generate_secure_token(): array {
    $raw = bin2hex(random_bytes(32));
    return [$raw, hash('sha256', $raw)];
}

// Sends an email through the Resend HTTP API (https://resend.com/docs/api-reference/emails/send-email).
// Requires config/resend.php to have been loaded first. Returns false on any
// transport or API error instead of throwing, since a failed notification
// email shouldn't break the page that triggered it.
function send_email_via_resend(string $toEmail, string $subject, string $html): bool {
    if (!RESEND_API_KEY) {
        return false;
    }

    $payload = json_encode([
        'from'    => MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>',
        'to'      => [$toEmail],
        'subject' => $subject,
        'html'    => $html,
    ]);

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response !== false && $status >= 200 && $status < 300;
}
