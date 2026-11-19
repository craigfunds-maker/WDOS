/* WODDI Institute service worker: push notifications and a light offline fallback. */
var CACHE = 'woddi-php-v2';
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  var r = e.request;
  if (r.method !== 'GET' || new URL(r.url).origin !== location.origin) return;
  if (/\/assets\//.test(r.url)) {
    e.respondWith(caches.open(CACHE).then(function (c) { return c.match(r).then(function (hit) { var net = fetch(r).then(function (res) { if (res.ok) c.put(r, res.clone()); return res; }).catch(function () { return hit; }); return hit || net; }); }));
  } else if (r.mode === 'navigate') {
    e.respondWith(fetch(r).then(function (res) { if (res.ok && /\/course\//.test(r.url)) caches.open(CACHE).then(function (c) { c.put(r, res.clone()); }); return res.clone ? res : res; }).catch(function () { return caches.match(r).then(function (hit) { return hit || new Response('<h1 style="font-family:sans-serif;padding:24px">You are offline</h1><p style="font-family:sans-serif;padding:0 24px">Open a lesson you have already visited, or reconnect to continue.</p>', { headers: { 'Content-Type': 'text/html' } }); }); }));
  }
});
self.addEventListener('push', function (e) {
  var d = {}; try { d = e.data ? e.data.json() : {}; } catch (x) { d = { title: 'WODDI Institute', body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'WODDI Institute', { body: d.body || '', icon: d.icon || 'assets/icon-192.png', badge: d.icon || 'assets/icon-192.png', data: { url: d.url || './' } }));
});
self.addEventListener('notificationclick', function (e) {
  e.notification.close();
  var u = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) { for (var i = 0; i < list.length; i++) { if ('focus' in list[i]) { list[i].navigate(u); return list[i].focus(); } } return self.clients.openWindow(u); }));
});
