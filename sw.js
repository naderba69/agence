var CACHE = 'v1.5-prod';
var ASSETS = [
  '/agence/',
  '/agence/assets/css/frontend.css',
  '/agence/assets/js/frontend.js',
  '/agence/manifest.json'
];
self.addEventListener('install', function(e) {
  e.waitUntil(caches.open(CACHE).then(function(c) { return c.addAll(ASSETS); }));
  self.skipWaiting();
});
self.addEventListener('activate', function(e) {
  e.waitUntil(caches.keys().then(function(k) { return Promise.all(k.filter(function(c) { return c !== CACHE; }).map(function(c) { return caches.delete(c); })); }));
  self.clients.claim();
});
self.addEventListener('fetch', function(e) {
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.match(e.request).then(function(r) { return r || fetch(e.request).then(function(res) {
      if (res.status === 200 && e.request.url.indexOf('/api/') === -1) {
        var clone = res.clone(); caches.open(CACHE).then(function(c) { c.put(e.request, clone); });
      }
      return res;
    });
  }));
});