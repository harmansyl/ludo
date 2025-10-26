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

$data = require_json();
$roomCode = trim((string)($data['room'] ?? ''));
if ($roomCode === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing room code']);
    exit;
}

// fetch room id (case-insensitive)
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE UPPER(code) = UPPER(?) LIMIT 1");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    respond([
        'ok' => false,
        'error' => 'Room not found',
        'debugRoomCode' => $roomCode
    ]);
}

$roomId = (int)$room['id'];

// clear old pieces if restart
$pdo->prepare("DELETE FROM room_pieces WHERE room_id = ?")->execute([$roomId]);

// load players
$stmt = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? ORDER BY position ASC");
$stmt->execute([$roomId]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$players) {
    echo json_encode(['ok' => false, 'error' => 'No players in room']);
    exit;
}

// create 4 pieces per player (all start at home = -1)
$stmt = $pdo->prepare("INSERT INTO room_pieces (room_id, user_id, piece_index, position) VALUES (?, ?, ?, -1)");
foreach ($players as $p) {
    for ($i = 0; $i < 4; $i++) {
        $stmt->execute([$roomId, (int)$p['user_id'], $i]);
    }
}

// set first turn user (player 0) and initialize dice with 6
$firstUser = (int)$players[0]['user_id'];
$stmt = $pdo->prepare("
    INSERT INTO game_state (room_id, turn_user, last_dice, winner_user, updated_at)
    VALUES (?, ?, 6, NULL, NOW())
    ON DUPLICATE KEY UPDATE
        turn_user = VALUES(turn_user),
        last_dice = VALUES(last_dice),
        winner_user = NULL,
        updated_at = NOW()
");
$stmt->execute([$roomId, $firstUser]);

echo json_encode([
    'ok' => true,
    'started' => true,
    'turnUser' => $firstUser,
    'turnDice' => 6
]);
