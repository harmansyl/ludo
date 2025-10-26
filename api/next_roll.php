<?php
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');

auth_require();
$pdo = db();

$data = $_GET ?: require_json();
$roomCode = trim((string)($data['room'] ?? ''));
if ($roomCode === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing room code']);
    exit;
}

// Get room
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE code=? LIMIT 1");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}
$roomId = (int)$room['id'];

// Get current state
$stmt = $pdo->prepare("SELECT turn_user, turn_color FROM game_state WHERE room_id=? LIMIT 1");
$stmt->execute([$roomId]);
$state = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$state) {
    echo json_encode(['ok' => false, 'error' => 'Game state not found']);
    exit;
}

$currentColor = $state['turn_color'];

// Clockwise order
$order = ['red','green','blue','yellow'];
$currentIndex = array_search($currentColor, $order, true);

// Pick next clockwise color that exists in room
$nextColor = null;
for ($i = 1; $i <= count($order); $i++) {
    $checkColor = $order[($currentIndex + $i) % count($order)];
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id=? AND color=? LIMIT 1");
    $stmt->execute([$roomId, $checkColor]);
    $found = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($found) {
        $nextColor = $checkColor;
        $nextUser  = (int)$found['user_id'];
        break;
    }
}

// Update turn
if ($nextColor !== null) {
    $stmt = $pdo->prepare("UPDATE game_state 
        SET turn_user=?, turn_color=?, updated_at=NOW() 
        WHERE room_id=?");
    $stmt->execute([$nextUser, $nextColor, $roomId]);
}

// Return new state
echo json_encode([
    'ok' => true,
    'turnColor' => $nextColor,
    'turnUser' => $nextUser
]);
