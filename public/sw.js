const CACHE = 'ludo-static-v1';
const ASSETS = [
  '/ludo/public/index.php',
  '/ludo/public/css/style.css',
  '/ludo/public/js/app.js'
];
self.addEventListener('install', e=>{
  e.waitUntil(caches.open(CACHE).then(c=>c.addAll(ASSETS)));
});
self.addEventListener('fetch', e=>{
  e.respondWith(
    caches.match(e.request).then(r=> r || fetch(e.request))
  );
});
