<?php
declare(strict_types=1);
require_once __DIR__ . '/../../lib/boot.php';
auth_require();
$user = current_user();
$BOARD_IMG = '../img/board.jpg';
?>
<!doctype html>
1
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
  background: #0b0f1a url("../img/bg.jpg") no-repeat center center fixed;
  background-size: cover;
}

.topbar{ 
  position:relative;
  top:15px; 
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
  background: #000 url("../img/board.jpg") center/contain no-repeat; /* your board */
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
  background-image: url("../img/yellow.png");
}
.token.yellow1 { left:13.9%; top:18.5%; }
.token.yellow2 { left:28.9%; top:18.5%; }
.token.yellow3 { left:13.9%; top:30.89%; }
.token.yellow4 { left:28.9%; top:30.89%; }

/* Red tokens */
.token.red1, .token.red2, .token.red3, .token.red4 {
  background-image: url("../img/red.png");
}
.token.red1 { left:70.8%; top:18.5%; }
.token.red2 { left:85.8%; top:18.5%; }
.token.red3 { left:70.8%; top:30.89%; }
.token.red4 { left:85.8%; top:30.89%; }

/* Blue tokens */
.token.blue1, .token.blue2, .token.blue3, .token.blue4 {
  background-image: url("../img/blue.png");
}
.token.blue1 { left:13.9%; top:69.7%; }
.token.blue2 { left:28.9%; top:69.7%; }
.token.blue3 { left:13.9%; top:82.25%; }
.token.blue4 { left:28.9%; top:82.25%; }

/* Green tokens */
.token.green1, .token.green2, .token.green3, .token.green4 {
  background-image: url("../img/green.png");
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


</style>
</head>
<body>
  <!-- Sound Effects -->
<audio id="snd-dice" src="../sound/dice.mp3" preload="auto"></audio>
<audio id="snd-step" src="../sound/step_move.mp3" preload="auto"></audio>
<audio id="snd-home" src="../sound/reach_home.mp3" preload="auto"></audio>
<audio id="snd-win" src="../sound/win.mp3" preload="auto"></audio>
<audio id="snd-over" src="../sound/game_over.mp3" preload="auto"></audio>
<audio id="snd-start" src="../sound/game_start.mp3" preload="auto"></audio>

<div class="topbar">
  
  <div class="title">QUANTUM LUDO</div>
</div>
<button class="exit" onclick="window.location.href='../dashboard.php'">EXIT GAME</button>


<!-- STAGE remains the outer container -->
<div class="ludo-container">
<div class="stage" id="stage" data-players="4" aria-live="polite" aria-atomic="true">
  
  <!-- NEW: a perfectly square, relative container that holds EVERYTHING -->
  <div class="board-wrap" role="img" aria-label="Ludo board">
    <!-- Board image as background; no <img> shifting the layout -->
    
    <!-- Avatars -->
    <div class="overlay avatar g-yellow" style="left:var(--ava-tl-x); top:var(--ava-tl-y);">
      <img src="../img/profile.png" alt="Yellow player avatar">
    </div>
    <div class="overlay avatar g-red" style="left:var(--ava-tr-x); top:var(--ava-tr-y);">
      <img src="../img/profile.png" alt="Red player avatar">
    </div>
    <div class="overlay avatar g-blue" style="left:var(--ava-bl-x); top:var(--ava-bl-y);">
      <img src="../img/profile.png" alt="Blue player avatar">
    </div>
    <div class="overlay avatar g-green" style="left:var(--ava-br-x); top:var(--ava-br-y);">
      <img src="../img/profile.png" alt="Green player avatar">
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

<script>
// Replace existing playSound with this clone-based version
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


function getDiceRoll() {
  return Math.floor(Math.random() * 6) + 1; // normal random
}


  
var currentPlayerIndex = 0; // 0=Red,1=Blue,2=Green,3=Yellow
var players = ["red","blue","green","yellow"];
var extraTurn = false;
var extraTurnGivenForMove = false;
// ===== Quantum Ludo patches =====

// Toggle: also advance a token after it leaves home on a six (house rule).
const ADVANCE_FROM_HOME_ON_SIX = false;

// Optional: future grid helpers (if you later want to use grid instead of %)
const GRID_COLS = 16, GRID_ROWS = 16;
const CELL = 100 / GRID_COLS;
function gridToPct(pos){ // pos = {c, r}
  return {
    left: ((pos.c + 0.5) * CELL).toFixed(1) + '%',
    top:  ((pos.r + 0.5) * CELL).toFixed(1) + '%'
  };
}


function playerHasFinishedToken(color){
  return Object.keys(tokenStates).some(function(tk){
    if (!tk.startsWith(color)) return false;
    var st = tokenStates[tk];
    return st && st.finished;
  });
}

(function(){
  "use strict";

  /* ---------------------------
     Helpers / DOM utilities
     --------------------------- */
  var stage = document.getElementById('stage');
  var order = ['blue','yellow','red','green'];

  function qsa(sel){ return Array.prototype.slice.call(document.querySelectorAll(sel)); }
  function qs(sel){ return document.querySelector(sel); }
  function on(el, ev, fn){ if(!el) return; el.addEventListener(ev, fn); }


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

  /* ---------------------------
     Build paths from a small GRID reference
     This keeps coordinates consistent and easy to tweak.
     GRID contains the most commonly used percent coordinates
     (these match the visual grid used in your board art).
     --------------------------- */

  // If you want to tweak alignment, change these numbers slightly.
  const GRID = {
    // horizontally used columns (approx)
    X: [ "14%", "22%", "30%", "38%", "46%", "54%", "62%", "70%", "78%", "86%" ],
    // vertically used rows (approx)
    Y: [ "14%", "22%", "30%", "38%", "46%", "54%", "62%", "70%", "78%", "86%" ],
    // a slightly more centered X used where you previously used 46.5%
    CENTER_X: "46.5%"
  };

  // convenience helpers
  function x(i){ return GRID.X[i]; }
  function y(i){ return GRID.Y[i]; }

  // For clarity: indexes we will use from GRID arrays
  // 0->14%, 1->22%, 2->30%, ... , 9->86%
  // center index for cross uses INDEX_CENTER = 4 (46%)
  const IDX = { A:0, B:1, C:2, D:3, E:4, F:5, G:6, H:7, I:8, J:9 };

  // Build the path arrays using GRID indexes so they are consistent.
  // Sequence follows the standard Ludo outer loop and then the 6-cell home stretch at the end.
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


  // entry index for leaving home (first outer-loop index for that color)
  const entryPoints = {
  blue: 0,
  red: 0,
  green: 0,
  yellow: 0
};


  /* ---------------------------
     Safe positions set (used for capture immunity)
     We'll populate using some canonical positions from each path.
     --------------------------- */
     // Safe squares (stars + starting positions)


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
  let tokenStates = {
    blue1:   { index: -1, inHome: true, finished:false },
    blue2:   { index: -1, inHome: true, finished:false },
    blue3:   { index: -1, inHome: true, finished:false },
    blue4:   { index: -1, inHome: true, finished:false },

    red1:    { index: -1, inHome: true, finished:false },
    red2:    { index: -1, inHome: true, finished:false },
    red3:    { index: -1, inHome: true, finished:false },
    red4:    { index: -1, inHome: true, finished:false },

    green1:  { index: -1, inHome: true, finished:false },
    green2:  { index: -1, inHome: true, finished:false },
    green3:  { index: -1, inHome: true, finished:false },
    green4:  { index: -1, inHome: true, finished:false },

    yellow1: { index: -1, inHome: true, finished:false },
    yellow2: { index: -1, inHome: true, finished:false },
    yellow3: { index: -1, inHome: true, finished:false },
    yellow4: { index: -1, inHome: true, finished:false }
  };

  // Ensure tokens animate smoothly: add transition
  function ensureTokenTransition() {
    qsa('.token').forEach(function(el){
      if (!el.style.transition || el.style.transition.indexOf('left')===-1){
        el.style.transition = 'left .10s ease, top .10s ease';
      }
    });
  }

  function placeAllTokensFromState() {
  Object.keys(tokenStates).forEach(function(key){
    var state = tokenStates[key];
    var el = qs('.token.' + key);
    if (!el) return;

    // still in home? -> use CSS-defined position
    if (state.inHome) {
      el.style.left = "";  // reset so CSS class applies
      el.style.top  = "";
      return;
    }

    // finished? -> put in center stack
    if (state.finished) {
      el.style.left = "46.5%"; // center
      el.style.top  = "46.5%"; // adjust as needed
      return;
    }

    // otherwise -> on path
    var color = key.replace(/[0-9]/g, "");
    var pos = path[color][state.index];
    if (pos) {
      el.style.left = pos.left;
      el.style.top  = pos.top;
    }
  });
}


  /* ---------------------------
     Movement & capture helpers
     --------------------------- */
  function posKey(pos){ return pos.left + ',' + pos.top; }

function canTokenMove(tokenKey, roll){
  var st = tokenStates[tokenKey];
  if (!st || st.finished) return false;
  var color = tokenKey.replace(/[0-9]/g, '');
  
  // Still in home
  if (st.inHome || st.index === -1){
    return roll === 6; // can leave home only on a six
  }

  // Normal move
  var newIndex = st.index + roll;
  return newIndex < path[color].length;
}


  function getMovableTokensForColor(color, roll){
    var list = [];
    for (var i = 1; i <= 4; i++){
      var key = color + i;
      if (canTokenMove(key, roll)) list.push(key);
    }
    return list;
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

  function sendTokenHome(tokenKey){
    var st = tokenStates[tokenKey];
    if (!st) return;
    st.index = -1;
    st.inHome = true;
    st.finished = false;
    var el = qs('.token.' + tokenKey);
    if (el){
      el.style.left = '';
      el.style.top = '';
      el.style.zIndex = 30;
      setTimeout(function(){ el.style.zIndex = 20; }, 400);
    }
  }

// ---- Safe spots (stars + start positions) ----
// Use the positions you gave me (you can tweak / add more if needed)
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

// tolerance in percentage points for "same square" matching
const SAFE_TOL = 0.6; // adjust down if you want stricter equality (0.3 - 0.6 is typical)

// ---- helpers ----
function toNums(pos){
  // Accepts objects like {left:"43.8%", top:"19.2%"} or strings "43.8%" etc.
  if(!pos) return null;
  let lx = pos.left;
  let ty = pos.top;
  if(typeof lx === 'string') lx = parseFloat(lx.replace('%',''));
  if(typeof ty === 'string') ty = parseFloat(ty.replace('%',''));
  return { x: Number(lx), y: Number(ty) };
}
function nearEqual(a, b, tol = SAFE_TOL){
  return Math.abs(a - b) <= tol;
}
function isSamePos(posA, posB, tol = SAFE_TOL){
  if(!posA || !posB) return false;
  const na = toNums(posA);
  const nb = toNums(posB);
  if(na == null || nb == null) return false;
  return nearEqual(na.x, nb.x, tol) && nearEqual(na.y, nb.y, tol);
}
function isSafeSpot(pos){
  if(!pos) return false;
  for(let s of SAFE_SPOTS){
    if(isSamePos(s, pos)) return true;
  }
  return false;
}

// ---- New capture handler (replace your old handleCaptures) ----
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
  if (!state) return { ok:false, reason:'no such token' };
  if (state.finished) return { ok:false, reason:'already finished' };

  var color = tokenKey.replace(/[0-9]/g, '');

  // --- Case 1: Token still in home
  if (state.inHome || state.index === -1) {
    if (steps === 6) {
      state.index = 0;      // place on entry square
      state.inHome = false;

      // advance extra steps if rule enabled
      if (ADVANCE_FROM_HOME_ON_SIX) {
        var extra = steps - 1;
        state.index += extra;
      }
    } else {
      return { ok:false, reason:'need a 6 to leave home' };
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


function moveTokenByKeyWithAnimation(tokenKey, steps) {
  return new Promise(async function (resolve) {
    var el = qs('.token.' + tokenKey);
    var state = tokenStates[tokenKey];
    if (!el || !state) {
      resolve({ ok: false, reason: 'no element/state' });
      return;
    }

    el.style.transition = el.style.transition || 'left .25s ease, top .25s ease';
    el.style.zIndex = 50;

    var color = tokenKey.replace(/[0-9]/g, '');
    let justFinishedNow = false;

    async function animateToIndex(idx) {
      return new Promise(function (res) {
        var p = path[color][idx];
        if (!p) { res(); return; }

        var tokensHere = tokensAtPosition(p) || [];
        var slot = tokensHere.indexOf(tokenKey);
        if (slot === -1) slot = tokensHere.length;

        var leftNum = parseFloat(p.left);
        var topNum = parseFloat(p.top);
        var step = 3;
        var cols = Math.ceil(Math.sqrt(Math.max(1, tokensHere.length)));
        var row = Math.floor(slot / cols);
        var col = slot % cols;
        var startCol = (cols - 1) / 2;
        var rows = Math.ceil(tokensHere.length / cols);
        var startRow = (rows - 1) / 2;

        var newLeft = leftNum + (col - startCol) * step;
        var newTop = topNum + (row - startRow) * step;

        let done = false;
        const finish = () => { if (done) return; done = true; res(); };
        const to = setTimeout(finish, 300);
        function te(e) {
          if (e.propertyName === 'left' || e.propertyName === 'top') {
            clearTimeout(to);
            el.removeEventListener('transitionend', te);
            finish();
          }
        }
        el.addEventListener('transitionend', te);

          // play sound immediately and slightly speed it up for snappiness
          playSound("snd-step", { rate: 1.05 });

          // schedule the style change in next frame so the browser processes the sound immediately
          requestAnimationFrame(function(){
            el.style.left = newLeft + '%';
            el.style.top  = newTop + '%';
          });

      });
    }

    // --- CASE 1: Leaving home on a 6
    if ((state.inHome || state.index === -1) && steps === 6) {
      state.index = 0;
      state.inHome = false;
      await animateToIndex(state.index);

      if (state.index >= path[color].length - 1) {
        state.index = path[color].length - 1;
        state.finished = true;
        justFinishedNow = true;
      }

      if (ADVANCE_FROM_HOME_ON_SIX) {
        let s = steps - 1;
        while (s-- > 0 && !state.finished) {
          state.index++;
          if (state.index >= path[color].length - 1) {
            state.index = path[color].length - 1;
            state.finished = true;
            justFinishedNow = true;
          }
          await animateToIndex(state.index);
        }
      }
    }

    // --- CASE 2: Normal movement
    else {
      if (state.inHome || state.index === -1) {
        el.style.zIndex = 20;
        resolve({ ok: false, reason: 'need a 6 to leave home' });
        return;
      }

      let s = steps;
      while (s-- > 0 && !state.finished) {
        state.index++;
        if (state.index >= path[color].length - 1) {
          state.index = path[color].length - 1;
          state.finished = true;
          justFinishedNow = true;
        }
        await animateToIndex(state.index);
      }
    }

    el.style.zIndex = 20;

    if (typeof handleCaptures === 'function') {
      handleCaptures(tokenKey);
    }

    if (justFinishedNow) {
      playSound("snd-home");
    }

    resolve({
      ok: true,
      token: tokenKey,
      pos: path[color][state.index],
      finished: justFinishedNow
    });
  });
}


  /* ---------------------------
     Selection UI
     --------------------------- */
  function markTokenSelectable(el){
    if (!el) return;
    el.classList.add('selectable');
    el.style.boxShadow = '0 0 0 6px rgba(255,215,0,0.12), 0 6px 18px rgba(0,0,0,0.45)';
    el.style.cursor = 'pointer';
    el.style.transform = 'scale(1.15)';
    el.style.zIndex = 60;
  }
  function unmarkTokenSelectable(el){
    if (!el) return;
    el.classList.remove('selectable');
    el.style.boxShadow = '';
    el.style.cursor = '';
    el.style.transform = '';
    el.style.zIndex = 20;
    el.onclick = null;
  }
  function clearAllSelectable(){
    qsa('.token.selectable').forEach(function(el){ unmarkTokenSelectable(el); });
    qsa('.token').forEach(function(el){ el.onclick = null; });
  }


// keep track of winners globally
// use global winners array safely
// Declare at the top of your script (only once!)
var winners = [];  

function declareWinner(color) {
  console.log(">>> DECLARE WINNER CALLED for", color);

  // prevent duplicate entries
  if (winners.includes(color)) return;

  playSound("snd-win");
  winners.push(color);

  // ✅ Create crown/position tag
  showWinnerTag(color, winners.length);

  let totalPlayers = active.length;   // number of players in current match
  let currentRank = winners.length;   // how many have finished so far

  // === Game-ending conditions ===
  if (
    (totalPlayers === 2 && currentRank === 1) ||   // 2-player → stop at 1 winner
    (totalPlayers === 3 && currentRank === 2) ||   // 3-player → stop at 2nd winner
    (totalPlayers === 4 && currentRank === 3)      // 4-player → stop at 3rd winner
  ) {
    endGame();
  }
}

function showWinnerTag(color, rank) {
  console.log("Placing crown for", color, "rank", rank);

  // target the home area
  let home = qs('.home.g-' + color);
  if (!home) {
    console.warn("Home not found for", color);
    return;
  }

  let img = document.createElement("img");
  if (rank === 1) { img.src = "../img/win1.png"; img.alt = "1st Place"; }
  if (rank === 2) { img.src = "../img/win2.png"; img.alt = "2nd Place"; }
  if (rank === 3) { img.src = "../img/win3.png"; img.alt = "3rd Place"; }

  img.classList.add("winner-crown");
  home.appendChild(img);
}

// End game overlay
function endGame() {
  console.log(">>> GAME OVER. Winners:", winners);
  playSound("snd-over");
  window.__ludo_game_over = true;

  // Disable all dice
  qsa('.dice-wrap').forEach(function(btn){
    btn.classList.add('disabled');
    btn.classList.remove('active-turn');
    btn.setAttribute('aria-disabled','true');
  });

  // Overlay
  var overlay = document.createElement('div');
  overlay.style.position = 'fixed';
  overlay.style.top = 0;
  overlay.style.left = 0;
  overlay.style.width = '100%';
  overlay.style.height = '100%';
  overlay.style.background = 'rgba(0,0,0,0.85)';
  overlay.style.display = 'flex';
  overlay.style.flexDirection = 'column';
  overlay.style.alignItems = 'center';
  overlay.style.justifyContent = 'center';
  overlay.style.zIndex = 9999;
  overlay.style.color = '#fff';
  overlay.style.fontSize = '2rem';
  overlay.style.fontWeight = 'bold';

  overlay.innerHTML = `
    <div style="margin-bottom: 24px;">🏆 Game Over 🏆</div>
    <div style="margin-bottom: 20px;">Results:<br>${winners.map((c,i)=>`${i+1}. ${c.toUpperCase()}`).join("<br>")}</div>
    <div>
      <button onclick="window.location.href='../dashboard.php'" 
              style="margin:10px;padding:12px 24px;font-size:1.2rem;border:none;border-radius:10px;cursor:pointer;background:#FFD700;color:#000;font-weight:bold;">
        Home
      </button>
      <button onclick="window.location.reload()" 
              style="margin:10px;padding:12px 24px;font-size:1.2rem;border:none;border-radius:10px;cursor:pointer;background:#28a745;color:#fff;font-weight:bold;">
        Re-match
      </button>
    </div>
  `;
  document.body.appendChild(overlay);

}

function declareWinnerPlacement(color, place) {
  var house = qs('.house.g-' + color);
  if (!house) return;

  var tag = document.createElement('div');
  tag.className = 'winner-tag';
  tag.innerText = place === 1 ? "🥇 1st" :
                  place === 2 ? "🥈 2nd" :
                  place === 3 ? "🥉 3rd" :
                  place + "th";

  tag.style.position = "absolute";
  tag.style.top = "50%";
  tag.style.left = "50%";
  tag.style.transform = "translate(-50%, -50%)";
  tag.style.fontSize = "2rem";
  tag.style.fontWeight = "bold";
  tag.style.color = "#fff";
  tag.style.background = "rgba(0,0,0,0.6)";
  tag.style.padding = "8px 16px";
  tag.style.borderRadius = "12px";
  tag.style.textAlign = "center";
  tag.style.zIndex = 2000;

  house.appendChild(tag);
}
  /* ---------------------------
     Turn & dice logic
     --------------------------- */
  var turnIdx = 0;
  var sixStreak = { yellow:0, red:0, blue:0, green:0 };

function refreshDiceEnabled(){
  
  if (window.__ludo_game_over) return;

  qsa('.dice-wrap').forEach(function(btn){
    btn.classList.add('disabled');
    btn.classList.remove('active-turn');
    btn.setAttribute('aria-disabled','true');
  });

  var color = active[turnIdx];
  var btn = qs('.dice-wrap.g-' + color);
  if (btn){
    btn.classList.remove('disabled');
    btn.classList.add('active-turn');
    btn.removeAttribute('aria-disabled');
    try { btn.focus({ preventScroll:true }); } catch(e){}
  }
}

function finalRollWithRule(color){
    var result = getDiceRoll();

    if (result === 6) {
        if (sixStreak[color] >= 2) {
            // Already had 2 sixes → force different number
            result = 1 + Math.floor(Math.random() * 5); // 1–5 only
            sixStreak[color] = 0;
        } else {
            sixStreak[color]++;
        }
    } else {
        sixStreak[color] = 0;
    }
    return result;
}

function onDiceRolled(color, roll) {
  console.log('[onDiceRolled] color=', color, 'roll=', roll, 'turnIdx=', turnIdx, 'active=', active);
  clearAllSelectable();
  var movable = getMovableTokensForColor(color, roll);
  console.log('[onDiceRolled] movable tokens:', movable);
  // No moves possible -> next player's turn
  if (movable.length === 0) {
    setTimeout(function () { nextTurn(false); }, 180);
    return;
  }

  // Case: only one move available
  if (movable.length === 1) {
    var single = movable[0];
    moveTokenByKeyWithAnimation(single, roll).then(function (result) {
      console.log('[onDiceRolled] move result:', result);
      if (result && result.finished) {
        // ✅ extra turn only here
       
        checkWinner(color);
        refreshDiceEnabled(); // stay same player
      } else {
        nextTurn(roll === 6 || extraTurn);
      }
    }).catch(function (err) {
      console.error('[onDiceRolled] error', err);
      nextTurn(roll === 6 || extraTurn);
    });
    return;
  }

  // Case: multiple choices -> let player click
  movable.forEach(function (tokenKey) {
    var el = qs('.token.' + tokenKey);
    if (!el) return;
    markTokenSelectable(el);
    el.onclick = function () {
      clearAllSelectable();
      moveTokenByKeyWithAnimation(tokenKey, roll).then(function (result) {
        console.log('[onDiceRolled] chosen result:', result);
        if (result && result.finished) {
          extraTurn = true;
          extraTurnGivenForMove = true;
          checkWinner(color);
          refreshDiceEnabled(); // same player continues
        } else {
          nextTurn(roll === 6 || extraTurn);
        }
      }).catch(function (err) {
        console.error('[onDiceRolled] user move error', err);
        nextTurn(roll === 6 || extraTurn);
      });
    };
  });
}

     /*Dice animation wrapper (keeps your visuals)
     --------------------------- */
function rollAnimated(color){
  var wrap = qs('.dice-wrap.g-' + color);
  var cube = wrap ? wrap.querySelector('.dice') : null;
  if (!wrap || !cube || wrap.classList.contains('disabled')) return;

  playSound("snd-dice");
  // disable all dice while animating
  qsa('.dice-wrap').forEach(function(b){
    b.classList.add('disabled');
    b.classList.remove('active-turn');
  });
  var final = finalRollWithRule(color);
  // varied random rotations so animation looks different each time
  var randX = Math.floor(Math.random() * 6) + 2 + (Date.now() % 3);
  var randY = Math.floor(Math.random() * 6) + 2 + ((Date.now() >> 3) % 4);
  var xRot = randX * 360;
  var yRot = randY * 360;

  switch(final){
    case 1: xRot +=   0; yRot +=   0;  break;
    case 2: xRot +=   0; yRot += 180;  break;
    case 3: xRot +=   0; yRot +=  90;  break;
    case 4: xRot +=   0; yRot += -90;  break;
    case 5: xRot += -90; yRot +=   0;  break;
    case 6: xRot +=  90; yRot +=   0;  break;
  }

  // smooth animation
  cube.style.transition = 'transform 1100ms cubic-bezier(.2,.8,.2,1)';
  void cube.offsetWidth; // force reflow
  cube.style.transform = 'rotateX(' + xRot + 'deg) rotateY(' + yRot + 'deg)';

  setTimeout(function(){
    console.log(color + " rolled " + final);
    onDiceRolled(color, final);
  }, 1150);
}

  /* ---------------------------
     Event binding and initialization
     --------------------------- */
  order.forEach(function(color){
    var btn = qs('.dice-wrap.g-' + color);
    if (btn){
      btn.addEventListener('click', function(){ rollAnimated(color); });
      btn.addEventListener('keydown', function(e){
        if (e.key === 'Enter' || e.key === ' '){
          e.preventDefault();
          rollAnimated(color);
        }
      });
    }
  });

  function applyPlayers(){
    order.forEach(function(color){
      var show = isActiveColor(color);
      qsa('.avatar.g-' + color).forEach(function(el){ el.classList.toggle('hidden', !show); });
      qsa('.token.g-'  + color).forEach(function(el){ el.classList.toggle('hidden', !show); });
      qsa('.dice-wrap.g-' + color).forEach(function(el){ el.classList.toggle('hidden', !show); });
    });
    placeAllTokensFromState();
  }
  applyPlayers();
  playSound("snd-start");
  refreshDiceEnabled();

  // debug helper: draw small markers at each path position (uncomment if you want to visually verify)
  function debugDrawPath() {
    var wrap = qs('.board-wrap');
    if (!wrap) return;
    // remove previous markers
    qsa('.__path_marker').forEach(function(m){ m.parentNode.removeChild(m); });
    Object.keys(path).forEach(function(c){
      path[c].forEach(function(p,i){
        var m = document.createElement('div');
        m.className = '__path_marker';
        m.style.position = 'absolute';
        m.style.left = p.left;
        m.style.top = p.top;
        m.style.transform = 'translate(-50%,-50%)';
        m.style.width = '10px';
        m.style.height = '10px';
        m.style.borderRadius = '50%';
        m.style.background = c;
        m.style.opacity = '0.85';
        m.style.zIndex = 1000;
        m.title = c + ' [' + i + ']';
        wrap.appendChild(m);
      });
    });
  }
  // expose debug + state to console
  window.__ludo = {
    tokenStates: tokenStates,
    path: path,
    safePositions: Array.from(safePositions),
    moveTokenByKey: moveTokenByKey,
    sendTokenHome: sendTokenHome,
    placeAllTokensFromState: placeAllTokensFromState,
    debugDrawPath: debugDrawPath
  };

function isStackedSafe(color, index){
  var count = 0;
  for (var key in tokenStates){
    var st = tokenStates[key];
    if (!st || st.inHome || st.finished) continue;
    var c = key.replace(/[0-9]/g,'');
    if (c !== color) continue;
    if (st.index === index) count++;
  }
  return count >= 2; // two or more of same color at same index → safe
}
/////// test ////
// ---------- Paste/replace these definitions (grantExtraTurn + nextTurn + helper) ----------

// ensure flags exist (don't clobber if already present)
if (typeof extraTurn === 'undefined') var extraTurn = false;
if (typeof extraTurnGivenForMove === 'undefined') var extraTurnGivenForMove = false;

// helper: true when all 4 tokens of that color are finished
function isPlayerCompletelyFinished(color) {
  for (var i = 1; i <= 4; i++) {
    var st = tokenStates[color + i];
    // if a token state is missing, treat as not finished (defensive)
    if (!st || !st.finished) return false;
  }
  return true; // all 4 tokens finished
}

/**
 * Centralized extra-turn granting.
 * - idempotent per move (extraTurnGivenForMove).
 * - reason is optional (e.g. "home" or "capture") — used only for logging.
 */
function grantExtraTurn(color, reason) {
  if (window.__ludo_game_over) return;

  console.trace("grantExtraTurn called:", color, reason);

  // already granted for this move -> skip
  if (extraTurnGivenForMove) {
    console.log("grantExtraTurn: already granted for this move - skipping. color=", color, "reason=", reason);
    return;
  }

  console.log("grantExtraTurn: granting extra turn for", color, " reason=", reason);
  extraTurn = true;
  extraTurnGivenForMove = true;
}

/**
 * nextTurn(keepFromRoll)
 * - keepFromRoll: boolean -> if true keep same player (e.g. rolled a 6 OR extraTurn was true)
 * Behavior:
 * - If keepFromRoll or extraTurn -> allow same player again, consume extraTurn and reset per-move guard.
 * - Otherwise pick next player who still has tokens left. If none found -> end game.
 * - Always call refreshDiceEnabled() so dice become clickable (no stuck dice).
 */
function nextTurn(keepFromRoll) {
  // stop if game over
  if (window.__ludo_game_over) return;

  clearAllSelectable();

  keepFromRoll = !!keepFromRoll; // coerce

  // If an extra turn was requested OR caller wants to keep (e.g. rolled 6),
  // keep same player. IMPORTANT: always re-enable dice before returning.
  if (keepFromRoll || extraTurn) {
    console.log("nextTurn: keeping same player =>", active[turnIdx], "keepFromRoll:", keepFromRoll, "extraTurn:", extraTurn);

    // consume the extra-turn (so it doesn't persist forever)
    extraTurn = false;

    // allow extra-turns to be granted again in a subsequent move
    extraTurnGivenForMove = false;

    // re-enable the correct dice for the current player
    refreshDiceEnabled();
    return;
  }

  // Otherwise, advance to next player who still has tokens (skip finished players).
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

    // skip players who have completed all their tokens
    if (isPlayerCompletelyFinished(col)) {
      continue;
    }

    // choose this player
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

  // Reset flags for the new player's upcoming move
  extraTurn = false;
  extraTurnGivenForMove = false;

  // enable the dice for the selected player
  refreshDiceEnabled();
}
///////test end////



function adjustStacking(){
  var positions = {};
  for (var key in tokenStates){
    var st = tokenStates[key];
    if (!st || st.inHome || st.finished) continue;
    var color = key.replace(/[0-9]/g,'');
    var pos = path[color][st.index];
    var id = pos.left + "_" + pos.top + "_" + color;
    if (!positions[id]) positions[id] = [];
    positions[id].push(key);
  }
  // apply transforms
  for (var id in positions){
    var tokens = positions[id];
    tokens.forEach(k => {
      var el = qs('.token.'+k);
      if (el){
        if (tokens.length >= 2){
          el.style.transform = "scale(0.7)";
        } else {
          el.style.transform = "scale(1)";
        }
      }
    });
  }
}
var winners = [];
function checkWinner(color){
  // if already winner, skip
  if (winners.includes(color)) return false;
  var finishedCount = 0;
  for (var key in tokenStates){
    var state = tokenStates[key];
    if (!state) continue;
    var c = key.replace(/[0-9]/g,'');
    if (c !== color) continue;
      if (state.finished) {
        finishedCount++;
      }
  }
  if (finishedCount === 4 && !winners.includes(color)) {
  winners.push(color);
  console.log("Winner detected:", color, "position =", winners.length);
  declareWinnerPlacement(color, winners.length);
  // Remove from active turns
  active = active.filter(c => c !== color);
  // End game when only one player remains
  if (active.length <= 1) {
    console.log(">>> Game Over (all placements decided)");
    window.__ludo_game_over = true;
  }
  return true;
}
  return false;
}

// use global winners array safely
window.winners = window.winners || [];

/* ===========================
   WINNER BADGE / RANK TAGS
   =========================== */

window.winners = window.winners || [];
var winners = window.winners;

function getHouseEl(color){
  return document.querySelector('.overlay.avatar.g-' + color) || document.querySelector('.board-wrap');
}

// inject minimal styles once
(function ensureWinnerStyles(){
  if (document.getElementById('winner-badge-styles')) return;
  const style = document.createElement('style');
  style.id = 'winner-badge-styles';
  style.textContent = `
    .winner-badge {
      position: absolute;
      top: -12px;
      right: -12px;
      z-index: 2000;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 6px 12px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 13px;
      background: #ffd700;
      color: #000;
      border: 2px solid rgba(0,0,0,.25);
      box-shadow: 0 6px 18px rgba(0,0,0,.35);
      user-select: none;
      pointer-events: none;
    }
  `;
  document.head.appendChild(style);
})();

/**
 * Show badge text on the winner's house:
 * - 2P → "👑 WINNER"
 * - 3P → "Winner 1", "Winner 2"
 * - 4P → "Winner 1", "Winner 2", "Winner 3"
 */
function showWinnerTag(color, rank) {
  let house = qs('.home.g-' + color);  // ⬅️ Attach to home/start box
  console.log(">>> showWinnerTag for", color, "rank", rank, "house=", house);

  if (!house) {
    console.warn("❌ No .home element found for", color);
    return;
  }

  let img = document.createElement("img");
  if (active.length === 2) {
    img.src = "../img/win1.png";
    img.alt = "Winner";
  } else {
    if (rank === 1) { img.src = "../img/win1.png"; img.alt = "1st Place"; }
    if (rank === 2) { img.src = "../img/win2.png"; img.alt = "2nd Place"; }
    if (rank === 3) { img.src = "../img/win3.png"; img.alt = "3rd Place"; }
  }

  img.style.position = "absolute";
  img.style.width = "60px";
  img.style.height = "60px";
  img.style.zIndex = 2000;
  img.style.top = "10px";    // Position inside box (adjust if needed)
  img.style.left = "10px";

  house.appendChild(img);
  console.log("✅ Badge appended to", color, "home");
}


/* ===========================
   DECLARE WINNER + END GAME
   =========================== */

function declareWinner(color) {
  console.log(">>> DECLARE WINNER CALLED for", color);
  if (winners.includes(color)) return; // prevent duplicates

  playSound("snd-win");
  winners.push(color);

  // show badge
  showWinnerTag(color, winners.length);

  // 🔍 Debugging log
  console.log(">>> showWinnerTag called for", color, "rank", winners.length);

  const totalPlayers = active.length;
  const currentRank = winners.length;

  // End conditions by lobby size
  const shouldEnd =
    (totalPlayers === 2 && currentRank === 1) ||   // 2P → stop at first winner
    (totalPlayers === 3 && currentRank === 2) ||   // 3P → stop at second winner
    (totalPlayers === 4 && currentRank === 3);     // 4P → stop at third winner

  if (shouldEnd) endGame();
}

// ---------- Replace your existing endGame() with this ----------
function endGame() {
  if (window.__ludo_game_over) return;
  window.__ludo_game_over = true;

  console.log(">>> GAME OVER. Winners:", winners);
  playSound("snd-over");

  // disable dice UI
  qsa('.dice-wrap').forEach(function(btn){
    btn.classList.add('disabled');
    btn.classList.remove('active-turn');
    btn.setAttribute('aria-disabled','true');
  });

  // Make sure only ACTIVE players' UI remains visible (hide everything else)
  const allColors = ['blue','yellow','red','green'];
  allColors.forEach(c => {
    const show = isActiveColor(c);
    // tokens, dice, avatars, homes
    qsa('.token.g-' + c).forEach(el => el.classList.toggle('hidden', !show));
    qsa('.dice-wrap.g-' + c).forEach(el => el.classList.toggle('hidden', !show));
    qsa('.avatar.g-' + c).forEach(el => el.classList.toggle('hidden', !show));
    qsa('.home.g-' + c).forEach(el => el.classList.toggle('hidden', !show));
  });

  // Build cleaned winners array limited to active players and unique
  const filteredWinners = (Array.isArray(winners) ? winners : [])
    .filter((c, idx, arr) => isActiveColor(c) && arr.indexOf(c) === idx);

  // Build finalResults: winners (in order) then remaining active players
  const finalResults = filteredWinners.map((c, i) => ({ color: c, rank: i + 1 }));
  active.forEach(c => {
    if (!finalResults.find(r => r.color === c)) {
      finalResults.push({ color: c, rank: finalResults.length + 1 });
    }
  });

  // Call the modal helper (if exists) or create a fallback modal
  if (typeof showGameOverScreen === 'function') {
    try {
      showGameOverScreen(finalResults);
    } catch (err) {
      console.error("showGameOverScreen error:", err);
      // fallback to inline modal below
      _fallbackGameOverModal(finalResults);
    }
  } else {
    _fallbackGameOverModal(finalResults);
  }
}

// Small fallback modal creator (used if showGameOverScreen isn't present or fails)
function _fallbackGameOverModal(results) {
  // Remove existing fallback if any
  let existing = document.getElementById('__ludo_game_over_modal');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = '__ludo_game_over_modal';
  modal.style.position = 'fixed';
  modal.style.top = 0;
  modal.style.left = 0;
  modal.style.width = '100%';
  modal.style.height = '100%';
  modal.style.display = 'flex';
  modal.style.alignItems = 'center';
  modal.style.justifyContent = 'center';
  modal.style.background = 'rgba(0,0,0,0.8)';
  modal.style.zIndex = 99999;

  const inner = document.createElement('div');
  inner.style.width = '340px';
  inner.style.background = '#fff';
  inner.style.color = '#000';
  inner.style.padding = '18px';
  inner.style.borderRadius = '12px';
  inner.style.textAlign = 'center';
  inner.innerHTML = `<h2 style="margin-top:0;">🏆 Game Over</h2>`;

  const list = document.createElement('div');
  list.style.margin = '12px 0';
  list.innerHTML = results
    .map(r => `<div style="margin:6px 0; font-weight:700;">${r.rank}. ${r.color.toUpperCase()}</div>`)
    .join('');
  inner.appendChild(list);

  const actions = document.createElement('div');
  actions.style.marginTop = '10px';
  actions.innerHTML = `
    <button id="__ludo_go_dashboard" style="margin-right:8px;padding:8px 14px;border-radius:8px;border:none;cursor:pointer;background:#FFD700;font-weight:700;">Dashboard</button>
    <button id="__ludo_rematch" style="padding:8px 14px;border-radius:8px;border:none;cursor:pointer;background:#28a745;color:#fff;font-weight:700;">Rematch</button>
  `;
  inner.appendChild(actions);
  modal.appendChild(inner);
  document.body.appendChild(modal);

  document.getElementById('__ludo_go_dashboard').addEventListener('click', function(){ window.location.href = '../dashboard.php'; });
  document.getElementById('__ludo_rematch').addEventListener('click', function(){ location.reload(); });
}

/*function declareWinnerPlacement(color, position) {
  let placeText = "";
  if (position === 1) placeText = "1st";
  else if (position === 2) placeText = "2nd";
  else if (position === 3) placeText = "3rd";
  else placeText = position + "th";
  playSound("snd-win");
  var overlay = document.createElement('div');
  overlay.style.position = 'fixed';
  overlay.style.top = '10%';
  overlay.style.left = '50%';
  overlay.style.transform = 'translateX(-50%)';
  overlay.style.background = '#222';
  overlay.style.color = '#fff';
  overlay.style.padding = '12px 24px';
  overlay.style.borderRadius = '12px';
  overlay.style.fontSize = '1.5rem';
  overlay.style.zIndex = 9999;
  overlay.innerText = color.toUpperCase() + " finished " + placeText + "!";
  document.body.appendChild(overlay);
  setTimeout(()=>{ overlay.remove(); }, 3000);
}*/ 

})();

window.addEventListener("DOMContentLoaded", () => {
  initStepSound();
});

function showGameOverScreen(results) {
  let modal = document.getElementById("game-over-modal");
  let container = document.getElementById("final-positions");
  container.innerHTML = results
    .map(r => `<p style="margin:6px 0;">${r.rank}️⃣  ${r.color.toUpperCase()}</p>`)
    .join("");
  modal.style.display = "flex";
}


</script>
</body>
</html>
