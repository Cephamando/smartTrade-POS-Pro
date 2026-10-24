(function () {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('sw.js').catch(function () {});
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

    var ua = navigator.userAgent || '';
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function manualSteps() {
        if (ios && /crios/i.test(ua)) {
            return 'Tap the share icon in Chrome, then Add to Home Screen.';
        }
        if (ios) {
            return 'Tap the Share button at the bottom of Safari, then Add to Home Screen, then Add.';
        }
        return 'Open the browser menu and choose Install app or Add to Home screen.';
    }

    function showSteps() {
        var help = bar.querySelector('[data-install-help]');
        if (!help) {
            help = document.createElement('div');
            help.setAttribute('data-install-help', '');
            help.className = 'small';
            help.style.flexBasis = '100%';
            help.style.width = '100%';
            bar.appendChild(help);
        }
        help.textContent = manualSteps();
        help.hidden = false;
    }

    bar.hidden = false;
    bar.style.display = 'flex';
    var install = bar.querySelector('[data-install]');
    if (install) {
        install.hidden = false;
        install.onclick = function () {
            var promptEvent = window.__pwaPrompt;
            if (promptEvent && promptEvent.prompt) {
                promptEvent.prompt();
                var choice = promptEvent.userChoice || Promise.resolve();
                choice.then(function (result) {
                    window.__pwaPrompt = null;
                    if (result && result.outcome === 'accepted') {
                        bar.hidden = true;
                        bar.style.display = '';
                    }
                });
                return;
            }
            showSteps();
        };
    }
    if (ios) showSteps();
    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        window.__pwaPrompt = event;
        var help = bar.querySelector('[data-install-help]');
        if (help && !ios) help.hidden = true;
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
