<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$pdo = db();

function generateKey(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // exclude confusing chars
    $key = '';
    for ($i = 0; $i < $length; $i++) {
        $key .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $key;
}

$count = 500;
$inserted = 0;

for ($i = 0; $i < $count; $i++) {
    $key = generateKey(10);

    try {
        $stmt = $pdo->prepare("INSERT INTO special_keys (skey) VALUES (?)");
        $stmt->execute([$key]);
        $inserted++;
    } catch (Throwable $e) {
        // skip duplicates, regenerate
        $i--;
    }
}

echo "Generated $inserted special keys.\n";
