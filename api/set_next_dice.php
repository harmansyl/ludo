<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/boot.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

auth_require();
$pdo  = db();

// Accept GET (or JSON fallback)
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

// begin transaction and lock game_state row to avoid races
$pdo->beginTransaction();
$stmt = $pdo->prepare("SELECT turn_user, turn_color, last_dice, last_color FROM game_state WHERE room_id = ? FOR UPDATE");
$stmt->execute([$roomId]);
$gs = $stmt->fetch(PDO::FETCH_ASSOC);

// load joined players in deterministic clockwise color order
$stmt = $pdo->prepare("
    SELECT rp.user_id, rp.color, u.username
    FROM room_players rp
    JOIN users u ON rp.user_id = u.id
    WHERE rp.room_id = ?
    ORDER BY FIELD(rp.color,'red','green','blue','yellow')
");
$stmt->execute([$roomId]);
$players = $stmt->fetchAll(PDO::FETCH_ASSOC);
$joinedColors = array_map(function($p){ return $p['color']; }, $players);

// fixed clockwise axis
$clockwiseFull = ['red','green','blue','yellow'];
$clockwise = $clockwiseFull;

// special-case: if exactly 2 players and they are red & blue — use red <-> blue alternation
if (count($joinedColors) === 2 && in_array('red', $joinedColors, true) && in_array('blue', $joinedColors, true)) {
    $clockwise = ['red','blue'];
}

// ensure there is a game_state row
if (!$gs) {
    // pick first present in clockwise order as starter
    $starterColor = null;
    foreach ($clockwise as $c) {
        if (in_array($c, $joinedColors, true)) {
            $starterColor = $c;
            break;
        }
    }
    if ($starterColor !== null) {
        $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND color = ? LIMIT 1");
        $stmt->execute([$roomId, $starterColor]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r) {
            $pdo->prepare("INSERT INTO game_state (room_id, turn_user, turn_color, last_dice, last_color, updated_at) VALUES (?, ?, ?, NULL, NULL, NOW())")
                ->execute([$roomId, (int)$r['user_id'], $starterColor]);
            $gs = ['turn_user' => (int)$r['user_id'], 'turn_color' => $starterColor];
        }
    } else {
        $pdo->prepare("INSERT INTO game_state (room_id, updated_at) VALUES (?, NOW())")->execute([$roomId]);
        $gs = ['turn_user' => null, 'turn_color' => null];
    }
}

// If we have a current turn color, advance to the next present color in clockwise order
if ($gs && isset($gs['turn_color']) && $gs['turn_color'] !== null) {
    $current = $gs['turn_color'];
    $idx = array_search($current, $clockwise, true);
    if ($idx === false) $idx = 0;
    $nextIdx = ($idx + 1) % count($clockwise);

    // skip missing colors (guard to prevent infinite loop)
    $guard = 0;
    while (!in_array($clockwise[$nextIdx], $joinedColors, true)) {
        $nextIdx = ($nextIdx + 1) % count($clockwise);
        if (++$guard > 10) break;
    }

    $newColor = $clockwise[$nextIdx];

    // find the user for that color
    $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND color = ? LIMIT 1");
    $stmt->execute([$roomId, $newColor]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $pdo->prepare("UPDATE game_state SET turn_user = ?, turn_color = ?, updated_at = NOW() WHERE room_id = ?")
            ->execute([(int)$row['user_id'], $newColor, $roomId]);
        $gs['turn_user'] = (int)$row['user_id'];
        $gs['turn_color'] = $newColor;
    }
}

$pdo->commit();

// return info for frontend: players + active dice color
echo json_encode([
    'ok' => true,
    'turnUser' => isset($gs['turn_user']) ? (int)$gs['turn_user'] : null,
    'turnColor' => $gs['turn_color'] ?? null,
    'players' => $players,
    'joinedColors' => $joinedColors,
    'activeDiceColor' => $gs['turn_color'] ?? null
]);
