// Waiter POS — service worker (osnovno keširanje ljuske aplikacije)
const CACHE = 'waiter-pos-v2';
const ASSETS = ['/assets/css/app.css', '/assets/icon.svg'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASSETS)));
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
  if (req.method !== 'GET') return; // POST/AJAX ne diramo

  // Statički resursi: prvo keš, pa mreža
  if (ASSETS.some((a) => req.url.endsWith(a))) {
    e.respondWith(caches.match(req).then((r) => r || fetch(req)));
    return;
  }
  // Ostalo (stranice, podaci): prvo mreža, pa keš kao rezerva
  e.respondWith(fetch(req).catch(() => caches.match(req)));
});
