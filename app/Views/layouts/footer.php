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
</body>
</html>
