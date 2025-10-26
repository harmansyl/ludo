<?php declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


csrf_verify();
$data = require_json();
$pdo = db();

header('Content-Type: application/json');
csrf_verify();
$userId = auth_require();  // <--- add this
audit_log($userId, "LOGOUT", "User logged out");
logout_user();
json_out(['ok'=>true]);
