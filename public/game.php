<?php
declare(strict_types=1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function log_error($msg) {
  file_put_contents(__DIR__ . "/error_log.txt", "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n", FILE_APPEND);
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  log_error("Error: $errstr in $errfile on line $errline");
});

set_exception_handler(function ($e) {
  log_error("Exception: " . $e->getMessage());
});

require_once __DIR__ . '/../lib/boot.php';

// Force PHP to log errors into a file we control
ini_set("log_errors", 1);
ini_set("error_log", __DIR__ . "/game-error.log");

auth_require();
$user = current_user();
$db = db();

$roomCode = $_GET['room'] ?? '';
$stmt = $db->prepare("SELECT * FROM rooms WHERE code = ?");
$stmt->execute([$roomCode]);
$room = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$room) {

  die("❌ Invalid Room Code");
}

// ✅ Always check first if user is already in room
$stmt = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
$stmt->execute([$room['id'], $user['id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
    // get available colors
    $allColors = ['red', 'blue', 'green', 'yellow'];
    $stmt = $db->prepare("SELECT color FROM room_players WHERE room_id = ?");
    $stmt->execute([$room['id']]);
    $taken = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $freeColors = array_values(array_diff($allColors, $taken));
    if (empty($freeColors)) {
        die(json_encode(['ok' => false, 'error' => 'Room is full']));
    }
    $color = $freeColors[0]; // assign first free color

    // ✅ safe insert (user_id + room_id unique check prevents duplicate user)
    $stmt = $db->prepare("
        INSERT INTO room_players (room_id, user_id, color, position, is_winner, joined_at)
        VALUES (?, ?, ?, (SELECT IFNULL(MAX(position)+1,1) FROM room_players WHERE room_id = ?), 0, NOW())
    ");
    $stmt->execute([$room['id'], $user['id'], $color, $room['id']]);

    $playerId = $db->lastInsertId();
    $stmt2 = $db->prepare("SELECT * FROM room_players WHERE id = ?");
    $stmt2->execute([$playerId]);
    $player = $stmt2->fetch(PDO::FETCH_ASSOC);
}


?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Quantum Ludo — Local Board</title>
<style>
:root{
  --board-max: min(80vmin, 720px); /* responsive square size */
  /* avatar & dice percentage positions relative to the board-wrap */
  --ava-tl-x: 7%;  --ava-tl-y: -11%;
  --dice-tl-x: 26%; --dice-tl-y: -11%;
  --ava-tr-x: 93%;  --ava-tr-y: -11%;
  --dice-tr-x: 74%; --dice-tr-y: -11%;
  --ava-bl-x: 7%;  --ava-bl-y: 111%;
  --dice-bl-x: 26%; --dice-bl-y: 111%;
  --ava-br-x: 93%;  --ava-br-y: 111%;
  --dice-br-x: 74%; --dice-br-y: 111%;
}
*{ box-sizing:border-box; }
html,body{ height:100%; }
body{
  margin:0;
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji", "Segoe UI Symbol", sans-serif;
  color:#000;
  background: #0b0f1a url("img/bg.jpg") no-repeat center center fixed;
  background-size: cover;
}

.topbar{ 
  position:relative;
  top:35px; 
  padding:18px 12px 8px; 
  display:flex; 
  justify-content:center;
  align-items:center;
  user-select:none; 
  }
  
.exit{ position:absolute; left:18px; top:12px; padding:8px 16px; border-radius:26px; border:3px solid #FFD700; background:rgba(0,0,0,0.15); color:#FFD700; font-weight:800; cursor:pointer; letter-spacing:.4px; }
.exit:hover{ background:rgba(0,0,0,0.3); }
.title{ display:inline-block; background:#FFD700; color:#000; padding:14px 28px; border-radius:14px; font-weight:900; font-size: clamp(22px, 3vw, 42px); letter-spacing:.8px; box-shadow:0 8px 22px rgba(0,0,0,0.35); text-transform: uppercase; }

/* STAGE just centers the board and gives perspective if needed */
.stage{
  width:var(--board-max);
  aspect-ratio:1/1;
  margin:12px auto 40px;
  position:relative;
  perspective: 900px;
  perspective-origin: 50% 50%;
}

.ludo-container {
  margin-top: 100px;   /* adjust as needed */
  display: flex;
  justify-content: center;  /* keep centered horizontally */
  align-items: center;
}


/* NEW: unified container; everything sits inside this square */
.board-wrap{
  position:absolute;
  left:0; top:40px;
  width:100%; height:100%;
  border-radius:12px;
  overflow:visible; /* keep overlays visible */
  box-shadow:0 10px 34px rgba(0,0,0,0.6);
  background: #000 url("img/board.jpg") center/contain no-repeat; /* your board */
}

/* Overlays now position inside the same coordinate space as the board */
.overlay{
  position:absolute;
  transform:translate(-50%,-50%);
  text-align:center;
}
.avatar{
  width:48px; height:48px;
  border-radius:50%;
  background:#fff;
  border:3px solid #fff;
  box-shadow: 0 8px 20px rgba(0,0,0,0.45);
  overflow:hidden; display:flex; align-items:center; justify-content:center;
}
.avatar img{ width:100%; height:100%; object-fit:cover; }

.dice-wrap{
  width:68px; height:68px;
  opacity: 1 !important;   /* force no transparency */
  background: #fff !important; /* force solid white */
  background:#fff; border-radius:14px;
  box-shadow: 0 8px 18px rgba(0,0,0,.35), inset 0 0 0 3px #2b2b2b;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; user-select:none; padding:0; border:0; outline:none;
  transition: box-shadow .15s ease, transform .12s ease;
}
.dice-wrap:active{ transform: translate(-50%,-50%) scale(.98); }
.dice-wrap:focus-visible,
.dice-wrap.active-turn{
  box-shadow:
    0 10px 22px rgba(0,0,0,.42),
    0 0 0 3px #2b2b2b,
    0 0 0 6px rgba(255,215,0, .65);
}

.hidden {
  display: none !important;
}


.dice-wrap.disabled{ opacity:.38; filter:grayscale(1); pointer-events:none; }
.dice-wrap.offgame{ opacity:.26; filter:grayscale(1) contrast(.85); pointer-events:none; }

/* Cube sizing same as before */
.dice{ position: relative; width:30px; height:30px; transform-style: preserve-3d; transform-origin: 50% 50%; transform: rotateX(0deg) rotateY(0deg); /* inside .dice { ... } */
transition: transform 0.7s cubic-bezier(.17,.67,.83,.67); will-change: transform; }
.dice-face{ position:absolute; width:30px; height:30px; background:#fff; border:2px solid #111; border-radius:8px; display:block; box-shadow: inset 0 0 6px rgba(0,0,0,.18); backface-visibility:hidden; }
.dice-face.f2{ transform: rotateY(180deg) translateZ(22px);} 
.dice-face.f1{ transform: rotateY(0deg)   translateZ(22px);} 
.dice-face.f3{ transform: rotateY(-90deg) translateZ(22px);} 
.dice-face.f4{ transform: rotateY(90deg)  translateZ(22px);} 
.dice-face.f5{ transform: rotateX(90deg)  translateZ(22px);} 
.dice-face.f6{ transform: rotateX(-90deg) translateZ(22px);} 
.pip{ position:absolute; width:7px; height:7px; background:#111; border-radius:50%; box-shadow: 0 1px 0 rgba(0,0,0,.25), inset 0 0 0 1px rgba(255,255,255,.4); }
.pip.tl{ left:22%; top:22%; } .pip.tm{ left:50%; top:22%; transform:translateX(-50%); } .pip.tr{ right:22%; top:22%; }
.pip.ml{ left:22%; top:50%; transform:translateY(-50%); } .pip.mm{ left:50%; top:50%; transform:translate(-50%,-50%); } .pip.mr{ right:22%; top:50%; transform:translateY(-50%); }
.pip.bl{ left:22%; bottom:22%; } .pip.bm{ left:50%; bottom:22%; transform:translateX(-50%); } .pip.br{ right:22%; bottom:22%; }

.dice.glow {
    box-shadow: 0 0 20px 5px yellow;
    border-radius: 10px;
}



/* Common token style */
.token {
  position: absolute;
  width: 5%;               /* keep your current size */
  height: 5%;     
  background-size: contain; /* scale image to fit */
  background-repeat: no-repeat;
  background-position: center;
  transform: translate(-50%, -50%);
}

/* Yellow tokens */
.token.yellow1, .token.yellow2, .token.yellow3, .token.yellow4 {
  background-image: url("img/yellow.png");
}
.token.yellow1 { left:13.9%; top:18.5%; }
.token.yellow2 { left:28.9%; top:18.5%; }
.token.yellow3 { left:13.9%; top:30.89%; }
.token.yellow4 { left:28.9%; top:30.89%; }

/* Red tokens */
.token.red1, .token.red2, .token.red3, .token.red4 {
  background-image: url("img/red.png");
}
.token.red1 { left:70.8%; top:18.5%; }
.token.red2 { left:85.8%; top:18.5%; }
.token.red3 { left:70.8%; top:30.89%; }
.token.red4 { left:85.8%; top:30.89%; }

/* Blue tokens */
.token.blue1, .token.blue2, .token.blue3, .token.blue4 {
  background-image: url("img/blue.png");
}
.token.blue1 { left:13.9%; top:69.7%; }
.token.blue2 { left:28.9%; top:69.7%; }
.token.blue3 { left:13.9%; top:82.25%; }
.token.blue4 { left:28.9%; top:82.25%; }

/* Green tokens */
.token.green1, .token.green2, .token.green3, .token.green4 {
  background-image: url("img/green.png");
}
.token.green1 { left:70.8%; top:69.7%; }
.token.green2 { left:85.8%; top:69.7%; }
.token.green3 { left:70.8%; top:82.25%; }
.token.green4 { left:85.8%; top:82.25%; }

.hidden{ display:none !important; }

@media (max-width: 640px){
  .dice-wrap{ width:62px; height:62px; border-radius:12px; }
  .dice{ width:40px; height:40px; }
  .dice-face{ width:40px; height:40px; }
}
/* Highlight selectable tokens */
.token.active {
  /* Remove any scale/translate */
  transform: translate(-50%, -50%);
  
  /* Add glow ring */
  box-shadow: 0 0 14px 4px rgba(255, 215, 0, 0.9);
  
  /* Make sure it’s on top */
  z-index: 10;
  
  cursor: pointer;
}

@keyframes popUp {
  0%   { transform: translate(-50%, -50%) scale(1); }
  50%  { transform: translate(-50%, -50%) scale(1.25); }
  100% { transform: translate(-50%, -50%) scale(1); }
}

/* Winner badge home positioning (paste into your existing <style>) */
/* tweak sizes if needed to perfectly match your board art */
.home {
  position: absolute;
  width: 16%;              /* size of the white start box area */
  height: 16%;
  transform: translate(-50%, -50%);
  pointer-events: none;
  z-index: 800;            /* above tokens but below modal overlays */
  display: block;
}

/* centers are calculated from your token starter positions */
.home.g-yellow { left: 21.4%; top: 24.7%; }  /* top-left white box */
.home.g-red    { left: 78.3%; top: 24.7%; }  /* top-right white box */
.home.g-blue   { left: 21.4%; top: 76.0%; }  /* bottom-left white box */
.home.g-green  { left: 78.3%; top: 76.0%; }  /* bottom-right white box */

/* badge image style (keeps it responsive inside .home) */
.winner-badge {
  position: absolute;
  left: 50%;
  top: 50%;
  transform: translate(-50%, -50%);
  width: 60%;        /* relative to .home; tweak if too big/small */
  height: auto;
  max-width: 96px;   /* cap so it doesn't overflow on large screens */
  pointer-events: none;
  filter: drop-shadow(0 6px 12px rgba(0,0,0,0.35));
}
  
.dice-wrap.debug-click-flash 
{ outline: 3px solid rgba(255,200,0,0.9); transform: scale(0.98); }

.dice-wrap.active-turn {
  border: 3px solid gold;
  box-shadow: 0 0 15px gold;
  border-radius: 10px;
}

@keyframes pulseGlow {
  from { box-shadow: 0 0 10px 2px gold; }
  to   { box-shadow: 0 0 20px 5px orange; }
}

.hidden {
  display: none !important;
}

.dice-wrap.active-turn {
  box-shadow: 0 0 15px 5px gold;
  border-radius: 10px;
  transition: box-shadow 0.3s ease;
}
.dice-wrap.active-dice .dice {
    box-shadow: 0 0 20px 6px rgba(255, 215, 0, 0.9); /* golden glow */
    border-radius: 10px;
}

</style>
</head>
<body>

<script>
// ✅ GLOBAL SAFE INITIALIZATION — only once at the top
window.GameGlobals = window.GameGlobals || {};

window.GameGlobals.tokenStates = window.GameGlobals.tokenStates || {};
window.GameGlobals.entryPoints = window.GameGlobals.entryPoints || {
  red: 0,
  green: 13,
  yellow: 26,
  blue: 39
};

var tokenStates = window.GameGlobals.tokenStates;
var entryPoints = window.GameGlobals.entryPoints;

  window.ROOM_CODE = <?= json_encode($roomCode ?? '') ?>;
</script>

  <!-- Sound Effects -->
<audio id="snd-dice" src="sound/dice.mp3" preload="auto"></audio>
<audio id="snd-step" src="sound/step_move.mp3" preload="auto"></audio>
<audio id="snd-home" src="sound/reach_home.mp3" preload="auto"></audio>
<audio id="snd-win" src="sound/win.mp3" preload="auto"></audio>
<audio id="snd-over" src="sound/game_over.mp3" preload="auto"></audio>
<audio id="snd-start" src="sound/game_start.mp3" preload="auto"></audio>

 <button class="exit" onclick="window.location.href='dashboard.php'">EXIT GAME</button>

<div class="topbar">
  <div class="title">QUANTUM LUDO</div>
</div>



<!-- STAGE remains the outer container -->
<div class="ludo-container">
<div class="stage" id="stage" data-players="4" aria-live="polite" aria-atomic="true">
  
  <!-- NEW: a perfectly square, relative container that holds EVERYTHING -->
  <div class="board-wrap" role="img" aria-label="Ludo board">
    <!-- Board image as background; no <img> shifting the layout -->
    
    <!-- Avatars -->
    <div class="overlay avatar g-yellow" style="left:var(--ava-tl-x); top:var(--ava-tl-y);">
      <img src="img/profile.png" alt="Yellow player avatar">
    </div>
    <div class="overlay avatar g-red" style="left:var(--ava-tr-x); top:var(--ava-tr-y);">
      <img src="img/profile.png" alt="Red player avatar">
    </div>
    <div class="overlay avatar g-blue" style="left:var(--ava-bl-x); top:var(--ava-bl-y);">
      <img src="img/profile.png" alt="Blue player avatar">
    </div>
    <div class="overlay avatar g-green" style="left:var(--ava-br-x); top:var(--ava-br-y);">
      <img src="img/profile.png" alt="Green player avatar">
    </div>

    <!-- Dice buttons (unchanged) -->
    <!-- 🎲 Yellow Dice -->
<button class="overlay dice-wrap g-yellow" data-seat="yellow" style="left:var(--dice-tl-x); top:var(--dice-tl-y);">
  <div class="dice">
    <div class="dice-face f1"><span class="pip mm"></span></div>
    <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
    <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
    <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f6"><span class="pip tl"></span><span class="pip ml"></span><span class="pip bl"></span><span class="pip tr"></span><span class="pip mr"></span><span class="pip br"></span></div>
  </div>
</button>

<!-- 🎲 Red Dice -->
<button class="overlay dice-wrap g-red" data-seat="red" style="left:var(--dice-tr-x); top:var(--dice-tr-y);">
  <div class="dice">
    <div class="dice-face f1"><span class="pip mm"></span></div>
    <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
    <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
    <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f6"><span class="pip tl"></span><span class="pip ml"></span><span class="pip bl"></span><span class="pip tr"></span><span class="pip mr"></span><span class="pip br"></span></div>
  </div>
</button>

<!-- 🎲 Blue Dice -->
<button class="overlay dice-wrap g-blue" data-seat="blue" style="left:var(--dice-bl-x); top:var(--dice-bl-y);">
  <div class="dice">
    <div class="dice-face f1"><span class="pip mm"></span></div>
    <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
    <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
    <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f6"><span class="pip tl"></span><span class="pip ml"></span><span class="pip bl"></span><span class="pip tr"></span><span class="pip mr"></span><span class="pip br"></span></div>
  </div>
</button>

<!-- 🎲 Green Dice -->
<button class="overlay dice-wrap g-green" data-seat="green" style="left:var(--dice-br-x); top:var(--dice-br-y);">
  <div class="dice">
    <div class="dice-face f1"><span class="pip mm"></span></div>
    <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
    <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
    <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
    <div class="dice-face f6"><span class="pip tl"></span><span class="pip ml"></span><span class="pip bl"></span><span class="pip tr"></span><span class="pip mr"></span><span class="pip br"></span></div>
  </div>
</button>


    <!-- Tokens -->
    <div class="token yellow1 g-yellow"></div>
    <div class="token yellow2 g-yellow"></div>
    <div class="token yellow3 g-yellow"></div>
    <div class="token yellow4 g-yellow"></div>

    <div class="token red1 g-red"></div>
    <div class="token red2 g-red"></div>
    <div class="token red3 g-red"></div>
    <div class="token red4 g-red"></div>

    <div class="token blue1 g-blue"></div>
    <div class="token blue2 g-blue"></div>
    <div class="token blue3 g-blue"></div>
    <div class="token blue4 g-blue"></div>

    <div class="token green1 g-green"></div>
    <div class="token green2 g-green"></div>
    <div class="token green3 g-green"></div>
    <div class="token green4 g-green"></div>
    <!-- Player Homes (for winner crowns) -->
    <div class="home g-yellow"></div>
    <div class="home g-red"></div>
    <div class="home g-blue"></div>
    <div class="home g-green"></div>


  </div>

</div>
</div>

<!-- Game Over Modal -->
<div id="game-over-modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; 
     background:rgba(0,0,0,0.7); z-index:5000; justify-content:center; align-items:center;">

  <div style="background:#fff; padding:20px; border-radius:12px; text-align:center; width:300px;">
    <h2>🏆 Game Over</h2>
    <div id="final-positions"></div>
    <button onclick="window.location.href='../dashboard.php'" style="margin-top:10px;">Go to Dashboard</button>
    <button onclick="location.reload()" style="margin-top:10px;">Rematch</button>
  </div>
</div>

<?php
// Fetch joined players
$stmt = $db->prepare("SELECT position FROM room_players WHERE room_id = ?");
$stmt->execute([$room['id']]);

$joinedSeats = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Map numeric positions to colors (adjust if your DB stores differently)
$seatMap = [
    1 => "yellow",
    2 => "red",
    3 => "blue",
    4 => "green"
];

$myColor = '';

// Always check DB/server first (trust authoritative source)
$userId = $_SESSION['user_id'] ?? 0;
$roomCode = $_GET['room'] ?? '';

if ($userId && $roomCode) {
    $stmt = $db->prepare("SELECT color FROM room_players WHERE room_id = ? AND user_id = ?");
    $stmt->execute([$room['id'], $userId]);
    $color = $stmt->fetchColumn();
    if ($color) {
        $myColor = $color;
        $_SESSION['player_color'] = $color; // keep in session
    }
}

// Fallback: session (if DB failed for some reason)
if (empty($myColor) && !empty($_SESSION['player_color'])) {
    $myColor = $_SESSION['player_color'];
}

// Last fallback: position → color map
if (empty($myColor) && !empty($player['position'])) {
    $myColor = $seatMap[$player['position']] ?? '';
}

// Build joinedColors
$joinedColors = [];
if (!empty($room['joinedPlayers']) && is_array($room['joinedPlayers'])) {
    foreach ($room['joinedPlayers'] as $p) {
        if (!empty($p['color'])) $joinedColors[] = $p['color'];
    }
} elseif (!empty($room['players']) && is_array($room['players'])) {
    foreach ($room['players'] as $p) {
        if (!empty($p['color'])) $joinedColors[] = $p['color'];
    }
} elseif (!empty($room['tokens']) && is_array($room['tokens'])) {
    foreach (array_keys($room['tokens']) as $k) {
        $c = preg_replace('/\d+$/', '', $k);
        if (!in_array($c, $joinedColors)) $joinedColors[] = $c;
    }
}
// --- Build tokens for the current player (and later extend to all players) ---

// Fetch all pieces for this player
$stmt = $db->prepare("
    SELECT piece_index, position 
    FROM room_pieces 
    WHERE room_id = ? AND user_id = ?
    ORDER BY piece_index
");
$stmt->execute([$room['id'], $user['id']]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$playerTokens = [];
foreach ($rows as $row) {
    $idx = (int)$row['piece_index'];
    $pos = (int)$row['position'];

    $playerTokens[] = [
        "color"    => $myColor,                  // player's color (e.g. "red")
        "piece"    => $idx,                      // numeric piece index
        "token"    => $myColor . $idx,           // unique token id like "red1"
        "position" => $pos
    ];
}

// Expose to JS (window.PLAYER_TOKENS)
echo "<script>window.PLAYER_TOKENS = " . json_encode($playerTokens) . ";</script>";
?>
<script>
  window.JOINED_PLAYERS = <?= json_encode($joinedColors) ?>;
  window.ROOM_CODE = "<?= htmlspecialchars($_GET['room'] ?? '') ?>";
  window.CURRENT_USER_COLOR = "<?= $myColor ?>";
  console.log("Injected CURRENT_USER_COLOR =", window.CURRENT_USER_COLOR);
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  function qsa(selector) {
    return document.querySelectorAll(selector);
}

/* ==========================
   Quantum Ludo – Game Logic (Cleaned)
   ========================== */
   // ---- INIT (replace previous ROOM_CODE / currentUserId / API_BASE definitions) ----
const API_BASE = "/ludo/api/";

function loadPlayers() {
  fetch("../api/get_players.php?room=" + encodeURIComponent(roomCode))
    .then(r => r.json())
    .then(data => {
      if (!data.ok) return console.error(data.error);

      // Hide all dice & tokens first
      document.querySelectorAll(".dice-wrap, .token").forEach(el => {
        el.style.display = "none";
      });

      // Show only joined players
      data.players.forEach(p => {
        document.querySelectorAll(`.dice-wrap[data-seat="${p.color}"]`).forEach(d => d.style.display = "");
        document.querySelectorAll('.token.' + p.color + '1, .token.' + p.color + '2, .token.' + p.color + '3, .token.' + p.color + '4')
        .forEach(t => t.style.display = "");
      });
    })
    .catch(err => console.error(err));
}


// ✅ Extract roomCode once, make it global
const urlParams = new URLSearchParams(window.location.search);
const roomCode = urlParams.get('room') || "";
const currentUserId = "<?php echo $_SESSION['user_id'] ?? ''; ?>"; // leave if your file is PHP-rendered
let myColor = localStorage.getItem('ludo_myColor') || (window.CURRENT_USER_COLOR || null);

// 🚨 FIX: remove ROOM_CODE check, use roomCode instead
if (!roomCode) {
  console.warn("❌ roomCode is empty. Check URL or server injection.");
}

const PLAYER_COLOR = window.PLAYER_COLOR || null;
const API_GET_STATE = API_BASE + "get_state.php";
const API_ROLL_DICE = API_BASE + "roll_dice.php";

function updateDiceState(gameState) {
    const myColor = (window.CURRENT_USER_COLOR || "").toLowerCase();
    const turnColor = (gameState.turnColor || "").toLowerCase();

    console.log("DEBUG → myColor:", myColor, "turnColor:", turnColor);

    // Disable all dice first
    document.querySelectorAll(".dice-wrap").forEach(el => {
        el.classList.remove("clickable");
    });

    // Only enable if it's *my* turn
    if (myColor && myColor === turnColor) {
        const myDice = document.querySelector(`.dice-wrap[data-seat="${turnColor}"]`);
        if (myDice) {
            myDice.classList.add("clickable");
            console.log("✅ It's my turn! Dice enabled for:", myColor);
        }
    }
}

// Global state
let lastDiceValues = {};
let firstLoad = true;  // <-- this was missing, now defined


// ...existing code...
function refreshGameState() {
    fetch("/ludo/api/game_state.php?room=" + encodeURIComponent(window.ROOM_CODE))
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                console.warn("⚠️ refreshGameState → Server error:", data.error);
                return;
            }
            // ...existing handling...
        })
        .catch(err => console.error("❌ refreshGameState error:", err));
    const room = encodeURIComponent(roomCode || window.ROOM_CODE || (new URLSearchParams(location.search)).get('room') || '');
    if (!room) {
      console.warn("refreshGameState: no room available");
      return;
    }
    fetch(API_BASE + "game_state.php?room=" + room, {
        credentials: "same-origin",
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(r => {
        if (!r.ok) throw new Error("Network response not ok: " + r.status);
        return r.json();
    })
    .then(data => {
        if (!data || !data.ok) {
            console.warn("refreshGameState → Server error or no-data:", data && data.error);
            return;
        }

        // update pieces
        if (typeof placeAllTokensFromState === "function") {
            let pieces = (data.pieces && data.pieces.length) ? data.pieces : null;
            if (!pieces && data.tokens) {
                pieces = Object.entries(data.tokens).map(([key, val]) => {
                    const m = key.match(/([a-z]+)(\d+)/i);
                    return { color: m ? m[1].toLowerCase() : 'red', piece: m ? parseInt(m[2],10) : 1, position: val.pos ?? val.position ?? val };
                });
            }
            if (pieces) placeAllTokensFromState(pieces);
        }

        if (typeof highlightTurn === "function") highlightTurn(data.turnColor);
        updateDiceState({ turnUser: data.turnUser, turnColor: data.turnColor });

        // animate dice if changed
        if (!firstLoad && data.lastDice && data.lastColor) {
            if (lastDiceValues[data.lastColor] !== data.lastDice) {
                updateDiceUI(data.lastColor, data.lastDice);
                lastDiceValues[data.lastColor] = data.lastDice;
            }
        }
        firstLoad = false;
    })
    .catch(err => console.error("refreshGameState error:", err));
}
// ...existing code...
const ROOM_CODE = window.ROOM_CODE || null;

// ---- joinRoomAndStart ----
// ...existing code...
async function joinRoomAndStart() {
          try {
            const room = (typeof roomCode !== "undefined" && roomCode) || (typeof ROOM_CODE !== "undefined" && ROOM_CODE) || (new URLSearchParams(location.search)).get('room');
            if (!room) {
              console.warn("joinRoomAndStart: missing room code, skipping join.");
              refreshGameState();
              return null;
            }
        
           const url = API_BASE + "join_room.php?room=" + encodeURIComponent(room);
            const res = await fetch(url, {
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
        
            // tolerate either JSON or text responses
            const txt = await res.text();
            let data;
            try {
              data = txt ? JSON.parse(txt) : null;
            } catch (e) {
              console.warn("joinRoomAndStart: response not strict JSON, attempting fallback parse", e);
              try { data = await res.json(); } catch (_) { data = null; }
            }
        
            console.log("join_room response", data);
        
            if (!data || !data.ok) {
              const msg = data && data.error ? data.error : "Join failed (no data)";
              alert("Join failed: " + msg);
              throw new Error("Join failed: " + msg);
            }
        
            if (data.color) {
              window.CURRENT_USER_COLOR = String(data.color).toLowerCase();
              localStorage.setItem("ludo_myColor", window.CURRENT_USER_COLOR);
              console.log("✅ assigned color:", window.CURRENT_USER_COLOR);
            } else {
              console.warn("joinRoomAndStart: server did not return color", data);
            }
        
            // If server returned joined players, update UI immediately
            if (Array.isArray(data.players) && typeof showOnlyJoined === "function") {
              const cols = data.players.map(p => (p.color || "").toLowerCase()).filter(Boolean);
              showOnlyJoined(cols);
              if (typeof setActivePlayers === "function") setActivePlayers(parseJoinedFromResponse({ players: cols.map(c => ({ color: c })) }));
            }
        
            // Trigger one immediate state refresh and ensure websocket connects (polling handled by WS fallback)
            try { refreshGameState(); } catch (e) { console.warn("refreshGameState failed:", e); }
            if (window.LUDO_WS && typeof window.LUDO_WS.connect === "function") {
              try { window.LUDO_WS.connect(); } catch (e) { /* ignore */ }
            }
        
            return data;
          } catch (err) {
            console.error("joinRoomAndStart error:", err);
            return null;
          }
          try {
            const room = roomCode || window.ROOM_CODE || (new URLSearchParams(location.search)).get('room');
            if (!room) {
              console.warn("joinRoomAndStart: missing room code, skipping join.");
              refreshGameState();
              return null;
            }
        
            const res = await fetch(API_BASE + "join_room.php?room=" + encodeURIComponent(room), {
              credentials: "same-origin",
              headers: { "X-Requested-With": "XMLHttpRequest" }
            });
        
            const txt = await res.text();
            let data = null;
            try { data = txt ? JSON.parse(txt) : null; } catch (e) { console.error("joinRoomAndStart invalid JSON:", txt, e); throw e; }
        
            console.log("join_room response", data);
            if (!data || !data.ok) {
              const msg = data && data.error ? data.error : "Join failed (no data)";
              alert("Join failed: " + msg);
              throw new Error("Join failed: " + msg);
            }
        
            if (data.color) {
              window.CURRENT_USER_COLOR = String(data.color).toLowerCase();
              localStorage.setItem("ludo_myColor", window.CURRENT_USER_COLOR);
              console.log("✅ assigned color:", window.CURRENT_USER_COLOR);
            }
        
            if (Array.isArray(data.players) && typeof showOnlyJoined === "function") {
              const cols = data.players.map(p => (p.color || "").toLowerCase()).filter(Boolean);
              showOnlyJoined(cols);
              if (typeof setActivePlayers === "function") setActivePlayers(parseJoinedFromResponse({ players: cols.map(c => ({ color: c })) }));
            }
        
            // single immediate refresh; DO NOT start a new interval here — WS fallback handles polling
            refreshGameState();
            if (window.LUDO_WS && typeof window.LUDO_WS.connect === "function") window.LUDO_WS.connect();
            return data;
} catch (err) {
  console.error("joinRoomAndStart error:", err);
  return null;
}
}

function enableDice(color) {
  const el = document.querySelector(`.dice-wrap[data-seat="${color}"]`);
  if (el) {
    el.classList.add("clickable");
    console.log("✅ Dice ENABLED for", color);
  } else {
    console.warn("⚠️ enableDice: no dice element for", color);
  }
}

function disableDice(color) {
  const el = document.querySelector(`.dice-wrap[data-seat="${color}"]`);
  if (el) {
    el.classList.remove("clickable");
    console.log("🚫 Dice DISABLED for", color);
  } else {
    console.warn("⚠️ disableDice: no dice element for", color);
  }
}


function renderPieces(pieces) {
  console.log("renderPieces called with:", pieces);

  pieces.forEach(p => {
    const pieceId = "piece-" + p.id;

    // If this piece is already on the board, skip
    if (document.getElementById(pieceId)) {
      return;
    }

    // Create a new piece element
    const el = document.createElement("div");
    el.id = pieceId;
    el.className = "piece " + p.color;
    el.dataset.userId = p.userId;
    el.dataset.index = p.piece;

    // Place on board (example: grid positioning based on p.position)
    // You will adjust placement logic to your board
    el.style.position = "absolute";
    el.style.left = (50 + p.position * 20) + "px";
    el.style.top = (50 + p.position * 20) + "px";

    document.getElementById("board").appendChild(el);
  });
}


// ---- Call once on page load ----
joinRoomAndStart();
</script>
<script>
// local state
var active = [];   // joined colors in position order
var turnIdx = 0;
window.__ludo_game_over = false;

// Track the most recent dice roll value
let lastDiceRoll = null;

function createRoom() {
  $.post(API_BASE + "/create_room.php", {}, function(res) {
    if (res.ok) {
      roomCode = res.roomCode;
      $("#roomInfo").text("Room created! Share this code: " + roomCode);
    } else {
      alert("Error creating room");
    }
  }, "json");
}

function joinRoom() {
  let code = $("#joinCode").val().trim();
  let color = $("#joinColor").val();
  $.ajax({
    url: API_BASE + "/join_room.php",
    type: "POST",
    contentType: "application/json",
    data: JSON.stringify({roomCode: code, color: color}),
    success: function(res) {
      if (res.ok) {
        roomCode = code;
        playerColor = res.player.color;
        $("#roomInfo").text("Joined room " + roomCode + " as " + playerColor);
        startGame();
      } else {
        alert(res.error);
      }
    }
  });
}

var active = []; // must exist globally

function setActivePlayers(colors) {
    if (!Array.isArray(colors)) return;
    active = colors;
    refreshDiceEnabled(); // enable dice for first player
}

// ---- showJoinedPlayersOnly (normalize colors, set display properly) ----
function showJoinedPlayersOnly(joinedColors) {
  if (!Array.isArray(joinedColors)) return;

  // normalize to strings & lowercase
  const colors = joinedColors.map(c => String(c).toLowerCase());

  // Hide all dice and tokens first
  document.querySelectorAll(".dice-wrap, .token").forEach(el => {
    el.style.display = "none";
    el.style.pointerEvents = "none";
    el.classList.remove("clickable");
  });

  colors.forEach(color => {
    // Show dice(s) for this color (data-seat attr)
    document.querySelectorAll(".dice-wrap[data-seat='" + color + "']").forEach(el => {
      el.style.display = "";
      el.style.pointerEvents = "auto";
    });

    // Show tokens for this color (red1..red4)
    for (let i = 1; i <= 4; i++) {
      const tokenEl = document.querySelector(".token." + color + i);
      if (tokenEl) {
        tokenEl.style.display = "";
        tokenEl.style.pointerEvents = "auto";
      }
    }
  });
}


function animateDice(color, diceValue) {
              if (!wrap || !cube) return;
          
              // --- Glow highlight ---
              document.querySelectorAll(".dice").forEach(d => d.classList.remove("glow"));
              cube.classList.add("glow");
          
              // Random base rotations
              const randX = Math.floor(Math.random() * 6) + 1;
              const randY = Math.floor(Math.random() * 6) + 1;
              let xRot = randX * 360;
              let yRot = randY * 360;
          
              // Map dice result → rotation
              switch(final) {
                  case 1: xRot += 0;   yRot += 0;   break;
                  case 2: xRot += 0;   yRot += 180; break;
                  case 3: xRot += 0;   yRot += 90;  break;
                  case 4: xRot += 0;   yRot += -90; break;
                  case 5: xRot += -90; yRot += 0;   break;
                  case 6: xRot += 90;  yRot += 0;   break;
              }
          
              // Smooth animation (❌ don’t reset to 0deg)
              cube.style.transition = 'transform 1.2s cubic-bezier(.2,.8,.2,1)';
              cube.style.transform = `rotateX(${xRot}deg) rotateY(${yRot}deg)`;
            // Defensive: prefer rollAnimated (handles fetching + rules) or direct UI update
            const wrap = document.querySelector('.dice-wrap.g-' + color) || document.querySelector(`.dice-wrap[data-seat="${color}"]`);
            const cube = wrap ? wrap.querySelector('.dice') : null;
            if (!wrap || !cube) return;
          
            document.querySelectorAll(".dice").forEach(d => d.classList.remove("glow"));
            cube.classList.add("glow");
          
            if (typeof diceValue === "number") {
              // immediate visual for known value
              updateDiceUI(color, Number(diceValue));
              return;
            }
          
            // else use the animated roll helper if available
            if (typeof rollAnimated === "function") {
              rollAnimated(color);
              return;
            }
          
            // fallback: small spin then show random face
            const rand = 1 + Math.floor(Math.random() * 6);
            updateDiceUI(color, rand);
          }

function rollDice(color) {
    console.log("rollDice called with → room:", window.ROOM_CODE, "color:", color);

    fetch("/ludo/api/roll_dice.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
            room: window.ROOM_CODE,
            color: color
        })
    })

    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            console.log("🎲 Rolled dice:", data.color, data.dice);

            // ✅ Update only the correct dice
            updateDiceUI(data.color, data.dice);

            // Store last value if needed
            lastDiceValues[data.color] = data.dice;

            // 🔥 Immediately refresh game state so glow + turn update instantly
            refreshGameState();
        } else {
            console.error("Dice roll error:", data.error);
        }
    })
    .catch(err => console.error("Dice roll fetch error:", err));
}


function setupDiceClickEvents() {
    document.querySelectorAll(".dice-wrap").forEach(btn => {
        // Get color from dataset or fallback from class name
        let color = btn.dataset.seat;
        if (!color) {
            const match = [...btn.classList].find(c => c.startsWith("g-"));
            if (match) color = match.replace("g-", "");
        }

        if (!color) return;

        btn.onclick = () => {
            console.log("Dice clicked:", color);
            rollDice(color);
        };
    });
}


const ADVANCE_FROM_HOME_ON_SIX = false;

// Define order and qs before attaching events
var order = ["yellow", "red", "green", "blue"];
const qs = (sel) => document.querySelector(sel);

let lastDice = null;   // global tracker (define at top of file)

function handleGameState(data) {
  if (data.lastRoll && data.lastRoll.id !== lastDiceEventId) {
    lastDiceEventId = data.lastRoll.id;
    lastDiceRoll = data.lastRoll.value;  // ✅ track last dice
    onDiceRolled(data.lastRoll.color, data.lastRoll.value);
  }
}

// returns a promise that resolves to the parsed JSON from roll_dice.php
function getDiceRoll(roomCode, color) {
  return fetch("/ludo/api/roll_dice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ room: roomCode, color: color })
  }).then(r => r.json());
}

function getRotation(roll) {
  switch (roll) {
    case 1: return {x:0,y:0};
    case 2: return {x:0,y:180};
    case 3: return {x:0,y:90};
    case 4: return {x:0,y:-90};
    case 5: return {x:-90,y:0};
    case 6: return {x:90,y:0};
    default: return {x:0,y:0};
  }
}

// NEW: show only joined players' dice and tokens
function showOnlyJoined(joinedColors) {
    ['red','blue','green','yellow'].forEach(color => {
        const dice = document.querySelector('.dice-wrap.g-' + color);
        const tokens = document.querySelectorAll(
            '.token.' + color + '1, .token.' + color + '2, .token.' + color + '3, .token.' + color + '4'
        );

        if (joinedColors.includes(color)) {
            if (dice) dice.style.display = '';
            tokens.forEach(t => t.style.display = '');
        } else {
            if (dice) dice.style.display = 'none';
            tokens.forEach(t => t.style.display = 'none');
        }
    });
}

// ✅ Ensure entryPoints is globally available before anything uses it
if (typeof window.entryPoints === "undefined") {
  window.entryPoints = {
    red: 0,
    green: 13,
    yellow: 26,
    blue: 39
  };
}

// ✅ Ensure tokenStates is globally available before anything uses it
if (typeof window.tokenStates === "undefined") {
  window.tokenStates = {};
}

// ✅ Always reference the global copy

function placeAllTokensFromState(serverTokens) {
  if (!Array.isArray(serverTokens)) {
    console.warn("placeAllTokensFromState: invalid data", serverTokens);
    return;
  }

  const ENTRY_INDEX = { red: 0, green: 13, yellow: 26, blue: 39 };

  console.log("[placeAllTokensFromState] received", serverTokens.length, "tokens");

  // 🧮 Count tokens at home for each color
  const tokensAtHomeByColor = {};
  serverTokens.forEach(p => {
    if (!p?.color) return;
    const c = p.color.toLowerCase();
    tokensAtHomeByColor[c] = (tokensAtHomeByColor[c] || 0) + (Number(p.position) === -1 ? 1 : 0);
  });

  // 🛡️ Track which color already had a token exit during this update
  const tokenExitedThisFrame = {};

  // (Optional debug)
  console.table(serverTokens.map(p => ({
    color: p.color,
    piece: p.piece,
    position: p.position
  })));

  // 🌀 Main loop
  serverTokens.forEach(p => {
    if (!p?.color || !Number.isInteger(p.piece)) return;

    const color = p.color.toLowerCase().trim();
    const pieceNum = parseInt(p.piece, 10);
    const key = color + pieceNum;
    const newIndex = Number(p.position);
    const entryIndex = entryPoints?.[color] ?? ENTRY_INDEX[color];

    if (!tokenStates[key]) {
      tokenStates[key] = { index: -1, inHome: true, finished: false, isMoving: false, color };
    }

    const state = tokenStates[key];
    const oldIndex = state.index;

    // Skip if no change
    if (oldIndex === newIndex) return;
    if (state.isMoving) return;

    // --- TOKEN SENT HOME ---
    if (newIndex === -1) {
      sendTokenHome(key);
      Object.assign(state, { index: -1, inHome: true, finished: false });
      return;
    }

    // --- TOKEN FINISHED ---
    if (newIndex >= 57) {
      updateTokenPosition(key, 57);
      Object.assign(state, { index: 57, finished: true, inHome: false });
      return;
    }

    // --- COMING OUT OF HOME ---
    if (oldIndex === -1 && newIndex === entryIndex) {
      // ✅ Only allow one token per color per frame
      if (tokenExitedThisFrame[color]) {
        console.log(`[skip entry] ${key} ignored (already exited this frame)`);
        return;
      }

      // ✅ Allow if at least one token is still home
      const homeCount = tokensAtHomeByColor[color] || 0;

      if (homeCount >= 1) {
        tokenExitedThisFrame[color] = true;
        console.log(`[entry OK] ${key} coming out of home (homeCount=${homeCount})`);

        state.isMoving = true;
        state.justMovedOut = true;

        animateTokenToEntry(key);
        setTimeout(() => {
          Object.assign(state, { index: newIndex, inHome: false, isMoving: false });
          updateTokenPosition(key, newIndex);
          setTimeout(() => (state.justMovedOut = false), 600);
        }, 400);
      } else {
        console.log(`[skip entry] ${key} - invalid homeCount (${homeCount})`);
      }
      return;
    }

    // --- NORMAL MOVE ---
    const diff = newIndex - oldIndex;
    state.isMoving = true;

    (async () => {
      try {
        if (diff > 0) {
          await moveTokenByKeyWithAnimation(key, diff);
        } else {
          updateTokenPosition(key, newIndex);
        }
      } catch (err) {
        console.error(`[error] move for ${key}:`, err);
        updateTokenPosition(key, newIndex);
      } finally {
        Object.assign(state, { index: newIndex, inHome: false, isMoving: false });
      }
    })();
  });
}

/* --- helper to debug --- */
function dbgDumpTokens(serverTokens) {
  console.log("DBG tokenStates:", JSON.parse(JSON.stringify(tokenStates)));
  if (serverTokens) console.log("DBG serverTokens:", JSON.parse(JSON.stringify(serverTokens)));
}

/* --- helper for sending token home --- */
function sendTokenHome(key) {
  if (!key || typeof key !== "string") {
    console.warn("sendTokenHome called with invalid key:", key);
    return;
  }

  if (!tokenStates[key]) {
    tokenStates[key] = { index: -1, inHome: true, finished: false };
  }

  const st = tokenStates[key];
  if (st.inHome && st.index === -1) return; // already home

  st.index = -1;
  st.inHome = true;
  st.finished = false;

  const el = document.querySelector(".token." + key);
  if (!el) {
    console.warn("sendTokenHome: token element not found for", key);
    return;
  }

  const color = key.replace(/[0-9]/g, '');
  const pieceIndex = parseInt(key.replace(/\D/g, ''), 10) - 1;

  // Defined home positions for each color
  const homePositions = {
    red:    [{ left: "70.8%", top: "18.5%" }, { left: "85.8%", top: "18.5%" }, { left: "70.8%", top: "30.89%" }, { left: "85.8%", top: "30.89%" }],
    yellow: [{ left: "13.9%", top: "18.5%" }, { left: "28.9%", top: "18.5%" }, { left: "13.9%", top: "30.89%" }, { left: "28.9%", top: "30.89%" }],
    blue:   [{ left: "13.9%", top: "69.7%" }, { left: "28.9%", top: "69.7%" }, { left: "13.9%", top: "82.25%" }, { left: "28.9%", top: "82.25%" }],
    green:  [{ left: "70.8%", top: "69.7%" }, { left: "85.8%", top: "69.7%" }, { left: "70.8%", top: "82.25%" }, { left: "85.8%", top: "82.25%" }]
  };

  const posObj = (homePositions[color] && homePositions[color][pieceIndex]) ? homePositions[color][pieceIndex] : null;

  if (posObj) {
    // 🧷 Only reposition visually if the element isn’t already there
    const curLeft = el.style.left;
    const curTop = el.style.top;
    if (curLeft !== posObj.left || curTop !== posObj.top) {
      el.style.transition = 'none';
      el.style.left = posObj.left;
      el.style.top = posObj.top;
      setTimeout(() => {
        if (el) el.style.transition = 'left .10s ease, top .10s ease';
      }, 10);
    }
  } else {
    el.style.display = 'none';
  }

  el.dataset.pos = '-1';
}

  function getQueryParam(name){
    var m = new RegExp('[?&]'+name+'=([^&]*)').exec(window.location.search);
    return m ? decodeURIComponent(m[1].replace(/\+/g,' ')) : null;
  }

  function clampPlayers(n){
    n = parseInt(n, 10);
    if (isNaN(n)) return 4;
    if (n < 2) return 2;
    if (n > 4) return 4;
    return n;
  }

function detectPlayers(){
    var fromUrl = clampPlayers(getQueryParam('players'));
    var fromDataAttr = clampPlayers(stage ? stage.getAttribute('data-players') : null);
    if (fromUrl >= 2 && fromUrl <= 4) return fromUrl;
    if (fromDataAttr >= 2 && fromDataAttr <= 4) return fromDataAttr;
    return 4;
  }

  var playersCount = detectPlayers();
  var active;
  if (playersCount === 2) {
    active = ['blue','red'];
  } else if (playersCount === 3) {
    active = ['blue','yellow','red'];
  } else {
    active = ['blue','yellow','red','green'];
  }

  function isActiveColor(color){ return active.indexOf(color) !== -1; }
  const GRID = {
    X: [ "14%", "22%", "30%", "38%", "46%", "54%", "62%", "70%", "78%", "86%" ],
    Y: [ "14%", "22%", "30%", "38%", "46%", "54%", "62%", "70%", "78%", "86%" ],
    CENTER_X: "46.5%"
  };

  function x(i){ return GRID.X[i]; }
  function y(i){ return GRID.Y[i]; }
  const IDX = { A:0, B:1, C:2, D:3, E:4, F:5, G:6, H:7, I:8, J:9 };
 const path = {
  blue: [
      // go up   & start of blue 
  { "left": "43.6%", "top": "87.3%" },
  { "left": "43.6%", "top": "81.1%" },
  { "left": "43.6%", "top": "74.9%" },
  { "left": "43.6%", "top": "68.7%" },
  { "left": "43.6%", "top": "62.5%" },
  //turn left to reach yellow
  { "left": "37.5%", "top": "56.2%" },
  { "left": "31.3%", "top": "56.2%" },
  { "left": "25.1%", "top": "56.2%" },
  { "left": "18.9%", "top": "56.2%" },
  { "left": "12.7%", "top": "56.2%" },
  { "left": "6.5%", "top": "56.2%" },
// go up
  { "left": "6.5%", "top": "50%" },
   { "left": "6.5%", "top": "43.8%" },
    //start of yellow  
  { "left": "12.6%", "top": "43.8%" },
  { "left": "19%", "top": "43.8%" },
  { "left": "25.4%", "top": "43.8%" },
  { "left": "31.8%", "top": "43.8%" },
  { "left": "38.2%", "top": "43.8%" },
  // turn left up
  { "left": "43.8%", "top": "37.8%" },
  { "left": "43.8%", "top": "31.6%" },
  { "left": "43.8%", "top": "25.4%" },
  { "left": "43.8%", "top": "19.2%" },
  { "left": "43.8%", "top": "13%" },
  { "left": "43.8%", "top": "6.8%" },
  //turn right
  { "left": "50%", "top": "6.8%" },
  { "left": "56.2%", "top": "6.8%" },
  // red start from here 
    { "left": "56.2%", "top": "13%" },
  { "left": "56.2%", "top": "19.2%" },
  { "left": "56.2%", "top": "25.4%" },
  { "left": "56.2%", "top": "31.6%" },
  { "left": "56.2%", "top": "37.8%" },
  //turn right
  { "left": "62.3%", "top": "43.8%" },
  { "left": "68.5%", "top": "43.8%" },
  { "left": "74.7%", "top": "43.8%" },
  { "left": "80.9%", "top": "43.8%" },
  { "left": "87.1%", "top": "43.8%" },
  { "left": "93.3%", "top": "43.8%" },
  // down 
  { "left": "93.3%", "top": "50%" },
  { "left": "93.3%", "top": "56.2%" },
  //turn left  & start of green 
  { "left": "87.1%", "top": "56.2%" },
  { "left": "80.9%", "top": "56.2%" },
  { "left": "74.7%", "top": "56.2%" },
  { "left": "68.5%", "top": "56.2%" },
  { "left": "62.3%", "top": "56.2%" },
  //come down 
  { "left": "56.2%", "top": "62.5%" },
  { "left": "56.2%", "top": "68.7%" },
  { "left": "56.2%", "top": "74.9%" },
  { "left": "56.2%", "top": "81.1%" },
  { "left": "56.2%", "top": "87.3%" },
  { "left": "56.2%", "top": "93.5%" },
  // turn left 
  { "left": "49.8%", "top": "93.5%" },
  //goto blues home 
  { "left": "49.8%", "top": "87.3%" },
  { "left": "49.8%", "top": "81.1%" },
  { "left": "49.8%", "top": "74.9%" },
  { "left": "49.8%", "top": "68.7%" },
  { "left": "49.8%", "top": "62.5%" },
  { "left": "49.8%", "top": "56.3%" },


  ],
  red: [
    // red start from here 
    { "left": "56.2%", "top": "13%" },
  { "left": "56.2%", "top": "19.2%" },
  { "left": "56.2%", "top": "25.4%" },
  { "left": "56.2%", "top": "31.6%" },
  { "left": "56.2%", "top": "37.8%" },
  //turn right
  { "left": "62.3%", "top": "43.8%" },
  { "left": "68.5%", "top": "43.8%" },
  { "left": "74.7%", "top": "43.8%" },
  { "left": "80.9%", "top": "43.8%" },
  { "left": "87.1%", "top": "43.8%" },
  { "left": "93.3%", "top": "43.8%" },
  // down 
  { "left": "93.3%", "top": "50%" },
  { "left": "93.3%", "top": "56.2%" },
  //turn left  & start of green 
  { "left": "87.1%", "top": "56.2%" },
  { "left": "80.9%", "top": "56.2%" },
  { "left": "74.7%", "top": "56.2%" },
  { "left": "68.5%", "top": "56.2%" },
  { "left": "62.3%", "top": "56.2%" },
  //come down 
  { "left": "56.2%", "top": "62.5%" },
  { "left": "56.2%", "top": "68.7%" },
  { "left": "56.2%", "top": "74.9%" },
  { "left": "56.2%", "top": "81.1%" },
  { "left": "56.2%", "top": "87.3%" },
  { "left": "56.2%", "top": "93.5%" },
  // turn left 
  { "left": "49.8%", "top": "93.5%" },
  { "left": "43.6%", "top": "93.5%" },
  // go up   & start of blue 
  { "left": "43.6%", "top": "87.3%" },
  { "left": "43.6%", "top": "81.1%" },
  { "left": "43.6%", "top": "74.9%" },
  { "left": "43.6%", "top": "68.7%" },
  { "left": "43.6%", "top": "62.5%" },
  //turn left to reach yellow
  { "left": "37.5%", "top": "56.2%" },
  { "left": "31.3%", "top": "56.2%" },
  { "left": "25.1%", "top": "56.2%" },
  { "left": "18.9%", "top": "56.2%" },
  { "left": "12.7%", "top": "56.2%" },
  { "left": "6.5%", "top": "56.2%" },
// go up
  { "left": "6.5%", "top": "50%" },
   { "left": "6.5%", "top": "43.8%" },
      //start of yellow  
  { "left": "12.6%", "top": "43.8%" },
  { "left": "19%", "top": "43.8%" },
  { "left": "25.4%", "top": "43.8%" },
  { "left": "31.8%", "top": "43.8%" },
  { "left": "38.2%", "top": "43.8%" },
  // turn left up
  { "left": "43.8%", "top": "37.8%" },
  { "left": "43.8%", "top": "31.6%" },
  { "left": "43.8%", "top": "25.4%" },
  { "left": "43.8%", "top": "19.2%" },
  { "left": "43.8%", "top": "13%" },
  { "left": "43.8%", "top": "6.8%" },
  //turn right
  { "left": "50%", "top": "6.8%" },
  //go to home of red
  { "left": "50%", "top": "13%" },
  { "left": "50%", "top": "19.2%" },
  { "left": "50%", "top": "25.4%" },
  { "left": "50%", "top": "31.6%" },
  { "left": "50%", "top": "37.8%" },
  { "left": "50%", "top": "44%" },


  ],
  yellow: [
   //start of yellow  
  { "left": "12.6%", "top": "43.8%" },
  { "left": "19%", "top": "43.8%" },
  { "left": "25.4%", "top": "43.8%" },
  { "left": "31.8%", "top": "43.8%" },
  { "left": "38.2%", "top": "43.8%" },
  // turn left up
  { "left": "43.8%", "top": "37.8%" },
  { "left": "43.8%", "top": "31.6%" },
  { "left": "43.8%", "top": "25.4%" },
  { "left": "43.8%", "top": "19.2%" },
  { "left": "43.8%", "top": "13%" },
  { "left": "43.8%", "top": "6.8%" },
  //turn right
  { "left": "50%", "top": "6.8%" },
  { "left": "56.2%", "top": "6.8%" },
  //come down    & start of red 
  { "left": "56.2%", "top": "13%" },
  { "left": "56.2%", "top": "19.2%" },
  { "left": "56.2%", "top": "25.4%" },
  { "left": "56.2%", "top": "31.6%" },
  { "left": "56.2%", "top": "37.8%" },
  //turn right
  { "left": "62.3%", "top": "43.8%" },
  { "left": "68.5%", "top": "43.8%" },
  { "left": "74.7%", "top": "43.8%" },
  { "left": "80.9%", "top": "43.8%" },
  { "left": "87.1%", "top": "43.8%" },
  { "left": "93.3%", "top": "43.8%" },
  // down 
  { "left": "93.3%", "top": "50%" },
  { "left": "93.3%", "top": "56.2%" },
  //turn left  & start of green 
  { "left": "87.1%", "top": "56.2%" },
  { "left": "80.9%", "top": "56.2%" },
  { "left": "74.7%", "top": "56.2%" },
  { "left": "68.5%", "top": "56.2%" },
  { "left": "62.3%", "top": "56.2%" },
  //come down 
  { "left": "56.2%", "top": "62.5%" },
  { "left": "56.2%", "top": "68.7%" },
  { "left": "56.2%", "top": "74.9%" },
  { "left": "56.2%", "top": "81.1%" },
  { "left": "56.2%", "top": "87.3%" },
  { "left": "56.2%", "top": "93.5%" },
  // turn left 
  { "left": "49.8%", "top": "93.5%" },
  { "left": "43.6%", "top": "93.5%" },
  // go up   & start of blue 
  { "left": "43.6%", "top": "87.3%" },
  { "left": "43.6%", "top": "81.1%" },
  { "left": "43.6%", "top": "74.9%" },
  { "left": "43.6%", "top": "68.7%" },
  { "left": "43.6%", "top": "62.5%" },
  //turn left to reach back home
  { "left": "37.5%", "top": "56.2%" },
  { "left": "31.3%", "top": "56.2%" },
  { "left": "25.1%", "top": "56.2%" },
  { "left": "18.9%", "top": "56.2%" },
  { "left": "12.7%", "top": "56.2%" },
  { "left": "6.5%", "top": "56.2%" },
  { "left": "6.5%", "top": "50%" },

  // 6 steps of yellow's home
  { "left": "12.7%", "top": "50%" },
  { "left": "18.9%", "top": "50%" },
  { "left": "25.1%", "top": "50%" },
  { "left": "31.3%", "top": "50%" },
  { "left": "37.5%", "top": "50%" },
  { "left": "43.7%", "top": "50%" }


  ],
  green: [


     { "left": "87.1%", "top": "56.2%" },
  { "left": "80.9%", "top": "56.2%" },
  { "left": "74.7%", "top": "56.2%" },
  { "left": "68.5%", "top": "56.2%" },
  { "left": "62.3%", "top": "56.2%" },
  //come down 
  { "left": "56.2%", "top": "62.5%" },
  { "left": "56.2%", "top": "68.7%" },
  { "left": "56.2%", "top": "74.9%" },
  { "left": "56.2%", "top": "81.1%" },
  { "left": "56.2%", "top": "87.3%" },
  { "left": "56.2%", "top": "93.5%" },
  // turn left 
  { "left": "49.8%", "top": "93.5%" },
  { "left": "43.6%", "top": "93.5%" },
  // go up   & start of blue 
  { "left": "43.6%", "top": "87.3%" },
  { "left": "43.6%", "top": "81.1%" },
  { "left": "43.6%", "top": "74.9%" },
  { "left": "43.6%", "top": "68.7%" },
  { "left": "43.6%", "top": "62.5%" },
  //turn left to reach yellow
  { "left": "37.5%", "top": "56.2%" },
  { "left": "31.3%", "top": "56.2%" },
  { "left": "25.1%", "top": "56.2%" },
  { "left": "18.9%", "top": "56.2%" },
  { "left": "12.7%", "top": "56.2%" },
  { "left": "6.5%", "top": "56.2%" },
// go up
  { "left": "6.5%", "top": "50%" },
   { "left": "6.5%", "top": "43.8%" },
    //start of yellow  
  { "left": "12.6%", "top": "43.8%" },
  { "left": "19%", "top": "43.8%" },
  { "left": "25.4%", "top": "43.8%" },
  { "left": "31.8%", "top": "43.8%" },
  { "left": "38.2%", "top": "43.8%" },
  // turn left up
  { "left": "43.8%", "top": "37.8%" },
  { "left": "43.8%", "top": "31.6%" },
  { "left": "43.8%", "top": "25.4%" },
  { "left": "43.8%", "top": "19.2%" },
  { "left": "43.8%", "top": "13%" },
  { "left": "43.8%", "top": "6.8%" },
  //turn right
  { "left": "50%", "top": "6.8%" },
  { "left": "56.2%", "top": "6.8%" },
  //come down    & start of red 
  { "left": "56.2%", "top": "13%" },
  { "left": "56.2%", "top": "19.2%" },
  { "left": "56.2%", "top": "25.4%" },
  { "left": "56.2%", "top": "31.6%" },
  { "left": "56.2%", "top": "37.8%" },
  //turn right
  { "left": "62.3%", "top": "43.8%" },
  { "left": "68.5%", "top": "43.8%" },
  { "left": "74.7%", "top": "43.8%" },
  { "left": "80.9%", "top": "43.8%" },
  { "left": "87.1%", "top": "43.8%" },
  { "left": "93.3%", "top": "43.8%" },
  // down 
  { "left": "93.3%", "top": "50%" },
  // go to home green 

   { "left": "87.1%", "top": "50%" },
   { "left": "80.9%", "top": "50%" },
   { "left": "74.7%", "top": "50%" },
   { "left": "68.5%", "top": "50%" },
   { "left": "62.3%", "top": "50%" },
   { "left": "56.1%", "top": "50%" },
  
  ]
};


const SAFE_SPOTS = [
  // Stars
  { left: "43.8%", top: "19.2%" },  // yellow star
  { left: "80.9%", top: "43.8%" },  // red star
  { left: "56.2%", top: "81.1%" },  // green star
  { left: "18.9%", top: "56.2%" },  // blue star

  // Start squares
  { left: "43.6%", top: "87.3%" },  // blue start
  { left: "12.6%", top: "43.8%" },  // yellow start
  { left: "56.2%", top: "13%"   },  // red start
  { left: "87.1%", top: "56.2%" }   // green start
];

  var safePositions = new Set();
  ['blue','red','green','yellow'].forEach(function(c){
    var idx = entryPoints[c];
    if (path[c] && path[c][idx]) safePositions.add(path[c][idx].left + ',' + path[c][idx].top);
    // mark every 13th square along the loop as a safe square (common Ludo rule)
    for (var i=0;i<path[c].length;i+=13){
      if (path[c][i]) safePositions.add(path[c][i].left + ',' + path[c][i].top);
    }
  });

  /* ---------------------------
     Token states
     --------------------------- */

  const SAFE_TOL = 0.6; 
function renderGameState(gameState) {
  // Hide all tokens first
  document.querySelectorAll(".token").forEach(t => t.style.display = "none");

  // Loop through pieces and show them
  gameState.pieces.forEach((piece, index) => {
    let selector = `.${piece.color}${piece.index + 1}`;
    let tokenEl = document.querySelector(selector);
    if (tokenEl) {
      tokenEl.style.display = "block";
      // If you also track board positions:
      if (piece.position) {
        tokenEl.style.left = piece.position.x + "%";
        tokenEl.style.top = piece.position.y + "%";
      }
    }
  });
}

function grantExtraTurn(color) {
    console.log('[grantExtraTurn] color=', color);
    extraTurn = true;
}

function isPlayerCompletelyFinished(color) {
  for (var i = 1; i <= 4; i++) {
    var st = tokenStates[color + i];
    // if a token state is missing, treat as not finished (defensive)
    if (!st || !st.finished) return false;
  }
  return true; // all 4 tokens finished
}

function nextTurn(keepFromRoll=false) {
    clearAllSelectable();
    if (keepFromRoll || extraTurn) {
        console.log('[nextTurn] same player keeps turn:', active[turnIdx]);
    } else {
        turnIdx = (turnIdx + 1) % active.length;
        console.log('[nextTurn] advancing turn to:', active[turnIdx]);
    }
    extraTurn = false;
    refreshDiceEnabled();
}

function playSound(id, opts) {
  opts = opts || {};
  try {
    var orig = document.getElementById(id);
    if (!orig) {
      console.warn("playSound: missing element", id);
      return;
    }

    // clone so the sound can overlap / play immediately
    var a = orig.cloneNode(true);
    // optional playback rate (makes it feel snappier when >1)
    if (opts.rate) a.playbackRate = opts.rate;
    // ensure starts from beginning
    try { a.currentTime = 0; } catch (e) {}
    a.style.display = 'none';
    document.body.appendChild(a);

    // play & catch promise rejections
    var p = a.play();
    if (p && typeof p.then === 'function') {
      p.catch(function(err){
        // common: NotAllowedError if user gesture not present
        console.warn("playSound: play rejected for", id, err);
        try { orig.currentTime = 0; orig.play().catch(()=>{}); } catch(e){}
      });
    }

    // remove element when finished (safety)
    a.addEventListener('ended', function(){ if (a.parentNode) a.parentNode.removeChild(a); });
    // Failsafe remove after 2s
    setTimeout(function(){ if (a.parentNode) a.parentNode.removeChild(a); }, 2000);
  } catch (err) {
    console.error("playSound error", err);
  }
}


function playStepSound() {
  if (!stepBuffer) return;
  if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();

  const source = audioCtx.createBufferSource();
  source.buffer = stepBuffer;
  source.playbackRate.value = 1.05; // slightly faster for snappiness
  source.connect(audioCtx.destination);
  source.start(0);
}

let currentUsername = "<?php echo $_SESSION['username']; ?>";  
let forcedDice = null; // store forced value temporarily

function isAdmin() {
  return (currentUsername === "raghav" || currentUsername === "honey");
}
// apply six-streak rule but await the server roll if needed
async function finalRollWithRule(color) {
  try {
    // try to ask server for the roll (getDiceRoll returns {ok:..., dice: ...})
    const res = await getDiceRoll(window.ROOM_CODE, color);
    if (!res || !res.ok) {
      console.warn("finalRollWithRule: server roll missing, fallback to random");
      // fallback to random
      return 1 + Math.floor(Math.random() * 6);
    }

    // server returns dice number in res.dice (adjust if your API uses another key)
    let result = Number(res.dice ?? res.value ?? res.roll);
    if (isNaN(result)) {
      console.warn("finalRollWithRule: invalid dice from server, fallback:", res);
      result = 1 + Math.floor(Math.random() * 6);
    }

    // six-streak logic
    sixStreak[color] = sixStreak[color] || 0;
    if (result === 6) {
      sixStreak[color] += 1;
      if (sixStreak[color] > 2) {
        // force 1-5 and reset streak
        sixStreak[color] = 0;
        return 1 + Math.floor(Math.random() * 5);
      }
    } else {
      sixStreak[color] = 0;
    }

    return result;
  } catch (err) {
    console.error("finalRollWithRule error:", err);
    return 1 + Math.floor(Math.random() * 6);
  }
}


function isStackedSafe(pos, tokensHere) {
    // A position is safe if it's in the predefined safePositions
    // OR if multiple tokens of the same color are stacked there
    if (!pos) return false;
    const key = pos.left + "," + pos.top;
    if (safePositions.has(key)) return true;

    if (tokensHere && tokensHere.length > 1) {
        const firstColor = tokensHere[0].replace(/[0-9]/g, "");
        return tokensHere.every(tk => tk.startsWith(firstColor));
    }
    return false;
}

function handleCaptures(movedTokenKey){
  var st = tokenStates[movedTokenKey];
  if (!st || st.inHome || st.index === -1) return;

  var attackerColor = movedTokenKey.replace(/[0-9]/g,'');
  var pos = path[attackerColor] && path[attackerColor][st.index];
  if (!pos) return;

  // If the landing square is inherently safe → no captures
  if (isSafeSpot(pos)) return;

  var killedSomeone = false;

  Object.keys(tokenStates).forEach(function(tk){
    if (tk === movedTokenKey) return;

    var victim = tokenStates[tk];
    if (!victim || victim.inHome || victim.index === -1) return;

    var victimColor = tk.replace(/[0-9]/g,'');
    var victimPos = path[victimColor] && path[victimColor][victim.index];
    if (!victimPos) return;

    if (isSamePos(victimPos, pos)) {
      // 1. same-color? → skip
      if (victimColor === attackerColor) return;

      // 2. check if victim has stack making it safe
      if (isStackedSafe(victimColor, victim.index)) return;

      // 3. normal safe spots still protect
      if (isSafeSpot(victimPos)) return;

      // ❌ capture
      console.log('Captured token:', tk, 'by', movedTokenKey, 'at', pos);
      sendTokenHome(tk);
      killedSomeone = true;
    }
  });

  // ✅ give extra turn if capture happened
  if (killedSomeone){
    grantExtraTurn(attackerColor);
  }
}

function moveTokenByKey(tokenKey, steps) {
  var state = tokenStates[tokenKey];
  if (!state) return { ok: false, reason: 'no such token' };
  if (state.finished) return { ok: false, reason: 'already finished' };

  var color = tokenKey.replace(/[0-9]/g, '');

  // --- Case 1: Token still in home
  if (state.inHome || state.index === -1) {
    if (steps === 6) {
      state.index = 0;      // place on entry square
      state.inHome = false;
      
    } else {
      return { ok: false, reason: 'need a 6 to leave home' };
    }
  } else {
    // --- Case 2: Normal move
    state.index += steps;
  }

  // --- Case 3: Reached or passed finish
  if (state.index >= path[color].length) {
    state.index = path[color].length - 1;
    state.finished = true;

    // ✅ grant extra turn for finishing
  }

  var pos = path[color][state.index];

  // --- Capture handling (if not on safe spot)
  if (!isSafeSpot(pos)) {
    handleCaptures(tokenKey);
  }

  return { ok: true, pos: pos, state: state };
}

async function moveTokenByKeyWithAnimation(tokenKey, steps) {
  return new Promise(async (resolve) => {
    const el = document.querySelector('.token.' + tokenKey);
    const state = tokenStates[tokenKey];
    if (!el || !state) {
      console.warn("moveTokenByKeyWithAnimation: missing token or state", tokenKey);
      resolve({ ok: false, reason: 'no element/state' });
      return;
    }

    const color = tokenKey.replace(/[0-9]/g, '');
    const pathArr = path[color];
    if (!pathArr) {
      console.error('No path found for color', color);
      resolve({ ok: false });
      return;
    }

    el.style.transition = 'left 0.35s ease, top 0.35s ease';
    el.style.zIndex = 50;

    const safeGetPos = (idx) => {
      const p = pathArr[idx];
      if (!p) return null;
      return {
        left: typeof p.left === 'number' ? `${p.left}%` : p.left,
        top: typeof p.top === 'number' ? `${p.top}%` : p.top,
      };
    };

    async function animateToIndex(idx) {
      return new Promise((res) => {
        const p = safeGetPos(idx);
        if (!p) {
          console.warn("Missing path position for", color, idx);
          res();
          return;
        }

        const done = () => {
          el.removeEventListener('transitionend', te);
          clearTimeout(fallback);
          res();
        };

        const te = (e) => {
          if (e.propertyName === 'left' || e.propertyName === 'top') done();
        };

        const fallback = setTimeout(done, 500);
        el.addEventListener('transitionend', te);

        requestAnimationFrame(() => {
          el.style.left = p.left;
          el.style.top = p.top;
          el.dataset.pos = idx;
        });
      });
    }

    // === CASE 1: Coming out of home ===
    if (state.inHome || state.index === -1) {
      if (steps !== 6) {
        resolve({ ok: false, reason: 'need 6 to leave home' });
        return;
      }

      // Relative animation index
      const colorEntryIndex = 0; // always 0 relative to color path
      // Absolute path index for logic
      const globalEntryIndex = { red: 0, green: 13, yellow: 26, blue: 39 }[color];

      state.inHome = false;

      // Animate to first step visually
      await animateToIndex(colorEntryIndex);

      // Update logical path index
      state.index = globalEntryIndex;

      if (typeof playSound === 'function') playSound("snd-step");
      el.style.zIndex = 20;

      if (typeof handleCaptures === 'function') handleCaptures(tokenKey);

      console.log(`✅ ${tokenKey} entered board at index ${state.index}`);

      // ✅ Do NOT auto-move further after entering
resolve({ ok: true, entered: true, finalIndex: state.index });
return;

    }

    // === CASE 2: Normal move ===
    let justFinished = false;
    const MAX_INDEX = pathArr.length - 1;

    for (let i = 0; i < steps; i++) {
      if (state.finished) break;
      const nextIndex = state.index + 1;
      if (nextIndex >= MAX_INDEX) {
        state.index = MAX_INDEX;
        state.finished = true;
        justFinished = true;
        break;
      }

      state.index = nextIndex;
      await animateToIndex(nextIndex);
      if (typeof playSound === 'function') playSound("snd-step", { rate: 1.05 });
      await new Promise(r => setTimeout(r, 100));
    }

    if (justFinished) {
      const centerPos = { left: "46.5%", top: "46.5%" };
      el.style.transition = 'left 0.4s ease, top 0.4s ease';
      requestAnimationFrame(() => {
        el.style.left = centerPos.left;
        el.style.top = centerPos.top;
      });
      if (typeof playSound === 'function') playSound("snd-home");
      await new Promise(r => setTimeout(r, 400));
    }

    el.style.zIndex = 20;
    if (typeof handleCaptures === 'function') handleCaptures(tokenKey);

    resolve({
      ok: true,
      token: tokenKey,
      pos: pathArr[state.index],
      finished: justFinished
    });
  });
}

// Animate token coming out of home (after rolling a 6)
function animateTokenToEntry(tokenKey) {
  const el = document.querySelector('.token.' + tokenKey);
  const state = tokenStates[tokenKey];
  if (!el || !state) {
    console.warn("animateTokenToEntry: missing token or state", tokenKey);
    return;
  }

  const color = tokenKey.replace(/[0-9]/g, "");
  const pathArr = path[color]; // ✅ FIX: define pathArr before using
  if (!pathArr) {
    console.error("animateTokenToEntry: no path found for", color);
    return;
  }

  // Each color starts at its unique entry index
  const entryPoints = { red: 0, green: 13, yellow: 26, blue: 39 };
  const entryIndex = entryPoints[color] ?? 0;
  const entryPos = pathArr[entryIndex];
  if (!entryPos) {
    console.error("animateTokenToEntry: invalid entry index for", color, entryIndex);
    return;
  }

  // Animate from home to entry
  el.style.transition = 'left 0.35s ease, top 0.35s ease';
  el.style.zIndex = 50;

  requestAnimationFrame(() => {
    el.style.left = typeof entryPos.left === 'number' ? `${entryPos.left}%` : entryPos.left;
    el.style.top = typeof entryPos.top === 'number' ? `${entryPos.top}%` : entryPos.top;
    el.dataset.pos = entryIndex;
  });

  // Update state after animation completes
  setTimeout(() => {
    state.index = entryIndex;
    state.inHome = false;
    state.finished = false;
    el.style.zIndex = 20;
    if (typeof playSound === 'function') playSound('snd-enter');
  }, 400);
}

function toNums(pos) {
  if (!pos) return null;
  let lx = pos.left;
  let ty = pos.top;
  if (typeof lx === 'string') lx = parseFloat(lx.replace('%', ''));
  if (typeof ty === 'string') ty = parseFloat(ty.replace('%', ''));
  return { x: Number(lx), y: Number(ty) };
}
function nearEqual(a, b, tol = SAFE_TOL) {
  return Math.abs(a - b) <= tol;
}

function isSamePos(posA, posB, tol = SAFE_TOL) {
  if (!posA || !posB) return false;
  const na = toNums(posA);
  const nb = toNums(posB);
  if (na == null || nb == null) return false;
  return nearEqual(na.x, nb.x, tol) && nearEqual(na.y, nb.y, tol);
}

function isSafeSpot(pos) {
  if (!pos) return false;
  for (let s of SAFE_SPOTS) {
    if (isSamePos(s, pos)) return true;
  }
  return false;
}

// ---------------------------
// SERVER SYNC
// ---------------------------
function commitMoveToServer(tokenKey, steps) {
  const pieceIndex = parseInt(tokenKey.replace(/\D/g, ''), 10) - 1;
  if (isNaN(pieceIndex) || pieceIndex < 0) {
    console.error("Invalid piece key:", tokenKey);
    return Promise.resolve({ ok: false, error: 'invalid piece key' });
  }

  const state = tokenStates[tokenKey];
  const body = {
    room: roomCode,
    pieceIndex: pieceIndex,
    steps: steps
  };

  // ✅ If token is at home and dice = 6, mark leaveHome
  if (state && (state.inHome || state.index === -1) && steps === 6) {
    body.leaveHome = true;
  }

  return fetch("/ludo/api/move_token.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(body)
  })
    .then(r => r.json())
    .then(resp => {
      if (!resp || !resp.ok) {
        console.error("move_token failed:", resp && resp.error);
        return resp || { ok: false, error: 'no-response' };
      }

      const moveType = resp.moveType;
      const newPos = resp.newPos;

      if (tokenStates[tokenKey]) {
        tokenStates[tokenKey].index = newPos;
        tokenStates[tokenKey].inHome = (moveType === "enter") ? false : tokenStates[tokenKey].inHome;
      }

      if (moveType === "enter") {
        animateTokenToEntry(tokenKey);
      } 
      else if (moveType === "steps" && Array.isArray(resp.path) && resp.path.length > 0) {
        animateTokenMove(tokenKey, resp.path);
      }

      if (Array.isArray(resp.pieces)) {
        placeAllTokensFromState(resp.pieces);
      } else {
        refreshGameState();
      }

      return resp;
    })
    .catch(err => {
      console.error("move_token fetch error:", err);
      return { ok: false, error: err.message || err };
    });
}


// ✅ Clean and consistent animation using backend path
function animateTokenMove(tokenKey, pathArray) {
  const el = document.querySelector('.token.' + tokenKey);
  const state = tokenStates[tokenKey];
  if (!el || !state || !Array.isArray(pathArray)) return;

  const color = tokenKey.replace(/[0-9]/g, "");
  let i = 0;

  function step() {
    if (i >= pathArray.length) return;

    const posIndex = pathArray[i];
    const pos = path[color][posIndex];
    if (!pos) return;

    el.style.transition = 'left 0.25s ease, top 0.25s ease';
    requestAnimationFrame(() => {
      el.style.left = typeof pos.left === 'number' ? `${pos.left}%` : pos.left;
      el.style.top = typeof pos.top === 'number' ? `${pos.top}%` : pos.top;
    });

    state.index = posIndex; // ✅ keep live index in sync
    i++;
    setTimeout(step, 250);
  }

  step();
}

// ---------------------------
// MAIN DICE HANDLER
// ---------------------------

async function onDiceRolled(color, roll) {
    if (!color) {
        color = currentPlayerColor;
    }

    lastDiceRoll = roll;

    // Standard roll normalization and validation block
    if (roll && typeof roll.then === "function") {
        try { roll = await roll; } catch (e) { console.error("Dice roll promise failed:", e); return; }
    }
    if (typeof roll === "object" && roll !== null) {
        if (roll.dice !== undefined) roll = roll.dice;
        else if (roll.value !== undefined) roll = roll.value;
        else if (roll.roll !== undefined) roll = roll.roll;
    }
    roll = Number(roll);
    if (isNaN(roll)) { console.error("onDiceRolled received invalid roll:", roll); return; }

    console.log("[onDiceRolled] color=", color, "roll=", roll);
    clearAllSelectable();
    
    let movable = [];
    try {
        movable = getMovableTokensForColor(color, roll) || [];
    } catch (e) {
        console.error("getMovableTokensForColor failed", e);
        movable = [];
    }
    console.log("[onDiceRolled] movable tokens:", movable);

    if (movable.length === 0) {
        setTimeout(() => nextTurn(false), 200);
        return;
    }

    // Auto move if only one token
    if (movable.length === 1) {
        const singleKey = movable[0];
        const st = tokenStates[singleKey];

        if ((st.inHome || st.index === -1) && roll === 6) {
            // ✅ FIX: Send move to server. Server returns moveType: "enter", newPos: 0.
            // commitMoveToServer will handle the animation (animateTokenToEntry) based on server response.
            console.log(`[onDiceRolled] Auto-committing ${singleKey} exit home (roll 6)`);
            commitMoveToServer(singleKey, 6).then(() => {
                nextTurn(true); // Extra turn for the 6-roll.
            });
            return;
        }

        // Normal move
        moveTokenByKeyWithAnimation(singleKey, roll).then(result => {
            commitMoveToServer(singleKey, roll).then(resp => {
                if (!resp.ok) { console.warn("Server rejected move:", resp.error); refreshGameState(); }
                // Server response handles extra turn via the 'extraTurn' flag in next turn logic
            });

            if (result && result.finished) {
                extraTurn = true;
                extraTurnGivenForMove = true;
                checkWinner(color);
                refreshDiceEnabled();
            } else {
                nextTurn(roll === 6 || extraTurn);
            }
        });
        return;
    }

    // Multiple → wait for user click
    movable.forEach(function (tokenKey) {
        const el = qs(".token." + tokenKey);
        if (!el) return;

        markTokenSelectable(el);

        el.onclick = function () {
            clearAllSelectable();

            const st = tokenStates[tokenKey] || {};

            if ((st.inHome || st.index === -1) && roll === 6) {
                // ✅ FIX: Send move to server. Server returns moveType: "enter", newPos: 0.
                console.log(`[onDiceRolled] User selected ${tokenKey} to exit home. Committing to server.`);
                commitMoveToServer(tokenKey, 6).then(() => {
                    nextTurn(true); // Extra turn for the 6-roll.
                });
                return;
            }

            // Normal move
            const color = tokenKey.replace(/[0-9]/g, '');
            st.pendingUntil = Date.now() + 4000;
            st.pendingExpected = st.index;
            tokenStates[tokenKey] = st;

            moveTokenByKeyWithAnimation(tokenKey, roll).then(result => {
                commitMoveToServer(tokenKey, roll).then(serverResp => {
                    if (!serverResp.ok) {
                        console.warn("Server did not accept move:", serverResp.error);
                        delete st.pendingUntil;
                        delete st.pendingExpected;
                        refreshGameState();
                    }

                    if (result && result.finished) {
                        extraTurn = true;
                        extraTurnGivenForMove = true;
                        checkWinner(color);
                        refreshDiceEnabled();
                    } else {
                        nextTurn(roll === 6 || extraTurn);
                    }
                }).catch(err => {
                    console.error("commitMoveToServer failed:", err);
                    delete st.pendingUntil;
                    delete st.pendingExpected;
                    refreshGameState();
                });
            });
        };
    });
}
// ----------------------------------------------------
// Inject CURRENT_USER_COLOR from PHP into JS
// ----------------------------------------------------
window.CURRENT_USER_COLOR = "<?php echo isset($myColor) ? strtolower($myColor) : ''; ?>";
console.log("CURRENT_USER_COLOR from PHP:", window.CURRENT_USER_COLOR);

// ----------------------------------------------------
// Dice logic & polling
// ----------------------------------------------------
lastDiceValues = {};
firstLoad = true;

// rollAnimated: accepts optional diceValue (number). If not provided, calls finalRollWithRule()
async function rollAnimated(color, diceValue) {
  if (!wrap || !cube) return;

  // Prevent double-click / disabled state
  if (wrap.classList.contains('disabled')) return;

  playSound("snd-dice");
  document.querySelectorAll('.dice-wrap').forEach(d => {
    d.classList.add('disabled');
    d.classList.remove('active-turn');
  });

  // resolve dice: use provided numeric value OR fetch + apply rule
  let final;
  if (typeof diceValue === 'number') {
    final = Number(diceValue);
  } else {
    final = await finalRollWithRule(color);
  }

  // safety: coerce
  final = Number(final) || (1 + Math.floor(Math.random() * 6));

  // create some spin then map face rotation
  const randX = Math.floor(Math.random() * 6) + 2 + (Date.now() % 3);
  const randY = Math.floor(Math.random() * 6) + 2 + ((Date.now() >> 3) % 4);
  let xRot = randX * 360;
  let yRot = randY * 360;

  switch (final) {
    case 1: xRot += 0;   yRot += 0;   break;
    case 2: xRot += 0;   yRot += 180; break;
    case 3: xRot += 0;   yRot += -90; break;
    case 4: xRot += 0;   yRot += 90;  break;
    case 5: xRot += -90; yRot += 0;   break;
    case 6: xRot += 90;  yRot += 0;   break;
  }

  cube.style.transition = 'transform 1100ms cubic-bezier(.2,.8,.2,1)';
  void cube.offsetWidth; // force reflow
  cube.style.transform = 'rotateX(' + xRot + 'deg) rotateY(' + yRot + 'deg)';

  setTimeout(function() {
    console.log(color + " rolled " + final);
    // deliver numeric final to onDiceRolled (onDiceRolled will handle Number)
    try { onDiceRolled(color, final); } catch(e) { console.error("onDiceRolled error:", e); }
  }, 1150);
}
  var turnIdx = 0;
  var sixStreak = { yellow:0, red:0, blue:0, green:0 };

/*function updateDiceGlow(color) {
  const diceEl = document.querySelector('.dice');
  if (!diceEl) return;
  diceEl.classList.remove('glow-red', 'glow-blue', 'glow-green', 'glow-yellow');
  if (color) diceEl.classList.add('glow-' + color);
}
*/


function refreshDiceEnabled() {
    if (window.__ludo_game_over) return;

    document.querySelectorAll('.dice-wrap').forEach(btn => {
        btn.classList.add('disabled');
        btn.classList.remove('active-turn');
        btn.setAttribute('aria-disabled', true);
    });

    if (!active || active.length === 0) return;

    const color = active[turnIdx % active.length];

    if (window.CURRENT_USER_COLOR && window.CURRENT_USER_COLOR === color) {
        const btn = document.querySelector('.dice-wrap.g-' + color);
        if (btn) {
            btn.classList.remove('disabled');
            btn.classList.add('active-turn');
            btn.removeAttribute('aria-disabled');
        }
    }
}

function ensureTokenTransition() {
  qsa('.token').forEach(el => {
    if (!el.style.transition || !el.style.transition.includes('left')) {
      el.style.transition = 'left 0.35s ease, top 0.35s ease';
    }
  });
}

function updateTokenPosition(key, pos) {
  // pos is a numeric path index OR -1 (home) OR >=57 (finished)
  if (!tokenStates[key]) tokenStates[key] = { index: -1, inHome: true, finished: false };

  const el = document.querySelector('.token.' + key);
  if (!el) {
    console.warn("updateTokenPosition: missing token element", key);
    // still update internal state so canTokenMove checks work
    if (pos === -1) {
      tokenStates[key] = { index: -1, inHome: true, finished: false };
    } else if (pos >= 57) {
      tokenStates[key] = { index: 57, inHome: false, finished: true };
    } else {
      tokenStates[key] = { index: pos, inHome: false, finished: false };
    }
    return;
  }

  // update canonical state first
  if (pos === -1) {
    tokenStates[key] = { index: -1, inHome: true, finished: false };
    sendTokenHome(key);
    return;
  }

  if (typeof pos !== 'number') {
    // Unexpected type from server — try coercing
    pos = Number(pos);
    if (Number.isNaN(pos)) {
      console.warn("updateTokenPosition: invalid pos for", key, pos);
      return;
    }
  }

  if (pos >= 57) {
    tokenStates[key] = { index: 57, inHome: false, finished: true };
    // finished tokens placed at center
    el.style.transition = 'none';
    el.style.left = '46.5%';
    el.style.top  = '46.5%';
    setTimeout(() => { el.style.transition = 'left .10s ease, top .10s ease'; }, 10);
    el.dataset.pos = '57';
    return;
  }

  // normal board index
  tokenStates[key] = { index: pos, inHome: false, finished: false };

  const color = key.replace(/[0-9]/g, '');
  const p = (path[color] && path[color][pos]) ? path[color][pos] : null;
  if (!p) {
    console.warn("updateTokenPosition: no path entry for", key, "pos", pos);
    return;
  }

  // set css with percent values
  el.style.transition = el.style.transition || 'left .10s ease, top .10s ease';
  el.style.left = (typeof p.left === "number" ? (p.left + "%") : p.left);
  el.style.top  = (typeof p.top  === "number" ? (p.top  + "%") : p.top);
  el.dataset.pos = String(pos);
}
function posKey(pos){ return pos.left + ',' + pos.top; }

function canTokenMove(tokenKey, roll) {
  var st = tokenStates[tokenKey];
  if (!st) return false;

  // Token is in home
  if (st.index === -1) {
    return roll === 6;  // only movable if dice = 6
  }

  // Token already finished
  if (st.finished) return false;

  // Normal movement inside path
  var color = tokenKey.replace(/[0-9]/g, '');
  var pathArr = path[color];
  if (!pathArr) return false;

  var newIndex = st.index + roll;
  if (newIndex >= pathArr.length) return false;

  return true;
}

function getMovableTokensForColor(color, roll) {
  roll = Number(roll);
  if (isNaN(roll)) {
    console.warn("getMovableTokensForColor: invalid roll", roll);
    return [];
  }

  const result = [];
  for (const key in tokenStates) {
    if (!tokenStates.hasOwnProperty(key)) continue;
    if (!key.startsWith(color)) continue;

    const state = tokenStates[key];
    if (!state) continue;

    // --- Case 1: Token in home ---
    if (state.inHome || state.index === -1) {
      if (roll === 6) result.push(key); // ✅ only allow 6
      continue;
    }

    // --- Case 2: On board ---
    const newIndex = state.index + roll;
    if (newIndex <= path[color].length - 1 && !state.finished) {
      result.push(key);
    }
  }

  console.log(`[getMovableTokensForColor] color=${color}, roll=${roll}, movable=`, result);
  return result;
}

  function tokensAtPosition(pos){
    var key = posKey(pos);
    var found = [];
    Object.keys(tokenStates).forEach(function(tk){
      var st = tokenStates[tk];
      if (!st || st.inHome || st.index === -1) return;
      var color = tk.replace(/[0-9]/g,'');
      var p = path[color][st.index];
      if (!p) return;
      if (posKey(p) === key) found.push(tk);
    });
    return found;
  }

function sendTokenHome(key) {
  if (!key) {
    console.warn("sendTokenHome called without key");
    return;
  }

  if (!tokenStates[key]) {
    tokenStates[key] = { index: -1, inHome: true, finished: false };
  }

  const st = tokenStates[key];
  if (st.inHome && st.index === -1) return; // already home

  st.index = -1;
  st.inHome = true;
  st.finished = false;

  const el = document.querySelector(".token." + key);
  if (!el) {
    console.warn("sendTokenHome: token element not found for", key);
    return;
  }

  const color = key.replace(/[0-9]/g, '');
  const pieceIndex = parseInt(key.replace(/\D/g, ''), 10) - 1;

  const homePositions = {
    red:    [{ left: "70.8%", top: "18.5%" }, { left: "85.8%", top: "18.5%" }, { left: "70.8%", top: "30.89%" }, { left: "85.8%", top: "30.89%" }],
    yellow: [{ left: "13.9%", top: "18.5%" }, { left: "28.9%", top: "18.5%" }, { left: "13.9%", top: "30.89%" }, { left: "28.9%", top: "30.89%" }],
    blue:   [{ left: "13.9%", top: "69.7%" }, { left: "28.9%", top: "69.7%" }, { left: "13.9%", top: "82.25%" }, { left: "28.9%", top: "82.25%" }],
    green:  [{ left: "70.8%", top: "69.7%" }, { left: "85.8%", top: "69.7%" }, { left: "70.8%", top: "82.25%" }, { left: "85.8%", top: "82.25%" }]
  };

  const posObj = (homePositions[color] && homePositions[color][pieceIndex]) ? homePositions[color][pieceIndex] : null;

  if (posObj) {
    const curLeft = el.style.left;
    const curTop = el.style.top;
    if (curLeft !== posObj.left || curTop !== posObj.top) {
      el.style.transition = 'none';
      el.style.left = posObj.left;
      el.style.top = posObj.top;
      setTimeout(() => {
        if (el) el.style.transition = 'left .10s ease, top .10s ease';
      }, 10);
    }
  } else {
    el.style.display = 'none';
  }

  el.dataset.pos = '-1';
}

function markTokenSelectable(el) {
  if (!el) return;
  el.classList.add('selectable');
  el.style.boxShadow = '0 0 0 6px rgba(255,215,0,0.12), 0 6px 18px rgba(0,0,0,0.45)';
  el.style.cursor = 'pointer';
  el.style.transform = 'scale(1.15)';
  el.style.zIndex = 60;
}

function unmarkTokenSelectable(el) {
  if (!el) return;
  el.classList.remove('selectable');
  el.style.boxShadow = '';
  el.style.cursor = '';
  el.style.transform = '';
  el.style.zIndex = 20;
  el.onclick = null; // clear click handler
}

function clearAllSelectable() {
  qsa('.token.selectable').forEach(function (el) {
    unmarkTokenSelectable(el);
  });
  qsa('.token').forEach(function (el) {
    el.onclick = null;
  });
}

if (typeof extraTurn === 'undefined') var extraTurn = false;
if (typeof extraTurnGivenForMove === 'undefined') var extraTurnGivenForMove = false;

function grantExtraTurn(color, reason) {
  if (window.__ludo_game_over) return;

  console.trace("grantExtraTurn called:", color, reason);

  if (extraTurnGivenForMove) {
    console.log("grantExtraTurn: already granted for this move - skipping. color=", color, "reason=", reason);
    return;
  }

  console.log("grantExtraTurn: granting extra turn for", color, " reason=", reason);
  extraTurn = true;
  extraTurnGivenForMove = true;
}

function nextTurn(keepFromRoll) {
  if (window.__ludo_game_over) return;

  clearAllSelectable();

  keepFromRoll = !!keepFromRoll; // coerce

  if (keepFromRoll || extraTurn) {
    console.log("nextTurn: keeping same player =>", active[turnIdx], "keepFromRoll:", keepFromRoll, "extraTurn:", extraTurn);

    extraTurn = false;

    extraTurnGivenForMove = false;

    refreshDiceEnabled();
    return;
  }

  if (!active || active.length === 0) {
    console.log("nextTurn: no active players array found.");
    window.__ludo_game_over = true;
    qsa('.dice-wrap').forEach(function(btn){
      btn.classList.add('disabled');
      btn.classList.remove('active-turn');
    });
    return;
  }

  var len = active.length;
  var found = false;
  for (var i = 1; i <= len; i++) {
    var idx = (turnIdx + i) % len;
    var col = active[idx];

    if (isPlayerCompletelyFinished(col)) {
      continue;
    }

    turnIdx = idx;
    found = true;
    break;
  }

  if (!found) {
    // no player left with tokens -> game finished
    console.log("nextTurn: no remaining active players (everyone finished?) -> ending game.");
    window.__ludo_game_over = true;
    qsa('.dice-wrap').forEach(function(btn){
      btn.classList.add('disabled');
      btn.classList.remove('active-turn');
    });
    return;
  }

  extraTurn = false;
  extraTurnGivenForMove = false;

  refreshDiceEnabled();
}

const CLOCKWISE_ORDER = ['red', 'green', 'blue', 'yellow'];

function parseJoinedFromResponse(res) {
  if (!res) return CLOCKWISE_ORDER;
  let cols = [];

  if (res.players) {
    res.players.forEach(p => {
      if (p.color && !cols.includes(p.color)) cols.push(p.color);
    });
  }

  // return only those in fixed order
  return CLOCKWISE_ORDER.filter(c => cols.includes(c));
}

// 🎲 Dice click handler

// Keep track of last rolled value for each color
// Track last shown dice per color
// Track last shown dice per color

// ----------------------------------------------------
// Inject CURRENT_USER_COLOR from PHP into JS
// ----------------------------------------------------
window.CURRENT_USER_COLOR = "<?php echo isset($myColor) ? strtolower($myColor) : ''; ?>";
console.log("CURRENT_USER_COLOR from PHP:", window.CURRENT_USER_COLOR);

// ----------------------------------------------------
// Dice logic & polling
// ----------------------------------------------------

function updateDiceUI(color, value) {
  console.log("🎲 updateDiceUI →", color, value);

  const diceWrap = document.querySelector(`.dice-wrap[data-seat="${color}"]`);
  if (!diceWrap) return;

  const dice = diceWrap.querySelector(".dice");
  if (!dice) return;

  dice.style.transition = "transform 1s cubic-bezier(.2,.8,.2,1)";

  // Correct mapping → 1–6
  let rotX = 0, rotY = 0;
  switch (value) {
    case 1: rotX = 0;   rotY = 0; break;
    case 2: rotX = 0;   rotY = 180; break;
    case 3: rotX = 0;   rotY = 90; break;   // ✅ fixed
    case 4: rotX = 0;   rotY = -90; break;  // ✅ fixed
    case 5: rotX = -90; rotY = 0; break;
    case 6: rotX = 90;  rotY = 0; break;
  }

  // Always reset base + spin
  const spinX = 720, spinY = 720;
  dice.style.transform = `rotateX(${spinX + rotX}deg) rotateY(${spinY + rotY}deg)`;
}


function updateDiceState(gameState) {
  const myColor = (window.CURRENT_USER_COLOR || "").toLowerCase();
  const turnColor = (gameState.turnColor || "").toLowerCase();

  document.querySelectorAll(".dice-wrap").forEach(el => {
    el.classList.remove("clickable");
    el.classList.add("disabled");
  });

  if (myColor && myColor === turnColor) {
    const myDice = document.querySelector(`.dice-wrap[data-seat="${turnColor}"]`);
    if (myDice) {
      myDice.classList.add("clickable");
      myDice.classList.remove("disabled");
      console.log("✅ My turn →", myColor);
    }
  }
}

lastDiceValues = {};
firstLoad = true;



document.querySelectorAll(".dice-wrap").forEach(el => {
  el.addEventListener("click", () => {
    if (!el.classList.contains("clickable")) return;

    const color = el.dataset.seat;

    fetch("/ludo/api/roll_dice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        room: roomCode,
        color: color
      })
    })
    .then(r => r.json())
    .then(data => {
  if (data.ok) {
    console.log("🎲 Rolled dice:", data.color, data.dice);

    // 🎲 Animate dice
   // rollAnimated(data.color);
    updateDiceUI(data.color, data.dice);
    lastDiceValues[data.color] = data.dice;

    // ✅ Process game logic (highlight tokens, auto-move if only one)
    onDiceRolled(data.color, data.dice);

    // 🔄 Dice UI turn switching
    ["red", "blue", "green", "yellow"].forEach(disableDice);
    enableDice(data.nextColor);
  } else {
    console.error("Dice roll error:", data.error);
  }
})

    .catch(err => console.error("Dice roll fetch error:", err));
  });
});

// ...existing code...
// WebSocket-first client (replaces AJAX polling IIFE)
(function(){
  const WS_PORT = 8080; // adjust to your websocket server port
  const WS_PATH = '/';
  const WS_URL = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':' + WS_PORT + WS_PATH;

  let ws = null;
  let reconnectDelay = 2000;
  const room = roomCode || window.ROOM_CODE || (new URLSearchParams(location.search)).get('room');

  function safeSend(obj) {
    if (!ws || ws.readyState !== WebSocket.OPEN) return false;
    try { ws.send(JSON.stringify(obj)); return true; } catch (e) { return false; }
  }

  function handleMessage(evt) {
    try {
      const msg = JSON.parse(evt.data);
      if (!msg || !msg.type) return;
      switch (msg.type) {
        case 'state':
          if (msg.data) {
            if (Array.isArray(msg.data.pieces) && typeof placeAllTokensFromState === 'function') placeAllTokensFromState(msg.data.pieces);
            if (Array.isArray(msg.data.players) && typeof showOnlyJoined === 'function') showOnlyJoined(msg.data.players.map(p => (p.color || p).toLowerCase()));
            if (msg.data.lastColor !== undefined && msg.data.lastDice !== undefined && typeof updateDiceUI === 'function') updateDiceUI(msg.data.lastColor, Number(msg.data.lastDice));
            if (typeof updateDiceState === 'function') updateDiceState(msg.data);
          }
          break;
        case 'players':
          if (Array.isArray(msg.data)) {
            if (typeof showOnlyJoined === 'function') showOnlyJoined(msg.data.map(c => (c.color || c).toLowerCase()));
            if (typeof setActivePlayers === 'function') setActivePlayers(parseJoinedFromResponse({ players: msg.data.map(c => ({ color: c })) }));
          }
          break;
        case 'dice':
          if (msg.data && msg.data.color && typeof msg.data.value !== 'undefined') {
            if (typeof updateDiceUI === 'function') updateDiceUI(msg.data.color, Number(msg.data.value));
            if (typeof onDiceRolled === 'function') onDiceRolled(msg.data.color, Number(msg.data.value));
          }
          break;
        case 'move':
          if (msg.data && Array.isArray(msg.data.pieces) && typeof placeAllTokensFromState === 'function') {
            placeAllTokensFromState(msg.data.pieces);
          }
          break;
        case 'ping':
          safeSend({ type: 'pong' });
          break;
        default:
          console.debug('WS unknown', msg.type, msg);
      }
    } catch (e) {
      console.warn('WS msg parse error', e, evt.data);
    }
  }

  function connectWS() {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
    try {
      ws = new WebSocket(WS_URL);
    } catch (e) {
      ws = null;
      scheduleReconnect();
      return;
    }

    ws.onopen = () => {
      reconnectDelay = 2000;
      console.info('WebSocket connected ->', WS_URL);
      if (room) safeSend({ type: 'subscribe', room });
    };
    ws.onmessage = handleMessage;
    ws.onclose = (ev) => {
      console.warn('WebSocket closed', ev);
      ws = null;
      scheduleReconnect();
    };
    ws.onerror = (err) => {
      console.error('WebSocket error', err);
      try { ws.close(); } catch (_) {}
      ws = null;
    };
  }

  let reconnectTimer = null;
  function scheduleReconnect() {
    if (reconnectTimer) return;
    reconnectTimer = setTimeout(() => {
      reconnectTimer = null;
      connectWS();
      reconnectDelay = Math.min(60000, Math.floor(reconnectDelay * 1.5));
    }, reconnectDelay);
  }

  // Expose API and attempt immediate connect
  window.LUDO_WS = { connect: connectWS, ws: () => ws, send: safeSend };
  connectWS();

  // One-shot fallback refresh if WS not open quickly (no repeated polling)
  setTimeout(() => {
    if (!ws || ws.readyState !== WebSocket.OPEN) {
      console.info('WS not ready — performing single refreshGameState() fallback.');
      try { refreshGameState(); } catch(e){ console.warn('fallback refresh failed', e); }
    }
  }, 1200);
})();
</script>
</body>
</html>