<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();
require_once __DIR__ . '/../lib/boot.php';

header('Content-Type: application/json');

try {
    auth_require();
    $pdo = db();

    $roomCode = $_POST['room'] ?? ($_GET['room'] ?? '');
    $roomCode = trim((string)$roomCode);
    if ($roomCode === '') {
        throw new RuntimeException("Missing room code");
    }

    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException("Not logged in");
    }
    $userId = (int) $_SESSION['user_id'];

    // start transaction and lock room row for concurrent safety
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) throw new RuntimeException("Room not found");
    $roomId = (int)$room['id'];

    // already joined?
    $stmt = $pdo->prepare("SELECT color FROM room_players WHERE room_id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$roomId, $userId]);
    $already = $stmt->fetch(PDO::FETCH_ASSOC);
    $color = $already['color'] ?? null;

    if (!$already) {
        // read taken colors in deterministic clockwise order
        $stmt = $pdo->prepare("SELECT color FROM room_players WHERE room_id = ? ORDER BY FIELD(color,'red','green','blue','yellow')");
        $stmt->execute([$roomId]);
        $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);
        // reduce to unique non-empty values (defensive)
        $taken = array_values(array_filter(array_unique($taken), function($c){ return $c !== null && $c !== ''; }));

        $currentCount = count($taken);
        if ($currentCount >= 4) {
            throw new RuntimeException("Room is full");
        }

        // Decide seat/color preferences:
        // - If game will be 2 players → prefer red & blue (cross seats)
        // - Otherwise prefer clockwise order red, green, blue, yellow
        if ($currentCount + 1 === 2) {
            $preferred = ['red','blue'];
        } elseif ($currentCount + 1 === 3) {
            // 3 players in clockwise order (red, green, blue)
            $preferred = ['red','green','blue'];
        } else {
            $preferred = ['red','green','blue','yellow'];
        }

        // pick the first preferred color not taken
        $free = null;
        foreach ($preferred as $c) {
            if (!in_array($c, $taken, true)) {
                $free = $c;
                break;
            }
        }
        if ($free === null) {
            throw new RuntimeException("Room is full");
        }
        $color = $free;

        // position: store seat index (1..4) based on clockwiseFull positions
        $clockwiseFull = ['red','green','blue','yellow'];
        $posIdx = array_search($color, $clockwiseFull, true);
        $position = ($posIdx === false) ? ($currentCount + 1) : ($posIdx + 1);

        $ins = $pdo->prepare("INSERT INTO room_players (room_id, user_id, color, position, is_winner, joined_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $ins->execute([$roomId, $userId, $color, $position]);
    }

    // ensure 4 tokens per player (idempotent)
    $chkPieces = $pdo->prepare("SELECT piece_index FROM room_pieces WHERE room_id = ? AND user_id = ?");
    $chkPieces->execute([$roomId, $userId]);
    $existing = $chkPieces->fetchAll(PDO::FETCH_COLUMN);
    $have = array_map('intval', $existing);
    $insPiece = $pdo->prepare("INSERT INTO room_pieces (room_id, user_id, piece_index, position) VALUES (?, ?, ?, ?)");
    for ($i = 0; $i < 4; $i++) {
        if (!in_array($i, $have, true)) {
            $insPiece->execute([$roomId, $userId, $i, 0]);
        }
    }

    // ensure game_state exists and a starter is set (prefer red if present)
    $stmt = $pdo->prepare("SELECT turn_user FROM game_state WHERE room_id = ?");
    $stmt->execute([$roomId]);
    $gs = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$gs) {
        $stmt = $pdo->prepare("SELECT user_id, color FROM room_players WHERE room_id = ? ORDER BY FIELD(color,'red','green','blue','yellow') LIMIT 1");
        $stmt->execute([$roomId]);
        $first = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($first) {
            $stmt = $pdo->prepare("INSERT INTO game_state (room_id, turn_user, turn_color, last_dice, last_color, winner_user, updated_at) VALUES (?, ?, ?, NULL, NULL, NULL, NOW())");
            $stmt->execute([$roomId, $first['user_id'], $first['color']]);
        } else {
            // fallback
            $pdo->prepare("INSERT INTO game_state (room_id, updated_at) VALUES (?, NOW())")->execute([$roomId]);
        }
    }

    $pdo->commit();

    $isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    if ($isAjax) {
        echo json_encode(['ok' => true, 'color' => $color, 'room' => $roomCode]);
        exit;
    } else {
        header("Location: ../public/dashboard/create_room.php?room=" . urlencode($roomCode));
        exit;
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    exit;
}
