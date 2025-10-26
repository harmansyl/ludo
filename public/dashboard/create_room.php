<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/boot.php';

auth_require();
$user = current_user();
$db = db();

// If a room code is passed → join/show lobby
$roomCode = $_GET['room'] ?? null;

if ($roomCode) {
    // Find existing room
    $stmt = $db->prepare("SELECT id, status FROM rooms WHERE code = ?");
    $stmt->execute([$roomCode]);
    $room = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$room) {
        die("Room not found");
    }
    $roomId = (int)$room['id'];

    // Ensure user is in room_players
    $stmt = $db->prepare("SELECT id FROM room_players WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$roomId, $user['id']]);
    $already = $stmt->fetchColumn();

    if (!$already) {
        // Find next position
        $stmt = $db->prepare("SELECT IFNULL(MAX(position), 0) + 1 FROM room_players WHERE room_id = ?");
        $stmt->execute([$roomId]);
        $pos = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, position, is_winner, joined_at) VALUES (?, ?, ?, 0, NOW())");
        $stmt->execute([$roomId, $user['id'], $pos]);
    }

} else {
    // Host creates a NEW room
    function generateRoomCode($length = 8): string {
        return strtoupper(substr(bin2hex(random_bytes($length)), 0, $length));
    }

    $roomCode = generateRoomCode();

    // Insert new room
    $stmt = $db->prepare("INSERT INTO rooms (code, status, created_at) VALUES (?, 'waiting', NOW())");
    $stmt->execute([$roomCode]);
    $roomId = (int)$db->lastInsertId();

    // Insert host
    $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, position, is_winner, joined_at) VALUES (?, ?, 1, 0, NOW())");
    $stmt->execute([$roomId, $user['id']]);
}

// Find first player = host
$stmt = $db->prepare("SELECT user_id FROM room_players WHERE room_id = ? ORDER BY position ASC LIMIT 1");
$stmt->execute([$roomId]);
$firstUserId = (int)$stmt->fetchColumn();
$isHost = ($firstUserId === (int)$user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Quantum Ludo - Lobby</title>
  <link rel="stylesheet" href="../css/dashboard.css">
  <style>
    .room-container { text-align: center; margin-top: 50px; }
    .room-code { font-size: 28px; font-weight: bold; margin: 15px 0; color: #ffcc00; background: rgba(0,0,0,0.6); padding: 10px 20px; border-radius: 10px; display: inline-block; }
    .players { margin: 20px auto; padding: 15px; border: 2px solid #ffcc00; border-radius: 10px; max-width: 400px; background: rgba(255,255,255,0.9); color: #000; }
    .player { padding: 8px; font-size: 18px; font-weight: 600; }
    .btn { display: inline-block; margin: 15px; padding: 10px 20px; border-radius: 8px; background: #2a9d8f; color: #fff; text-decoration: none; border: none; cursor: pointer; }
    .btn-start { background: #28a745; }
    .btn-back { background: #f4a261; }
    .button-container { display: flex; justify-content: center; gap: 20px; margin-top: 20px; }
  </style>
</head>
<body>
  <?php include "partials/profile_menu.php"; ?>

  <div class="room-container">
    <h1>Quantum Ludo</h1>
    <div class="room-code">Room Code: <?= htmlspecialchars($roomCode) ?></div>

    <div class="players">
        <h3>Players Joined</h3>
        <div id="players-list"></div>
    </div>

    <div class="button-container">
      <?php if ($isHost): ?>
        <button id="start-btn" class="btn btn-start">▶ Start Match</button>
      <?php else: ?>
        <p>Waiting for host to start…</p>
      <?php endif; ?>

      <a href="join_room.php" class="btn btn-back">← Back</a>
    </div>
  </div>

  <script>
  const roomCode = "<?= $roomCode ?>";
  const roomId   = "<?= $roomId ?>";
  const isHost   = <?= $isHost ? 'true' : 'false' ?>;

  function refreshPlayers() {
  fetch("../../api/fetch_players.php?room_id=" + roomId)
    .then(r => r.json())
    .then(players => {
      const list = document.getElementById("players-list");
      list.innerHTML = "";

      players.forEach((p, idx) => {
        const div = document.createElement("div");
        div.className = "player";
        div.textContent = p.username + (idx === 0 ? " (Host)" : "");
        list.appendChild(div);
      });
    })
    .catch(err => console.error("fetch_players error:", err));
}

function checkGameStart() {
  fetch("../../api/check_game.php?room=" + roomCode + "&t=" + Date.now())
    .then(r => r.json())
    .then(data => {
      if (data.ok && data.status === "active") {
        window.location.href = "../game.php?room=" + roomCode;
      } else if (data.ok && data.status === "finished") {
        alert("This game has already finished.");
        window.location.href = "../dashboard.php";
      }
    })
    .catch(err => console.error("check_game error:", err));
}

  setInterval(refreshPlayers, 3000);
  setInterval(checkGameStart, 3000);
  refreshPlayers();

  if (isHost) {
    document.getElementById("start-btn").addEventListener("click", function() {
      fetch("../../api/init_game.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ room: roomCode })
      })
      .then(r => r.json())
      .then(data => {
        if (data.ok) {
          window.location.href = "../game.php?room=" + roomCode;
        } else {
          alert("Error: " + data.error);
        }
      })
      .catch(err => alert("Server error: " + err));
    });
  }
  </script>
</body>
</html>
