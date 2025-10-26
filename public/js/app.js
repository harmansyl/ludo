(function(){

// Simple POST helper without CSRF
async function jpost(url, body) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    credentials: 'include',   // send PHPSESSID cookie
    body: JSON.stringify(body || {})
  });
  return res.json();
}

// ------------------- Index -------------------
const btnLogin = document.getElementById('btnLogin');
if (btnLogin) btnLogin.onclick = async () => {
  const r = await jpost('../api/login.php', {username: v('l_user'), password: v('l_pass')});
  if (r.ok) location.href = 'dashboard.php'; else alert(r.error || 'Login failed');
};

const btnRegister = document.getElementById('btnRegister');
if (btnRegister) btnRegister.onclick = async () => {
  console.log("📌 Register button clicked");
  const r = await jpost('../api/register.php', {username: v('r_user'), phone: v('r_phone'), password: v('r_pass')});
  console.log("📌 Server response:", r);
  if (r.ok) location.href = 'dashboard.php'; else alert(r.error || 'Registration failed');
};

// ------------------- Dashboard -------------------
q('#logout', (el)=> el.onclick = async ()=>{ await jpost('../api/logout.php',{}); location.href='index.php'; });
q('#createRoom', (el)=> el.onclick = async ()=>{
  const r = await jpost('../api/create_room.php', { mode: 'ONLINE' });
  if (r.ok) location.href = `game.php?mode=ONLINE&room=${r.code}`; else alert(r.error);
});
q('#joinRoom', (el)=> el.onclick = async ()=>{
  const code = v('roomCode');
  const r = await jpost('../api/join_room.php', { code });
  if (r.ok) location.href = `game.php?mode=ONLINE&room=${code}`; else alert(r.error);
});

// ------------------- Admin -------------------
q('#btnCreateTournament', (el)=> el.onclick = async ()=>{
  const r = await jpost('../api/tournament_bracket.php', { action:'create', name: v('t_name') });
  alert(r.ok ? `Created T#${r.tournament_id}` : r.error);
});

// ------------------- Tournament -------------------
q('#btnTJoin', (el)=> el.onclick = async ()=>{
  const r = await jpost('../api/tournament_enter.php', {
    bucket: v('bucket'),
    username: v('t_user'),
    phone: v('t_phone'),
    special_key: v('t_key')
  });
  alert(r.ok ? 'Joined!' : r.error);
});
q('#btnLoadBracket', (el)=> el.onclick = async ()=>{
  const r = await jpost('../api/tournament_bracket.php', { action:'view' });
  if (!r.ok) return alert(r.error);
  byId('bracket').textContent = JSON.stringify(r.bracket, null, 2);
});

// ------------------- Game page -------------------
if (window.__LUDO__) {
  const mode = window.__LUDO__.mode;
  const room = window.__LUDO__.room;

  if (mode === 'LOCAL') {
    jpost('../api/create_room.php', { mode: 'LOCAL' }).then(r=>{
      if (r.ok) location.href=`game.php?mode=ONLINE&room=${r.code}`;
      else alert(r.error);
    });
  } else {
    const adminPanel = byId('adminPanel');
    const btnRoll = byId('btnRoll');
    const btnMove = byId('btnMove');
    const lastDice = byId('lastDice');
    const who = byId('who');

    btnRoll.onclick = async ()=>{
      const r = await jpost('../api/roll_dice.php', { room: room });
      if (!r.ok) return alert(r.error);
      lastDice.textContent = `Dice: ${r.dice}`;
    };

    btnMove.onclick = async ()=>{
      const r = await jpost('../api/make_move.php', {
        room: room,
        tokenIndex: parseInt(v('tokenIdx'),10)
      });
      if (!r.ok) return alert(r.error);
    };

    q('#btnForceDice', (el)=> el.onclick = async ()=>{
      const r = await jpost('../api/set_next_dice.php', {
        room: room,
        dice: parseInt(v('forceDice'),10)
      });
      if (!r.ok) alert(r.error); else alert('Next dice set for this turn.');
    });

    async function poll(){
      const r = await jpost('../api/poll_game.php', { room: room });
      if (r.ok) {
        who.textContent = `Turn: ${r.turnUser}`;
        lastDice.textContent = r.lastDice ? `Dice: ${r.lastDice}` : '';
        if (r.adminControls && r.adminControls.mayOverride) {
          adminPanel.classList.remove('hidden');
        } else {
          adminPanel.classList.add('hidden');
        }
      }
      setTimeout(poll, 1000);
    }
    poll();
  }
}

// ------------------- Helpers -------------------
function q(sel, fn){ const el = document.querySelector(sel); if (el) fn(el); }
function byId(id){ return document.getElementById(id); }
function v(id){ const el = byId(id); return el ? el.value.trim() : ''; }

})();
