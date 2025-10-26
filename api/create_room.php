<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

auth_require();
$user = current_user();
$pdo  = db();

// Generate a unique room code
function generateRoomCode(PDO $pdo): string {
    do {
        $code = strtoupper(bin2hex(random_bytes(3))); // e.g. A1B2C3
        $st = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
        $st->execute([$code]);
    } while ($st->fetch());
    return $code;
}

$code = generateRoomCode($pdo);

// ✅ Insert room with created_by (host)
$stmt = $pdo->prepare("INSERT INTO rooms (code, created_by, status, created_at) VALUES (?, ?, 'waiting', NOW())");
$stmt->execute([$code, $user['id']]);
$roomId = (int)$pdo->lastInsertId();

// Insert initial game_state
$stmt = $pdo->prepare("INSERT INTO game_state (room_id, turn_user, last_dice, winner_user) VALUES (?, NULL, NULL, NULL)");
$stmt->execute([$roomId]);

// ✅ Insert creator as host in room_players (always red)
$stmt = $pdo->prepare("INSERT INTO room_players (room_id, user_id, color, position, is_winner, joined_at) 
                       VALUES (?, ?, 'red', 0, 0, NOW())");
$stmt->execute([$roomId, $user['id']]);

echo json_encode([
    'ok'      => true,
    'room'    => $code,
    'creator' => $user['username'],
    'color'   => 'red'
]);
