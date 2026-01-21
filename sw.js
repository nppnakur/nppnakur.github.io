self.addEventListener('install', (e) => {
  console.log('PWA Service Worker Installed');
});
self.addEventListener('fetch', (e) => {
  e.respondWith(fetch(e.request));
});
