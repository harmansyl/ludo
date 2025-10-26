<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';

$server_key = trim((string)($_GET['key'] ?? $_POST['key'] ?? ''));
$EXPECTED_KEY = getenv('LUDO_WS_KEY') ?: ''; // set LUDO_WS_KEY in environment or Apache SetEnv
$__ws_server_call = false;
if ($server_key !== '' && $EXPECTED_KEY !== '') {
    // use hash_equals to avoid timing attacks
    if (hash_equals($EXPECTED_KEY, $server_key)) {
        $__ws_server_call = true;
    }
}

// require auth for browser clients, but allow WS server when key matches
if (! $__ws_server_call) {
    auth_require();
}

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

auth_require();
$pdo = db();

// Accept GET or JSON body
$data = $_GET ?: (function(){
    $j = file_get_contents('php://input');
    $d = json_decode($j, true);
    return is_array($d) ? $d : [];
})();
$roomCode = trim((string)($data['room'] ?? ''));
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
    // pick first player in default clockwise order
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

// map user->color
$userColor = [];
$joinedColors = [];
foreach ($players as $p) {
    $uid = (int)$p['user_id'];
    $color = strtolower($p['color']);
    $userColor[$uid] = $color;
    $joinedColors[] = $color;
}

// load pieces
$stmt = $pdo->prepare("SELECT rp.user_id, rp.piece_index, COALESCE(rp.position,0) AS position
                       FROM room_pieces rp
                       WHERE rp.room_id = ?
                       ORDER BY rp.user_id, rp.piece_index");
$stmt->execute([$roomId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// build two formats:
// 1) 'pieces' => [ { color, piece (1..4), position (client-ready; -1=home) }, ... ]
// 2) 'tokens' => { "red1": { pos: N, finished: bool }, ... }  (back-compat)
$pieces = [];
$tokens = [];
foreach ($rows as $r) {
    $uid = (int)$r['user_id'];
    $pieceIndex = (int)$r['piece_index']; // 0-based in DB
    $dbPos = (int)$r['position'];
    $color = $userColor[$uid] ?? null;
    if (!$color) continue;

    // client expects -1 for home; treat dbPos <= 0 as home
    $clientPos = ($dbPos <= 0) ? -1 : $dbPos;
    $finished = ($dbPos >= 57);

    $pieces[] = [
        'color' => $color,
        'piece' => $pieceIndex + 1,
        'position' => $clientPos
    ];

    $tokens[$color . ($pieceIndex + 1)] = ['pos' => $clientPos, 'finished' => $finished];
}

// get game_state values for response
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
