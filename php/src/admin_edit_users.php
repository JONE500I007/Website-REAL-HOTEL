<?php
session_start();
require_once "database.php";

// check permissions
if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    echo "<div class='alert alert-danger'>คุณไม่มีสิทธิ์เข้าหน้านี้</div>";
    exit;
}

// update users
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["update_user"])) {
    $id = intval($_POST["id"]);
    $full_name = $_POST["full_name"];
    $email = $_POST["email"];
    $phone = $_POST["phone_number"];
    $role = $_POST["role"];

    $sql = "UPDATE users SET full_name=?, email=?, phone_number=?, role=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssi", $full_name, $email, $phone, $role, $id);

    if ($stmt->execute()) {
        $msg = "อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว";
    } else {
        $msg = "เกิดข้อผิดพลาด: " . $stmt->error;
    }
    $stmt->close();
}

// Retrieve all user data
$sql = "SELECT * FROM users ORDER BY id ASC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการผู้ใช้ (Admin)</title>
    <link rel="stylesheet" href="style2.css?v=1.6">
</head>
<body>
    <div class="container">
        <h2 class="booking-title">จัดการผู้ใช้</h2>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <table class="booking-list">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ชื่อเต็ม</th>
                    <th>อีเมล</th>
                    <th>เบอร์โทร</th>
                    <th>Role</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <form method="post">
                        <td><?= $row["id"] ?></td>
                        <td><input type="text" name="full_name" value="<?= htmlspecialchars($row["full_name"]) ?>"></td>
                        <td><input type="email" name="email" value="<?= htmlspecialchars($row["email"]) ?>"></td>
                        <td><input type="text" name="phone_number" value="<?= htmlspecialchars($row["phone_number"]) ?>"></td>
                        <td>
                            <select name="role" class="styled-select">
                                <option value="user" <?= $row["role"]=="user" ? "selected" : "" ?>>User</option>
                                <option value="owner" <?= $row["role"]=="owner" ? "selected" : "" ?>>Owner</option>
                                <option value="admin" <?= $row["role"]=="admin" ? "selected" : "" ?>>Admin</option>
                            </select>
                        </td>
                        <td>
                            <input type="hidden" name="id" value="<?= $row["id"] ?>">
                            <button type="submit" name="update_user">บันทึก</button>
                        </td>
                    </form>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="booking-back">
            <a href="admin_manage.php">⬅ กลับหน้าหลัก</a>
        </div>
    </div>
</body>
</html>