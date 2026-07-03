<?php
// config/mail.php
require_once __DIR__ . '/env.php';

define('MAIL_HOST', getenv('MAIL_HOST'));
define('MAIL_PORT', (int) getenv('MAIL_PORT'));
define('MAIL_USERNAME', getenv('MAIL_USERNAME'));
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD'));
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME'));
