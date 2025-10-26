// public/js/play.js
(async function(){
  // helper to show/hide messages
  const loadingEl = id('loading');
  const errEl     = id('error');

  window.createLocal = async function(ev) {
    const btn = ev.currentTarget;
    const players = parseInt(btn.dataset.players || '4', 10);

    errEl.style.display = 'none';
    loadingEl.style.display = 'block';
    loadingEl.textContent = 'Creating local room…';

    try {
      const res = await fetch('../api/create_room.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ mode: 'LOCAL', players })
      });
      // Some endpoints may return empty body on error — handle that gracefully
      const txt = await res.text();
      let data = {};
      try { data = txt ? JSON.parse(txt) : {}; } catch(e){ data = {}; }
      if (!res.ok) {
        loadingEl.style.display = 'none';
        errEl.style.display = 'block';
        errEl.textContent = data.error || `Server returned ${res.status}`;
        return;
      }
      if (!data.ok || !data.code) {
        loadingEl.style.display = 'none';
        errEl.style.display = 'block';
        errEl.textContent = data.error || 'Invalid response from server';
        return;
      }

      // Redirect to the existing game page (re-use your game.php)
      // some older code uses ?mode=ONLINE&room=..., we will use same pattern:
      const room = encodeURIComponent(data.code);
      location.href = `game.php?mode=ONLINE&room=${room}`;

    } catch (e) {
      loadingEl.style.display = 'none';
      errEl.style.display = 'block';
      errEl.textContent = 'Network error — please try again';
      console.error(e);
    }
  };

  function id(n){ return document.getElementById(n); }
})();
