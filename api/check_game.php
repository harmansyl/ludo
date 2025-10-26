<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');

try {
    auth_require();
    $pdo = db();

    $roomCode = $_GET['room'] ?? '';
    if ($roomCode === '') {
        throw new RuntimeException("Missing room code");
    }

    $stmt = $pdo->prepare("SELECT id, status FROM rooms WHERE code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$room) {
        throw new RuntimeException("Room not found");
    }
echo json_encode([
    'ok' => true,
    'status' => $room['status'],
    'started' => ($room['status'] === 'active')
]);

    exit;

} catch (Throwable $e) {
    file_put_contents(__DIR__ . '/../error_log.txt', $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}

