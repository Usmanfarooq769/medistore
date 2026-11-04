const CACHE = 'medistore-shell-v1';
const SHELL = ['/','/manifest.webmanifest','/medistore-icon-192.png','/medistore-icon-512.png'];
self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  // Never cache authenticated API responses, POSTs, or private pharmacy/customer data.
  if (event.request.method !== 'GET' || url.pathname.startsWith('/api/') || url.pathname.startsWith('/sanctum/')) return;
  if (event.request.mode === 'navigate') { event.respondWith(fetch(event.request).catch(() => caches.match('/'))); return; }
  if (url.origin === self.location.origin && (url.pathname.startsWith('/build/') || url.pathname.startsWith('/assets/'))) {
    event.respondWith(caches.match(event.request).then(cached => cached || fetch(event.request).then(response => {
      if (response.ok) { const copy = response.clone(); caches.open(CACHE).then(cache => cache.put(event.request, copy)); }
      return response;
    })));
  }
});

self.addEventListener('push', event => {
  let payload = {};
  try { payload = event.data ? event.data.json() : {}; } catch { payload = { body: event.data?.text() || '' }; }
  const title = payload.title || 'MediStore notification';
  const options = {
    body: payload.body || 'You have a new pharmacy notification.',
    icon: '/medistore-icon-192.png',
    badge: '/medistore-icon-192.png',
    data: { url: payload.url || '/mobile' },
    tag: payload.tag || 'medistore-notification'
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  const requested = new URL(event.notification.data?.url || '/mobile', self.location.origin);
  const target = requested.origin === self.location.origin ? requested.href : new URL('/mobile', self.location.origin).href;
  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
    const existing = clients.find(client => client.url === target);
    return existing ? existing.focus() : self.clients.openWindow(target);
  }));
});
