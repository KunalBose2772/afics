const CACHE_NAME = 'documantraa-v3';

const OFFLINE_URL = './index.php';

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.addAll([
        OFFLINE_URL,
        './assets/images/documantraa_logo.png'
      ]);
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    })
  );
  self.clients.claim();
});

self.addEventListener('fetch', function(event) {
  if (event.request.method !== 'GET') {
    return;
  }

  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(function() {
        return caches.match(OFFLINE_URL);
      })
    );
  } else {
    event.respondWith(
      caches.match(event.request).then(function(response) {
        if (response) {
          return response;
        }

        return fetch(event.request).catch(function() {
          // Avoid unhandled promise rejections for transient network failures.
          return new Response('', { status: 503, statusText: 'Offline' });
        });
      })
    );
  }
});
