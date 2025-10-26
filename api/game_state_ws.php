<?php
declare(strict_types=1);
header('Content-Type: application/json');

// secret key — set LUDO_WS_KEY in environment or Apache SetEnv
$EXPECTED_KEY = getenv('LUDO_WS_KEY') ?: '';
$key = trim((string)($_GET['key'] ?? $_POST['key'] ?? ''));
if ($EXPECTED_KEY === '' || $key === '' || !hash_equals($EXPECTED_KEY, $key)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../lib/boot.php';
$pdo = db();

$roomCode = trim((string)($_GET['room'] ?? ''));
if ($roomCode === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing room code']);
    exit;
}

// find room
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}
$roomId = (int)$room['id'];

// ensure game_state exists (create starter row if necessary)
$stmt = $pdo->prepare("SELECT turn_user, turn_color, last_dice, last_color, winner_user FROM game_state WHERE room_id = ? LIMIT 1");
$stmt->execute([$roomId]);
$gs = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gs) {
    $stmt2 = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? ORDER BY FIELD(color,'red','green','blue','yellow') LIMIT 1");
    $stmt2->execute([$roomId]);
    $starter = $stmt2->fetch(PDO::FETCH_ASSOC);
    if ($starter) {
        $ins = $pdo->prepare("INSERT INTO game_state (room_id, turn_user, turn_color, last_dice, last_color, winner_user, updated_at) VALUES (?, ?, ?, NULL, NULL, NULL, NOW())");
        $ins->execute([$roomId, (int)$starter['user_id'], $starter['color']]);
        $gs = [
            'turn_user' => (int)$starter['user_id'],
            'turn_color' => $starter['color'],
            'last_dice' => null,
            'last_color' => null,
            'winner_user' => null
        ];
    } else {
        $pdo->prepare("INSERT INTO game_state (room_id, updated_at) VALUES (?, NOW())")->execute([$roomId]);
        $gs = ['turn_user' => null, 'turn_color' => null, 'last_dice' => null, 'last_color' => null, 'winner_user' => null];
    }
}

// load room players (ordered)
$stmt = $pdo->prepare("
    SELECT rp.user_id, rp.position as seat_position, rp.color, u.username
    FROM room_players rp
    LEFT JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY FIELD(rp.color,'red','green','blue','yellow')
");
$stmt->execute([$roomId]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// map user->color and joined colors
$userColor = [];
$joinedColors = [];
foreach ($players as $p) {
    $uid = (int)$p['user_id'];
    $color = strtolower($p['color']);
    $userColor[$uid] = $color;
    $joinedColors[] = $color;
}

// load pieces
$stmt = $pdo->prepare("SELECT rp.user_id, rp.piece_index, COALESCE(rp.position,0) AS position FROM room_pieces rp WHERE rp.room_id = ? ORDER BY rp.user_id, rp.piece_index");
$stmt->execute([$roomId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// build pieces and tokens formats
$pieces = [];
$tokens = [];
foreach ($rows as $r) {
    $uid = (int)$r['user_id'];
    $pieceIndex = (int)$r['piece_index'];
    $dbPos = (int)$r['position'];
    $color = $userColor[$uid] ?? null;
    if (!$color) continue;

    $clientPos = ($dbPos <= 0) ? -1 : $dbPos;
    $finished = ($dbPos >= 57);

    $pieces[] = ['color' => $color, 'piece' => $pieceIndex + 1, 'position' => $clientPos];
    $tokens[$color . ($pieceIndex + 1)] = ['pos' => $clientPos, 'finished' => $finished];
}

$response = [
    'ok' => true,
    'turnUser' => isset($gs['turn_user']) ? ((int)$gs['turn_user']) : null,
    'turnColor' => $gs['turn_color'] ?? null,
    'lastDice' => isset($gs['last_dice']) ? (is_null($gs['last_dice']) ? null : (int)$gs['last_dice']) : null,
    'lastColor' => $gs['last_color'] ?? null,
    'players' => $players,
    'pieces' => $pieces,
    'tokens' => $tokens,
    'joinedColors' => $joinedColors,
    'winner' => $gs['winner_user'] ?? null
];

echo json_encode($response);
exit;