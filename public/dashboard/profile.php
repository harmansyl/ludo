<?php
session_start();
include(__DIR__ . "/../../db_connect.php"); 

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// ✅ Fetch phone & join date from users table
$sql_user = "SELECT phone, created_at FROM users WHERE username = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("s", $username);
$stmt_user->execute();
$res_user = $stmt_user->get_result()->fetch_assoc();

$phone = $res_user['phone'] ?? "N/A";
$joined = $res_user['created_at'] ?? "N/A";

// ✅ Fetch match stats
$sql = "SELECT total_matches, wins, losses FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

$total_matches = $result['total_matches'] ?? 0;
$wins = $result['wins'] ?? 0;
$losses = $result['losses'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Profile - Quantum Ludo</title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: Arial, sans-serif;
      background: url('../img/bg.jpg') no-repeat center center fixed;
      background-size: cover;
      color: white;
      text-align: center;
    }
    .profile-header {
      background: yellow;
      color: black;
      font-weight: bold;
      padding: 12px 25px;
      border-radius: 8px;
      display: inline-block;
      margin-top: 20px;
      font-size: 24px;
      box-shadow: 0 4px 10px rgba(0,0,0,0.4);
    }
    .card {
      background: rgba(20, 20, 20, 0.85); /* darker solid background */
      display: block;
      max-width: 400px;
      margin: 25px auto;
      padding: 25px 30px;
      border-radius: 12px;
      text-align: left;
      box-shadow: 0 6px 20px rgba(0,0,0,0.6);
    }
    .card p {
      font-size: 18px;
      margin: 12px 0;
      color: #ddd;
    }
    .card strong {
      color: #FFD700; /* gold highlight */
    }
    .stats h2 {
      margin-bottom: 15px;
      color: #FFD700;
      text-align: center;
    }
    .stats p {
      font-size: 17px;
      margin: 6px 0;
      text-align: center;
    }
    .back-btn {
      display: inline-block;
      margin-top: 20px;
      padding: 10px 20px;
      background: #FFD700;
      color: black;
      font-weight: bold;
      border-radius: 8px;
      text-decoration: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.4);
      transition: 0.2s;
    }
    .back-btn:hover {
      background: #e6c200;
    }
  </style>
</head>
<body>
  <div class="profile-header">PROFILE</div>

  <!-- Profile Card -->
  <div class="card">
    <p>👤 <strong>Username:</strong> <?php echo htmlspecialchars($username); ?></p>
    <p>📱 <strong>Phone:</strong> <?php echo htmlspecialchars($phone); ?></p>
    <p>📅 <strong>Joined:</strong> <?php echo htmlspecialchars($joined); ?></p>
  </div>

  <!-- Match History Card -->
  <div class="card stats">
    <h2>🎮 Match History</h2>
    <p><strong>Total Matches:</strong> <?php echo $total_matches; ?></p>
    <p><strong>Wins:</strong> <?php echo $wins; ?></p>
    <p><strong>Losses:</strong> <?php echo $losses; ?></p>
  </div>

  <!-- Back Button -->
  <a href="../dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
</body>
</html>
