<?php
// public/play.php
/*declare(strict_types=1);

require_once __DIR__ . '/../lib/boot.php'; // uses auth functions in your boot
auth_require();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Quantum Ludo — Play Local</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <link rel="stylesheet" href="css/style.css"> <!-- keep your global css if any -->
  <style>
    /* Minimal page-specific styles to match your visuals */
    /*:root{--yellow:#FFD700;--yellow-2:#FFEA00}
    body{margin:0;padding:0;font-family:Inter,Arial,Helvetica,sans-serif;background:url("img/bg.jpg") center/cover no-repeat;color:#fff}
    .wrap{max-width:420px;margin:40px auto;padding:18px;text-align:center}
    h1{display:inline-block;background:var(--yellow);color:#000;padding:12px 18px;border-radius:12px;font-size:28px}
    .logo{width:260px;margin:18px auto;display:block}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:20px}
    .btn{background:var(--yellow);color:#000;font-weight:700;border:none;padding:16px;border-radius:18px;cursor:pointer;font-size:16px;box-shadow:0 4px 10px rgba(0,0,0,.25)}
    .btn:hover{background:var(--yellow-2)}
    .back{grid-column:1/-1;background:transparent;border:2px solid rgba(255,255,255,.08);color:#fff}
    .profile-btn{position:fixed;right:16px;top:12px;width:46px;height:46px;border-radius:50%;overflow:hidden;border:3px solid var(--yellow);background:#fff}
    .profile-btn img{width:100%;height:100%;object-fit:cover;display:block}
    .loading{margin-top:18px;font-weight:700}
  </style>
</head>
<body>
  <button class="profile-btn" title="Profile" onclick="location.href='dashboard/profile.php'">
    <img src="img/profile.png" alt="Profile">
  </button>

  <div class="wrap">
    <h1>QUANTUM LUDO</h1>
    <img src="img/logo.png" alt="Ludo" class="logo">

    <div class="grid">
      <button class="btn" data-players="2" onclick="createLocal(event)">PLAY 2</button>
      <button class="btn" data-players="3" onclick="createLocal(event)">PLAY 3</button>
      <button class="btn" data-players="4" onclick="createLocal(event)">PLAY 4</button>
      <a class="btn back" href="dashboard.php">⬅ BACK</a>
    </div>

    <div id="loading" class="loading" style="display:none">Creating local room…</div>
    <div id="error" style="color:#ffb3b3;margin-top:12px;display:none"></div>
  </div>

  <script src="js/play.js"></script>
</body>
</html>
*/