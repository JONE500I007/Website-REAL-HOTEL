<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/mail.php';

function sendBookingEmail($toEmail, $toName, $hotelName, $checkIn, $checkOut) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_USERNAME, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'ยืนยันการจองโรงแรม — ' . $hotelName;
        $mail->Body    = "
            <h2>ยืนยันการจองโรงแรม</h2>
            <p>เรียน คุณ {$toName}</p>
            <p>การจองของคุณได้รับการยืนยันแล้วครับ</p>
            <ul>
                <li><strong>โรงแรม:</strong> {$hotelName}</li>
                <li><strong>เช็คอิน:</strong> {$checkIn}</li>
                <li><strong>เช็คเอาท์:</strong> {$checkOut}</li>
            </ul>
            <p>ขอบคุณที่ใช้บริการ JustHottel ครับ</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: " . $e->getMessage());
        return false;
    }
}