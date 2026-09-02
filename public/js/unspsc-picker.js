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
        return '<div class="d-flex justify-content-between gap-2 align-items-start"><div><strong class="font-monospace">'
            + escapeHtml(item.item_cls_cd) + '</strong><div>' + escapeHtml(item.item_cls_nm || '') + '</div>'
            + path + '</div>' + badge + '</div>';
    }

    global.bindUnspscPicker = function (searchEl, resultsEl, hiddenEl, chosenEl, endpoint) {
        if (!searchEl || !resultsEl || !hiddenEl) {
            return;
        }
        const base = endpoint || 'index.php?page=unspsc&ajax=search';
        let timer = null;
        searchEl.addEventListener('input', function () {
            clearTimeout(timer);
            const q = searchEl.value.trim();
            hiddenEl.value = '';
            if (chosenEl) {
                chosenEl.textContent = 'No code selected.';
            }
            if (!q) {
                resultsEl.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                const sep = base.indexOf('?') >= 0 ? '&' : '?';
                fetch(base + sep + 'q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        resultsEl.innerHTML = '';
                        const items = data.results || [];
                        if (!items.length) {
                            resultsEl.innerHTML = '<div class="list-group-item small text-muted">No matching codes. Import UNSPSC first.</div>';
                            return;
                        }
                        items.forEach(function (item) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            btn.innerHTML = renderItem(item);
                            btn.addEventListener('click', function () {
                                hiddenEl.value = item.item_cls_cd;
                                searchEl.value = item.item_cls_cd + ' ' + (item.item_cls_nm || '');
                                if (chosenEl) {
                                    chosenEl.textContent = 'Selected: ' + item.item_cls_cd + ' — ' + (item.item_cls_nm || '');
                                }
                                resultsEl.innerHTML = '';
                            });
                            resultsEl.appendChild(btn);
                        });
                    })
                    .catch(function () {
                        resultsEl.innerHTML = '<div class="list-group-item small text-danger">Search failed.</div>';
                    });
            }, 250);
        });
    };
})(window);
