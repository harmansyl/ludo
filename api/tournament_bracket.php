<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");


csrf_verify();
$data = require_json();
$pdo = db();
// Verify login + CSRF
if (!current_user()) {
    echo json_encode(['ok'=>false,'error'=>'Login required']); exit;
}
csrf_verify($_POST['csrf'] ?? null);

$action = $_POST['action'] ?? '';
$userId = auth_userid();

switch ($action) {
    case 'create':
        if (!is_admin()) { echo json_encode(['ok'=>false,'error'=>'Admin only']); exit; }

        $name = trim($_POST['name'] ?? 'Tournament');
        $pdo->prepare("INSERT INTO tournaments (name,status) VALUES (?, 'PENDING')")
            ->execute([$name]);
        $tid = $pdo->lastInsertId();

        audit_log($userId, "CREATE_TOURNAMENT", "T#{$tid} - $name");

        echo json_encode(['ok'=>true,'tournament_id'=>$tid]);
        break;

    case 'view':
        $tid = intval($_POST['tournament_id'] ?? 0);
        if (!$tid) { echo json_encode(['ok'=>false,'error'=>'Missing tournament']); exit; }

        $stmt = $pdo->prepare("SELECT * FROM tournament_matches WHERE tournament_id=? ORDER BY round_number, match_number");
        $stmt->execute([$tid]);
        $matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['ok'=>true,'bracket'=>$matches]);
        break;

    case 'report_winner':
        $matchId = intval($_POST['match_id'] ?? 0);
        $winnerId = intval($_POST['winner_user_id'] ?? 0);

        // Validate match exists
        $stmt = $pdo->prepare("SELECT * FROM tournament_matches WHERE id=?");
        $stmt->execute([$matchId]);
        $match = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$match) { echo json_encode(['ok'=>false,'error'=>'Match not found']); exit; }

        // Save winner
        $pdo->prepare("UPDATE tournament_matches SET winner_user_id=? WHERE id=?")
            ->execute([$winnerId,$matchId]);

        audit_log($userId,"REPORT_WINNER","Match#{$matchId} Winner={$winnerId}");

        echo json_encode(['ok'=>true]);
        break;

    default:
        echo json_encode(['ok'=>false,'error'=>'Unknown action']);
}
