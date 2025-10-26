<?php
require_once __DIR__."/../lib/boot.php";
if (!is_admin()) exit("Admins only");

$stmt = $pdo->query("SELECT a.*, u.username 
    FROM audit_log a 
    LEFT JOIN users u ON a.user_id=u.id 
    ORDER BY a.created_at DESC LIMIT 200");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Audit Log</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<h2>Audit Log (latest 200)</h2>
<table border="1" cellpadding="5">
<tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?=htmlspecialchars($r['created_at'])?></td>
  <td><?=htmlspecialchars($r['username']??'')?></td>
  <td><?=htmlspecialchars($r['action'])?></td>
  <td><?=htmlspecialchars($r['details'])?></td>
</tr>
<?php endforeach; ?>
</table>
</body>
</html>
