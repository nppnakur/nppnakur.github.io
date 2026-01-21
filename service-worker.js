const CACHE_NAME = 'nagar-palika-cache-v1';
const urlsToCache = [
  '/',
  '/index.html',
  '/icon-192.png',
  '/icon-512.png',
  '/apple-touch-icon.png'
];

// इंस्टॉल करने पर
self.addEventListener('install', event => {
  console.log('Service Worker इंस्टॉल हो रहा है');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('फाइलें कैश हो रही हैं');
        return cache.addAll(urlsToCache);
      })
      .then(() => self.skipWaiting())
  );
});

// एक्टिवेट होने पर
self.addEventListener('activate', event => {
  console.log('Service Worker एक्टिवेट हुआ');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME) {
            console.log('पुराना कैश डिलीट हो रहा है:', cache);
            return caches.delete(cache);
          }
        })
      );
    })
    .then(() => self.clients.claim())
  );
});

// रिक्वेस्ट इंटरसेप्ट
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(response => {
        if (response) {
          return response;
        }
        return fetch(event.request)
          .catch(() => {
            // ऑफलाइन होने पर फॉलबैक
            if (event.request.url.indexOf('.html') > -1) {
              return caches.match('/');
            }
          });
      })
  );
});
