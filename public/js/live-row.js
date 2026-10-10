(function (window) {
    'use strict';

    function parseJson(response) {
        return response.text().then(function (text) {
            try {
                return JSON.parse(text);
            } catch (err) {
                throw new Error('The save did not return an update.');
            }
        });
    }

    function toast(data, fallback) {
        if (!window.AppAlert) {
            return;
        }
        var ok = data && data.status === 'success';
        AppAlert.notify({
            icon: (data && data.icon) || (ok ? 'success' : 'error'),
            text: (data && (data.message || data.msg)) || fallback || (ok ? 'Saved.' : 'Unable to save.'),
            confirmText: 'OK'
        });
    }

    function closeModal(form) {
        var modal = form && form.closest ? form.closest('.modal') : null;
        if (!modal || !window.bootstrap || !bootstrap.Modal) {
            return;
        }
        var inst = bootstrap.Modal.getInstance(modal);
        if (inst) {
            inst.hide();
        }
    }

    function rowFromHtml(html) {
        var wrap = document.createElement('tbody');
        wrap.innerHTML = (html || '').trim();
        return wrap.firstElementChild;
    }

    function flash(node) {
        if (!node) {
            return;
        }
        node.classList.add('table-warning');
        window.setTimeout(function () {
            node.classList.remove('table-warning');
        }, 1600);
    }

    function revealDataRow(dt, node) {
        if (!dt || !node) {
            return;
        }
        var idx = dt.row(node).index();
        var indexes = dt.rows({ search: 'applied', order: 'applied' }).indexes();
        var pos = typeof indexes.indexOf === 'function' ? indexes.indexOf(idx) : -1;
        var len = dt.page.len();
        if (pos < 0 || len < 1) {
            return;
        }
        var page = Math.floor(pos / len);
        if (dt.page() !== page) {
            dt.page(page).draw(false);
        }
    }

    function upsertRow(html, id, listSelector) {
        var node = rowFromHtml(html);
        if (!node) {
            throw new Error('The saved row could not be shown.');
        }
        node.setAttribute('data-live-id', String(id));
        var existing = document.querySelector('tr[data-live-id="' + String(id).replace(/[^0-9]/g, '') + '"]');
        var table = existing ? existing.closest('table') : null;
        if (!table && listSelector) {
            var list = document.querySelector(listSelector);
            table = list ? list.closest('table') : null;
        }
        if (table && window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable(table)) {
            var dt = jQuery(table).DataTable();
            if (existing) {
                while (existing.attributes.length) {
                    existing.removeAttribute(existing.attributes[0].name);
                }
                Array.prototype.forEach.call(node.attributes, function (attr) {
                    existing.setAttribute(attr.name, attr.value);
                });
                existing.innerHTML = node.innerHTML;
                dt.row(existing).invalidate('dom').draw(false);
                flash(existing);
                return existing;
            }
            var api = dt.row.add(node);
            api.draw(false);
            var placed = api.node();
            revealDataRow(dt, placed);
            flash(placed);
            return placed;
        }
        if (existing) {
            existing.replaceWith(node);
            flash(node);
            return node;
        }
        var host = listSelector ? document.querySelector(listSelector) : null;
        if (!host) {
            throw new Error('The list could not be updated.');
        }
        Array.prototype.forEach.call(host.querySelectorAll('tr'), function (tr) {
            if (tr.id === 'noResultsRow') {
                return;
            }
            if (!tr.getAttribute('data-live-id') && tr.querySelector('td[colspan]')) {
                tr.remove();
            }
        });
        var marker = host.querySelector('#noResultsRow');
        if (marker) {
            host.insertBefore(node, marker);
        } else {
            host.appendChild(node);
        }
        flash(node);
        return node;
    }

    function postForm(form) {
        var body = new FormData(form);
        body.set('ajax', '1');
        var submit = form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
        if (submit) {
            submit.disabled = true;
        }
        return fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                Accept: 'application/json'
            }
        }).then(parseJson).then(function (data) {
            if (!data || data.status !== 'success') {
                throw new Error((data && (data.msg || data.message)) || 'Unable to save.');
            }
            return data;
        }).finally(function () {
            if (submit) {
                submit.disabled = false;
            }
        });
    }

    window.LiveRow = {
        parseJson: parseJson,
        toast: toast,
        closeModal: closeModal,
        upsertRow: upsertRow,
        postForm: postForm,
        flash: flash
    };
})(window);
