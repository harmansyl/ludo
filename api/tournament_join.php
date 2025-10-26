<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

try {
    $data = require_json();

    $tournament_id = (int)($data['tournament_id'] ?? 0);
    $special_key   = trim((string)($data['special_key'] ?? ''));

    $user = current_user();
    if (!$user) {
        echo json_encode(["ok" => false, "error" => "Not logged in"]);
        exit;
    }

    if ($tournament_id <= 0 || $special_key === '') {
        echo json_encode(["ok" => false, "error" => "Missing data"]);
        exit;
    }

    $pdo = db();

    // check tournament
    $stmt = $pdo->prepare("SELECT id FROM tournaments WHERE id=? AND status='scheduled'");
    $stmt->execute([$tournament_id]);
    if (!$stmt->fetch()) {
        echo json_encode(["ok" => false, "error" => "Tournament not found or not open"]);
        exit;
    }

    // check special key validity
    $stmt = $pdo->prepare("SELECT id, used FROM special_keys WHERE code=? LIMIT 1");
    $stmt->execute([$special_key]);
    $keyRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$keyRow) {
        echo json_encode(["ok" => false, "error" => "Invalid special key"]);
        exit;
    }
    if ($keyRow['used']) {
        echo json_encode(["ok" => false, "error" => "Key already used"]);
        exit;
    }

    // join tournament
    $stmt = $pdo->prepare(
        "INSERT INTO tournament_players (tournament_id, user_id, special_key) VALUES (?, ?, ?)"
    );
    $stmt->execute([$tournament_id, $user['id'], $special_key]);

    // mark key as used
    $pdo->prepare("UPDATE special_keys SET used=1 WHERE id=?")->execute([$keyRow['id']]);

    echo json_encode(["ok" => true, "message" => "Joined tournament successfully"]);

} catch (Throwable $e) {
    echo json_encode(["ok" => false, "error" => $e->getMessage()]);
}
