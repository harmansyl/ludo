<?php
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';

$tournament_id = intval($_GET['id'] ?? 0);
if (!$tournament_id) exit("Tournament not found");

$stmt = $pdo->prepare("SELECT * FROM tournaments WHERE id=?");
$stmt->execute([$tournament_id]);
$tournament = $stmt->fetch();
if (!$tournament) exit("Invalid tournament");

$stmt = $pdo->prepare("SELECT * FROM tournament_matches WHERE tournament_id=? ORDER BY round, id");
$stmt->execute([$tournament_id]);
$matches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by rounds
$bracket = [];
foreach ($matches as $m) {
    $bracket[$m['round']][] = $m;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Tournament Bracket</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js"></script>
    <style>
        .round { margin: 20px; padding: 10px; border: 1px solid #ccc; }
        .match { padding: 8px; margin: 5px 0; border-radius: 5px; background: #f9f9f9; }
        .winner { font-weight: bold; color: green; }
        .pending { color: orange; }
    </style>
</head>
<body>
<h2><?= htmlspecialchars($tournament['name']) ?> Bracket</h2>

<?php foreach ($bracket as $round => $matches): ?>
    <div class="round">
        <h3>Round <?= $round ?></h3>
        <?php foreach ($matches as $m): ?>
            <div class="match">
                <b>Room Code:</b> <?= htmlspecialchars($m['room_code']) ?> <br>
                <b>Scheduled:</b> <?= htmlspecialchars($m['scheduled_at']) ?> <br>
                <?php if ($m['winner_id']): ?>
                    <span class="winner">Winner: User <?= $m['winner_id'] ?></span>
                <?php else: ?>
                    <span class="pending">Pending...</span>
                    <?php if (is_admin()): ?>
                        <form method="post" action="../api/tournament_report.php" style="margin-top:5px;">
                            <input type="hidden" name="match_id" value="<?= $m['id'] ?>">
                            <input type="number" name="winner_id" placeholder="Winner User ID" required>
                            <button type="submit">Report Winner</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<script>
// Auto-refresh every 15s
setTimeout(() => { location.reload(); }, 15000);
</script>
</body>
</html>
