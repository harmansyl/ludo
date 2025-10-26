<?php
// lib/game_logic.php
declare(strict_types=1);

/**
 * A robust game logic layer that adapts to either:
 * - tokens + rooms.turn_color + rooms.last_roll
 * OR
 * - room_pieces + game_state.turn_user + game_state.last_dice
 *
 * This allows your existing frontend (game.php) and current API files to keep working
 * without having to rewrite the rest of the project.
 */

/**
 * Check whether a table exists in current DB
 */
function tableExists(PDO $pdo, string $tableName): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $st->execute([$tableName]);
    return (bool)$st->fetchColumn();
}

/**
 * Check whether a column exists in a table
 */
function columnExists(PDO $pdo, string $tableName, string $columnName): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->execute([$tableName, $columnName]);
    return (bool)$st->fetchColumn();
}

/**
 * Normalize query for room lookup
 */
function findRoomId(PDO $pdo, string $roomCode): ?int {
    $st = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
    $st->execute([$roomCode]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? (int)$r['id'] : null;
}

/**
 * Build tokens object used by frontend:
 * - If tokens table exists, use it.
 * - Otherwise, if room_pieces exists, build tokens from pieces.
 *
 * Returns array keyed like: red1 => ['pos'=>int,'finished'=>bool]
 */
function buildTokensFromDB(PDO $pdo, int $roomId, array $userColorMap): array {
    $tokens = [];

    if (tableExists($pdo, 'tokens')) {
        $st = $pdo->prepare("SELECT id, user_id, `index` AS idx, `position` FROM tokens WHERE room_id = ?");
        $st->execute([$roomId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            $color = $userColorMap[$uid] ?? null;
            if (!$color) continue;
            $idx = (int)$r['idx']; // assume 0-based or 1-based depending on your schema — frontend expects numbers starting 1
            // Try to keep convention: if idx >=0 and <=3 map to 1..4
            $indexKey = ($idx >= 0 && $idx <= 3) ? ($idx + 1) : $idx;
            $key = $color . $indexKey;
            $position = isset($r['position']) ? (int)$r['position'] : 0;
            $tokens[$key] = [
                'pos' => $position,
                'finished' => ($position === 57)
            ];
        }
        return $tokens;
    }

    // fallback: room_pieces
    if (tableExists($pdo, 'room_pieces')) {
        $st = $pdo->prepare("SELECT user_id, piece_index, position FROM room_pieces WHERE room_id = ? ORDER BY user_id, piece_index");
        $st->execute([$roomId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {
            $uid = (int)$r['user_id'];
            $color = $userColorMap[$uid] ?? null;
            if (!$color) continue;
            $idx = (int)$r['piece_index']; // assume 0-based
            $key = $color . ($idx + 1);
            $position = isset($r['position']) ? (int)$r['position'] : 0;
            $tokens[$key] = [
                'pos' => $position,
                'finished' => ($position === 57)
            ];
        }
        return $tokens;
    }

    // no tokens or room_pieces table -> empty
    return $tokens;
}

/**
 * Read winners for a room (if room_winners table exists)
 */
function loadWinners(PDO $pdo, int $roomId): array {
    if (!tableExists($pdo, 'room_winners')) {
        // fallback: maybe rooms.winners JSON column exists
        if (columnExists($pdo, 'rooms', 'winners')) {
            $st = $pdo->prepare("SELECT winners FROM rooms WHERE id = ? LIMIT 1");
            $st->execute([$roomId]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            if ($r && !empty($r['winners'])) {
                $decoded = json_decode($r['winners'], true);
                return is_array($decoded) ? $decoded : [];
            }
        }
        return [];
    }

    $st = $pdo->prepare("
        SELECT rw.rank, rw.user_id, u.username, rp.color
        FROM room_winners rw
        JOIN users u ON u.id = rw.user_id
        LEFT JOIN room_players rp ON rp.room_id = rw.room_id AND rp.user_id = rw.user_id
        WHERE rw.room_id = ?
        ORDER BY rw.rank ASC
    ");
    $st->execute([$roomId]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch the full room state by room code (frontend-friendly)
 */
function getRoomState(PDO $pdo, string $roomCode): array {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) {
        return ['ok' => false, 'error' => 'Room not found'];
    }

    // players
    // choose seat OR position ordering if seat not present
    $orderCol = columnExists($pdo, 'room_players', 'seat') ? 'seat' : 'position';
    $st = $pdo->prepare("SELECT rp.user_id, rp.$orderCol AS ordering, rp.color, u.username FROM room_players rp LEFT JOIN users u ON u.id = rp.user_id WHERE rp.room_id = ? ORDER BY rp.$orderCol ASC");
    $st->execute([$roomId]);
    $players = $st->fetchAll(PDO::FETCH_ASSOC);

    // build user->color map and joinedColors
    $userColor = [];
    $joinedColors = [];
    foreach ($players as $p) {
        $uid = (int)$p['user_id'];
        $color = $p['color'] ?? null;
        if ($color) {
            $userColor[$uid] = $color;
            $joinedColors[] = $color;
        }
    }

    // tokens (either tokens table or pieces)
    $tokens = buildTokensFromDB($pdo, $roomId, $userColor);

    // turn detection: prefer game_state.turn_user if exists, otherwise rooms.turn_color
    $turnColor = null;
    if (tableExists($pdo, 'game_state') && columnExists($pdo, 'game_state', 'turn_user')) {
        $st = $pdo->prepare("SELECT turn_user, last_dice FROM game_state WHERE room_id = ? LIMIT 1");
        $st->execute([$roomId]);
        $gs = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!empty($gs['turn_user'])) {
            $turnUid = (int)$gs['turn_user'];
            $turnColor = $userColor[$turnUid] ?? null;
        }
        $lastDice = isset($gs['last_dice']) ? (int)$gs['last_dice'] : null;
    } else {
        // fallback: rooms.turn_color and rooms.last_roll
        $cols = [];
        if (columnExists($pdo, 'rooms', 'turn_color')) $cols[] = 'turn_color';
        if (columnExists($pdo, 'rooms', 'last_roll')) $cols[] = 'last_roll';
        if (!empty($cols)) {
            $st = $pdo->prepare("SELECT " . implode(", ", $cols) . " FROM rooms WHERE id = ? LIMIT 1");
            $st->execute([$roomId]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
            $turnColor = $r['turn_color'] ?? null;
            $lastDice = isset($r['last_roll']) ? (int)$r['last_roll'] : null;
        } else {
            $lastDice = null;
        }
    }

    $winners = loadWinners($pdo, $roomId);

    return [
        'ok' => true,
        'roomId' => $roomId,
        'players' => $players,
        'joinedColors' => $joinedColors,
        'tokens' => $tokens,
        'turnColor' => $turnColor,
        'lastDice' => $lastDice ?? null,
        'winners' => $winners
    ];
}

/**
 * Get the color of a user in a room
 */
function getUserColor(PDO $pdo, int $userId, string $roomCode): ?string {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) return null;
    $st = $pdo->prepare("SELECT color FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
    $st->execute([$roomId, $userId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r['color'] ?? null;
}

/**
 * Roll dice for a user (works with either schema)
 * - Ensures it's this user's turn (based on game_state.turn_user OR rooms.turn_color)
 * - Updates last_dice (game_state) or last_roll (rooms)
 */
function rollDiceForUser(PDO $pdo, int $userId, string $roomCode): array {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) {
        return ['ok' => false, 'error' => 'Room not found'];
    }

    // Determine whether this user is the current turn
    $isTurn = false;

    // If game_state.turn_user exists -> check it
    if (tableExists($pdo, 'game_state') && columnExists($pdo, 'game_state', 'turn_user')) {
        $st = $pdo->prepare("SELECT turn_user FROM game_state WHERE room_id = ? LIMIT 1");
        $st->execute([$roomId]);
        $turnUser = $st->fetchColumn();
        if ($turnUser !== false && (int)$turnUser === $userId) $isTurn = true;
    } else if (columnExists($pdo, 'rooms', 'turn_color')) {
        // Use rooms.turn_color vs user's color
        $st = $pdo->prepare("SELECT turn_color FROM rooms WHERE id = ? LIMIT 1");
        $st->execute([$roomId]);
        $turnColor = $st->fetchColumn();
        $st2 = $pdo->prepare("SELECT color FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
        $st2->execute([$roomId, $userId]);
        $myColor = $st2->fetchColumn();
        if ($turnColor !== false && $myColor !== false && $turnColor === $myColor) $isTurn = true;
    } else {
        // Unknown turn method -> allow roll (safe fallback)
        $isTurn = true;
    }

    if (!$isTurn) return ['ok' => false, 'error' => 'Not your turn'];

    $dice = random_int(1, 6);

    // Save last roll to appropriate place
    if (tableExists($pdo, 'game_state') && columnExists($pdo, 'game_state', 'last_dice')) {
        $st = $pdo->prepare("UPDATE game_state SET last_dice = ? WHERE room_id = ?");
        $st->execute([$dice, $roomId]);
    } else if (columnExists($pdo, 'rooms', 'last_roll')) {
        $st = $pdo->prepare("UPDATE rooms SET last_roll = ? WHERE id = ?");
        $st->execute([$dice, $roomId]);
    }

    return ['ok' => true, 'dice' => $dice];
}

/**
 * Move a piece (room_pieces model) — used by your current move_token.php
 */
function movePiece(PDO $pdo, int $userId, string $roomCode, int $pieceIndex, int $steps): array {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) return ['ok' => false, 'error' => 'Room not found'];

    if (!tableExists($pdo, 'room_pieces')) {
        return ['ok' => false, 'error' => 'room_pieces table missing'];
    }

    $st = $pdo->prepare("SELECT position FROM room_pieces WHERE room_id = ? AND user_id = ? AND piece_index = ? LIMIT 1");
    $st->execute([$roomId, $userId, $pieceIndex]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return ['ok' => false, 'error' => 'Piece not found'];

    $newPos = (int)$r['position'] + $steps;
    if ($newPos > 57) $newPos = 57;

    $st = $pdo->prepare("UPDATE room_pieces SET position = ?, updated_at = NOW() WHERE room_id = ? AND user_id = ? AND piece_index = ?");
    $st->execute([$newPos, $roomId, $userId, $pieceIndex]);

    // check winner (if you have room_winners)
    checkForWinner($pdo, $roomId, $userId);

    return ['ok' => true, 'pieceIndex' => $pieceIndex, 'newPos' => $newPos];
}

/**
 * Move a token (tokens table model)
 * tokenId is integer id of tokens table row
 */
function moveTokenById(PDO $pdo, int $userId, string $roomCode, int $tokenId, int $steps): array {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) return ['ok' => false, 'error' => 'Room not found'];

    if (!tableExists($pdo, 'tokens')) {
        return ['ok' => false, 'error' => 'tokens table missing'];
    }

    $st = $pdo->prepare("SELECT `index`, position, room_id FROM tokens WHERE id = ? AND user_id = ? AND room_id = ?");
    $st->execute([$tokenId, $userId, $roomId]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) return ['ok' => false, 'error' => 'Token not found'];

    $newPos = (int)$r['position'] + $steps;
    if ($newPos > 57) $newPos = 57;

    $st = $pdo->prepare("UPDATE tokens SET position = ?, updated_at = NOW() WHERE id = ?");
    $st->execute([$newPos, $tokenId]);

    // check winner
    checkForWinner($pdo, $roomId, $userId);

    return ['ok' => true, 'tokenId' => $tokenId, 'newPos' => $newPos];
}

/**
 * Set next turn - works for both schemas
 * If $nextColor is provided, writes to rooms.turn_color if exists, else attempts game_state.turn_user mapping.
 */
function setNextTurn(PDO $pdo, string $roomCode, ?string $nextColor = null): bool {
    $roomId = findRoomId($pdo, $roomCode);
    if ($roomId === null) return false;

    // If explicit color provided and rooms.turn_color exists -> update directly
    if ($nextColor !== null && columnExists($pdo, 'rooms', 'turn_color')) {
        $st = $pdo->prepare("UPDATE rooms SET turn_color = ? WHERE id = ?");
        return (bool)$st->execute([$nextColor, $roomId]);
    }

    // Build ordered list of players (by seat or position)
    $orderCol = columnExists($pdo, 'room_players', 'seat') ? 'seat' : 'position';
    $st = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? ORDER BY $orderCol ASC");
    $st->execute([$roomId]);
    $players = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$players) return false;

    $userIds = array_column($players, 'user_id');
    $colors = array_column($players, 'color');

    // Determine current "index" based on schema
    $currentIdx = false;
    if (tableExists($pdo, 'game_state') && columnExists($pdo, 'game_state', 'turn_user')) {
        $st = $pdo->prepare("SELECT turn_user FROM game_state WHERE room_id = ? LIMIT 1");
        $st->execute([$roomId]);
        $turnUser = $st->fetchColumn();
        if ($turnUser !== false) $currentIdx = array_search((int)$turnUser, $userIds, true);
    } elseif (columnExists($pdo, 'rooms', 'turn_color')) {
        $st = $pdo->prepare("SELECT turn_color FROM rooms WHERE id = ? LIMIT 1");
        $st->execute([$roomId]);
        $turnColor = $st->fetchColumn();
        if ($turnColor !== false) $currentIdx = array_search($turnColor, $colors, true);
    }

    // winners to skip (if room_winners exists)
    $finished = [];
    if (tableExists($pdo, 'room_winners')) {
        $st = $pdo->prepare("SELECT user_id FROM room_winners WHERE room_id = ?");
        $st->execute([$roomId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $uid) $finished[] = (int)$uid;
    } elseif (columnExists($pdo, 'room_players', 'is_winner')) {
        $st = $pdo->prepare("SELECT user_id FROM room_players WHERE room_id = ? AND is_winner = 1");
        $st->execute([$roomId]);
        $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        foreach ($rows as $uid) $finished[] = (int)$uid;
    }

    $n = count($userIds);
    for ($i = 1; $i <= $n; $i++) {
        $nextIdx = ($currentIdx === false) ? ($i - 1) : (($currentIdx + $i) % $n);
        $candidateUser = (int)$userIds[$nextIdx];
        if (!in_array($candidateUser, $finished, true)) {
            // write back depending on schema
            if (tableExists($pdo, 'game_state') && columnExists($pdo, 'game_state', 'turn_user')) {
                $st = $pdo->prepare("UPDATE game_state SET turn_user = ? WHERE room_id = ?");
                return (bool)$st->execute([$candidateUser, $roomId]);
            } elseif (columnExists($pdo, 'rooms', 'turn_color')) {
                $candidateColor = $colors[$nextIdx];
                $st = $pdo->prepare("UPDATE rooms SET turn_color = ? WHERE id = ?");
                return (bool)$st->execute([$candidateColor, $roomId]);
            } else {
                // nothing to write to
                return false;
            }
        }
    }

    // all players finished -> no next turn
    return false;
}

/**
 * Check winner for both models:
 * - For room_pieces: user has 4 pieces at position 57
 * - For tokens: user has 4 tokens at position 57
 * Inserts into room_winners table if present.
 */
function checkForWinner(PDO $pdo, int $roomId, int $userId): void {
    $finishedCount = 0;
    if (tableExists($pdo, 'room_pieces')) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM room_pieces WHERE room_id = ? AND user_id = ? AND position = 57");
        $st->execute([$roomId, $userId]);
        $finishedCount = (int)$st->fetchColumn();
    } elseif (tableExists($pdo, 'tokens')) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM tokens WHERE room_id = ? AND user_id = ? AND position = 57");
        $st->execute([$roomId, $userId]);
        $finishedCount = (int)$st->fetchColumn();
    }

    if ($finishedCount < 4) return;

    // Already recorded?
    if (tableExists($pdo, 'room_winners')) {
        $st = $pdo->prepare("SELECT 1 FROM room_winners WHERE room_id = ? AND user_id = ? LIMIT 1");
        $st->execute([$roomId, $userId]);
        if ($st->fetch()) return;

        $st = $pdo->prepare("SELECT COALESCE(MAX(`rank`),0)+1 FROM room_winners WHERE room_id = ?");
        $st->execute([$roomId]);
        $rank = (int)$st->fetchColumn();
        $st = $pdo->prepare("INSERT INTO room_winners (room_id, user_id, `rank`) VALUES (?, ?, ?)");
        $st->execute([$roomId, $userId, $rank]);
        return;
    }

    // fallback: if room_players has is_winner flag, set it
    if (columnExists($pdo, 'room_players', 'is_winner')) {
        $st = $pdo->prepare("UPDATE room_players SET is_winner = 1 WHERE room_id = ? AND user_id = ?");
        $st->execute([$roomId, $userId]);
    }
}

// ... existing functions above ...

function fetchRoomState(PDO $pdo, string $roomCode): ?array {
    // find room
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) return null;
    $roomId = (int)$room['id'];

    // load game state
    $stmt = $pdo->prepare("SELECT turn_user, last_dice, winner_user FROM game_state WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $gs = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // load players
    $stmt = $pdo->prepare("
        SELECT rp.user_id, rp.position, rp.color, u.username
        FROM room_players rp
        JOIN users u ON rp.user_id = u.id
        WHERE rp.room_id = ?
        ORDER BY rp.position ASC
    ");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $userColor = [];
    $joinedColors = [];
    foreach ($players as $p) {
        $userColor[(int)$p['user_id']] = $p['color'];
        $joinedColors[] = $p['color'];
    }

    // load pieces
    $stmt = $pdo->prepare("SELECT user_id, piece_index, position FROM room_pieces WHERE room_id = ? ORDER BY user_id, piece_index");
    $stmt->execute([$roomId]);
    $pieces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tokens = [];
    foreach ($pieces as $p) {
        $color = $userColor[(int)$p['user_id']] ?? null;
        if (!$color) continue;
        $key = $color . ((int)$p['piece_index'] + 1);
        $tokens[$key] = [
            'pos' => (int)$p['position'],
            'finished' => ((int)$p['position'] === 57)
        ];
    }

    $turnColor = null;
    if (!empty($gs['turn_user'])) {
        $turnUser = (int)$gs['turn_user'];
        $turnColor = $userColor[$turnUser] ?? null;
    }

    return [
        'ok'          => true,
        'turnUser'    => $gs['turn_user'] ?? null,
        'turnColor'   => $turnColor,
        'lastDice'    => isset($gs['last_dice']) ? (int)$gs['last_dice'] : null,
        'players'     => $players,
        'tokens'      => $tokens,
        'joinedColors'=> $joinedColors,
        'winners'     => [] // TODO: extend winner logic
    ];
}
