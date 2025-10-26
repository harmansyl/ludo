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

    $data = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $roomCode = trim((string)($data['room'] ?? $_GET['room'] ?? ''));

    if ($roomCode === '') {
        throw new RuntimeException("Missing room code");
    }

    $pdo->beginTransaction();

    // lock room row
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        throw new RuntimeException("Room not found");
    }
    $roomId = (int)$room['id'];

    // load players in the room (ordered)
    $stmt = $pdo->prepare("SELECT user_id, color, position FROM room_players WHERE room_id = ? ORDER BY position ASC");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($players)) {
        throw new RuntimeException("No players in room");
    }

    // assign fixed colors
    $colorMap = ['red', 'blue', 'green', 'yellow'];
    $updColor = $pdo->prepare("UPDATE room_players SET color=? WHERE room_id=? AND user_id=?");
    foreach ($players as $idx => $p) {
        $color = $colorMap[$idx] ?? null;
        if ($color && $p['color'] !== $color) {
            $updColor->execute([$color, $roomId, $p['user_id']]);
            $players[$idx]['color'] = $color; // update in array
        }
    }

    // ensure each player has 4 tokens
    $chk = $pdo->prepare("SELECT piece_index FROM room_pieces WHERE room_id = ? AND user_id = ?");
    $ins = $pdo->prepare("INSERT INTO room_pieces (room_id, user_id, piece_index, position) VALUES (?, ?, ?, ?)");
    foreach ($players as $p) {
        $uid = (int)$p['user_id'];
        $chk->execute([$roomId, $uid]);
        $existing = $chk->fetchAll(PDO::FETCH_COLUMN);
        $have = array_map('intval', $existing);
        for ($i = 0; $i < 4; $i++) {
            if (!in_array($i, $have, true)) {
                $ins->execute([$roomId, $uid, $i, 0]);
            }
        }
    }

    // setup game_state
    $gsStmt = $pdo->prepare("SELECT turn_user, turn_color FROM game_state WHERE room_id = ? LIMIT 1 FOR UPDATE");
    $gsStmt->execute([$roomId]);
    $gs = $gsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$gs) {
        $first = $players[0]; // host (red) always starts
        $insertGs = $pdo->prepare("INSERT INTO game_state (room_id, turn_user, turn_color, last_dice, last_color, winner_user, updated_at)
            VALUES (?, ?, ?, NULL, NULL, NULL, NOW())");
        $insertGs->execute([$roomId, (int)$first['user_id'], $first['color']]);
    } else {
        if ($gs['turn_user'] === null) {
            $first = $players[0];
            $upd = $pdo->prepare("UPDATE game_state SET turn_user = ?, turn_color = ?, updated_at = NOW() WHERE room_id = ?");
            $upd->execute([(int)$first['user_id'], $first['color'], $roomId]);
        } else {
            $pdo->prepare("UPDATE game_state SET updated_at = NOW() WHERE room_id = ?")->execute([$roomId]);
        }
    }

    // mark room active
    $updRoom = $pdo->prepare("UPDATE rooms SET status = 'active' WHERE id = ?");
    $updRoom->execute([$roomId]);

    file_put_contents(__DIR__ . "/debug.log", "Init_game reached for room $roomId\n", FILE_APPEND);

    $pdo->commit();

    echo json_encode(['ok' => true]);
    exit;

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Init failed: ' . $e->getMessage()]);
    exit;
}
