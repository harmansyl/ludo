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
  <title>Quantum Ludo - Tournament</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

  <?php include __DIR__ . "/partials/profile_menu.php"; ?>

  <div class="dashboard">
    <h1>TOURNAMENT</h1>
    <img src="../img/logo.png" alt="Tournament">

    <div class="btn-grid">
      <a href="#" class="btn">VIEW UPCOMING</a>
      <a href="#" class="btn">MY TOURNAMENTS</a>
      <a href="#" class="btn">CREATE TOURNAMENT</a>
      <a href="../dashboard.php" class="btn">⬅ BACK</a>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
