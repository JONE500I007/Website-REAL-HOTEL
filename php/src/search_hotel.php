<?php
require_once "config/database.php";

if (!isset($_GET['query'])) {
    echo json_encode([]);
    exit;
}

$search = "%" . $_GET['query'] . "%";
$stmt   = $conn->prepare("
    SELECT id, hotel_name, location, price, description
    FROM hotels
    WHERE hotel_name LIKE ? OR location LIKE ? OR description LIKE ?
    LIMIT 10
");
$stmt->bind_param("sss", $search, $search, $search);
$stmt->execute();
$hotels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

header("Content-Type: application/json");
echo json_encode($hotels);
