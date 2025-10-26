<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

auth_require();
$user = current_user();
$pdo  = db();

// read JSON or form
$data = $_POST;
if (empty($data)) {
    $json = file_get_contents('php://input');
    $decoded = json_decode($json, true);
    if (is_array($decoded)) $data = $decoded;
}

$roomCode = trim((string)($data['room'] ?? ''));
$diceRoll = isset($data['dice']) ? (int)$data['dice'] : 0;

if ($roomCode === '' || $diceRoll <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    // find room
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        echo json_encode(['ok' => false, 'error' => 'Room not found']);
        exit;
    }
    $roomId = (int)$room['id'];

    // get player entry
    $stmt = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$roomId, $user['id']]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) {
        echo json_encode(['ok' => false, 'error' => 'You are not a player in this room']);
        exit;
    }
    $playerColor = strtolower($player['color']);

    // get all pieces of this player
    $stmt = $pdo->prepare("SELECT piece_index, position FROM room_pieces WHERE room_id = ? AND user_id = ? ORDER BY piece_index");
    $stmt->execute([$roomId, $user['id']]);
    $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $MAX_INDEX = 57;

    // entry square for each color
    $entrySquares = [
        'red'   => 0,
        'green' => 13,
        'yellow'=> 26,
        'blue'  => 39
    ];

    $moves = [];

    foreach ($pieces as $p) {
        $idx = (int)$p['piece_index'];
        $pos = (int)$p['position'];

        $atHome = ($pos === -1); // ✅ only -1 is home

        if ($atHome) {
            // piece is still at home → only valid if dice is 6
            if ($diceRoll === 6) {
                $entrySquare = $entrySquares[$playerColor] ?? 0;
                $moves[] = [
                    'pieceIndex' => $idx,
                    'from' => -1,
                    'to' => $entrySquare,   // ✅ correct entry square
                    'note' => 'enter_board'
                ];
            }
        } else {
            // already on board (pos >= 0)
            $target = $pos + $diceRoll;
            if ($target > $MAX_INDEX) {
                $target = $MAX_INDEX;
            }

            // allow move only if not beyond finish
            if ($pos < $MAX_INDEX) {
                $moves[] = [
                    'pieceIndex' => $idx,
                    'from' => $pos,
                    'to' => $target,
                    'note' => ($target === $MAX_INDEX) ? 'finished' : 'move'
                ];
            }
        }
    }

    echo json_encode([
        'ok' => true,
        'moves' => $moves
    ]);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
