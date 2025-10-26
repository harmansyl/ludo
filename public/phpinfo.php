<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/boot.php';

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

// ensure user is part of room_players
$stmt = $db->prepare("SELECT * FROM room_players WHERE room_id = ? AND user_id = ?");
$stmt->execute([$room['id'], $user['id']]);
$player = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$player) {
  // auto-join if not already joined
  $stmt = $db->prepare("INSERT INTO room_players (room_id, user_id, position, is_winner, joined_at) VALUES (?, ?, (SELECT IFNULL(MAX(position)+1,2) FROM room_players WHERE room_id = ?), 0, NOW())");
  $stmt->execute([$room['id'], $user['id'], $room['id']]);
  $playerId = $db->lastInsertId();
} else {
  $playerId = $player['id'];
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
  

#admin-dice-panel .cheat-dice {
  width: 50px;
  height: 50px;
  font-size: 22px;
  font-weight: bold;
  border: 2px solid #444;
  border-radius: 6px;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s ease;
}

#admin-dice-panel .cheat-dice:hover {
  background: #ffd700;
  border-color: #000;
}

#admin-dice-panel .cheat-dice.active {
  background: #444;
  color: #fff;
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

</style>
</head>
<body>

<script>
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

<!-- 🎲 Admin Dice Cheat Panel -->
<div id="admin-dice-panel" style="display:none; text-align:center; margin:10px;">
  <div style="display:flex; justify-content:center; gap:8px;">
    <button class="cheat-dice" data-val="6">6</button>
    <button class="cheat-dice" data-val="5">5</button>
    <button class="cheat-dice" data-val="4">4</button>
    <button class="cheat-dice" data-val="3">3</button>
    <button class="cheat-dice" data-val="2">2</button>
    <button class="cheat-dice" data-val="1">1</button>
  </div>
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
    <button class="overlay dice-wrap g-yellow" data-seat="yellow" aria-label="Yellow dice"
            style="left:var(--dice-tl-x); top:var(--dice-tl-y);">
      <div class="dice" aria-hidden="true">
        <div class="dice-face f1"><span class="pip mm"></span></div>
        <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
        <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
        <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f6"><span class="pip tl"></span><span class="pip tr"></span><span class="pip ml"></span><span class="pip mr"></span><span class="pip bl"></span><span class="pip br"></span></div>
      </div>
    </button>

    <button class="overlay dice-wrap g-red" data-seat="red" aria-label="Red dice"
            style="left:var(--dice-tr-x); top:var(--dice-tr-y);">
      <div class="dice" aria-hidden="true">
        <div class="dice-face f1"><span class="pip mm"></span></div>
        <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
        <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
        <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f6"><span class="pip tl"></span><span class="pip tr"></span><span class="pip ml"></span><span class="pip mr"></span><span class="pip bl"></span><span class="pip br"></span></div>
      </div>
    </button>

    <button class="overlay dice-wrap g-blue" data-seat="blue" aria-label="Blue dice"
            style="left:var(--dice-bl-x); top:var(--dice-bl-y);">
      <div class="dice" aria-hidden="true">
        <div class="dice-face f1"><span class="pip mm"></span></div>
        <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
        <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
        <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f6"><span class="pip tl"></span><span class="pip tr"></span><span class="pip ml"></span><span class="pip mr"></span><span class="pip bl"></span><span class="pip br"></span></div>
      </div>
    </button>

    <button class="overlay dice-wrap g-green" data-seat="green" aria-label="Green dice"
            style="left:var(--dice-br-x); top:var(--dice-br-y);">
      <div class="dice" aria-hidden="true">
        <div class="dice-face f1"><span class="pip mm"></span></div>
        <div class="dice-face f2"><span class="pip tl"></span><span class="pip br"></span></div>
        <div class="dice-face f3"><span class="pip tl"></span><span class="pip mm"></span><span class="pip br"></span></div>
        <div class="dice-face f4"><span class="pip tl"></span><span class="pip tr"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f5"><span class="pip tl"></span><span class="pip tr"></span><span class="pip mm"></span><span class="pip bl"></span><span class="pip br"></span></div>
        <div class="dice-face f6"><span class="pip tl"></span><span class="pip tr"></span><span class="pip ml"></span><span class="pip mr"></span><span class="pip bl"></span><span class="pip br"></span></div>
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

$joinedColors = [];
foreach ($joinedSeats as $pos) {
    if (isset($seatMap[$pos])) {
        $joinedColors[] = $seatMap[$pos];
    }
}
?>
<script>
  window.JOINED_PLAYERS = <?= json_encode($joinedColors) ?>;
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
/* ==========================
   Quantum Ludo – Game Logic (Cleaned)
   ========================== */
   const API_BASE   = window.API_BASE   || "../api";
const roomCode = "<?php echo $_GET['room'] ?? ''; ?>";
const currentUserId = "<?php echo $_SESSION['user_id'] ?? ''; ?>";
const PLAYER_COLOR = window.PLAYER_COLOR || null;
const API_GET_STATE = "../api/get_state.php";
const API_ROLL_DICE = "../api/roll_dice.php";
const ROOM = window.ROOM_CODE || ""; // injected by PHP

// local state
var active = [];   // joined colors in position order
var turnIdx = 0;
window.__ludo_game_over = false;

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


// show only joined players' dice & tokens
function showJoinedPlayersOnly(joinedColors) {
  active = Array.isArray(joinedColors) ? joinedColors.slice() : [];
  // hide all dice & tokens first
  $(".dice-wrap, .token").hide();
  // show only for joined colors
  active.forEach(function(c){
    $(".dice-wrap.g-" + c).show();
    $(".token.g-" + c).show();
  });
  // reset turn index (server decides turn; this helps refresh)
  turnIdx = 0;
  refreshDiceEnabled();
}

async function rollDice(room, userId, color) {
  btn.addEventListener("click", () => {
  console.log("Dice clicked:", color); // ✅ should log when clicked
  rollDice(roomCode, currentUserId, color);
});
  try {
    const res = await fetch("roll_dice.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ room: room, userId: userId })
    });
    const data = await res.json();

    if (!data.ok) {
      alert("Error: " + data.error);
      return;
    }

    // 🎲 Animate dice face according to backend result
    animateDice(color, data.dice);

    // Continue game logic
    setTimeout(() => {
      onDiceRolled(color, data.dice);
    }, 1150);

  } catch (err) {
    console.error("Dice roll failed:", err);
  }
}

function animateDice(color, final) {
  const wrap = document.querySelector('.dice-wrap.g-' + color);
  const cube = wrap ? wrap.querySelector('.dice') : null;
  if (!cube) return;

  const randX = Math.floor(Math.random() * 6) + 2;
  const randY = Math.floor(Math.random() * 6) + 2;
  let xRot = randX * 360;
  let yRot = randY * 360;

  switch(final){
    case 1: xRot +=   0; yRot +=   0;  break;
    case 2: xRot +=   0; yRot += 180;  break;
    case 3: xRot +=   0; yRot +=  90;  break;
    case 4: xRot +=   0; yRot += -90;  break;
    case 5: xRot += -90; yRot +=   0;  break;
    case 6: xRot +=  90; yRot +=   0;  break;
  }

  cube.style.transition = 'transform 1100ms cubic-bezier(.2,.8,.2,1)';
  cube.style.transform = `rotateX(${xRot}deg) rotateY(${yRot}deg)`;
}


function setDiceFace(color, roll) {
  var $cube = $(".dice-wrap.g-" + color + " .dice");
  if (!$cube.length) return;
  var rot = getRotation(roll);
  $cube.css({ transition: "none", transform: "rotateX(" + rot.x + "deg) rotateY(" + rot.y + "deg)" });
}

// Called after dice animation finishes.
// Updates dice UI and asks server for the new authoritative state.
function onDiceRolled(color, roll) {
  try {
    console.log('[onDiceRolled] color=', color, 'roll=', roll);

    // Update dice display immediately for the player
    var $val = $('.dice-wrap.g-' + color + ' .dice-value');
    if ($val.length) $val.text(roll);

    // After a roll, poll server for state changes (server should process roll and update tokens/turn)
    // Slight delay gives server time to process any synchronous DB updates
    setTimeout(function(){ pollGameState(); }, 300);
  } catch (e) {
    console.error('onDiceRolled error', e);
    // fallback poll
    setTimeout(pollGameState, 300);
  }
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

function placeAllTokensFromState(tokens) {
  if (!tokens) return;
  // Keep local cache (server authoritative)
  tokenStates = tokens;

  Object.keys(tokenStates).forEach(function(key){
    var st = tokenStates[key];
    var el = document.querySelector('.token.' + key);
    if (!el) return; // skip if token DOM missing

    // Token in home / not placed
    if (st.inHome || st.index === -1) {
      el.style.left = '';
      el.style.top  = '';
      el.style.zIndex = 20;
      el.classList.remove('finished');
      return; // skip to next token
    }

    // Finished tokens go to center/home area
    if (st.finished) {
      el.style.left = '46.5%';
      el.style.top  = '46.5%';
      el.style.zIndex = 30;
      el.classList.add('finished');
      return;
    }

    // Normal position on path
    var color = key.replace(/[0-9]/g,'');
    var idx = st.index;
    var p = (path[color] && typeof path[color][idx] !== 'undefined') ? path[color][idx] : null;
    if (!p) {
      // if invalid index, hide/in-home fallback
      el.style.left = '';
      el.style.top = '';
      el.style.zIndex = 20;
      return;
    }

    el.style.left = p.left;
    el.style.top  = p.top;
    el.style.zIndex = 20;
    el.classList.remove('finished');
  });
}


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

const entryPoints = { blue:0, red:0, green:0, yellow:0 };

const SAFE_SPOTS = [
  { left: "43.8%", top: "19.2%" },  // yellow star
  { left: "80.9%", top: "43.8%" },  // red star
  { left: "56.2%", top: "81.1%" },  // green star
  { left: "18.9%", top: "56.2%" },  // blue star
  { left: "43.6%", top: "87.3%" },  // blue start
  { left: "12.6%", top: "43.8%" },  // yellow start
  { left: "56.2%", top: "13%"   },  // red start
  { left: "87.1%", top: "56.2%" }   // green start
];
const SAFE_TOL = 0.6;

function grantExtraTurn(color){
  console.log('[grantExtraTurn] color=', color, 'turnIdx=', turnIdx, 'active[turnIdx]=', active[turnIdx]);
  extraTurn = true;
}

function nextTurn(keepFromRoll){
  clearAllSelectable();
  console.log('[nextTurn] before: turnIdx=', turnIdx, 'keepFromRoll=', keepFromRoll, 'extraTurn=', extraTurn);
  if (keepFromRoll || extraTurn) {
    console.log('[nextTurn] keeping same player:', active[turnIdx]);
  } else {
    turnIdx = (turnIdx + 1) % active.length;
    console.log('[nextTurn] advancing to turnIdx=', turnIdx, 'player=', active[turnIdx]);
  }
  extraTurn = false;
  refreshDiceEnabled();
}

function refreshDiceEnabled() {
  if (window.__ludo_game_over) return;
  $('.dice-wrap').addClass('disabled').removeClass('active-turn').attr('aria-disabled', true);
  if (active.length === 0) return;
  var color = active[turnIdx % active.length];
  var $btn = $('.dice-wrap.g-' + color);
  if ($btn.length) {
    $btn.removeClass('disabled').addClass('active-turn').removeAttr('aria-disabled');
  }
}

// click handler for dice
$(document).on('click', '.dice-wrap', function(e){
  e.preventDefault();
  var $btn = $(this);
  if ($btn.hasClass('disabled')) return;
  var color = $btn.data('seat') || null;
  if (!color) return;
  rollAnimated(color);
});


function parseJoinedFromResponse(res) {
  if (!res) return ['blue','yellow','red','green'];
  if (Array.isArray(res.joinedPlayers) && res.joinedPlayers.length) return res.joinedPlayers;
  if (Array.isArray(res.players) && res.players.length) {
    var cols = [];
    res.players.forEach(function(p){ if (p.color && cols.indexOf(p.color) === -1) cols.push(p.color); });
    if (cols.length) return cols;
  }
  if (res.tokens && typeof res.tokens === 'object') {
    var cols = [];
    Object.keys(res.tokens).forEach(function(k){ var c = k.replace(/\d+$/,''); if (cols.indexOf(c) === -1) cols.push(c); });
    if (cols.length) return cols;
  }
  return ['blue','yellow','red','green'];
}

function pollGameState() {
  fetch(API_BASE + "/game_state.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ room: ROOM_CODE })
  })
  .then(r => r.json())
  .then(state => {
    if (!state) {
      console.error('pollGameState: no state returned');
      return;
    }
    if (!state.ok) {
      if (state.error) console.error('pollGameState error:', state.error);
      return;
    }

    // joined players -> show/hide UI
    if (state.joinedColors) {
      setActivePlayers(state.joinedColors);
    }

    // Token positions (server authoritative)
    if (state.tokens) {
      try {
        placeAllTokensFromState(state.tokens);
      } catch (e) {
        console.error('placeAllTokensFromState failed', e);
      }
    }

    // Set which dice is active
    if (state.turnColor) {
      $('.dice-wrap').addClass('disabled').removeClass('active-turn');
      $('.dice-wrap.g-' + state.turnColor).removeClass('disabled').addClass('active-turn');
    }

    // Track winners/game over if provided
    if (Array.isArray(state.winners) && state.winners.length) {
      winners = state.winners;
      window.__ludo_game_over = true;
      // Optionally show your winner UI here
    }
  })
  .catch(err => {
    console.error('pollGameState fetch error', err);
  })
  .finally(() => {
    // keep polling continuously
    setTimeout(pollGameState, 1000);
  });
}




document.querySelectorAll(".dice-wrap").forEach(btn => {
  const color = btn.dataset.seat; // "red", "blue", "green", "yellow"

  btn.addEventListener("click", () => {
    rollDice(roomCode, currentUserId, color);
  });
});

</script>

</body>
</html>
