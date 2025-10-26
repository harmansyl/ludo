<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/boot.php';

auth_require();
$user = current_user();
$pdo  = db();

// Handle form submission (JOIN ROOM)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join'])) {
    $roomCode = trim($_POST['room'] ?? '');

    if ($roomCode !== '') {
        // Check if room exists
        $stmt = $pdo->prepare("SELECT id FROM rooms WHERE code = ?");
        $stmt->execute([$roomCode]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($room) {
            $roomId = (int)$room['id'];

            // Check if player already in room
            $check = $pdo->prepare("SELECT 1 FROM room_players WHERE room_id=? AND user_id=?");
            $check->execute([$roomId, $user['id']]);
            if (!$check->fetch()) {
                // Find next available position
                $posStmt = $pdo->prepare("SELECT IFNULL(MAX(position), -1) + 1 FROM room_players WHERE room_id=?");
                $posStmt->execute([$roomId]);
                $nextPos = (int)$posStmt->fetchColumn();

                // Assign a color based on position (0=red,1=blue,2=green,3=yellow)
                $colors = ['red', 'blue', 'green', 'yellow'];
                $color = $colors[$nextPos] ?? null;

                if (!$color) {
                    die("Room is full (max 4 players)");
                }

                // Insert player into room
                $ins = $pdo->prepare("INSERT INTO room_players (room_id, user_id, position, color, is_winner, joined_at) 
                                      VALUES (?, ?, ?, ?, 0, NOW())");
                $ins->execute([$roomId, $user['id'], $nextPos, $color]);
            }

            // Redirect all players to lobby (create_room.php)
            header("Location: create_room.php?room=" . urlencode($roomCode));
            exit;
        } else {
            $error = "Room not found!";
        }
    } else {
        $error = "Please enter a valid room code!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quantum Ludo - Join or Create Room</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    body {
      margin: 0;
      font-family: Arial, sans-serif;
      background: url('../img/bg.jpg') no-repeat center center fixed;
      background-size: cover;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      color: #fff;
    }
    .room-card {
      background: rgba(0, 0, 0, 0.8);
      padding: 40px;
      border-radius: 20px;
      text-align: center;
      box-shadow: 0 0 25px rgba(255, 215, 0, 0.6);
      width: 350px;
    }
    .room-card h1 {
      font-size: 26px;
      background: #FFD700;
      color: #000;
      padding: 12px;
      border-radius: 12px;
      margin-bottom: 25px;
    }
    .room-card img {
      width: 150px;
      margin-bottom: 25px;
    }
    .room-card input {
      width: 80%;
      padding: 12px;
      margin-bottom: 20px;
      border-radius: 10px;
      border: none;
      font-size: 16px;
      text-align: center;
    }
    .btn {
      display: block;
      width: 80%;
      margin: 12px auto;
      padding: 14px;
      font-size: 16px;
      font-weight: bold;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: 0.3s;
    }
    .btn-join {
      background: #28a745;
      color: #fff;
    }
    .btn-join:hover {
      background: #218838;
      box-shadow: 0 0 12px #28a745;
    }
    .btn-create {
      background: #FFD700;
      color: #000;
    }
    .btn-create:hover {
      background: #e6c200;
      box-shadow: 0 0 12px #FFD700;
    }
    .btn-back {
      background: #007bff;
      color: #fff;
    }
    .btn-back:hover {
      background: #0069d9;
      box-shadow: 0 0 12px #007bff;
    }
    hr {
      margin: 25px 0;
      border: 1px solid #444;
    }
    .error {
      color: #ff6666;
      font-weight: bold;
      margin-bottom: 15px;
    }
  </style>
</head>
<body>

  <div class="room-card">
    <h1>JOIN OR CREATE ROOM</h1>
    <img src="../img/logo.png" alt="Ludo Board">

    <?php if (!empty($error)): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- JOIN FORM -->
    <form method="post"> 
      <input type="text" name="room" placeholder="Enter Room Code" required>
      <button type="submit" name="join" class="btn btn-join">🎮 JOIN NOW</button>
    </form>

    <hr>

    <!-- CREATE ROOM -->
    <a href="create_room.php" class="btn btn-create">✨ CREATE NEW ROOM</a>

    <a href="../dashboard.php" class="btn btn-back">⬅ BACK</a>
  </div>

</body>
</html>
