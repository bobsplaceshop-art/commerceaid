/*
 * Anaya Global - service worker.
 *
 * Hand-written, no build step. Cache namespacing by version
 * only. Store namespacing is a future enhancement (the SW
 * would need the store id from the URL; today there is one
 * store per install).
 *
 * Cache strategy:
 *   - Navigations (HTML pages): network-first, fall back to
 *     /offline when the network fails.
 *   - Static assets (CSS, JS, fonts, icons): cache-first.
 *   - Skip: /commerce/, /account/, /api/, non-GET, cross-origin.
 *
 * Cache invalidation: bump CACHE_VERSION when any precached
 * asset changes. The activate handler deletes every cache
 * whose name does not match the current version.
 */

const CACHE_VERSION = '__COMMIT__';
const CACHE_NAME    = `anaya-${CACHE_VERSION}`;

/*
 * Every URL here is self-hosted and same-origin. The
 * Bootstrap CSS + JS still load from cdn.jsdelivr.net and
 * are deliberately excluded - the fetch handler skips
 * cross-origin requests.
 *
 * The versioned asset URLs on the HTML page carry a ?v=
 * query string. The fetch handler matches with
 * { ignoreSearch: true } so the unversioned URLs below
 * match the versioned requests.
 */
const PRECACHE_URLS = [
  // Offline page
  '/offline',

  // Self-hosted default fonts (body)
  '/assets/fonts/inter/inter-400.woff2',
  '/assets/fonts/inter/inter-500.woff2',
  '/assets/fonts/inter/inter-600.woff2',
  '/assets/fonts/inter/inter-700.woff2',

  // Self-hosted default fonts (display)
  '/assets/fonts/playfair-display/playfair-display-400.woff2',
  '/assets/fonts/playfair-display/playfair-display-500.woff2',
  '/assets/fonts/playfair-display/playfair-display-600.woff2',
  '/assets/fonts/playfair-display/playfair-display-700.woff2',

  // Self-hosted default fonts (script)
  '/assets/fonts/dancing-script/dancing-script-400.woff2',
  '/assets/fonts/dancing-script/dancing-script-600.woff2',
  '/assets/fonts/dancing-script/dancing-script-700.woff2',

  // Self-hosted icon libraries
  '/assets/bootstrap-icons/bootstrap-icons.min.css',
  '/assets/bootstrap-icons/fonts/bootstrap-icons.woff2',
  '/assets/fontawesome/css/all.min.css',
  '/assets/fontawesome/webfonts/fa-solid-900.woff2',
  '/assets/fontawesome/webfonts/fa-regular-400.woff2',
  '/assets/fontawesome/webfonts/fa-brands-400.woff2',

  // Storefront CSS
  '/assets/css/main-v2.css',
  '/assets/css/storefront.css',
  '/assets/css/storefront-home.css',
  '/assets/css/storefront-catalog.css',
  '/assets/css/storefront-product.css',
  '/assets/css/storefront-auth.css',
  '/assets/css/storefront-cart.css',
  '/assets/css/storefront-checkout.css',
  '/assets/css/storefront-search.css',

  // Storefront JS
  '/assets/js/storefront-hero.js',
  '/assets/js/wishlist.js',
  '/assets/js/product-tabs.js',
  '/assets/js/product-reviews.js',
];

/* ------------------------------------------------------------------
 * Install
 * ------------------------------------------------------------------ */
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      // Use individual add() calls so one failing URL does not
      // abort the entire precache. A 404 on a single asset
      // (e.g. a font that has not been downloaded yet) should
      // not block service worker installation.
      return Promise.all(
        PRECACHE_URLS.map((url) =>
          cache.add(url).catch((err) => {
            console.warn('[sw] precache failed for', url, err);
          })
        )
      ).then(() => {
        // Guarantee /offline is always cacheable. If the real
        // page failed to cache above (route not ready, network
        // error at install time), store a minimal inline HTML
        // fallback so the navigation handler never resolves to
        // undefined.
        return cache.match('/offline').then((existing) => {
          if (existing) return;
          const fallback = new Response(
            '<!doctype html><meta charset="utf-8">' +
            '<title>Offline</title>' +
            '<h1>You are offline</h1>' +
            '<p>Reconnect to continue shopping.</p>',
            {
              status: 503,
              statusText: 'Offline',
              headers: { 'Content-Type': 'text/html; charset=utf-8' },
            }
          );
          return cache.put('/offline', fallback);
        });
      });
    }).then(() => self.skipWaiting())
  );
});

/* ------------------------------------------------------------------
 * Activate
 * ------------------------------------------------------------------ */
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(
        keys.filter((k) => k !== CACHE_NAME).map((k) => caches.delete(k))
      )
    ).then(() => self.clients.claim())
  );
});

/* ------------------------------------------------------------------
 * Fetch
 * ------------------------------------------------------------------ */
self.addEventListener('fetch', (event) => {
  const req = event.request;

  // Only GET is cached.
  if (req.method !== 'GET') return;

  const url = new URL(req.url);

  // Same-origin only. Cross-origin (Bootstrap CDN) passes through.
  if (url.origin !== self.location.origin) return;

  // Never touch admin, account, or API routes. These are
  // dynamic and user-specific.
  if (
    url.pathname.startsWith('/commerce/') ||
    url.pathname.startsWith('/account/') ||
    url.pathname.startsWith('/api/') ||
    url.pathname.startsWith('/media/')
  ) {
    return;
  }

  // Navigation requests: network-first, fall back to /offline.
  // The chain is guaranteed to resolve to a Response object,
  // never undefined. This matters because respondWith() throws
  // "Failed to convert value to 'Response'" if given undefined,
  // which surfaces as an unhandled rejection in the console.
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req).catch(() =>
        caches.match('/offline').then((cached) => {
          if (cached) return cached;
          // Last-resort inline fallback. Reached only if the
          // install handler somehow did not cache /offline.
          return new Response(
            '<!doctype html><meta charset="utf-8">' +
            '<title>Offline</title>' +
            '<h1>You are offline</h1>' +
            '<p>Reconnect to continue shopping.</p>',
            {
              status: 503,
              statusText: 'Offline',
              headers: { 'Content-Type': 'text/html; charset=utf-8' },
            }
          );
        })
      )
    );
    return;
  }

  // Static assets: cache-first. ignoreSearch: true makes a
  // request to /assets/css/storefront.css?v=123 match the
  // cached /assets/css/storefront.css entry.
  event.respondWith(
    caches.match(req, { ignoreSearch: true }).then((cached) => {
      if (cached) return cached;
      return fetch(req)
        .then((response) => {
          // Cache successful same-origin GET responses that
          // look like static assets.
          if (
            response.ok &&
            url.pathname.startsWith('/assets/')
          ) {
            const clone = response.clone();
            caches.open(CACHE_NAME).then((cache) => cache.put(req, clone));
          }
          return response;
        })
        .catch(() => {
          // Offline and not in cache. Return an empty 504 so the
          // fetch handler settles cleanly. The browser gets a
          // real response instead of an unhandled rejection.
          return new Response('', {
            status: 504,
            statusText: 'Offline',
          });
        });
    })
  );
});
