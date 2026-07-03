<?php
require_once "config/database.php";
require_once "includes/functions.php";

header('Content-Type: application/json');

$room_type_id = (int) ($_GET["room_type_id"] ?? 0);
$checkin  = $_GET["checkin"] ?? '';
$checkout = $_GET["checkout"] ?? '';

$checkinDate  = DateTime::createFromFormat('Y-m-d', $checkin);
$checkoutDate = DateTime::createFromFormat('Y-m-d', $checkout);

if ($room_type_id <= 0 || !$checkinDate || !$checkoutDate) {
    http_response_code(400);
    echo json_encode(["error" => "invalid parameters"]);
    exit;
}

$available = get_room_availability($conn, $room_type_id, $checkin, $checkout);

echo json_encode(["available" => max(0, $available)]);
