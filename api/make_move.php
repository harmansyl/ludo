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

// Input
$roomCode   = trim((string)($data['room'] ?? ''));
$pieceIndex = isset($data['pieceIndex']) ? (int)$data['pieceIndex'] : -1;
$explicitPos = array_key_exists('position', $data) ? (int)$data['position'] : null;
$leaveHome = !empty($data['leaveHome']);
$steps = isset($data['steps']) ? (int)$data['steps'] : null;

if ($roomCode === '' || $pieceIndex < 0) json_error("Invalid input");

// find room
$stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ?");
$stmt->execute([$roomCode]);
$roomId = (int)$stmt->fetchColumn();
if (!$roomId) json_error("Room not found");

// ensure player in room & get color
$stmt = $pdo->prepare("SELECT user_id, color, position FROM room_players WHERE room_id = ? AND user_id = ?");
$stmt->execute([$roomId, $user['id']]);
$me = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$me) json_error("You are not a player in this room");
$myColor = strtolower($me['color'] ?? '');

// ensure it's your turn
$stmt = $pdo->prepare("SELECT turn_user, last_dice FROM game_state WHERE room_id = ?");
$stmt->execute([$roomId]);
$gs = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$gs) json_error("Game not started");
if ((int)$gs['turn_user'] !== (int)$user['id']) json_error("Not your turn");

// read current piece pos (and id)
$stmt = $pdo->prepare("SELECT id, position FROM room_pieces WHERE room_id = ? AND user_id = ? AND piece_index = ?");
$stmt->execute([$roomId, $user['id'], $pieceIndex]);
$curRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$curRow) json_error("Piece not found");
$pieceDbId = (int)$curRow['id'];
$curPos = (int)$curRow['position']; // -1 = home, >=0 path index or 57 finished

// Path and entry offsets (server authoritative)
$ENTRY_INDEX = ['red' => 0, 'green' => 13, 'yellow' => 26, 'blue' => 39];
$MAX_INDEX = 57; // finish index

if (!isset($ENTRY_INDEX[$myColor])) json_error("Invalid color");

// Determine new position server-side
$newPos = null;

// If explicit position provided, accept (useful for admin / direct moves)
if ($explicitPos !== null) {
    $newPos = $explicitPos;
} elseif ($leaveHome) {
    // bring out from home only if currently in home
    if ($curPos !== -1) {
        json_error("Cannot leave home: not currently in home");
    }
    // entry index for this color
    $newPos = $ENTRY_INDEX[$myColor];
} elseif ($steps !== null) {
    // require token is on board (not home) if steps provided (unless steps used to enter)
    if ($curPos === -1) {
        // If not in home and steps used, it's invalid unless steps == 0
        json_error("Token is in home — use leaveHome to bring out (server expects leaveHome)");
    }
    $newPos = $curPos + $steps;
} else {
    json_error("No move instruction (provide position or leaveHome or steps)");
}

// clamp and validate newPos
if ($newPos < -1) $newPos = -1;
if ($newPos > $MAX_INDEX) $newPos = $MAX_INDEX;

// Start transaction so we persist new position and captures atomically
$pdo->beginTransaction();
try {
    // Update the selected piece
    $stmt = $pdo->prepare("UPDATE room_pieces SET position = ? WHERE id = ? AND room_id = ?");
    $stmt->execute([$newPos, $pieceDbId, $roomId]);

    // Handle captures:
    // Only capture for outer loop squares (we assume path length to outer loop is 52; adjust if needed)
    // Convert a color+pos to an absolute outer index for comparison:
    $startOffsets = ['blue' => 0, 'red' => 13, 'yellow' => 26, 'green' => 39];
    $outerIndex = function(string $color, int $pos) use ($startOffsets) {
        if ($pos < 0) return null; // home
        if ($pos >= 52) return null; // in home stretch / finished -> not capturable
        return ($startOffsets[$color] + $pos) % 52;
    };

    $myOuter = $outerIndex($myColor, $newPos);
    if ($myOuter !== null) {
        // fetch all pieces on same room to find victims that occupy same absolute index
        $stmt = $pdo->prepare("SELECT p.id, p.user_id, p.piece_index, rp.color as color, p.position
                               FROM room_pieces p
                               JOIN room_players rp ON rp.room_id = p.room_id AND rp.user_id = p.user_id
                               WHERE p.room_id = ?");
        $stmt->execute([$roomId]);
        $allPieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($allPieces as $op) {
            // ignore own user's pieces (we already moved ours)
            if ((int)$op['user_id'] === (int)$user['id']) continue;

            $opPos = (int)$op['position'];
            $opColor = strtolower($op['color']);
            $opOuter = $outerIndex($opColor, $opPos);
            if ($opOuter !== null && $opOuter === $myOuter) {
                // send that opponent piece (all pieces of that user at that pos) back home
                $stmt2 = $pdo->prepare("UPDATE room_pieces SET position = -1 WHERE room_id = ? AND user_id = ? AND position = ?");
                $stmt2->execute([$roomId, $op['user_id'], $op['position']]);
            }
        }
    }

    // Check finished (all pieces position == 57) and set player finished flag if needed
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_pieces WHERE room_id = ? AND user_id = ? AND position = 57");
    $stmt->execute([$roomId, $user['id']]);
    $finishedCount = (int)$stmt->fetchColumn();
    if ($finishedCount === 4) {
        $stmt = $pdo->prepare("UPDATE room_players SET is_winner = 1 WHERE room_id = ? AND user_id = ?");
        $stmt->execute([$roomId, $user['id']]);
    }

    // Advance turn unless last_dice was 6
    $lastDice = (int)$gs['last_dice'];
    if ($lastDice === 6) {
        // keep same player (no change)
        $nextUser = (int)$user['id'];
    } else {
        // pick next player in room_players order who hasn't finished all tokens
        $stmt = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY position ASC");
        $stmt->execute([$roomId]);
        $players = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idx = array_search((string)$user['id'], $players, true);
        $n = count($players);
        $nextUser = (int)$user['id'];
        for ($i = 1; $i <= $n; $i++) {
            $candidate = $players[($idx + $i) % $n];
            $stmtf = $pdo->prepare("SELECT COUNT(*) FROM room_pieces WHERE room_id = ? AND user_id = ? AND position = 57");
            $stmtf->execute([$roomId, $candidate]);
            $fc = (int)$stmtf->fetchColumn();
            if ($fc < 4) { $nextUser = (int)$candidate; break; }
        }
    }

    // write new turn user and clear last_dice
    $stmt = $pdo->prepare("UPDATE game_state SET turn_user = ?, last_dice = NULL, updated_at = NOW() WHERE room_id = ?");
    $stmt->execute([$nextUser, $roomId]);

    // Commit
    $pdo->commit();

    // Return the authoritative pieces array (server-side positions) so frontend can reconcile
    $stmt = $pdo->prepare("SELECT p.piece_index AS piece, rp.color AS color, p.position 
                           FROM room_pieces p
                           JOIN room_players rp ON rp.room_id = p.room_id AND rp.user_id = p.user_id
                           WHERE p.room_id = ?");
    $stmt->execute([$roomId]);
    $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    json_success([
        'ok' => true,
        'nextTurnUser' => $nextUser,
        'moved' => [
            'pieceIndex' => $pieceIndex,
            'position' => $newPos,
            'color' => $myColor
        ],
        'pieces' => $pieces
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    json_error("Server error: " . $e->getMessage());
}
