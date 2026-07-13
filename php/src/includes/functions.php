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

function get_room_availability(mysqli $conn, int $room_type_id, string $checkin, string $checkout): int {
    $stmt = $conn->prepare("
        SELECT rt.quantity - COUNT(b.id) AS available
        FROM room_types rt
        LEFT JOIN bookings b
          ON b.room_type_id = rt.id
         AND b.checkin < ?
         AND b.checkout > ?
         AND b.payment_status <> 'rejected'
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
