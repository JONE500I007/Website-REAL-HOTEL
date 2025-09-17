<?php
require_once "database.php";

if (isset($_GET['query'])) {
    $search = "%" . $_GET['query'] . "%";

    $sql = "SELECT id, hotel_name, location, price, description
            FROM hotels 
            WHERE hotel_name LIKE ? OR location LIKE ? OR description LIKE ? 
            LIMIT 10";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();

    $hotels = [];
    while ($row = $result->fetch_assoc()) {
        $hotels[] = $row;
    }

    echo json_encode($hotels);
}
?>