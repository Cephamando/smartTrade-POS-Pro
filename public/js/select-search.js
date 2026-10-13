/**
 * Searchable dropdowns for every <select> in the app (Tom Select).
 *
 * - Every data-* attribute on an <option> becomes a searchable field, so a
 *   product list can be filtered by SKU, category, barcode, vendor, role…
 *   e.g. <option value="5" data-sku="BR-001" data-category="Drinks">Mosi</option>
 * - Multi-word search: every word must match somewhere ("mosi 330" or "drinks a").
 * - Opt out with class="no-search" or data-no-search on the <select>.
 * - Works for selects added later (modals, dynamic rows) via MutationObserver.
 * - Native "change" events still fire exactly once, so inline onchange=…,
 *   addEventListener and jQuery handlers all keep working.
 */
(function () {
    if (typeof window.TomSelect === 'undefined') {
        return;
    }

    var FIELD_SKIP = { data: 1, tomselect: 1, noSearch: 1 };

    function sizeClass(sel) {
        if (sel.classList.contains('form-select-lg')) return 'ts-lg';
        if (sel.classList.contains('form-select-sm')) return 'ts-sm';
        return '';
    }

    function collectFields(sel) {
        var fields = {};
        Array.prototype.forEach.call(sel.options, function (opt) {
            var data = {};
            Object.keys(opt.dataset).forEach(function (k) {
                if (FIELD_SKIP[k]) return;
                data[k] = String(opt.dataset[k]);
                fields[k] = 1;
            });
            if (opt.title) { data.title = opt.title; fields.title = 1; }
            if (Object.keys(data).length) {
                // Tom Select merges data-data JSON into the option object.
                var existing = {};
                try { existing = opt.getAttribute('data-data') ? JSON.parse(opt.getAttribute('data-data')) : {}; } catch (e) { existing = {}; }
                opt.setAttribute('data-data', JSON.stringify(Object.assign(existing, data)));
            }
        });
        return Object.keys(fields);
    }

    function shouldSkip(sel) {
        if (sel.tomselect) return true;
        if (sel.classList.contains('no-search') || sel.hasAttribute('data-no-search')) return true;
        if (sel.closest('.dataTables_length')) return true; // DataTables page-size picker
        if (sel.closest('[data-no-search]')) return true;
        // Third-party widgets that own their own (usually hidden) <select>s.
        if (sel.classList.contains('swal2-select') || sel.closest('.swal2-container, .ts-wrapper, .flatpickr-calendar')) return true;
        // Nothing to search in an empty select; it is being filled by script and
        // would otherwise show an empty "Select…" box (e.g. SweetAlert2's hidden input).
        if (sel.options.length === 0) return true;
        return false;
    }

    function init(sel) {
        if (shouldSkip(sel)) return;
        var extraFields = collectFields(sel);
        var hasPlaceholderOpt = sel.options.length && sel.options[0].value === '';
        var placeholder = sel.getAttribute('data-placeholder') || (hasPlaceholderOpt ? sel.options[0].text : 'Select…');
        var required = sel.required;
        var size = sizeClass(sel);

        var ts = new TomSelect(sel, {
            create: false,
            allowEmptyOption: true,
            placeholder: placeholder,
            searchField: ['text'].concat(extraFields),
            maxOptions: 2000,
            dropdownParent: 'body',
            controlInput: '<input type="text" autocomplete="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" size="1" />',
            plugins: sel.multiple ? ['remove_button'] : [],
            closeAfterSelect: !sel.multiple,
            // Every word in the query must appear in at least one field.
            score: function (search) {
                var self = this;
                var words = String(search || '').toLowerCase().split(/\s+/).filter(Boolean);
                var fields = self.settings.searchField;
                return function (item) {
                    if (!words.length) return 1;
                    var hay = fields.map(function (f) { return String(item[f] || ''); }).join(' ').toLowerCase();
                    var ok = words.every(function (w) { return hay.indexOf(w) !== -1; });
                    if (!ok) return 0;
                    var text = String(item.text || '').toLowerCase();
                    return text.indexOf(words[0]) === 0 ? 3 : (text.indexOf(words[0]) !== -1 ? 2 : 1);
                };
            },
            render: {
                option: function (data, escape) {
                    var sub = extraFields
                        .filter(function (f) { return data[f] && String(data[f]).trim() !== '' && f !== 'raw' && f !== 'reason' && f !== 'type' && f !== 'cost'; })
                        .slice(0, 3)
                        .map(function (f) { return escape(String(data[f])); })
                        .join(' · ');
                    return '<div>' + escape(data.text) + (sub ? '<div class="ts-sub">' + sub + '</div>' : '') + '</div>';
                },
                no_results: function (data, escape) {
                    return '<div class="no-results">No match for “' + escape(data.input) + '”</div>';
                }
            }
        });

        if (size) {
            ts.wrapper.classList.add(size);
        }
        // Keep Bootstrap validation / required behaviour on the real element.
        if (required) {
            sel.required = true;
        }
        // Mirror option disabled state changes made later (e.g. raw items at POS locations).
        sel.addEventListener('ts:refresh', function () { ts.sync(); });
    }

    function initAll(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var selects = scope.querySelectorAll('select');
        Array.prototype.forEach.call(selects, init);
        if (root && root.tagName === 'SELECT') init(root);
    }

    function boot() {
        initAll(document);
        var mo = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                Array.prototype.forEach.call(m.addedNodes, function (n) {
                    if (n.nodeType !== 1) return;
                    if (n.tagName === 'SELECT') init(n);
                    else if (n.tagName === 'OPTION' || n.tagName === 'OPTGROUP') {
                        // A select that was empty when inserted and is being filled by script.
                        var owner = n.closest('select');
                        if (owner && !owner.tomselect) init(owner);
                    }
                    else if (n.querySelectorAll) initAll(n);
                });
            });
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }

    window.SelectSearch = { init: init, initAll: initAll, refresh: function (sel) { if (sel && sel.tomselect) sel.tomselect.sync(); } };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
