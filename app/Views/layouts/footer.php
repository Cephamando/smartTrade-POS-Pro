    </div> 
    
    <footer class="footer mt-auto py-3 bg-white border-top">
        <div class="container text-center">
            <span class="text-muted small">
                &copy; <?= date('Y') ?> <strong>Odelia Enterprise</strong>. All rights reserved.<br>
                <i class="bi bi-geo-alt-fill"></i> Mazabuka, Zambia
            </span>
        </div>
    </footer>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="vendor/flatpickr/flatpickr.min.js"></script>
    <script src="vendor/tom-select/tom-select.complete.min.js"></script>
    <script src="js/select-search.js?v=3"></script>
    <script src="js/live-boards.js?v=2"></script>
    <script src="js/bulk-list.js?v=1"></script>
    <script>
        if (window.flatpickr) {
            document.querySelectorAll('input.datepicker, input[type="date"]').forEach(function (el) {
                if (el._flatpickr) {
                    return;
                }
                if (el.getAttribute('type') === 'date') {
                    el.setAttribute('type', 'text');
                }
                flatpickr(el, {
                    dateFormat: 'Y-m-d',
                    allowInput: true,
                    disableMobile: true
                });
            });
        }
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            if (window.bootstrap && bootstrap.Tooltip) {
                new bootstrap.Tooltip(el);
            }
        });
        <?php if (isset($_SESSION['swal_msg'])): ?>
            AppAlert.notify({
                icon: <?= json_encode($_SESSION['swal_type'] ?? 'info') ?>,
                title: <?= json_encode($_SESSION['swal_title'] ?? '') ?>,
                text: <?= json_encode((string)($_SESSION['swal_msg'] ?? '')) ?>,
                html: <?= json_encode((string)($_SESSION['swal_html'] ?? '')) ?>,
                confirmText: <?= json_encode($_SESSION['swal_confirm'] ?? '') ?>,
                cancelText: <?= json_encode($_SESSION['swal_cancel'] ?? '') ?>,
                url: <?= json_encode($_SESSION['swal_url'] ?? '') ?>
            });
            <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type'], $_SESSION['swal_title'], $_SESSION['swal_confirm'], $_SESSION['swal_cancel'], $_SESSION['swal_url'], $_SESSION['swal_html']); ?>
        <?php endif; ?>
    </script>
    <?php if (empty($isEmbedded)): ?>
    <script>
    (function () {
        const vsiasOrigin = <?= json_encode(\App\Helpers\Vsias::origin()) ?>;
        let modal = null;
        let pendingUrl = '';
        let returnTab = 'incoming';
        let reloadOnClose = false;
        let wired = false;
        const vsiasEvents = ['handoff-submitted', 'handoff-approved', 'handoff-disputed', 'handoff-reviewed'];

        function publicVsiasOrigin() {
            try {
                const url = new URL(vsiasOrigin);
                const pageHost = window.location.hostname;
                if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') {
                    url.hostname = pageHost;
                    url.protocol = window.location.protocol;
                }
                return url.origin;
            } catch (err) {
                return vsiasOrigin;
            }
        }

        function pageIsLoopback() {
            const host = window.location.hostname;
            return host === 'localhost' || host === '127.0.0.1';
        }

        function inspectionUrl(href, embed) {
            const url = new URL(href, window.location.href);
            const pageHost = window.location.hostname;
            if (url.hostname === 'localhost' || url.hostname === '127.0.0.1') {
                url.hostname = pageHost;
                url.protocol = window.location.protocol;
                if (!url.port) {
                    url.port = '8095';
                }
            }
            if (embed) {
                url.searchParams.set('embed', '1');
                url.searchParams.set('parent_origin', window.location.origin);
            } else {
                url.searchParams.delete('embed');
                url.searchParams.delete('parent_origin');
            }
            return url.toString();
        }

        function setFrameSrc(frame, url) {
            if (!frame || !url) {
                return;
            }
            const loading = document.getElementById('vsiasInspectLoading');
            const newTab = document.getElementById('vsiasInspectNewTab');
            if (newTab) {
                newTab.href = url;
            }
            if (loading) {
                loading.classList.remove('d-none');
                const hint = loading.querySelector('.js-vsias-frame-hint');
                if (hint) {
                    hint.classList.add('d-none');
                }
            }
            frame.onload = function () {
                if (loading) {
                    loading.classList.add('d-none');
                }
            };
            frame.onerror = function () {
                if (loading) {
                    loading.classList.remove('d-none');
                    const hint = loading.querySelector('.js-vsias-frame-hint');
                    if (hint) {
                        hint.classList.remove('d-none');
                    }
                }
            };
            if (frame.src === url) {
                frame.src = 'about:blank';
            }
            frame.src = url;
        }

        function closeReloadUrl() {
            const params = new URLSearchParams(window.location.search);
            if ((params.get('page') || '') === 'transfers') {
                return 'index.php?page=transfers&tab=' + encodeURIComponent(returnTab || params.get('tab') || 'incoming');
            }
            return window.location.pathname + window.location.search;
        }

        function ensureModal() {
            const modalEl = document.getElementById('vsiasInspectModal');
            const frame = document.getElementById('vsiasInspectFrame');
            if (!modalEl || !frame || !window.bootstrap || !bootstrap.Modal) {
                return null;
            }
            if (!modal) {
                modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            if (!wired) {
                wired = true;
                modalEl.addEventListener('shown.bs.modal', function () {
                    if (pendingUrl) {
                        setFrameSrc(frame, pendingUrl);
                    }
                });
                modalEl.addEventListener('hidden.bs.modal', function () {
                    pendingUrl = '';
                    frame.onload = null;
                    frame.src = 'about:blank';
                    if (reloadOnClose) {
                        window.location.href = closeReloadUrl();
                    }
                });
                window.addEventListener('message', function (e) {
                    if (e.origin !== publicVsiasOrigin()) {
                        return;
                    }
                    const data = e.data || {};
                    if (data.source !== 'vsias-pro' || !data.success || vsiasEvents.indexOf(data.event) === -1) {
                        return;
                    }
                    reloadOnClose = true;
                    modal.hide();
                });
            }
            return { modalEl: modalEl, frame: frame };
        }

        document.addEventListener('click', function (e) {
            const link = e.target.closest('.js-vsias-inspect');
            if (!link) {
                return;
            }
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const ui = ensureModal();
            if (!ui) {
                window.location.href = link.href;
                return;
            }
            const titleEl = document.getElementById('vsiasInspectTitle');
            returnTab = link.getAttribute('data-tab') || 'incoming';
            if (titleEl) {
                titleEl.innerHTML = '<i class="bi bi-clipboard-check text-warning me-2"></i>' + (link.getAttribute('data-title') || 'V-SIAS inspection');
            }
            if (!pageIsLoopback()) {
                window.location.href = inspectionUrl(link.href, false);
                return;
            }
            pendingUrl = inspectionUrl(link.href, true);
            reloadOnClose = false;
            modal.show();
            setTimeout(function () {
                if (!pendingUrl || !ui.frame) {
                    return;
                }
                const current = ui.frame.getAttribute('src') || '';
                if (current !== pendingUrl) {
                    setFrameSrc(ui.frame, pendingUrl);
                }
            }, 350);
        }, true);
    })();
    </script>
    <?php endif; ?>
    <?php if (empty($isEmbedded)):
        $guideReady = \App\Services\Guide::ready(\App\Models\Setting::all());
        $guideManager = in_array(strtolower((string)($_SESSION['role'] ?? '')), ['admin', 'manager', 'dev', 'owner'], true);
    ?>
    <style>
        .guide-dock { position: fixed; right: 18px; bottom: 18px; z-index: 1030; }
        .guide-ask {
            border: 0; border-radius: 999px; background: #1a1a1a; color: #e8c56b;
            font-weight: 700; padding: 10px 16px; box-shadow: 0 8px 24px rgba(0,0,0,.28);
        }
        .guide-ask:hover { background: #111; color: #f3d48a; }
        .guide-sheet {
            position: fixed; right: 18px; bottom: 72px; z-index: 1030;
            width: min(380px, calc(100vw - 24px)); max-height: min(70vh, 540px);
            display: none; flex-direction: column; background: #fff; border-radius: 14px;
            box-shadow: 0 16px 40px rgba(0,0,0,.28); overflow: hidden;
        }
        .guide-sheet.is-open { display: flex; height: min(70vh, 540px); }
        .guide-sheet header { background: #1a1a1a; color: #fff; padding: 12px 14px; flex: 0 0 auto; }
        .guide-sheet header small { color: #e8c56b; }
        .guide-log { flex: 1 1 auto; min-height: 0; overflow: auto; padding: 12px; background: #f6f4ef; display: flex; flex-direction: column; gap: 8px; }
        .guide-bubble { max-width: 94%; padding: 8px 10px; border-radius: 10px; font-size: .92rem; }
        .guide-bubble.is-user { align-self: flex-end; background: #1a1a1a; color: #fff; white-space: pre-wrap; }
        .guide-bubble.is-guide { align-self: flex-start; background: #fff; border: 1px solid #eadfbe; display: flex; flex-direction: column; gap: 8px; }
        .guide-lead { margin: 0; font-weight: 600; line-height: 1.4; }
        .guide-note { margin: 0; line-height: 1.45; color: #333; }
        .guide-steps { margin: 0; padding-left: 1.15rem; display: flex; flex-direction: column; gap: 6px; }
        .guide-steps li { line-height: 1.4; }
        .guide-open {
            align-self: flex-start; text-decoration: none; font-weight: 700; font-size: .84rem;
            color: #1a1a1a; background: #f3e2b3; border-radius: 999px; padding: 5px 10px;
        }
        .guide-open:hover { background: #e8c56b; color: #1a1a1a; }
        .guide-compose { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eee; background: #fff; flex: 0 0 auto; }
        .guide-compose input { flex: 1; }
    </style>
    <button type="button" class="guide-ask guide-dock" id="guideOpen" aria-expanded="false" aria-controls="guideSheet">
        <i class="bi bi-chat-left-text"></i> Guide
    </button>
    <section class="guide-sheet" id="guideSheet" aria-label="Staff guide">
        <header class="d-flex justify-content-between align-items-start gap-2">
            <div>
                <div class="fw-bold">Guide</div>
                <small>How-to answers from the written manuals</small>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-light" id="guideClear" aria-label="Clear chat" title="Clear chat"><i class="bi bi-trash"></i></button>
                <button type="button" class="btn btn-sm btn-outline-light" id="guideClose" aria-label="Close guide">&times;</button>
            </div>
        </header>
        <div class="guide-log" id="guideLog"></div>
        <form class="guide-compose" id="guideForm">
            <input class="form-control" id="guideQuestion" maxlength="400" placeholder="Ask how to do something" autocomplete="off" <?= $guideReady ? '' : 'disabled' ?>>
            <button class="btn btn-dark" type="submit" <?= $guideReady ? '' : 'disabled' ?>>Ask</button>
        </form>
    </section>
    <script>
    (function () {
        const ready = <?= $guideReady ? 'true' : 'false' ?>;
        const manager = <?= $guideManager ? 'true' : 'false' ?>;
        const openBtn = document.getElementById('guideOpen');
        const sheet = document.getElementById('guideSheet');
        const log = document.getElementById('guideLog');
        const form = document.getElementById('guideForm');
        const input = document.getElementById('guideQuestion');
        const storeKey = 'smartrade-guide';
        let saved = {};
        try { saved = JSON.parse(sessionStorage.getItem(storeKey) || '{}') || {}; } catch (err) { saved = {}; }
        const history = Array.isArray(saved.turns) ? saved.turns.filter(function (turn) {
            return turn && (turn.role === 'user' || turn.role === 'model') && typeof turn.text === 'string' && turn.text !== '';
        }) : [];

        function remember() {
            sessionStorage.setItem(storeKey, JSON.stringify({
                open: sheet.classList.contains('is-open'),
                turns: history.slice(-12)
            }));
        }

        function plain(text) {
            return String(text || '')
                .replace(/\s*\([^)]*index\.php\?[^)]*\)/gi, '')
                .replace(/`([^`]*)`/g, '$1')
                .replace(/\*\*([^*]+)\*\*/g, '$1')
                .replace(/(^|[^*])\*([^*\n]+)\*/g, '$1$2')
                .replace(/index\.php\?page=\S+/gi, '')
                .replace(/^Open:\s*.+$/gim, '')
                .trim();
        }

        function addLinks(el, links) {
            (links || []).forEach(function (link) {
                if (!link || typeof link.href !== 'string' || !/^index\.php\?page=[a-z0-9_]+/.test(link.href)) return;
                const anchor = document.createElement('a');
                anchor.className = 'guide-open';
                anchor.href = link.href;
                anchor.textContent = link.label || 'Open the screen';
                if (link.blank) {
                    anchor.target = '_blank';
                    anchor.rel = 'noopener';
                }
                el.appendChild(anchor);
            });
        }

        function fillGuide(el, turn) {
            el.textContent = '';
            if (turn.html) {
                const body = document.createElement('div');
                body.className = 'guide-body';
                body.innerHTML = turn.html;
                body.querySelectorAll('*').forEach(function (node) {
                    if (!['P', 'OL', 'LI', 'STRONG'].includes(node.tagName)) {
                        node.replaceWith(document.createTextNode(node.textContent || ''));
                        return;
                    }
                    Array.from(node.attributes).forEach(function (attr) {
                        if (attr.name !== 'class' || !/^(guide-lead|guide-note|guide-steps)$/.test(node.className)) {
                            node.removeAttribute(attr.name);
                        }
                    });
                });
                el.appendChild(body);
            } else {
                el.appendChild(document.createTextNode(plain(turn.text)));
            }
            addLinks(el, turn.links);
        }

        function bubble(text, kind) {
            const el = document.createElement('div');
            el.className = 'guide-bubble ' + (kind === 'user' ? 'is-user' : 'is-guide');
            if (kind === 'user') {
                el.textContent = text;
            } else if (text && typeof text === 'object') {
                fillGuide(el, text);
            } else {
                el.textContent = plain(text);
            }
            log.appendChild(el);
            log.scrollTop = log.scrollHeight;
            return el;
        }

        history.forEach(function (turn) {
            if (turn.role === 'user') {
                bubble(turn.text, 'user');
            } else {
                bubble(turn, 'guide');
            }
        });

        if (!ready && history.length === 0) {
            const note = bubble('A manager still needs to save a Gemini key under System Settings, Guide.', 'guide');
            if (manager) {
                const link = document.createElement('a');
                link.href = 'index.php?page=settings&tab=guide';
                link.textContent = 'Open Guide settings';
                link.className = 'small';
                note.appendChild(document.createTextNode(' '));
                note.appendChild(link);
            }
        }

        function toggle(force) {
            const open = typeof force === 'boolean' ? force : !sheet.classList.contains('is-open');
            sheet.classList.toggle('is-open', open);
            openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && ready) input.focus();
            remember();
        }

        if (saved.open) toggle(true);

        document.addEventListener('click', function (event) {
            const link = event.target.closest && event.target.closest('a[href*="logout"]');
            if (link) sessionStorage.removeItem(storeKey);
        });

        openBtn.addEventListener('click', function () { toggle(); });
        document.getElementById('guideClose').addEventListener('click', function () { toggle(false); });

        document.getElementById('guideClear').addEventListener('click', function () {
            AppAlert.confirm({ title: 'Clear chat?', text: 'This will erase the guide\'s memory of this conversation.', confirmText: 'Clear', cancelText: 'Cancel', icon: 'question', confirmColor: '#dc3545' }).then(function(ok) {
                if (!ok) return;
                try { localStorage.removeItem(storeKey); } catch (e) {}
                log.innerHTML = '';
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (!ready) return;
            const question = input.value.trim();
            if (!question) return;
            input.value = '';
            bubble(question, 'user');
            const pending = bubble('Looking that up…', 'guide');
            input.disabled = true;
            const body = new FormData();
            body.set('question', question);
            body.set('history', JSON.stringify(history.slice(-6)));
            fetch('index.php?page=guide', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
                .then(function (result) {
                    const data = result.data || {};
                    const text = data.answer || data.msg || (data.links && data.links[0] && data.links[0].label) || 'The guide could not answer.';
                    if (data.status === 'ok' && (data.html || text)) {
                        fillGuide(pending, { html: data.html, links: data.links || [], text: text });
                        history.push({ role: 'user', text: question });
                        history.push({ role: 'model', text: text, html: data.html, links: data.links || [] });
                        remember();
                    } else {
                        pending.textContent = text;
                    }
                })
                .catch(function () {
                    pending.textContent = 'The guide could not answer. Try again in a moment.';
                })
                .finally(function () {
                    input.disabled = false;
                    input.focus();
                });
        });
    })();
    </script>
    <?php endif; ?>
<?php require APP_PATH . '/Views/partials/training_picker_modal.php'; ?>
<script src="pwa/register.js"></script>
</body>
</html>
