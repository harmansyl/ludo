<?php
declare(strict_types=1);

// Always return JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once __DIR__ . '/../lib/boot.php';

auth_require();
$pdo = db();

// Get room code from query parameter
$roomCode = strtoupper(trim($_GET['room'] ?? ''));

if (!$roomCode) {
    echo json_encode(['status' => 'error', 'error' => 'Missing room code']);
    exit;
}

// Fetch room status
$stmt = $pdo->prepare("SELECT status FROM rooms WHERE code = ?");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {
    echo json_encode(['status' => 'not_found']);
    exit;
}

// Return current room status
echo json_encode(['status' => $room['status']]);
