<?php
// api/force_roll.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/boot.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_login();

if (empty($_SESSION['is_admin'])) {
    json_error("Admin only", 403);
}

$data   = require_json();
$gameId = (int)($data['game_id'] ?? 0);
$val    = (int)($data['value'] ?? 0);
$seat   = isset($data['seat']) && $data['seat'] !== '' ? (int)$data['seat'] : null;

if ($gameId <= 0 || $val < 1 || $val > 6 || ($seat !== null && ($seat < 1 || $seat > 4))) {
    json_error("Bad params", 422);
}

$pdo = db();
$stmt = $pdo->prepare("INSERT INTO forced_dice (game_id, seat, value) VALUES (?,?,?)");
$stmt->execute([$gameId, $seat, $val]);

json_success(['queued' => $val, 'seat' => $seat]);
