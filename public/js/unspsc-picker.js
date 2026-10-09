(function (global) {
    function escapeHtml(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function renderItem(item) {
        const path = item.item_cls_path
            ? '<div class="small text-muted">' + escapeHtml(item.item_cls_path) + '</div>'
            : '';
        const badge = item.item_cls_lvl_label
            ? '<span class="badge ' + (item.item_cls_lvl >= 4 ? 'bg-primary' : 'bg-secondary') + '">' + escapeHtml(item.item_cls_lvl_label) + '</span>'
            : '';
        const used = item.favourite
            ? '<span class="badge bg-warning text-dark">Used' + (item.uses ? ' ' + escapeHtml(item.uses) : '') + '</span>'
            : '';
        return '<div class="d-flex justify-content-between gap-2 align-items-start"><div><strong class="font-monospace">'
            + escapeHtml(item.item_cls_cd) + '</strong><div>' + escapeHtml(item.item_cls_nm || '') + '</div>'
            + path + '</div><div class="d-flex flex-column gap-1 align-items-end">' + used + badge + '</div></div>';
    }

    function remember(item) {
        try {
            var list = JSON.parse(localStorage.getItem('unspscFav') || '[]');
            if (!Array.isArray(list)) list = [];
            list = [item].concat(list.filter(function (row) { return row && row.item_cls_cd !== item.item_cls_cd; })).slice(0, 12);
            list.forEach(function (row) { row.favourite = true; });
            localStorage.setItem('unspscFav', JSON.stringify(list));
        } catch (e) {}
    }

    function mergeRecent(items) {
        var recent = [];
        try { recent = JSON.parse(localStorage.getItem('unspscFav') || '[]'); } catch (e) { recent = []; }
        if (!Array.isArray(recent)) recent = [];
        var seen = {};
        var out = [];
        recent.concat(items || []).forEach(function (item) {
            if (!item || !item.item_cls_cd || seen[item.item_cls_cd]) return;
            seen[item.item_cls_cd] = 1;
            out.push(item);
        });
        return out;
    }

    function paint(resultsEl, items, onPick) {
        resultsEl.innerHTML = '';
        if (!items.length) {
            resultsEl.innerHTML = '<div class="list-group-item small text-muted">No codes yet. Search, or assign a code and it will show here.</div>';
            return;
        }
        if (items.length && items.every(function (item) { return item.favourite; })) {
            const head = document.createElement('div');
            head.className = 'list-group-item small fw-bold text-muted';
            head.textContent = 'Favourites · previously used';
            resultsEl.appendChild(head);
        }
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.innerHTML = renderItem(item);
            btn.addEventListener('click', function () { onPick(item); });
            resultsEl.appendChild(btn);
        });
    }

    global.bindUnspscPicker = function (searchEl, resultsEl, hiddenEl, chosenEl, endpoint) {
        if (!searchEl || !resultsEl || !hiddenEl) {
            return;
        }
        const base = endpoint || 'index.php?page=unspsc&ajax=search';
        let timer = null;
        function load(q) {
            const sep = base.indexOf('?') >= 0 ? '&' : '?';
            fetch(base + sep + 'q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    const items = q ? (data.results || []) : mergeRecent(data.results || []);
                    paint(resultsEl, items, function (item) {
                        remember(item);
                        hiddenEl.value = item.item_cls_cd;
                        searchEl.value = item.item_cls_cd + ' ' + (item.item_cls_nm || '');
                        if (chosenEl) {
                            chosenEl.textContent = 'Selected: ' + item.item_cls_cd + ' — ' + (item.item_cls_nm || '');
                        }
                        resultsEl.innerHTML = '';
                    });
                })
                .catch(function () {
                    resultsEl.innerHTML = '<div class="list-group-item small text-danger">Search failed.</div>';
                });
        }
        searchEl.addEventListener('focus', function () {
            if (!searchEl.value.trim()) load('');
        });
        searchEl.addEventListener('input', function () {
            clearTimeout(timer);
            const q = searchEl.value.trim();
            hiddenEl.value = '';
            if (chosenEl) {
                chosenEl.textContent = 'No code selected.';
            }
            timer = setTimeout(function () { load(q); }, q ? 250 : 0);
        });
    };
})(window);
