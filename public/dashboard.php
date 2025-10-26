<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';

// Require login
auth_require();
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quantum Ludo - Dashboard</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: url("img/bg.jpg") no-repeat center center fixed;
      background-size: cover;
      color: #fff;
      text-align: center;
    }

    .dashboard {
      margin-top: 80px;
    }

    .dashboard h1 {
      font-size: 36px;
      margin-bottom: 20px;
      background: #FFD700;
      color: black;
      padding: 10px 20px;
      border-radius: 10px;
      display: inline-block;
    }

    .dashboard img {
      width: 280px;
      margin: 15px auto;
      display: block;
    }

    .btn-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 15px;
      margin-top: 20px;
      max-width: 400px;
      margin-left: auto;
      margin-right: auto;
    }

    .btn, .join-btn {
      background: #FFD700;
      color: black;
      font-weight: bold;
      border: none;
      border-radius: 15px;
      padding: 15px;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      font-size: 16px;
      transition: background 0.2s ease;
    }
    .btn:hover, .join-btn:hover {
      background: #FFEA00;
    }

    .join-btn {
      margin-top: 25px;
      max-width: 365px;
      border-radius: 25px;
      display: block;
      margin-left: auto;
      margin-right: auto;
    }

    /* Profile button at top-right */
    .profile-button {
      position: fixed;
      top: 15px;
      right: 15px;
      background: none;
      border: none;
      cursor: pointer;
      padding: 0;
      z-index: 1100;
    }
    .profile-button img {
      width: 45px;
      height: 45px;
      border-radius: 50%;
      border: 2px solid #FFD700;
    }

    /* Dropdown menu */
    .dropdown-menu {
      position: fixed;
      top: 65px;
      right: 15px;
      background: rgba(0,0,0,0.95);
      border: 2px solid #FFD700;
      border-radius: 10px;
      display: flex;
      flex-direction: column;
      width: 180px;
      opacity: 0;
      transform: translateY(-10px);
      pointer-events: none;
      transition: all 0.3s ease;
      z-index: 1099;
    }
    .dropdown-menu.show {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }
    .dropdown-menu .user {
      padding: 10px;
      color: #FFD700;
      font-weight: bold;
      border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    .dropdown-menu a {
      color: #FFD700;
      text-decoration: none;
      padding: 12px;
      font-size: 14px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      text-align: left;
    }
    .dropdown-menu a:hover {
      background: #FFD700;
      color: black;
    }
    .dropdown-menu a:last-child {
      border-bottom: none;
    }
  </style>
</head>
<body>

  <!-- Profile Button -->
  <button class="profile-button" onclick="toggleMenu()">
    <img src="img/profile.png" alt="Profile">
  </button>

  <!-- Dropdown Menu -->
  <div id="profileMenu" class="dropdown-menu">
    <div class="user">👤 <?= htmlspecialchars($user['username']) ?></div>
    <a href="dashboard/profile.php">PROFILE</a>
    <a href="dashboard/history.php">HISTORY</a>
    <a href="dashboard/contact.php">CONTACT US</a>
    <a href="logout.php">LOGOUT</a>
  </div>

  <!-- Dashboard Content -->
  <div class="dashboard">
    <h1>QUANTUM LUDO</h1>
    <img src="img/logo.png" alt="Ludo Board">

    <div class="btn-grid">
      <a href="dashboard/local_play.php" class="btn">PLAY LOCAL</a>
      <a href="dashboard/join_room.php" class="btn">JOIN ROOM</a>
      <a href="dashboard/join_contest.php" class="btn">JOIN CONTEST</a>
      <a href="dashboard/tournament.php" class="btn">TOURNAMENT</a>
    </div>

    <a href="dashboard/join_now.php" class="join-btn">JOIN NOW</a>
  </div>

  <script>
    function toggleMenu() {
      document.getElementById("profileMenu").classList.toggle("show");
    }

    // Close menu if clicked outside
    document.addEventListener("click", function(e) {
      const menu = document.getElementById("profileMenu");
      const button = document.querySelector(".profile-button");
      if (!menu.contains(e.target) && !button.contains(e.target)) {
        menu.classList.remove("show");
      }
    });
  </script>
</body>
</html>
