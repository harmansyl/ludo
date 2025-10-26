<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/boot.php';

// Require login
auth_require();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Quantum Ludo — Local Play</title>
<link rel="stylesheet" href="../style/dashboard.css" />
<style>
body {
  margin:0; padding:0;
  font-family: "Segoe UI", Roboto, Arial, sans-serif;
  background: url("../img/bg.jpg") no-repeat center center fixed;
  background-size: cover;
  color: #fff;
}
.container {
  max-width: 420px;
  margin: 80px auto;
  text-align:center;
}
.hdr h1 {
  background:#FFD700; color:#000; padding:10px 18px;
  border-radius:14px; font-size:22px; font-weight:800;
  box-shadow: 0 6px 18px rgba(0,0,0,0.3);
  display:inline-block;
}
.btn {
  display:block;
  width:100%; max-width:260px;
  margin:14px auto; padding:14px;
  font-size:18px; font-weight:700;
  border:none; border-radius:28px;
  cursor:pointer;
  background:#FFD700; color:#000;
  box-shadow:0 8px 22px rgba(0,0,0,0.35);
}
.btn:hover { background:#ffea4d; }
.exit-btn {
  position: fixed; left: 18px; top: 18px;
  background: transparent;
  border: 3px solid #FFD700;
  color: #fff; padding: 10px 16px;
  border-radius: 16px; font-weight:700;
  cursor: pointer; background: rgba(0,0,0,0.25);
}
.exit-btn:hover { background: rgba(0,0,0,0.45); }
</style>
</head>
<body>
<button class="exit-btn" onclick="window.location.href='../dashboard.php'">EXIT</button>

<div class="container">
  <div class="hdr"><h1>QUANTUM LUDO</h1></div>
  <p style="margin-top:20px;font-weight:600;">Choose number of players:</p>
  <button class="btn" onclick="startGame(2)">2 PLAYERS</button>
  <button class="btn" onclick="startGame(3)">3 PLAYERS</button>
  <button class="btn" onclick="startGame(4)">4 PLAYERS</button>
</div>

<script>
function startGame(n) {
  window.location.href = "local_board.php?players=" + n;
}
</script>
</body>
</html>
