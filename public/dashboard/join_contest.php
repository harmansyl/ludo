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
  <title>Quantum Ludo - Join Contest</title>
  <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>

  <?php include "partials/profile_menu.php"; ?>

  <div class="dashboard">
    <h1>JOIN CONTEST</h1>
    <img src="../img/logo.png" alt="Ludo Contest">

    <div class="btn-grid">
      <a href="#" class="btn">CONTEST 1</a>
      <a href="#" class="btn">CONTEST 2</a>
      <a href="#" class="btn">CONTEST 3</a>
      <a href="../dashboard.php" class="btn">⬅ BACK</a>
    </div>
  </div>

  <script src="../js/dashboard.js"></script>
</body>
</html>
