<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

csrf_verify();

// Get JSON input
$data = require_json();

// Get DB connection
$pdo = db();
if (!auth_userid()) {
    http_response_code(403);
    exit("Login required");
}

$tournament_id = intval($_POST['tournament_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$key = trim($_POST['special_key'] ?? '');

if (!$tournament_id || !$username || !$phone || !$key) {
    exit("All fields required");
}

$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id=? AND status='upcoming'");
$stmt->execute([$tournament_id]);
$t = $stmt->fetch();
if (!$t) exit("Tournament not found or closed");

$stmt = $pdo->prepare("INSERT INTO tournament_entries (tournament_id, user_id, username, phone, special_key) VALUES (?,?,?,?,?)");
try {
    $stmt->execute([$tournament_id, auth_userid(), $username, $phone, $key]);
    echo json_encode(["success"=>true]);
} catch (PDOException $e) {
    echo json_encode(["success"=>false, "error"=>"Already joined?"]);
}
