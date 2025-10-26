<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/boot.php';

auth_require();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quantum Ludo - Join Now</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

  <?php include "partials/profile_menu.php"; ?>

  <div class="dashboard">
    <h1>JOIN NOW</h1>
    <img src="../img/logo.png" alt="Join Now">

    <form method="post" action="../../api/join_room.php">
  <input type="text" name="room" placeholder="Enter Room Code" required
         style="padding:12px; border-radius:10px; border:none; font-size:16px; margin:15px;">
  <br>
  <button type="submit" class="btn">JOIN GAME</button>
  <a href="../dashboard.php" class="btn">⬅ BACK</a>
</form>

  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
