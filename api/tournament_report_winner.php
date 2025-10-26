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

if (!auth_userid()) exit("Login required");

$match_id = intval($_POST['match_id'] ?? 0);
$winner_id = intval($_POST['winner_id'] ?? 0);

if (!$match_id || !$winner_id) exit("Missing data");

$stmt = $pdo->prepare("UPDATE tournament_matches SET winner_id=?, finished=1 WHERE id=?");
$stmt->execute([$winner_id, $match_id]);

echo json_encode(["success"=>true]);
