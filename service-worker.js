// service-worker.js - NAGAR PALIKA NAKUR PWA
const CACHE_NAME = 'nagar-palika-v3.0';
const DYNAMIC_CACHE = 'nagar-palika-dynamic-v1';

// इंस्टॉल होने पर कैश करने वाले रिसोर्सेज
const APP_SHELL = [
  '/',
  '/index.html',
  'img1.png',
  'https://cdn.jsdelivr.net/npm/@emailjs/browser@3/dist/email.min.js'
];

// इंस्टॉल इवेंट
self.addEventListener('install', event => {
  console.log('🔄 Service Worker इंस्टॉल हो रहा है...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        console.log('📦 एप्प शैल कैश हो रही है');
        return cache.addAll(APP_SHELL);
      })
      .then(() => self.skipWaiting())
  );
});

// एक्टिवेट इवेंट
self.addEventListener('activate', event => {
  console.log('✅ Service Worker एक्टिवेट हो गया');
  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cache => {
          if (cache !== CACHE_NAME && cache !== DYNAMIC_CACHE) {
            console.log('🗑️ पुरानी कैश डिलीट हो रही है:', cache);
            return caches.delete(cache);
          }
        })
      );
    })
    .then(() => self.clients.claim())
  );
});

// फेच इवेंट - नेटवर्क फर्स्ट स्ट्रेटेजी
self.addEventListener('fetch', event => {
  // API कॉल के लिए नेटवर्क फर्स्ट
  if (event.request.url.includes('emailjs') || event.request.url.includes('api')) {
    event.respondWith(
      fetch(event.request)
        .then(response => {
          // कैश में स्टोर करें
          const responseClone = response.clone();
          caches.open(DYNAMIC_CACHE)
            .then(cache => {
              cache.put(event.request, responseClone);
            });
          return response;
        })
        .catch(() => {
          // ऑफलाइन होने पर कैश से दें
          return caches.match(event.request);
        })
    );
    return;
  }

  // अन्य रिसोर्सेज के लिए कैश फर्स्ट
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        if (cachedResponse) {
          return cachedResponse;
        }

        return fetch(event.request)
          .then(response => {
            // इमेजेस और फॉन्ट्स कैश करें
            if (event.request.url.includes('jpg') || 
                event.request.url.includes('png') || 
                event.request.url.includes('css')) {
              const responseClone = response.clone();
              caches.open(DYNAMIC_CACHE)
                .then(cache => {
                  cache.put(event.request, responseClone);
                });
            }
            return response;
          })
          .catch(() => {
            // ऑफलाइन फॉलबैक
            if (event.request.destination === 'document') {
              return caches.match('/');
            }
          });
      })
  );
});

// बैकग्राउंड सिंक (भविष्य के लिए)
self.addEventListener('sync', event => {
  if (event.tag === 'sync-forms') {
    console.log('🔄 बैकग्राउंड सिंक शुरू');
  }
});

// पुश नोटिफिकेशन
self.addEventListener('push', event => {
  const data = event.data ? event.data.text() : 'नगर पालिका नाकुर से नई सूचना';
  const options = {
    body: data,
    icon: 'img1.png',
    badge: 'img1.png',
    vibrate: [200, 100, 200],
    data: {
      url: '/'
    }
  };
  
  event.waitUntil(
    self.registration.showNotification('NAGAR PALIKA NAKUR', options)
  );
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true })
      .then(clientList => {
        if (clientList.length > 0) {
          return clientList[0].focus();
        }
        return clients.openWindow('/');
      })
  );
});
