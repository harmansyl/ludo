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

// Accept POST or JSON
$data = $_POST;
if (empty($data)) {
    $json = file_get_contents('php://input');
    $decoded = json_decode($json, true);
    if (is_array($decoded)) $data = $decoded;
}

$roomCode   = trim((string)($data['room'] ?? ''));
$pieceIndex = isset($data['pieceIndex']) ? (int)$data['pieceIndex'] : -1;
$steps      = isset($data['steps']) ? (int)$data['steps'] : 0;
// $targetPos is mostly ignored as we use $steps for authority
$leaveHome  = !empty($data['leaveHome']);

if ($roomCode === '' || $pieceIndex < 0 || $steps <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid parameters']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Lock room and player info
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) throw new RuntimeException('Room not found');
    $roomId = (int)$room['id'];

    $stmt = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$roomId, $user['id']]);
    $player = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$player) throw new RuntimeException('You are not a player in this room');
    $playerColor = strtolower($player['color']);

    $entrySquares = [
        'red'    => 0,
        'green'  => 13,
        'yellow' => 26,
        'blue'   => 39
    ];
    $entrySquare = $entrySquares[$playerColor] ?? 0;

    // Lock token row
    $stmt = $pdo->prepare("SELECT position FROM room_pieces WHERE room_id = ? AND user_id = ? AND piece_index = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$roomId, $user['id'], $pieceIndex]);
    $piece = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$piece) throw new RuntimeException('Piece not found');

    $currentPos = (int)$piece['position'];
    $atHome = ($currentPos === -1);
    $MAX_INDEX = 57; // Final finish spot
    
    $path = [];
    $newPos = $currentPos;
    $moveType = 'steps';
    $extraTurn = false; // Initialize extra turn flag

    // =================================================================
    // 1. MOVE LOGIC (Guaranteed position calculation and validation)
    // =================================================================
    if ($atHome) {
        // Must be a 6 to leave home
        if ($steps !== 6) { 
             throw new RuntimeException('Need a 6 to leave home');
        }

        // ✅ FIX: New position is the entry square (0, 13, 26, 39) and ONLY that square.
        $newPos = $entrySquare;
        $path = [$entrySquare];
        $moveType = 'enter';

        // Grant extra turn for rolling a 6
        $extraTurn = true;
        
    } else {
        // Normal move on board
        
        $newPos = $currentPos + $steps;
        
        // 🔴 CRITICAL VALIDATION: Overshoot Check
        if ($newPos > $MAX_INDEX) {
            throw new RuntimeException('Cannot overshoot the final spot (' . $MAX_INDEX . ')');
        }
        
        // Build animation path (all intermediate steps)
        for ($i = 1; $i <= $steps; $i++) {
            $path[] = $currentPos + $i;
        }

        $moveType = 'steps';
        
        // Grant extra turn for rolling a 6 or finishing
        if ($steps === 6 || $newPos === $MAX_INDEX) {
            $extraTurn = true;
        }
    }

    // =================================================================
    // 2. DATABASE UPDATE
    // =================================================================
    $upd = $pdo->prepare("
        UPDATE room_pieces 
        SET position = ?, updated_at = NOW() 
        WHERE room_id = ? AND user_id = ? AND piece_index = ?
    ");
    // Ensure the database update uses the correctly calculated $newPos (0 for Red exit)
    $upd->execute([$newPos, $roomId, $user['id'], $pieceIndex]);
    
    // =================================================================
    // 3. CAPTURE LOGIC
    // =================================================================
    $KICK_SQUARE = 51; // Last square on the main loop

    if ($newPos >= 0 && $newPos <= $KICK_SQUARE && $moveType !== 'enter') {
        
        // NOTE: Capture logic (not fully implemented here, but ready for placement)
        
        $kickedOpponent = false;
        
        $stmt = $pdo->prepare("
            SELECT rp.piece_index, rp.user_id, rpl.color 
            FROM room_pieces rp
            JOIN room_players rpl ON rp.room_id = rpl.room_id AND rp.user_id = rpl.user_id
            WHERE rp.room_id = ? AND rp.position = ? AND rpl.color != ?
        ");
        $stmt->execute([$roomId, $newPos, $playerColor]);
        $victims = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($victims as $victim) {
            // TODO: Add check for safe spots here! 
            
            $pdo->prepare("
                UPDATE room_pieces SET position = -1, updated_at = NOW() 
                WHERE room_id = ? AND user_id = ? AND piece_index = ?
            ")->execute([$roomId, $victim['user_id'], $victim['piece_index']]);
            
            $kickedOpponent = true;
        }

        if ($kickedOpponent) {
            $extraTurn = true; 
        }
    }


    // =================================================================
    // 4. WIN CONDITION CHECK
    // =================================================================
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM room_pieces WHERE room_id = ? AND user_id = ? AND position = ?");
    $stmt->execute([$roomId, $user['id'], $MAX_INDEX]);
    $finishedCount = (int)$stmt->fetchColumn();

    if ($finishedCount >= 4) {
        $stmt = $pdo->prepare("SELECT winner_user FROM game_state WHERE room_id = ? LIMIT 1");
        $stmt->execute([$roomId]);
        $winner = $stmt->fetchColumn();
        if (empty($winner)) {
            $stmt = $pdo->prepare("UPDATE game_state SET winner_user = ?, updated_at = NOW() WHERE room_id = ?");
            $stmt->execute([$user['id'], $roomId]);
        }
    }

    $pdo->commit();

    // Return updated pieces
    $stmt = $pdo->prepare("
        SELECT rp.piece_index, rp.position, rpl.color
        FROM room_pieces rp
        JOIN room_players rpl ON rp.room_id = rpl.room_id AND rp.user_id = rpl.user_id
        WHERE rp.room_id = ?
        ORDER BY FIELD(rpl.color,'red','green','blue','yellow'), rp.piece_index
    ");
    $stmt->execute([$roomId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $piecesOut = [];
    foreach ($rows as $r) {
        $piecesOut[] = [
            'color'    => strtolower($r['color']),
            'piece'    => ((int)$r['piece_index']) + 1,
            'position' => (int)$r['position']
        ];
    }

    echo json_encode([
        'ok' => true,
        'pieceIndex' => $pieceIndex,
        'newPos' => $newPos,
        'path' => $path,
        'moveType' => $moveType,
        'pieces' => $piecesOut,
        'extraTurn' => $extraTurn // <--- CRITICAL for client turn logic
    ]);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}