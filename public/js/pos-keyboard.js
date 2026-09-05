(function () {
    const KEYS = [
        ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
        ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
        ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L'],
        ['Z', 'X', 'C', 'V', 'B', 'N', 'M', '.', '@'],
    ];
    const NUM_KEYS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '0', '00'];
    const STORAGE_KEY = 'posVirtualKeyboard';

    let enabled = true;
    let target = null;
    let root = null;
    let keysEl = null;
    let modeLabel = null;
    let forceNumeric = null;

    function isNumericField(el) {
        if (forceNumeric === true) return true;
        if (forceNumeric === false) return false;
        if (!el) return false;
        if (el.type === 'number' || el.inputMode === 'decimal' || el.inputMode === 'numeric') return true;
        return el.id === 'tenderedInput' || el.id === 'tipInput' || el.id === 'op_price' || el.name === 'amount_tendered'
            || el.name === 'starting_cash' || el.name === 'closing_cash' || el.name === 'custom_price'
            || el.name === 'expense_amount' || (el.classList && el.classList.contains('split-amount'));
    }

    function isTypable(el) {
        if (!el || el.disabled) return false;
        if (!/^(INPUT|TEXTAREA)$/.test(el.tagName)) return false;
        if (el.readOnly && el.id !== 'search') return false;
        return !['checkbox', 'radio', 'hidden', 'file', 'button', 'submit', 'reset', 'range', 'color'].includes(el.type);
    }

    function insertText(el, text) {
        if (!el) el = target;
        if (!el) return;
        el.focus();
        const start = el.selectionStart ?? String(el.value || '').length;
        const end = el.selectionEnd ?? String(el.value || '').length;
        if (el.type === 'number') {
            el.value = (el.value || '') + text;
        } else {
            const value = String(el.value || '');
            el.value = value.slice(0, start) + text + value.slice(end);
            try { el.setSelectionRange(start + text.length, start + text.length); } catch (e) {}
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('keyup', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function backspace(el) {
        if (!el) el = target;
        if (!el) return;
        el.focus();
        if (el.type === 'number') {
            el.value = String(el.value || '').slice(0, -1);
        } else {
            const start = el.selectionStart ?? String(el.value || '').length;
            const end = el.selectionEnd ?? String(el.value || '').length;
            const value = String(el.value || '');
            if (start !== end) {
                el.value = value.slice(0, start) + value.slice(end);
                try { el.setSelectionRange(start, start); } catch (e) {}
            } else if (start > 0) {
                el.value = value.slice(0, start - 1) + value.slice(start);
                try { el.setSelectionRange(start - 1, start - 1); } catch (e) {}
            }
        }
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('keyup', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function renderKeys() {
        if (!keysEl) return;
        keysEl.innerHTML = '';
        const numeric = isNumericField(target);
        if (root) root.classList.toggle('is-keypad', numeric);
        if (modeLabel) modeLabel.textContent = numeric ? 'Keypad' : 'Keyboard';
        if (numeric) {
            NUM_KEYS.forEach(function (k) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'pos-vk-key pos-vk-num';
                b.textContent = k;
                b.addEventListener('mousedown', function (e) { e.preventDefault(); insertText(target, k); });
                keysEl.appendChild(b);
            });
            updateSpace();
            return;
        }
        KEYS.forEach(function (row) {
            const rowEl = document.createElement('div');
            rowEl.className = 'pos-vk-row';
            row.forEach(function (k) {
                const b = document.createElement('button');
                b.type = 'button';
                b.className = 'pos-vk-key';
                b.textContent = k;
                b.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    insertText(target, k.length === 1 && k >= 'A' && k <= 'Z' ? k.toLowerCase() : k);
                });
                rowEl.appendChild(b);
            });
            keysEl.appendChild(rowEl);
        });
        updateSpace();
    }

    function syncToggleButtons() {
        document.querySelectorAll('#posKeyboardToggle, [data-pos-vk-toggle], #posKeyboardFab').forEach(function (btn) {
            btn.classList.toggle('active', enabled);
            btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        });
    }

    function hostFor(el) {
        if (!el) return document.body;
        return el.closest('.modal-content') || document.body;
    }

    function updateSpace() {
        const visible = enabled && root && !root.classList.contains('d-none');
        const inModal = !!(root && root.classList.contains('pos-vk-in-modal'));
        const h = visible && !inModal ? root.offsetHeight : 0;
        document.body.style.setProperty('--pos-vk-space', h + 'px');
        if (visible && target && typeof target.scrollIntoView === 'function') {
            try { target.scrollIntoView({ block: 'center', inline: 'nearest' }); } catch (e) {}
        }
    }

    function dock() {
        if (!root) return;
        const host = enabled && target ? hostFor(target) : document.body;
        const inModal = host !== document.body;
        if (inModal) {
            const footer = host.querySelector(':scope > .modal-footer');
            if (footer) host.insertBefore(root, footer);
            else if (root.parentNode !== host) host.appendChild(root);
        } else if (root.parentNode !== document.body) {
            document.body.appendChild(root);
        }
        root.classList.toggle('pos-vk-in-modal', inModal);
        updateSpace();
    }

    function showBoard() {
        if (!root || !enabled) return;
        root.classList.remove('d-none');
        dock();
        renderKeys();
    }

    function hideBoard() {
        if (root) {
            root.classList.add('d-none');
            if (root.parentNode !== document.body) document.body.appendChild(root);
            root.classList.remove('pos-vk-in-modal');
        }
        document.body.style.setProperty('--pos-vk-space', '0px');
    }

    function setEnabled(on) {
        enabled = !!on;
        localStorage.setItem(STORAGE_KEY, enabled ? '1' : '0');
        document.body.classList.toggle('pos-vk-on', enabled);
        syncToggleButtons();
        if (!enabled) {
            hideBoard();
            return;
        }
        if (isTypable(document.activeElement)) {
            attach(document.activeElement);
        } else {
            hideBoard();
        }
    }

    function attach(el) {
        if (!enabled || !isTypable(el)) return;
        target = el;
        forceNumeric = null;
        showBoard();
    }

    function build() {
        root = document.createElement('div');
        root.id = 'posVirtualKeyboard';
        root.className = 'pos-vk d-none';
        root.setAttribute('role', 'application');
        root.setAttribute('aria-label', 'On-screen keyboard');
        root.innerHTML = '<div class="pos-vk-bar">'
            + '<span class="pos-vk-title"><i class="bi bi-keyboard"></i> <span id="posVkMode">Keyboard</span></span>'
            + '<div class="pos-vk-actions">'
            + '<button type="button" class="pos-vk-key pos-vk-wide" data-act="abc">ABC</button>'
            + '<button type="button" class="pos-vk-key pos-vk-wide" data-act="num">123</button>'
            + '<button type="button" class="pos-vk-key pos-vk-wide" data-act="space">Space</button>'
            + '<button type="button" class="pos-vk-key pos-vk-wide" data-act="back">⌫</button>'
            + '<button type="button" class="pos-vk-key pos-vk-wide" data-act="hide">Hide</button>'
            + '</div></div><div class="pos-vk-keys" id="posVkKeys"></div>';
        document.body.appendChild(root);
        keysEl = document.getElementById('posVkKeys');
        modeLabel = document.getElementById('posVkMode');
        root.addEventListener('mousedown', function (e) { e.preventDefault(); });
        root.querySelector('[data-act="space"]').addEventListener('mousedown', function (e) {
            e.preventDefault();
            insertText(target, ' ');
        });
        root.querySelector('[data-act="back"]').addEventListener('mousedown', function (e) {
            e.preventDefault();
            backspace(target);
        });
        root.querySelector('[data-act="hide"]').addEventListener('mousedown', function (e) {
            e.preventDefault();
            setEnabled(false);
        });
        root.querySelector('[data-act="abc"]').addEventListener('mousedown', function (e) {
            e.preventDefault();
            forceNumeric = false;
            renderKeys();
        });
        root.querySelector('[data-act="num"]').addEventListener('mousedown', function (e) {
            e.preventDefault();
            forceNumeric = true;
            renderKeys();
        });

        const fab = document.createElement('button');
        fab.type = 'button';
        fab.id = 'posKeyboardFab';
        fab.className = 'pos-vk-fab';
        fab.title = 'On-screen keyboard';
        fab.setAttribute('aria-label', 'Toggle on-screen keyboard');
        fab.innerHTML = '<i class="bi bi-keyboard-fill"></i>';
        fab.addEventListener('click', function () { setEnabled(!enabled); });
        document.body.appendChild(fab);
    }

    document.addEventListener('DOMContentLoaded', function () {
        build();
        document.querySelectorAll('#posKeyboardToggle, [data-pos-vk-toggle]').forEach(function (btn) {
            btn.addEventListener('click', function () { setEnabled(!enabled); });
        });
        document.addEventListener('focusin', function (e) { attach(e.target); });
        ['shown.bs.modal', 'shown.bs.collapse'].forEach(function (evt) {
            document.addEventListener(evt, function (e) {
                if (!enabled) return;
                const box = e.target;
                const field = box && box.querySelector
                    ? (box.querySelector('#tenderedInput, #tipInput, #op_price, input[name="starting_cash"], input[name="expense_amount"], input:not([type=hidden]):not([type=checkbox]):not([type=radio])') )
                    : null;
                if (field && isTypable(field)) {
                    field.focus();
                    attach(field);
                }
            });
        });
        window.addEventListener('resize', updateSpace);
        setEnabled(true);
        const field = document.querySelector('#startShiftModal input[name="starting_cash"]')
            || document.querySelector('#pendingShiftModal input[name="mgr_username"]')
            || document.getElementById('search');
        if (field && isTypable(field)) {
            field.focus();
            attach(field);
        }
    });
})();
