<?php
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../lib/db.php';
$pdo = db();

try {
    // Support both form-data and raw JSON
    $data = $_POST;
    if (empty($data)) {
        $json = file_get_contents("php://input");
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }

    $roomCode = $data['room'] ?? null;
    $color    = $data['color'] ?? null;

    if (!$roomCode || !$color) {
        echo json_encode(["ok" => false, "error" => "Missing room or color"]);
        exit;
    }

    // ✅ Check if room exists
    $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        echo json_encode(["ok" => false, "error" => "Room not found"]);
        exit;
    }
    $roomId = (int)$room['id'];

    // 🎲 Generate random dice value 1–6
    $diceValue = rand(1, 6);

    // ✅ Get actual players in the room from room_players
    $stmt = $pdo->prepare("
        SELECT color 
        FROM room_players 
        WHERE room_id = ? 
        ORDER BY FIELD(color,'red','green','blue','yellow')
    ");
    $stmt->execute([$roomId]);
    $players = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!$players || !in_array(strtolower($color), array_map('strtolower', $players))) {
        echo json_encode(["ok" => false, "error" => "Player color not valid for this room"]);
        exit;
    }

    $players = array_map('strtolower', $players);

    // ✅ Decide next player (only among joined players, clockwise order)
    $currentColor = strtolower($color);
    $currentIndex = array_search($currentColor, $players);
    $nextIndex = ($currentIndex + 1) % count($players);
    $nextColor = $players[$nextIndex];

    // ✅ Save roll in DB (rooms table)
    $stmt = $pdo->prepare("
        UPDATE rooms 
        SET last_dice = ?, last_color = ?, turn_color = ?, turn_updated = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$diceValue, $currentColor, $nextColor, $roomId]);

    // ✅ Also update game_state (so board.js sees correct turn)
    $stmt = $pdo->prepare("
        UPDATE game_state 
        SET last_dice = ?, last_color = ?, turn_color = ?, updated_at = NOW()
        WHERE room_id = ?
    ");
    $stmt->execute([$diceValue, $currentColor, $nextColor, $roomId]);

    echo json_encode([
        "ok"        => true,
        "dice"      => $diceValue,
        "color"     => $currentColor,
        "nextColor" => $nextColor
    ]);

} catch (Throwable $e) {
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
