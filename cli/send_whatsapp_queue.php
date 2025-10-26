<?php
require_once __DIR__ . '/../lib/db.php';

$stmt = $pdo->query("SELECT m.id, m.room_code, m.scheduled_at, e.phone 
    FROM tournament_matches m
    JOIN tournament_entries e ON m.tournament_id=e.tournament_id
    WHERE m.finished=0 AND m.scheduled_at > NOW()");
$rows = $stmt->fetchAll();

foreach ($rows as $r) {
    echo "Send to {$r['phone']} => Room {$r['room_code']} at {$r['scheduled_at']}\n";
    // In real: call WhatsApp API
}
