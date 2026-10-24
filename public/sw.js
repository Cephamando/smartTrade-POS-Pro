var SHELL = 'st-shell-v2';
var MENU = 'st-menu-v1';

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(SHELL).then(function (cache) {
            return cache.addAll([
                'shop.webmanifest',
                'vendor.webmanifest',
                'app.webmanifest',
                'pwa/icon-192.png',
                'pwa/icon-512.png',
                'pwa/app-icon-192.png',
                'pwa/app-icon-512.png',
                'vendor/bootstrap/bootstrap.min.css',
                'vendor/bootstrap/bootstrap.bundle.min.js',
                'vendor/bootstrap-icons/bootstrap-icons.css',
                'vendor/bootstrap-icons/fonts/bootstrap-icons.woff2'
            ]);
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (key) {
                return key !== SHELL && key !== MENU;
            }).map(function (key) { return caches.delete(key); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET') return;
    var url = new URL(req.url);
    if (url.origin !== self.location.origin) return;
    if (url.pathname.slice(-6) === '/sw.js' || url.pathname.slice(-5) === 'sw.js') return;
    if (url.searchParams.get('page') === 'vendor') return;
    var view = url.searchParams.get('view') || '';
    var isMenu = url.searchParams.get('page') === 'shop' && (view === '' || view === 'home' || view === 'menu');
    if (!isMenu) return;
    event.respondWith(
        fetch(req).then(function (res) {
            var copy = res.clone();
            caches.open(MENU).then(function (cache) { cache.put(req, copy); });
            return res;
        }).catch(function () {
            return caches.match(req).then(function (hit) {
                return hit || caches.match('index.php?page=shop&view=menu');
            });
        })
    );
});
