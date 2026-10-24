(function () {
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('sw.js').catch(function () {});
        });
    }
    var bar = document.getElementById('pwaInstall');
    if (!bar) return;
    try {
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone) return;
    } catch (e) {}
    var key = bar.getAttribute('data-dismiss') || 'pwa-dismiss';
    try {
        if (localStorage.getItem(key) === '1') return;
    } catch (e) {}
    bar.hidden = false;
    bar.style.display = 'flex';
    var install = bar.querySelector('[data-install]');
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        if (!install) return;
        install.hidden = false;
        install.onclick = function () { event.prompt(); };
    });
    var dismiss = bar.querySelector('[data-dismiss-install]');
    if (dismiss) {
        dismiss.onclick = function () {
            bar.hidden = true;
            bar.style.display = '';
            try { localStorage.setItem(key, '1'); } catch (e) {}
        };
    }
})();
