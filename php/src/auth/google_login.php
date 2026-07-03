<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

$provider = new League\OAuth2\Client\Provider\Google([
    'clientId'     => getenv('GOOGLE_CLIENT_ID'),
    'clientSecret' => getenv('GOOGLE_CLIENT_SECRET'),
    'redirectUri'  => getenv('GOOGLE_REDIRECT_URI'),
]);

$authUrl = $provider->getAuthorizationUrl([
    'prompt' => 'select_account',
]);
$_SESSION['oauth2state'] = $provider->getState();

header('Location: ' . $authUrl);
exit;