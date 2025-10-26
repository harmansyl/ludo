<?php
session_start();
include(__DIR__ . "/../../db_connect.php"); 

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];

// ✅ Fetch user match history from new table
$sql = "SELECT players_count, position, result, played_at 
        FROM game_history 
        WHERE username = ?
        ORDER BY played_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Match History - Quantum Ludo</title>
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
    .header {
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
    table {
      width: 80%;
      margin: 30px auto;
      border-collapse: collapse;
      background: rgba(20, 20, 20, 0.85);
      border-radius: 10px;
      overflow: hidden;
      box-shadow: 0 6px 20px rgba(0,0,0,0.6);
    }
    th, td {
      padding: 12px 15px;
      border-bottom: 1px solid #444;
      text-align: center;
    }
    th {
      background: #FFD700;
      color: black;
      font-size: 18px;
    }
    tr:hover {
      background: rgba(255,255,255,0.1);
    }
    td {
      font-size: 16px;
    }
    .win {
      color: #00ff00;
      font-weight: bold;
    }
    .lose {
      color: #ff4d4d;
      font-weight: bold;
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
  <div class="header">📜 Match History</div>

  <table>
    <tr>
      <th>Players</th>
      <th>Position</th>
      <th>Result</th>
      <th>Date Played</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()) { ?>
      <tr>
        <td><?php echo $row['players_count']; ?>P</td>
        <td><?php echo $row['position'] ?? "-"; ?></td>
        <td class="<?php echo $row['result']; ?>">
          <?php echo ucfirst($row['result']); ?>
        </td>
        <td><?php echo $row['played_at']; ?></td>
      </tr>
    <?php } ?>
  </table>

  <a href="../dashboard.php" class="back-btn">⬅ Back to Dashboard</a>
</body>
</html>
