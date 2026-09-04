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
                text: <?= json_encode((string)$_SESSION['swal_msg']) ?>
            });
            <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
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

        function inspectionUrl(href) {
            const url = new URL(href, window.location.href);
            url.searchParams.set('embed', '1');
            url.searchParams.set('parent_origin', window.location.origin);
            return url.toString();
        }

        function setFrameSrc(frame, url) {
            if (!frame || !url) {
                return;
            }
            const loading = document.getElementById('vsiasInspectLoading');
            if (loading) {
                loading.classList.remove('d-none');
            }
            frame.onload = function () {
                if (loading) {
                    loading.classList.add('d-none');
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
                    if (e.origin !== vsiasOrigin) {
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
            pendingUrl = inspectionUrl(link.href);
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
