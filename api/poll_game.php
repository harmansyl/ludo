<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
require_once __DIR__ . '/../lib/game_logic.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

auth_require();
$user = current_user();
$pdo  = db();

$data = $_GET ?: require_json();
$roomCode = trim((string)($data['room'] ?? ''));
$since    = isset($data['since']) ? (int)$data['since'] : 0;

if ($roomCode === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing room code']);
    exit;
}

// lookup room id
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}
$roomId = (int)$room['id'];

// revision helper
function currentRevision(PDO $pdo, int $roomId): int {
    $stmt = $pdo->prepare("SELECT UNIX_TIMESTAMP(GREATEST(MAX(updated_at), NOW())) FROM (
        SELECT MAX(updated_at) FROM game_state WHERE room_id = ?
        UNION
        SELECT MAX(updated_at) FROM room_pieces WHERE room_id = ?
        UNION
        SELECT MAX(updated_at) FROM room_players WHERE room_id = ?
    ) t");
    $stmt->execute([$roomId, $roomId, $roomId]);
    return (int)$stmt->fetchColumn();
}

// long-poll
$timeout = 20; // seconds
$start   = time();
$newRev  = currentRevision($pdo, $roomId);

while ($newRev <= $since && (time() - $start) < $timeout) {
    usleep(250000); // 250ms
    $newRev = currentRevision($pdo, $roomId);
}

// final state
$state = fetchRoomState($pdo, $roomCode);
if (!$state) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}
$state['revision'] = $newRev;

echo json_encode($state);
