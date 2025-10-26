<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Database connection ---
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO(
            "mysql:host=sql100.infinityfree.com;dbname=if0_39750606_ludo;charset=utf8mb4",
            "if0_39750606",
            "HSgaming18"
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

// --- Helpers ---

// Read JSON request body
function require_json(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// Current user info
function current_user(): ?array {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, username, phone, is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Require login (redirect for web pages)
function auth_require(): void {
    $user = current_user();
    if (!$user) {
        header("Location: index.php");
        exit;
    }
}

// Require login (JSON API version)
function require_login(): void {
    if (!current_user()) {
        json_error("Not logged in");
    }
}

// Check if admin
function is_admin(): bool {
    $user = current_user();
    return !empty($user['is_admin']);
}

// --- JSON response helpers ---

function json_response(array $data): void {
    header("Content-Type: application/json");
    echo json_encode($data);
    exit;
}

function json_error(string $msg): void {
    json_response(['ok' => false, 'error' => $msg]);
}

function json_success(array $extra = []): void {
    json_response(['ok' => true] + $extra);
}

function init_game_state(PDO $pdo, int $roomId): void {
    // check if already exists
    $stmt = $pdo->prepare("SELECT 1 FROM game_state WHERE room_id=?");
    $stmt->execute([$roomId]);
    if ($stmt->fetch()) {
        return;
    }

    // pick first player as starting turn
    $stmt = $pdo->prepare("SELECT user_id, color 
                           FROM room_players 
                           WHERE room_id=? 
                           ORDER BY joined_at ASC LIMIT 1");
    $stmt->execute([$roomId]);
    $first = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$first) {
        throw new RuntimeException("No players in room to initialize game state");
    }

    // insert state with both user and color
    $stmt = $pdo->prepare("
        INSERT INTO game_state (room_id, turn_user, turn_color, last_dice, winner_color, updated_at) 
        VALUES (?, ?, ?, NULL, NULL, NOW())
    ");
    $stmt->execute([$roomId, $first['user_id'], $first['color']]);
}
