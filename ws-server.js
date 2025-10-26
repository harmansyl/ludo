const WebSocket = require('ws');
const fetch = require('node-fetch'); // v2 (npm install node-fetch@2)
const PORT = process.env.LUDO_WS_PORT ? Number(process.env.LUDO_WS_PORT) : 8080;
const API_BASE = (process.env.LUDO_API_BASE || 'http://127.0.0.1/ludo/api/').replace(/\/+$/, '/');
const POLL_MS = process.env.LUDO_POLL_MS ? Number(process.env.LUDO_POLL_MS) : 800;
const WS_KEY = process.env.LUDO_WS_KEY || 'WWY8WoivGAJD1RaLiYLmbbqPXxsMej9AhFrF6OZcPnM=';

if (!WS_KEY || WS_KEY === 'WWY8WoivGAJD1RaLiYLmbbqPXxsMej9AhFrF6OZcPnM=') {
  console.warn('Warning: LUDO_WS_KEY not set or left default. Set LUDO_WS_KEY env var for security.');
}

const wss = new WebSocket.Server({ port: PORT });
const rooms = new Map(); // room -> { clients: Set(ws), lastJson: string }

async function fetchState(room) {
  try {
    const url = `${API_BASE}game_state_ws.php?room=${encodeURIComponent(room)}&key=${encodeURIComponent(WS_KEY)}`;
    const res = await fetch(url, { timeout: 5000 });
    if (!res.ok) {
      console.error(`[fetchState] non-ok ${res.status} for room=${room}`);
      return null;
    }
    const json = await res.json();
    return json;
  } catch (err) {
    console.error('[fetchState] error for room=', room, err && err.message);
    return null;
  }
}

function broadcast(room, msg) {
  const entry = rooms.get(room);
  if (!entry) return;
  const payload = JSON.stringify(msg);
  for (const c of entry.clients) {
    if (c.readyState === WebSocket.OPEN) {
      try { c.send(payload); } catch (e) { /* ignore individual send errors */ }
    }
  }
}

async function pollRooms() {
  for (const [room, entry] of rooms.entries()) {
    try {
      const state = await fetchState(room);
      if (!state) continue;
      const s = JSON.stringify(state);
      if (entry.lastJson !== s) {
        entry.lastJson = s;
        broadcast(room, { type: 'state', data: state });
        if (state.lastColor && typeof state.lastDice !== 'undefined') {
          broadcast(room, { type: 'dice', data: { color: state.lastColor, value: state.lastDice } });
        }
        if (Array.isArray(state.players)) {
          const players = state.players.map(p => (typeof p === 'string') ? p : (p.color || p));
          broadcast(room, { type: 'players', data: players });
        }
      }
    } catch (err) {
      console.error('[pollRooms] error for room', room, err && err.message);
    }
  }
}

setInterval(pollRooms, POLL_MS);

wss.on('connection', (ws) => {
  ws.room = null;

  ws.on('message', async (raw) => {
    let msg;
    try { msg = JSON.parse(raw.toString()); } catch (e) { return; }
    if (!msg || !msg.type) return;

    if (msg.type === 'subscribe' && msg.room) {
      const room = String(msg.room);
      ws.room = room;
      if (!rooms.has(room)) rooms.set(room, { clients: new Set(), lastJson: null });
      rooms.get(room).clients.add(ws);

      // send immediate snapshot
      const state = await fetchState(room);
      if (state) {
        rooms.get(room).lastJson = JSON.stringify(state);
        try { ws.send(JSON.stringify({ type: 'state', data: state })); } catch (e) {}
      }
      return;
    }

    if (msg.type === 'ping') {
      try { ws.send(JSON.stringify({ type: 'pong' })); } catch (e) {}
      return;
    }

    // ignore other client messages by default
  });

  ws.on('close', () => {
    const r = ws.room;
    if (r && rooms.has(r)) {
      const set = rooms.get(r).clients;
      set.delete(ws);
      if (set.size === 0) rooms.delete(r);
    }
  });

  ws.on('error', (err) => {
    console.error('[ws] connection error', err && err.message);
  });

  try { ws.send(JSON.stringify({ type: 'hello', msg: 'ws-server ready' })); } catch (e) {}
});

process.on('SIGINT', () => {
  console.log('Shutting down ws-server...');
  try { wss.close(); } catch (e) {}
  process.exit();
});

console.log(`WebSocket server listening on ws://0.0.0.0:${PORT}  (API_BASE=${API_BASE}, POLL_MS=${POLL_MS})`);