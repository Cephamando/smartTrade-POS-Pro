/**
 * Keeps Web Orders, Pickup, Tabs and Approvals current without a full reload.
 * Badges use data-live-count="web|pickup|tabs|approvals".
 * When the kitchen marks food ready, a pass banner names the order and chimes.
 */
(function () {
    var stamp = '';
    var pulling = false;
    var ACK_KEY = 'smartrade-ready-ack';
    var lastReady = [];
    var batchKey = '';
    var soundUntil = 0;
    var lastChime = 0;
    var quiet = false;
    var audio = null;
    var armed = false;
    var resuming = false;

    function paint(key, count) {
        document.querySelectorAll('[data-live-count="' + key + '"]').forEach(function (el) {
            el.textContent = String(count);
            el.style.display = count > 0 ? '' : 'none';
        });
    }

    function modalOpen(id) {
        var el = document.getElementById(id);
        return !!(el && el.classList.contains('show'));
    }

    function busyIn(id) {
        var root = document.getElementById(id);
        if (!root) return false;
        var active = document.activeElement;
        return !!(active && root.contains(active) && /^(INPUT|SELECT|TEXTAREA)$/.test(active.tagName));
    }

    function swap(selector) {
        return function (doc) {
            var live = document.querySelector(selector);
            var fresh = doc.querySelector(selector);
            if (live && fresh) {
                live.innerHTML = fresh.innerHTML;
            }
        };
    }

    function refreshBoards() {
        if (pulling) return;
        if (!document.getElementById('tabsModal') && !document.getElementById('onlineTabsModal')) return;
        if (busyIn('tabsModal') || busyIn('onlineTabsModal')) return;
        pulling = true;
        fetch('index.php?page=pos', { credentials: 'same-origin' })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                if (!doc.querySelector('#tabsModal, #onlineTabsModal')) return;
                [
                    '#tabsModal .list-group',
                    '#hiddenTabTemplates',
                    '#onlineTabsModal .list-group',
                    '#hiddenOnlineTabTemplates'
                ].forEach(function (sel) { swap(sel)(doc); });
            })
            .catch(function () {})
            .then(function () { pulling = false; });
    }

    function ackSet() {
        try {
            return (localStorage.getItem(ACK_KEY) || '').split(',').filter(Boolean);
        } catch (e) {
            return [];
        }
    }

    function remember(ids) {
        var next = ackSet();
        ids.forEach(function (id) {
            var key = String(id);
            if (next.indexOf(key) === -1) next.push(key);
        });
        if (next.length > 300) next = next.slice(next.length - 300);
        try { localStorage.setItem(ACK_KEY, next.join(',')); } catch (e) {}
    }

    function ensureBar() {
        if (document.getElementById('readyPass')) return;
        var style = document.createElement('style');
        style.textContent = [
            '.ready-pass{display:flex;align-items:center;gap:0.75rem;flex:0 0 auto;position:sticky;top:0;z-index:1040;',
            'background:#0f6b45;color:#fff;padding:0.55rem 0.9rem;box-shadow:0 8px 18px rgba(0,0,0,0.18);',
            'font-family:inherit;}',
            '.ready-pass[hidden]{display:none !important;}',
            '.ready-pass-main{flex:1 1 auto;min-width:0;display:flex;flex-direction:column;align-items:flex-start;',
            'background:transparent;border:0;color:#fff;text-align:left;padding:0;cursor:pointer;}',
            '.ready-pass-kicker{font-size:0.68rem;letter-spacing:0.08em;text-transform:uppercase;opacity:0.85;font-weight:700;}',
            '.ready-pass-title{font-size:1rem;font-weight:800;line-height:1.2;}',
            '.ready-pass-items{font-size:0.85rem;opacity:0.95;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;}',
            '.ready-pass-more{flex:0 0 auto;background:rgba(255,255,255,0.16);border-radius:999px;padding:0.15rem 0.55rem;font-size:0.75rem;font-weight:700;}',
            '.ready-pass-note{flex:0 0 auto;font-size:0.75rem;opacity:0.9;white-space:nowrap;}',
            '.ready-pass-quiet,.ready-pass-dismiss{flex:0 0 auto;border:1px solid rgba(255,255,255,0.45);background:transparent;',
            'color:#fff;border-radius:999px;padding:0.28rem 0.7rem;font-weight:700;font-size:0.78rem;}',
            '.ready-pass-dismiss{background:#fff;color:#0f6b45;border-color:#fff;}',
            'body.ready-pass-on .app-navbar.sticky-top{top:var(--ready-pass-h,58px);}',
            '@media (max-width:640px){.ready-pass{flex-wrap:wrap;}.ready-pass-note{order:5;width:100%;}}'
        ].join('');
        document.head.appendChild(style);
        var bar = document.createElement('div');
        bar.id = 'readyPass';
        bar.className = 'ready-pass';
        bar.hidden = true;
        bar.setAttribute('role', 'status');
        bar.setAttribute('aria-live', 'assertive');
        bar.innerHTML = ''
            + '<button type="button" class="ready-pass-main" id="readyPassOpen">'
            + '<span class="ready-pass-kicker" id="readyPassKicker">Ready for pickup</span>'
            + '<span class="ready-pass-title" id="readyPassTitle"></span>'
            + '<span class="ready-pass-items" id="readyPassItems"></span>'
            + '</button>'
            + '<span class="ready-pass-more" id="readyPassMore" hidden></span>'
            + '<span class="ready-pass-note" id="readyPassNote"></span>'
            + '<button type="button" class="ready-pass-quiet" id="readyPassQuiet">Quiet</button>'
            + '<button type="button" class="ready-pass-dismiss" id="readyPassDismiss">Dismiss</button>';
        document.body.insertBefore(bar, document.body.firstChild);
        document.getElementById('readyPassOpen').addEventListener('click', openReady);
        document.getElementById('readyPassQuiet').addEventListener('click', function () {
            quiet = true;
            try { sessionStorage.setItem('smartrade-ready-quiet', '1'); } catch (e) {}
            render(lastReady);
        });
        document.getElementById('readyPassDismiss').addEventListener('click', function () {
            var ids = [];
            ordersFrom(lastReady).forEach(function (order) {
                order.ids.forEach(function (id) { ids.push(id); });
            });
            remember(ids);
            quiet = true;
            render(lastReady);
        });
    }

    function ordersFrom(rows) {
        var orders = [];
        var index = {};
        var seen = ackSet();
        (rows || []).forEach(function (row) {
            if (!row || seen.indexOf(String(row.id)) !== -1) return;
            var key = String(row.sale_id);
            if (!index[key]) {
                index[key] = {
                    sale_id: row.sale_id,
                    who: row.who || '',
                    web: !!row.web,
                    items: [],
                    ids: []
                };
                orders.push(index[key]);
            }
            var qty = Number(row.qty) || 1;
            index[key].items.push((qty > 1 ? qty + ' × ' : '') + (row.name || 'Item'));
            index[key].ids.push(row.id);
        });
        return orders;
    }

    function audioCtx() {
        var Ctx = window.AudioContext || window.webkitAudioContext;
        if (!Ctx) return null;
        if (!audio) audio = new Ctx();
        return audio;
    }

    function chime() {
        var ctx = audioCtx();
        if (!ctx) return;
        if (ctx.state === 'suspended') {
            armed = true;
            if (resuming) return;
            resuming = true;
            ctx.resume().then(function () {
                if (armed) tone(ctx);
            }).catch(function () {}).then(function () { resuming = false; });
            return;
        }
        tone(ctx);
    }

    function tone(ctx) {
        var now = ctx.currentTime;
        [523.25, 659.25, 783.99].forEach(function (freq, i) {
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = freq;
            var start = now + i * 0.14;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.16, start + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.42);
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start(start);
            osc.stop(start + 0.45);
        });
        lastChime = Date.now();
        armed = false;
    }

    function openReady() {
        quiet = true;
        var orders = ordersFrom(lastReady);
        var first = orders[0];
        render(lastReady);
        if (first && first.web && document.getElementById('onlineTabsModal') && window.bootstrap) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('onlineTabsModal')).show();
            return;
        }
        if (typeof window.showPickupModal === 'function') {
            window.showPickupModal();
            return;
        }
        window.location.href = 'index.php?page=pickup';
    }

    function render(rows) {
        ensureBar();
        var bar = document.getElementById('readyPass');
        var orders = ordersFrom(rows);
        if (!orders.length) {
            bar.hidden = true;
            document.body.classList.remove('ready-pass-on');
            batchKey = '';
            return;
        }
        var first = orders[0];
        var ids = [];
        orders.forEach(function (order) { order.ids.forEach(function (id) { ids.push(String(id)); }); });
        var key = ids.slice().sort().join(',');
        if (key !== batchKey) {
            var prev = [];
            try { prev = (sessionStorage.getItem('smartrade-ready-batch') || '').split(',').filter(Boolean); } catch (e) {}
            var grew = ids.some(function (id) { return prev.indexOf(id) === -1; });
            batchKey = key;
            try { sessionStorage.setItem('smartrade-ready-batch', ids.join(',')); } catch (e) {}
            if (grew) {
                quiet = false;
                soundUntil = Date.now() + 60000;
                try {
                    sessionStorage.setItem('smartrade-ready-until', String(soundUntil));
                    sessionStorage.removeItem('smartrade-ready-quiet');
                } catch (e) {}
                chime();
            } else {
                try { quiet = sessionStorage.getItem('smartrade-ready-quiet') === '1'; } catch (e2) {}
                try { soundUntil = parseInt(sessionStorage.getItem('smartrade-ready-until') || '0', 10) || 0; } catch (e3) {}
            }
        }
        var who = first.who ? ' · ' + first.who : '';
        document.getElementById('readyPassKicker').textContent = first.web ? 'Web order ready' : 'Ready for pickup';
        document.getElementById('readyPassTitle').textContent = 'Order #' + first.sale_id + who;
        document.getElementById('readyPassItems').textContent = first.items.join(', ');
        var more = document.getElementById('readyPassMore');
        if (orders.length > 1) {
            more.hidden = false;
            more.textContent = '+' + (orders.length - 1) + (orders.length === 2 ? ' other order' : ' other orders');
        } else {
            more.hidden = true;
        }
        var note = document.getElementById('readyPassNote');
        var left = Math.max(0, Math.ceil((soundUntil - Date.now()) / 1000));
        if (quiet) {
            note.textContent = 'Sound off';
        } else if (armed) {
            note.textContent = 'Tap the page to sound the chime';
        } else if (left > 0) {
            note.textContent = 'Chime stops in ' + left + 's';
        } else {
            note.textContent = 'Still waiting';
        }
        bar.hidden = false;
        document.body.classList.add('ready-pass-on');
        document.documentElement.style.setProperty('--ready-pass-h', bar.offsetHeight + 'px');
    }

    function soundTick() {
        if (quiet || !batchKey || Date.now() >= soundUntil) {
            if (document.getElementById('readyPass') && !document.getElementById('readyPass').hidden) {
                render(lastReady);
            }
            return;
        }
        if (Date.now() - lastChime >= 7000) chime();
        render(lastReady);
    }

    function tick() {
        fetch('index.php?action=live_status', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data) return;
                paint('web', data.web_orders || 0);
                paint('pickup', data.pickup || 0);
                paint('tabs', data.tabs_unsettled || 0);
                paint('approvals', data.approvals || 0);
                lastReady = data.ready || [];
                render(lastReady);
                var next = (data.web_ids || '') + '|' + (data.tab_ids || '') + '|' + (data.pickup || 0);
                if (stamp && next !== stamp && (modalOpen('onlineTabsModal') || modalOpen('tabsModal'))) {
                    refreshBoards();
                }
                stamp = next;
            })
            .catch(function () {});
    }

    document.addEventListener('shown.bs.modal', function (e) {
        var id = e.target && e.target.id;
        if (id === 'onlineTabsModal' || id === 'tabsModal') {
            refreshBoards();
        }
    });

    document.addEventListener('pointerdown', function () {
        if (!armed) return;
        var ctx = audioCtx();
        if (ctx && ctx.state === 'suspended') {
            ctx.resume().then(function () { if (armed) tone(ctx); }).catch(function () {});
        }
    }, { once: false });

    window.addEventListener('storage', function (e) {
        if (e.key === ACK_KEY) render(lastReady);
    });

    tick();
    setInterval(tick, 8000);
    setInterval(soundTick, 1000);
})();
