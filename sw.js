// Waiter POS — service worker: offline režim (keš ljuske + fallback na offline kasu)
const CACHE = 'waiter-pos-v3';
const PRECACHE = [
  '/offline-pos',
  '/assets/css/app.css',
  '/assets/js/ui.js',
  '/assets/js/offline.js',
  '/assets/icon.svg',
  '/img/w_logo_color.png',
  '/img/w_logo_white.png',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(PRECACHE)));
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return;                       // POST/AJAX ne diramo
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;             // samo naš domen

  // Navigacije (stranice): mreža prvo → keš → offline kasa
  if (req.mode === 'navigate') {
    e.respondWith(
      fetch(req).catch(() =>
        caches.match(req, { ignoreSearch: true }).then((r) => r || caches.match('/offline-pos'))
      )
    );
    return;
  }

  // Statika (css/js/slike): keš prvo, pa mreža (uz dopunu keša)
  if (/\.(css|js|svg|png|jpg|jpeg|ico)$/.test(url.pathname) || url.pathname === '/manifest.webmanifest') {
    e.respondWith(
      caches.match(req, { ignoreSearch: true }).then((hit) => {
        const net = fetch(req).then((res) => {
          if (res && res.ok) caches.open(CACHE).then((c) => c.put(req, res.clone()));
          return res;
        }).catch(() => hit);
        return hit || net;
      })
    );
    return;
  }

  // Ostalo (JSON i sl.): mreža, pa keš kao rezerva
  e.respondWith(fetch(req).catch(() => caches.match(req, { ignoreSearch: true })));
});
