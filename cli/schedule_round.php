<?php
require_once __DIR__ . '/../lib/db.php';

// Round schedule times (IST)
$schedule = [
    1 => "2025-08-17 16:00:00",
    2 => "2025-08-17 17:30:00",
    3 => "2025-08-17 19:00:00",
    4 => "2025-08-17 20:00:00",
    5 => "2025-08-17 21:00:00"
];

$tournament_id = intval($argv[1] ?? 0);
if (!$tournament_id) exit("Need tournament_id");

// Find what round is pending
$stmt = $pdo->prepare("SELECT MAX(round) as r FROM tournament_matches WHERE tournament_id=?");
$stmt->execute([$tournament_id]);
$last_round = $stmt->fetchColumn();
$next_round = $last_round ? $last_round+1 : 1;

if (!isset($schedule[$next_round])) exit("No more rounds");

$schedule_time = $schedule[$next_round];
$_POST = [
    "tournament_id" => $tournament_id,
    "round" => $next_round,
    "scheduled_at" => $schedule_time
];
include __DIR__ . '/../api/tournament_bracket.php';
