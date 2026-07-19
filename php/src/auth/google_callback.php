<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => getenv('GOOGLE_CLIENT_ID'),
    'clientSecret' => getenv('GOOGLE_CLIENT_SECRET'),
    'redirectUri'  => getenv('GOOGLE_REDIRECT_URI'),
]);

// ตรวจสอบ state
if (empty($_GET['state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    unset($_SESSION['oauth2state']);
    exit('Invalid state');
}

// ดึง token
$token = $provider->getAccessToken('authorization_code', [
    'code' => $_GET['code']
]);

// ดึงข้อมูล user จาก Google
$googleUser = $provider->getResourceOwner($token);
$googleId   = $googleUser->getId();
$name       = $googleUser->getName();
$email      = $googleUser->getEmail();
$avatar     = $googleUser->getAvatar();

// เช็คว่ามี user นี้ในฐานข้อมูลหรือยัง
$stmt = $conn->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
$stmt->bind_param("ss", $googleId, $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // มีอยู่แล้ว → อัพเดท google_id
    $user = $result->fetch_assoc();
    $stmt2 = $conn->prepare("UPDATE users SET google_id = ? WHERE id = ?");
    $stmt2->bind_param("si", $googleId, $user['id']);
    $stmt2->execute();
} else {
    // ยังไม่มี → สร้างใหม่
    $stmt2 = $conn->prepare("INSERT INTO users (full_name, email, google_id, profile_picture, role) VALUES (?, ?, ?, ?, 'user')");
    $stmt2->bind_param("ssss", $name, $email, $googleId, $avatar);
    $stmt2->execute();
    $user = ['id' => $conn->insert_id, 'full_name' => $name, 'email' => $email, 'role' => 'user', 'profile_picture' => $avatar];
}

// Google already verifies the account's email, so mark it verified here too.
$verifyStmt = $conn->prepare("UPDATE users SET email_verified_at = NOW() WHERE id = ? AND email_verified_at IS NULL");
$verifyStmt->bind_param("i", $user['id']);
$verifyStmt->execute();
$verifyStmt->close();

// สร้าง Session
$_SESSION['user']            = $user['full_name'];
$_SESSION['user_id']         = $user['id'];
$_SESSION['user_email']      = $user['email'];
$_SESSION['role']            = $user['role'];
$_SESSION['profile_picture'] = $user['profile_picture'] ?? 'default.jpg';

header('Location: ../index.php');
exit;