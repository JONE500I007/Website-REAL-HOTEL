<?php
session_start();
require_once "database.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

// Verify permissions (only the owner can access)
if ($_SESSION["role"] !== "owner") {
    echo "<div style='color:red; font-weight:bold;'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_hotel_id"])) {
    $delete_id = intval($_POST["delete_hotel_id"]);
    $owner_id = $_SESSION["user_id"];

    // you owner?
    $check_sql = "SELECT * FROM hotels WHERE id = ? AND owner_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $delete_id, $owner_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        // If it's true, delete the hotel
        $delete_sql = "DELETE FROM hotels WHERE id = ? AND owner_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("ii", $delete_id, $owner_id);
        $delete_stmt->execute();

        // Delete images in hotel_images (optional)
        $delete_img_sql = "DELETE FROM hotel_images WHERE hotel_id = ?";
        $img_stmt = $conn->prepare($delete_img_sql);
        $img_stmt->bind_param("i", $delete_id);
        $img_stmt->execute();

        echo "<script>alert('ลบโรงแรมเรียบร้อยแล้ว'); window.location.href='manage_hotels.php';</script>";
        exit;
    } else {
        echo "<script>alert('ไม่พบข้อมูลโรงแรมนี้ หรือคุณไม่มีสิทธิ์ลบ');</script>";
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $hotel_name  = $_POST["hotel_name"] ?? '';
    $location    = $_POST["location"] ?? '';
    $price       = $_POST["price"] ?? '';
    $description = $_POST["description"] ?? '';
    $facilities = $_POST["facilities"] ?? '';
    $surrounding = $_POST["surrounding"] ?? '';
    $type = $_POST["type"] ?? '';
    $owner_id    = $_SESSION["user_id"];

    if (!empty($hotel_name) && !empty($location) && !empty($price)) {
        // Check if this hotel is in the db
        $check_sql = "SELECT id FROM hotels WHERE owner_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $owner_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            // if have : just update
            $sql = "UPDATE hotels SET hotel_name = ?, location = ?, price = ?, description = ?, facilities = ?, surrounding = ?, type = ? WHERE owner_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $hotel_name, $location, $price, $description, $facilities, $surrounding, $type, $owner_id);
        } else {
            // if not have: add one
            $sql = "INSERT INTO hotels (hotel_name, location, price, description, facilities, surrounding, type, owner_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssi", $hotel_name, $location, $price, $description, $facilities, $surrounding, $type, $owner_id);
        }

        if ($stmt->execute()) {
            // got ID โรงแรม
            $hotel_id = ($check_result->num_rows > 0)
                ? $check_result->fetch_assoc()["id"]
                : $conn->insert_id;

            // uoload image
            if (isset($_FILES["hotel_images"])) {
                $upload_dir = __DIR__ . "/uploads/hotels/"; // absolute path real
                $relative_dir = "uploads/hotels/";          // path on web

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }

                foreach ($_FILES['hotel_images']['tmp_name'] as $index => $tmp_name) {
                    if ($_FILES['hotel_images']['error'][$index] === UPLOAD_ERR_OK) {
                        $original_name = basename($_FILES['hotel_images']['name'][$index]);
                        $ext = pathinfo($original_name, PATHINFO_EXTENSION);
                        $new_name = uniqid("hotel_") . "." . $ext;

                        $destination = $upload_dir . $new_name;   // path for full file
                        $relative_path = $relative_dir . $new_name; // path in to the DB

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

            // redirect af all done
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
    "description" => "",
    "facilities" => "",
    "surrounding" => "",
    "type" => ""
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
    <title>เพิ่มหรือแก้ไขโรงแรมใหม่</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="style2.css?v=1.5">
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
                <a href="hotel.php">Hotel</a>
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
                            <?php
                                $owner_id = $_SESSION["user_id"];
                                $check_sql = "SELECT id FROM hotels WHERE owner_id = ?";
                                $check_stmt = $conn->prepare($check_sql);
                                $check_stmt->bind_param("i", $owner_id);
                                $check_stmt->execute();
                                $check_result = $check_stmt->get_result();
                                $hasHotel = $check_result->num_rows > 0;
                                $check_stmt->close();
                            ?>
                            <a href="manage_hotels.php">
                                <?= $hasHotel ? "แก้ไขโรงแรม" : "เพิ่มโรงแรม" ?>
                            </a>
                        <?php endif; ?>
                        <a href="board.php">ดูการจองโรงแรม</a>
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
                <input type="text" name="facilities" placeholder="สิ่งอำนวยความสะดวก" value="<?= htmlspecialchars($hotel["facilities"])?>">
                <input type="text" name="surrounding" placeholder="บริเวณโดยรอบ" value="<?= htmlspecialchars($hotel["surrounding"])?>">
                <!--
                <div class="custom-select-wrapper">
                    <div class="custom-select" id="hotelTypeSelect">
                        <div class="custom-select-trigger">
                            <?= !empty($hotel["type"]) ? htmlspecialchars($hotel["type"]) : "-- เลือกประเภทโรงแรม --" ?>
                        </div>
                        <div class="custom-options">
                            <span class="custom-option <?= ($hotel["type"] == "โรงแรมราคาประหยัด") ? "selected" : "" ?>" data-value="Budget">ราคาประหยัด</span>
                            <span class="custom-option <?= ($hotel["type"] == "โรงแรมหรู") ? "selected" : "" ?>" data-value="Luxury">หรู</span>
                            <span class="custom-option <?= ($hotel["type"] == "โรงแรมครอบครัว") ? "selected" : "" ?>" data-value="Family">ครอบครัว</span>
                        </div>
                        
                    </div>
                    -->
                    <!-- hidden input เอาไว้ส่งค่าไป PHP -->
                     <!--
                    <input type="hidden" name="type" id="hotelTypeInput" value="<?= htmlspecialchars($hotel["type"]) ?>">
                </div>
                -->
                <label>อัปโหลดรูปภาพโรงแรม:</label>
                <input type="file" name="hotel_images[]" multiple accept="image/*">
                <button type="submit">บันทึกข้อมูล</button>
            </form>
            <form method="POST" action="manage_hotels.php" onsubmit="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบโรงแรมนี้?');">
                <?php if (!empty($hotel['id'])): ?>
                    <input type="hidden" name="delete_hotel_id" value="<?= htmlspecialchars($hotel['id']) ?>">
                    <button type="submit" class="btn btn-danger">ลบ</button>
                <?php else: ?>
                    <p style="color:gray;">ยังไม่มีโรงแรมให้ลบ</p>
                <?php endif; ?>
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
                    <a href="https://x.com/FGgez777"><img src="image\twwokX.png" alt="Twitter_X"></a>
                    <a href="https://www.instagram.com/face.2339/?igsh=dWh6eGtmbjVpanRt"><img src="image\insatagem.png" alt="Instagram"></a>
                    <a href="https://www.facebook.com/face.pengeid/"><img src="image\fackbookicon.png" alt="Facebook"></a>
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

<script>
document.addEventListener("DOMContentLoaded", () => {
    const customSelect = document.querySelector(".custom-select");
    const trigger = customSelect.querySelector(".custom-select-trigger");
    const options = customSelect.querySelectorAll(".custom-option");
    const hiddenInput = document.getElementById("hotelTypeInput");

    // toggle dropdown
    trigger.addEventListener("click", () => {
        customSelect.classList.toggle("open");
    });

    // เลือก option
    options.forEach(option => {
        option.addEventListener("click", () => {
            // set text
            trigger.textContent = option.textContent;
            // set value hidden input
            hiddenInput.value = option.dataset.value;

            // clear old selection
            options.forEach(o => o.classList.remove("selected"));
            option.classList.add("selected");

            // close dropdown
            customSelect.classList.remove("open");
        });
    });

    // ปิด dropdown ถ้าคลิกข้างนอก
    document.addEventListener("click", (e) => {
        if (!customSelect.contains(e.target)) {
            customSelect.classList.remove("open");
        }
    });
});
</script>