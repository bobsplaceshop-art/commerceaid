/*
 * Anaya Global - service worker.
 *
 * Hand-written, no build step. Cache namespacing by version only
 * (store namespacing is added when the SW can read the store id
 * from the URL; Phase 3 will inject it).
 */

const CACHE_VERSION = 'v1';
const CACHE_NAME = `anaya-${CACHE_VERSION}`;
const PRECACHE_URLS = [
  '/',
  '/assets/css/app.css',
  '/assets/js/app.js',
  '/assets/js/notifications.js',
  '/offline',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => cache.addAll(PRECACHE_URLS))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Never cache API or authenticated routes.
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/account/')) {
    return;
  }

  // Network-first for navigation, cache-first for static assets.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() => caches.match('/offline'))
    );
    return;
  }

  event.respondWith(
    caches.match(req).then((cached) => cached || fetch(req))
  );
});
