/* TT Team Planner — Service Worker
 * Strategy:
 *   - App shell (CSS/JS): cache-first
 *   - WP REST API (/wp-json/monclubtt-equipes/v1/*): network-first, fallback to cache
 *   - Everything else: network-first, no cache
 */

var CACHE_SHELL  = 'monclubtt-ge-shell-v1';
var CACHE_API    = 'monclubtt-ge-api-v1';

// Chemin du plugin, passé par PHP à l'enregistrement : le SW a désormais un
// scope global, il ne doit mettre en cache que ses propres assets.
var PLUGIN_BASE = new URL(self.location.href).searchParams.get('base')
  || '/wp-content/plugins/mon-club-tt/modules/gestion-equipes/';

var SHELL_ASSETS = [
  // Populated at install time — the plugin injects the real URLs via wp_localize_script
  // so we keep this list minimal and rely on runtime caching.
];

// ─── Install ───────────────────────────────
self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(CACHE_SHELL).then(function (cache) {
      return cache.addAll(SHELL_ASSETS);
    }).then(function () {
      return self.skipWaiting();
    })
  );
});

// ─── Activate ──────────────────────────────
self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(
        keys.filter(function (k) {
          return k !== CACHE_SHELL && k !== CACHE_API;
        }).map(function (k) {
          return caches.delete(k);
        })
      );
    }).then(function () {
      return self.clients.claim();
    })
  );
});

// ─── Fetch ─────────────────────────────────
self.addEventListener('fetch', function (e) {
  var url = new URL(e.request.url);

  // REST API — network-first, cache on success
  if (url.pathname.includes('/wp-json/monclubtt-equipes/v1/')) {
    if (e.request.method !== 'GET') return; // don't cache mutations
    e.respondWith(networkFirstCache(e.request, CACHE_API));
    return;
  }

  // Static assets (CSS/JS from this plugin) — cache-first
  if (url.pathname.startsWith(PLUGIN_BASE) &&
      (url.pathname.endsWith('.css') || url.pathname.endsWith('.js'))) {
    e.respondWith(cacheFirst(e.request, CACHE_SHELL));
    return;
  }

  // Default — network only (don't interfere with WordPress admin or other pages)
});

function cacheFirst(request, cacheName) {
  return caches.match(request).then(function (cached) {
    if (cached) return cached;
    return fetch(request).then(function (response) {
      if (response.ok) {
        var clone = response.clone();
        caches.open(cacheName).then(function (cache) { cache.put(request, clone); });
      }
      return response;
    });
  });
}

function networkFirstCache(request, cacheName) {
  return fetch(request).then(function (response) {
    if (response.ok) {
      var clone = response.clone();
      caches.open(cacheName).then(function (cache) { cache.put(request, clone); });
    }
    return response;
  }).catch(function () {
    return caches.match(request).then(function (cached) {
      return cached || new Response(
        JSON.stringify({ error: 'offline', message: 'Données indisponibles hors ligne.' }),
        { status: 503, headers: { 'Content-Type': 'application/json' } }
      );
    });
  });
}
