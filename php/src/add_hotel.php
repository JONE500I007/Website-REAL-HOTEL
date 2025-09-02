<?php
session_start();
require_once "database.php";
if (!isset($_SESSION["role"]) || $_SESSION["role"] !== "owner") {
    header("Location: login.php");
    exit;
}

$hotel_id = $conn->insert_id;

foreach ($_FILES["hotel_images"]["tmp_name"] as $index => $tmp_name) {
    $file_name = $_FILES["hotel_images"]["name"][$index];
    $target = "uploads/" . time() . "_" . basename($file_name);
    move_uploaded_file($tmp_name, $target);

    $img_sql = "INSERT INTO hotel_images (hotel_id, image_path) VALUES (?, ?)";
    $img_stmt = $conn->prepare($img_sql);
    $img_stmt->bind_param("is", $hotel_id, $target);
    $img_stmt->execute();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>เพิ่มข้อมูลโรงแรม</title>
</head>
<body>

    <h2>เพิ่มข้อมูลโรงแรม</h2>

    <form action="add_hotel.php" method="post" enctype="multipart/form-data">

        <label for="hotel_images">เลือกรูปภาพ:</label><br>
        <input type="file" id="hotel_images" name="hotel_images[]" multiple><br><br>
        
        <input type="submit" value="เพิ่มภาพ">
        
    </form>

</body>
</html>