<?php
session_start();
require_once __DIR__ . '/../lib/boot.php';

// If not logged in → redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Check if user is admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: dashboard.php"); // kick non-admins back to user dashboard
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Dashboard - Quantum Ludo</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: url("img/bg.jpg") no-repeat center center fixed;
      background-size: cover;
      min-height: 100vh;
      color: #fff;
    }

    .top-bar {
      background: #FFD700;
      color: black;
      padding: 15px;
      text-align: center;
      font-size: 20px;
      font-weight: bold;
      position: sticky;
      top: 0;
    }

    .container {
      display: flex;
      justify-content: center;
      align-items: flex-start;
      padding: 40px;
    }

    .card {
      background: rgba(0,0,0,0.8);
      border: 2px solid #FFD700;
      border-radius: 15px;
      padding: 25px;
      width: 300px;
      margin: 15px;
      text-align: center;
    }

    .card h2 {
      margin-bottom: 15px;
      color: #FFD700;
    }

    .btn {
      display: inline-block;
      margin: 10px 0;
      padding: 10px 20px;
      background: #FFD700;
      color: black;
      font-weight: bold;
      text-decoration: none;
      border-radius: 10px;
    }

    .btn:hover {
      background: #FFEA00;
    }

    .logout-btn {
      margin-top: 25px;
      background: crimson;
      color: white;
    }

    .logout-btn:hover {
      background: red;
    }
  </style>
</head>
<body>
  <div class="top-bar">
    ADMIN DASHBOARD - QUANTUM LUDO
  </div>

  <div class="container">
    <div class="card">
      <h2>User Management</h2>
      <a href="#" class="btn">View Users</a><br>
      <a href="#" class="btn">Ban User</a><br>
      <a href="#" class="btn">Promote User</a>
    </div>

    <div class="card">
      <h2>Game Management</h2>
      <a href="#" class="btn">View Rooms</a><br>
      <a href="#" class="btn">Active Tournaments</a><br>
      <a href="#" class="btn">Game Logs</a>
    </div>

    <div class="card">
      <h2>Settings</h2>
      <a href="#" class="btn">Site Config</a><br>
      <a href="#" class="btn">Reports</a><br>
      <a href="logout.php" class="btn logout-btn">Logout</a>
    </div>
  </div>
</body>
</html>
