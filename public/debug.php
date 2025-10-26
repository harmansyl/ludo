<?php
session_start();

header('Content-Type: application/json');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

echo json_encode([
    'session_id'   => session_id(),
    'csrf_token'   => $_SESSION['csrf_token'],
    'cookies_sent' => $_COOKIE
], JSON_PRETTY_PRINT);
