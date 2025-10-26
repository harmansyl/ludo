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

// Accept either GET params or JSON body
$data = $_GET ?: require_json();
$roomCode = trim((string)($data['room'] ?? ''));
if ($roomCode === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing room code']);
    exit;
}

// Find room
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    echo json_encode(['ok' => false, 'error' => 'Room not found']);
    exit;
}
$roomId = (int)$room['id'];

// Load game state (ensure row exists)
$stmt = $pdo->prepare("SELECT turn_user, turn_color, last_dice, winner_user 
                       FROM game_state WHERE room_id = ?");
$stmt->execute([$roomId]);
$gs = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gs) {
    // initialize if missing
    $pdo->prepare("INSERT INTO game_state 
        (room_id, turn_user, turn_color, last_dice, last_color, winner_user, updated_at) 
        VALUES (?, NULL, NULL, NULL, NULL, NULL, NOW())")
        ->execute([$roomId]);

    $gs = [
        'turn_user'   => null,
        'turn_color'  => null,
        'last_dice'   => null,
        'winner_user' => null
    ];
}

// Load players
$stmt = $pdo->prepare("
    SELECT rp.user_id, rp.position, rp.color, u.username
    FROM room_players rp
    JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY rp.position ASC
");
$stmt->execute([$roomId]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Assign first player as starter if no turn set yet
if (empty($gs['turn_user']) || empty($gs['turn_color'])) {
    if (count($players) > 0) {
        $firstPlayer = $players[0];
        $gs['turn_user']  = (int)$firstPlayer['user_id'];
        $gs['turn_color'] = $firstPlayer['color'];

        $pdo->prepare("
            UPDATE game_state
            SET turn_user = ?, turn_color = ?, updated_at = NOW()
            WHERE room_id = ?
        ")->execute([$gs['turn_user'], $gs['turn_color'], $roomId]);
    }
}

// Map user → color
$userColor = [];
$joinedColors = [];
foreach ($players as $p) {
    $uid = (int)$p['user_id'];
    $color = $p['color'];
    $userColor[$uid] = $color;
    $joinedColors[] = $color;
}

// Load pieces
$stmt = $pdo->prepare("
    SELECT user_id, piece_index, position
    FROM room_pieces
    WHERE room_id = ?
    ORDER BY user_id, piece_index
");
$stmt->execute([$roomId]);
$pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Build tokens keyed by color+index
$tokens = [];
foreach ($pieces as $p) {
    $uid = (int)$p['user_id'];
    $color = $userColor[$uid] ?? null;
    if (!$color) continue;

    $key = $color . ((int)$p['piece_index'] + 1); // e.g. red1, blue2
    $tokens[$key] = [
        'pos' => (int)$p['position'],
        'finished' => ((int)$p['position'] === 57)
    ];
}

// ✅ Final turn values
$turnUser  = $gs['turn_user'] ? (int)$gs['turn_user'] : null;
$turnColor = $gs['turn_color'] ?? null;

echo json_encode([
    'ok'           => true,
    'turnUser'     => $turnUser,
    'turnColor'    => $turnColor,
    'lastDice'     => isset($gs['last_dice']) ? (int)$gs['last_dice'] : null,
    'players'      => $players,
    'tokens'       => $tokens,
    'joinedColors' => $joinedColors,
    'winners'      => [] // extend later
]);
