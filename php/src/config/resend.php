<?php
// config/resend.php
require_once __DIR__ . '/env.php';

define('RESEND_API_KEY', getenv('RESEND_API_KEY'));
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'onboarding@resend.dev');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'JustHottel');

// Falls back to the current request's own host so links work in dev
// (localhost:8080) without needing APP_URL set until a real domain is live.
$app_url = getenv('APP_URL');
if (!$app_url) {
    $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $app_url = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
define('APP_URL', rtrim($app_url, '/'));
