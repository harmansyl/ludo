<?php
require_once __DIR__ . '/../lib/boot.php';

$u = current_user();   // ✅ this returns the full logged-in user row (id, username, role, etc.)
if (!$u || !$u['is_admin']) {
    header('Location: dashboard.php');
    exit;
}

$csrf = get_csrf_token();
?>
<!doctype html><html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin</title>
<link rel="stylesheet" href="css/style.css">
<meta name="csrf-token" content="<?=htmlspecialchars($csrf)?>">
</head><body>
<header class="card"><h2>Admin Console</h2></header>
<div class="card">
  <p>Basic tools: view audit log size / create tournament</p>
  <div class="row">
    <input id="t_name" placeholder="Tournament name (e.g., Independence Cup)">
    <button id="btnCreateTournament">Create</button>
  </div>
</div>
<script src="js/app.js"></script>
</body></html>
