<?php
require_once __DIR__.'/../lib/csrf.php';

require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

csrf_verify();

header('Content-Type: application/json');
echo json_encode(['csrf' => get_csrf_token()]);
