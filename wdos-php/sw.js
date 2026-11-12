/* WDOS service worker: installable offline shell + push notifications.
 * Bump BUILD with every release. The page reloads itself once per build. */
const BUILD = 'v104.0';
const CACHE = 'wdos-' + BUILD;
const SHELL = ['/', '/index.html', '/manifest.json', '/assets/css/app.css', '/assets/favicon.svg',
  '/assets/fonts/inter-latin-wght-normal.woff2', '/assets/fonts/fraunces-latin-wght-normal.woff2',
  '/assets/logos/woddi.png', '/assets/logos/woddi-disc.png', '/assets/logos/wgmn-disc.png', '/assets/logos/wnnn-disc.png', '/assets/icons/icon-192.png',
  '/js/app.js', '/js/api.js', '/js/ui.js', '/js/charts.js',
  '/js/pages/welcome.js', '/js/pages/login.js', '/js/pages/hq.js', '/js/pages/members.js', '/js/pages/announcements.js',
  '/js/pages/leader.js', '/js/pages/home.js', '/js/pages/notifications.js', '/js/pages/settings.js', '/js/pages/reporting.js', '/js/pages/tasks.js', '/js/pages/messages.js', '/js/pages/library.js', '/js/pages/help.js', '/js/pages/voices.js', '/js/pages/staff.js', '/js/pages/cases.js', '/js/pages/calendar.js', '/js/pages/coverage.js', '/js/pages/handoff.js', '/js/pages/journey.js', '/js/pages/community.js', '/js/pages/leadership.js', '/js/pages/reports.js', '/js/pages/communications.js', '/js/pages/institute.js', '/js/pages/integrations.js', '/js/pages/invite.js', '/js/pages/chapter.js', '/js/pages/chapters.js', '/js/pages/rules.js', '/js/pages/meetings.js', '/js/pages/structure.js', '/js/pages/onboarding.js', '/js/pages/referrals.js', '/js/pages/activation.js', '/js/goodmother.js', '/js/pushnudge.js', '/js/pages/inspiration.js'];

self.addEventListener('install', (e) => { e.waitUntil(caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting())); });
self.addEventListener('activate', (e) => {
  e.waitUntil((async () => {
    const had = (await caches.keys()).filter((k) => k.startsWith('wdos-') && k !== CACHE);
    await Promise.all(had.map((k) => caches.delete(k)));
    await self.clients.claim();
    if (had.length) (await self.clients.matchAll({ type: 'window' })).forEach((c) => c.postMessage({ type: 'wdos:new-build', build: BUILD }));
  })());
});
self.addEventListener('fetch', (e) => {
  const url = new URL(e.request.url);
  if (e.request.method !== 'GET' || url.origin !== location.origin || url.pathname.startsWith('/api/')) return;   // the API is never cached
  if (e.request.mode === 'navigate') { e.respondWith(fetch(e.request).catch(() => caches.match('/index.html'))); return; }
  e.respondWith(caches.match(e.request).then((hit) => hit || fetch(e.request).then((res) => {
    if (res.ok && (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/js/'))) { const copy = res.clone(); caches.open(CACHE).then((c) => c.put(e.request, copy)); }
    return res;
  })));
});

self.addEventListener('push', (e) => {
  e.waitUntil((async () => {
    let d = null;
    try { d = e.data ? e.data.json() : null; } catch { d = null; }
    if (!d || !d.title) {   // a push with no readable body: ask the server what it was
      try { const r = await fetch('/api/push/latest', { credentials: 'same-origin', headers: { 'X-WDOS': '1' } }); d = (await r.json()).item; } catch { d = null; }
    }
    d = d || { title: 'WDOS', body: 'You have a new notification.', url: '#/notifications' };
    await self.registration.showNotification(d.title, { body: d.body || '', icon: '/assets/icons/icon-192.png', badge: '/assets/icons/icon-192.png', tag: d.id ? 'wdos-' + d.id : undefined, data: { url: d.url || '#/notifications' } });
    (await self.clients.matchAll({ type: 'window' })).forEach((c) => c.postMessage({ type: 'wdos:push' }));
  })());
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const target = '/' + (e.notification.data?.url || '#/notifications');
  e.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    for (const w of wins) { if (new URL(w.url).origin === location.origin) { await w.focus(); return w.navigate ? w.navigate(target) : null; } }
    return self.clients.openWindow(target);
  })());
});
