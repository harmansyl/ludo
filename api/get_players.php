<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../lib/db.php';

header("Content-Type: application/json; charset=UTF-8");

function respond($data) {
    echo json_encode($data);
    exit;
}

try {
    $roomCode = $_GET['room'] ?? '';
    $roomCode = trim((string)$roomCode);
    if ($roomCode === '') {
        respond(['ok' => false, 'error' => 'Missing room code']);
    }

    $pdo = db();

    // ✅ Ensure room exists
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ? LIMIT 1");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        respond(['ok' => false, 'error' => 'Room not found']);
    }
    $roomId = (int)$room['id'];

    // ✅ Always return players in fixed Ludo order: red → blue → green → yellow
    $stmt = $pdo->prepare("
        SELECT user_id, color
        FROM room_players
        WHERE room_id = ?
        ORDER BY FIELD(color, 'red','blue','green','yellow')
    ");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach ($players as $p) {
        $out[] = [
            'user_id' => (int)$p['user_id'],
            'color'   => strtolower($p['color'])
        ];
    }

    respond(['ok' => true, 'players' => $out]);
} catch (Throwable $e) {
    respond(['ok' => false, 'error' => $e->getMessage()]);
}
