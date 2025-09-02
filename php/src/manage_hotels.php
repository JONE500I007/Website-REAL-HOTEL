<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// ตรวจสอบสิทธิ์ (เฉพาะ owner เท่านั้นที่เข้าได้)
if ($_SESSION["role"] !== "owner") {
    echo "<div style='color:red; font-weight:bold;'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hotel_name  = $_POST["hotel_name"] ?? '';
    $location    = $_POST["location"] ?? '';
    $price       = $_POST["price"] ?? '';
    $description = $_POST["description"] ?? '';
    $owner_id    = $_SESSION["user_id"];

    if (!empty($hotel_name) && !empty($location) && !empty($price)) {
        // เช็คว่ามีโรงแรมนี้ในระบบรึยัง
        $check_sql = "SELECT id FROM hotels WHERE owner_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $owner_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // มีอยู่แล้ว: อัปเดต
            $sql = "UPDATE hotels SET hotel_name = ?, location = ?, price = ?, description = ? WHERE owner_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $hotel_name, $location, $price, $description, $owner_id);
        } else {
            // ยังไม่มี: เพิ่มใหม่
            $sql = "INSERT INTO hotels (hotel_name, location, price, description, owner_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssi", $hotel_name, $location, $price, $description, $owner_id);
        }

        if ($stmt->execute()) {
            // ได้ ID โรงแรม
            $hotel_id = ($check_result->num_rows > 0)
                ? $check_result->fetch_assoc()["id"]
                : $conn->insert_id;

            // อัปโหลดรูป
            if (isset($_FILES["hotel_images"])) {
                $upload_dir = __DIR__ . "/uploads/hotels/"; // absolute path จริง
                $relative_dir = "uploads/hotels/";          // path ที่จะเอาไปใช้ในเว็บ

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['hotel_images']['tmp_name'] as $index => $tmp_name) {
                    if ($_FILES['hotel_images']['error'][$index] === UPLOAD_ERR_OK) {
                        $original_name = basename($_FILES['hotel_images']['name'][$index]);
                        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                        $new_name = uniqid("hotel_") . "." . $ext;

                        $destination = $upload_dir . $new_name;   // path เต็มสำหรับเก็บไฟล์
                        $relative_path = $relative_dir . $new_name; // path ที่เก็บลง DB

                        if (move_uploaded_file($tmp_name, $destination)) {
                            $img_sql = "INSERT INTO hotel_images (hotel_id, image_path) VALUES (?, ?)";
                            $img_stmt = $conn->prepare($img_sql);
                            $img_stmt->bind_param("is", $hotel_id, $relative_path);
                            $img_stmt->execute();
                            $img_stmt->close();
                        } else {
                            echo "<div style='color:red'>Upload failed: $original_name</div>";
                        }
                    }
                }
            }

            // redirect หลังจากเสร็จทุกอย่าง
            header("Location: manage_hotels.php?success=1");
            exit;
        } else {
            echo "<div class='alert alert-danger'>เกิดข้อผิดพลาด: " . $stmt->error . "</div>";
        }

        $stmt->close();
        $check_stmt->close();
    } else {
        echo "<div class='alert alert-danger'>กรุณากรอกข้อมูลให้ครบ</div>";
    }
}

$hotel = [
    "hotel_name" => "",
    "location" => "",
    "price" => "",
    "description" => ""
];

//โหลดเฉลาะข้อมูล user คนๆ นั้น
$owner_id = $_SESSION["user_id"];
$sql = "SELECT * FROM hotels WHERE owner_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $hotel = $result->fetch_assoc();
}

$stmt->close();
?>

<?php if (isset($_GET["success"])): ?>
    <div class="alert alert-success">บันทึกโรงแรมเรียบร้อยแล้ว</div>
<?php endif; ?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เพิ่มโรงแรมใหม่</title>
    <link rel="stylesheet" href="style2.css?v=1.2">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="logo">
                <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Logo">
            </div>
            <nav class="nav-links">
                <a href="index.php">Home</a>
                <a href="#">Hotel</a>
                <a href="contact.php">Contact</a>
            </nav>
            <div class="auth-links">
            <?php if (!isset($_SESSION["user"])): ?>
                <a href="register.php" class="btn-signup">สมัครสมาชิก</a>
                <a href="login.php" class="btn-signup">เข้าสู่ระบบ</a>
            <?php else: ?>
                <div class="profile-menu">
                    <div class="profile-icon" onclick="toggleMenu()">
                        <img src="uploads/<?= $_SESSION["profile_picture"] ?? 'default.jpg' ?>" alt="Profile">
                        <span><?= $_SESSION["user"] ?></span>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="edit_profile.php">แก้ไขโปรไฟล์</a>
                        <?php if ($_SESSION["role"] === "owner"): ?>
                            <a href="manage_hotels.php">แก้ไขโรงแรม</a>
                        <?php endif; ?>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="form-container">
        <div class="form-card">
            <h2>โรงแรมของคุณ</h2>
            <form action="manage_hotels.php" method="post" enctype="multipart/form-data">
                <input type="text" name="hotel_name" placeholder="ชื่อโรงแรม" value="<?= htmlspecialchars($hotel["hotel_name"])?>">
                <input type="text" name="location" placeholder="ที่ตั้ง" value="<?= htmlspecialchars($hotel["location"])?>">
                <input type="text" name="price" placeholder="ราคาต่อคืน (บาท)" value="<?= htmlspecialchars($hotel["price"])?>">
                <input type="text" name="description" placeholder="รายละเอียด" value="<?= htmlspecialchars($hotel["description"])?>">
                <label>อัปโหลดรูปภาพโรงแรม:</label>
                <input type="file" name="hotel_images[]" multiple accept="image/*">
                <button type="submit">บันทึกข้อมูล</button>
            </form>
            <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบโรงแรมนี้?');">
                <input type="hidden" name="delete_hotel_id" value="<?= $row['id'] ?>">
                <button type="submit" class="btn btn-danger">ลบโรงแรม</button>
            </form>
            <p><a href="index.php">กลับหน้าหลัก</a></p>
        </div>
    </div>

    <!-- just my under1 -->

    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <img src="image\hotel-icon-coupon-codes-hotel.png" alt="Footer Logo">
                </div>
                <p>© 2025 PNVC, นายครรชิดพล เพ็งเอียด</p>
                <div class="social-icons">
                    <a href="#"><img src="image\twwokX.png" alt="Twitter_X"></a>
                    <a href="#"><img src="image\insatagem.png" alt="Instagram"></a>
                    <a href="#"><img src="image\fackbookicon.png" alt="Facebook"></a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>
<script>
function toggleMenu() {
    const menu = document.getElementById("dropdownMenu");
    menu.style.display = (menu.style.display === "block") ? "none" : "block";
}
document.addEventListener('click', function(event) {
    const menu = document.getElementById("dropdownMenu");
    const profileIcon = document.querySelector('.profile-icon');
    if (menu && !profileIcon.contains(event.target)) {
        menu.style.display = "none";
    }
});
</script>