/**
 * Keeps Web Orders, Pickup, Tabs and Approvals current without a full reload.
 * Badges use data-live-count="web|pickup|tabs|approvals".
 */
(function () {
    var stamp = '';
    var pulling = false;

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

    function tick() {
        if (!document.querySelector('[data-live-count], #tabsModal, #onlineTabsModal, #pickupModal')) return;
        fetch('index.php?action=live_status', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data) return;
                paint('web', data.web_orders || 0);
                paint('pickup', data.pickup || 0);
                paint('tabs', data.tabs_unsettled || 0);
                paint('approvals', data.approvals || 0);
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

    tick();
    setInterval(tick, 8000);
})();
