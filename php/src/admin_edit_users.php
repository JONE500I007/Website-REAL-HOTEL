<?php
session_start();
require_once "config/database.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "admin") {
    header("Location: index.php");
    exit;
}

$msg     = '';
$msgType = 'success';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["update_user"])) {
        $id        = (int) $_POST["id"];
        $full_name = $_POST["full_name"];
        $email     = $_POST["email"];
        $phone     = $_POST["phone_number"];
        $role      = $_POST["role"];

        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=?, phone_number=?, role=? WHERE id=?");
        $stmt->bind_param("ssssi", $full_name, $email, $phone, $role, $id);
        $ok      = $stmt->execute();
        $msg     = $ok ? "อัปเดตข้อมูลผู้ใช้เรียบร้อยแล้ว" : "เกิดข้อผิดพลาด: " . $stmt->error;
        $msgType = $ok ? 'success' : 'danger';
        $stmt->close();
    }

    if (isset($_POST["delete_user"])) {
        $id   = (int) $_POST["id"];
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $id);
        $msg     = $stmt->execute() ? "ลบผู้ใช้เรียบร้อยแล้ว" : "เกิดข้อผิดพลาดในการลบ: " . $stmt->error;
        $msgType = 'success';
        $stmt->close();
    }
}

$result = $conn->query("SELECT * FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ (Admin)</title>
    <link rel="icon" type="image/png" href="image/hotel-icon-coupon-codes-hotel.png">
    <link rel="stylesheet" href="assets/css/style2.css?v=<?= filemtime(__DIR__ . '/assets/css/style2.css') ?>">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0" rel="stylesheet">
</head>
<body>

<?php require_once "includes/header.php"; ?>

<div class="container">
    <h2 class="booking-title"><span class="material-symbols-outlined">group</span> จัดการผู้ใช้</h2>

    <?php if (!empty($msg)): ?>
        <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
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
                    <td data-label="ID"><?= $row["id"] ?></td>
                    <td data-label="ชื่อเต็ม"><input type="text" name="full_name" value="<?= htmlspecialchars($row["full_name"]) ?>"></td>
                    <td data-label="อีเมล"><input type="email" name="email" value="<?= htmlspecialchars($row["email"]) ?>"></td>
                    <td data-label="เบอร์โทร"><input type="text" name="phone_number" value="<?= htmlspecialchars($row["phone_number"]) ?>"></td>
                    <td data-label="Role">
                        <select name="role" class="styled-select">
                            <option value="user"  <?= $row["role"] === "user"  ? "selected" : "" ?>>User</option>
                            <option value="owner" <?= $row["role"] === "owner" ? "selected" : "" ?>>Owner</option>
                            <option value="admin" <?= $row["role"] === "admin" ? "selected" : "" ?>>Admin</option>
                        </select>
                    </td>
                    <td data-label="การจัดการ">
                        <input type="hidden" name="id" value="<?= $row["id"] ?>">
                        <div class="admin-actions-cell">
                            <button type="submit" name="update_user" class="btn-table-save">
                                <span class="material-symbols-outlined">save</span> บันทึก
                            </button>
                            <button type="submit" name="delete_user" class="btn-table-delete"
                                    onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบผู้ใช้นี้?');">
                                <span class="material-symbols-outlined">delete</span> ลบ
                            </button>
                        </div>
                    </td>
                </form>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="booking-back">
        <a href="admin_manage.php"><span class="material-symbols-outlined">arrow_back</span> กลับเมนู Admin</a>
    </div>
</div>

<?php require_once "includes/footer.php"; ?>
</body>
</html>
