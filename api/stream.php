<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
session_start();
set_time_limit(0);

// Require auth
if (!current_user()) {
    http_response_code(401);
    exit('Not authenticated');
}

// Get room code from GET
$code = isset($_GET['room']) ? trim((string)$_GET['room']) : '';
if ($code === '') {
    http_response_code(400);
    exit('Missing room');
}

$pdo = db();

// Resolve room by code directly (do not call undefined helper)
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE code = ? LIMIT 1");
$stmt->execute([$code]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$room) {
    http_response_code(404);
    exit('Room not found');
}
$roomId = (int)$room['id'];

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Turn off output buffering to flush immediately
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
while (ob_get_level() > 0) { ob_end_flush(); }
ob_implicit_flush(true);

// Track last update time from game_state.updated_at
$st = $pdo->prepare("SELECT UNIX_TIMESTAMP(updated_at) AS ts FROM game_state WHERE room_id=?");
$lastTs = 0;
$st->execute([$roomId]);
if ($r = $st->fetch(PDO::FETCH_ASSOC)) $lastTs = (int)$r['ts'];

$start = time();
$timeout = 25; // keep connection ~25s; client will reconnect

while (true) {
    // Timeout so Apache/PHP process doesn't hang forever
    if ((time() - $start) > $timeout) break;

    // Re-check timestamp
    $st->execute([$roomId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    $ts = $r ? (int)$r['ts'] : 0;

    if ($ts > $lastTs) {
        // fetch compact game_state
        $gsq = $pdo->prepare("SELECT turn_user, last_dice, next_dice, winner_user FROM game_state WHERE room_id=?");
        $gsq->execute([$roomId]);
        $gs = $gsq->fetch(PDO::FETCH_ASSOC) ?: [];

        // players in seat order
        $playersQ = $pdo->prepare(
            "SELECT rp.user_id, u.username, u.is_admin, rp.seat, rp.color
             FROM room_players rp
             JOIN users u ON u.id = rp.user_id
             WHERE rp.room_id = ?
             ORDER BY rp.seat ASC"
        );
        $playersQ->execute([$roomId]);
        $players = $playersQ->fetchAll(PDO::FETCH_ASSOC);

        // pieces / positions
        $piecesQ = $pdo->prepare("SELECT user_id, piece_index, position FROM room_pieces WHERE room_id=?");
        $piecesQ->execute([$roomId]);
        $pieces = $piecesQ->fetchAll(PDO::FETCH_ASSOC);

        // Build payload
        $payload = [
            'turnUser'   => isset($gs['turn_user']) ? (int)$gs['turn_user'] : null,
            'lastDice'   => isset($gs['last_dice']) ? ($gs['last_dice'] !== null ? (int)$gs['last_dice'] : null) : null,
            'nextDice'   => isset($gs['next_dice']) ? ($gs['next_dice'] !== null ? (int)$gs['next_dice'] : null) : null,
            'winnerUser' => isset($gs['winner_user']) ? ($gs['winner_user'] !== null ? (int)$gs['winner_user'] : null) : null,
            'players'    => $players,
            'pieces'     => $pieces
        ];

        // Send SSE event
        echo "event: state\n";
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n\n";
        @ob_flush(); flush();

        $lastTs = $ts;
    }

    // Small sleep to reduce DB pressure
    usleep(250000); // 250ms
}

// final ping before closing
echo "event: ping\ndata: {}\n\n";
@ob_flush(); flush();
