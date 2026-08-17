<?php
// $conn ต้องถูก include ก่อนใช้ไฟล์นี้
require_once __DIR__ . '/functions.php';
?>
<header class="navbar">
    <div class="container">
        <div class="logo">
            <a href="index.php"><img src="image/hotel-icon-coupon-codes-hotel.png" alt="Logo"></a>
        </div>
        <button class="hamburger" id="hamburger" aria-label="Toggle menu" aria-expanded="false">
            <span class="bar"></span>
            <span class="bar"></span>
            <span class="bar"></span>
        </button>
        <div class="mobile-nav" id="mobileNav">
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
                        <img src="<?= htmlspecialchars(resolve_upload_src($_SESSION["profile_picture"] ?? null)) ?>" alt="Profile">
                        <span><?= htmlspecialchars($_SESSION["user"]) ?></span>
                    </div>
                    <div class="dropdown-menu" id="dropdownMenu">
                        <a href="edit_profile.php">แก้ไขโปรไฟล์</a>
                        <?php if (in_array($_SESSION["role"], ["owner", "admin"])): ?>
                            <?php
                                $nav_owner_id = $_SESSION["user_id"];
                                $nav_stmt = $conn->prepare("SELECT id FROM hotels WHERE owner_id = ?");
                                $nav_stmt->bind_param("i", $nav_owner_id);
                                $nav_stmt->execute();
                                $nav_hasHotel = $nav_stmt->get_result()->num_rows > 0;
                                $nav_stmt->close();
                            ?>
                            <a href="manage_hotels.php"><?= $nav_hasHotel ? "จัดการโรงแรม" : "เพิ่มโรงแรม" ?></a>
                        <?php endif; ?>
                        <?php if ($_SESSION["role"] === "owner" && $nav_hasHotel): ?>
                            <a href="owner_dashboard.php">แดชบอร์ดโรงแรม</a>
                            <a href="dashboard_owner.php">ตรวจสอบการจองของโรงแรม</a>
                        <?php endif; ?>
                        <?php if ($_SESSION["role"] === "admin"): ?>
                            <a href="admin_dashboard.php">แดชบอร์ดระบบ</a>
                            <a href="admin_manage.php">จัดการระบบ</a>
                        <?php endif; ?>
                        <a href="board.php">ดูการจองโรงแรม</a>
                        <a href="logout.php">ออกจากระบบ</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </div>
</header>
