let roomCode = window.ROOM_CODE || null;

async function fetchGameState() {
  if (!roomCode) return;

  try {
    const res = await fetch(`/ludo/api/game_state.php?room=${roomCode}`);
    const data = await res.json();
    if (!data.ok) {
      console.error("Game state error:", data.error);
      return;
    }
    updateBoard(data);
  } catch (err) {
    console.error("Fetch failed:", err);
  }
}

function updateBoard(state) {
  // Update tokens
  state.pieces.forEach(p => {
    const tokenClass = `.${p.color}${p.piece}`; // e.g. .red1, .blue3
    const token = document.querySelector(tokenClass);
    if (!token) return;

    // Example: home = position 0, otherwise just shift along X for now
    if (p.position === 0) {
      token.style.top = "";
      token.style.left = "";
      token.style.opacity = 1;
    } else {
      token.style.position = "absolute";
      token.style.top = (100 + p.position * 8) + "px";
      token.style.left = (200 + p.position * 8) + "px";
      token.style.opacity = 1;
    }
  });

  // Update dice
  if (state.lastDice) {
    const turnColor = state.turnColor;
    const diceWrap = document.querySelector(`.dice-wrap.g-${turnColor} .dice`);
    if (diceWrap) {
      diceWrap.setAttribute("data-roll", state.lastDice);
    }
  }
}

// Poll every 2 seconds
setInterval(fetchGameState, 2000);
fetchGameState();
