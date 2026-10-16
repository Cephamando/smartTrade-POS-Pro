<?php
$explainReady = !empty($explainReady);
$explainPage = (string)($explainPage ?? '');
$explainAction = (string)($explainAction ?? '');
$explainFields = is_array($explainFields ?? null) ? $explainFields : [];
$explainMaster = in_array(strtolower((string)($_SESSION['role'] ?? '')), ['admin', 'manager', 'dev'], true);
?>
<div class="no-print mb-3" data-explain-root>
    <button type="button" class="btn btn-outline-dark btn-sm fw-bold" data-explain-btn <?= $explainReady ? '' : 'disabled' ?>>
        <i class="bi bi-stars"></i> Explain these figures
    </button>
    <?php if (!$explainReady): ?>
    <span class="small text-muted ms-2">Save a Gemini key under Settings, Guide.</span>
    <?php endif; ?>
    <form class="d-none" data-explain-form method="POST" action="index.php?page=<?= htmlspecialchars($explainPage) ?>">
        <input type="hidden" name="<?= htmlspecialchars($explainAction) ?>" value="1">
        <?php foreach ($explainFields as $name => $value): ?>
        <input type="hidden" name="<?= htmlspecialchars((string)$name) ?>" value="<?= htmlspecialchars((string)$value) ?>">
        <?php endforeach; ?>
    </form>
    <div class="modal fade" tabindex="-1" data-explain-modal>
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Explain these figures</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="small" style="white-space: pre-wrap;" data-explain-text></div>
                    <p class="small text-muted mb-0 mt-3">Written from the totals on this screen. This is not a forecast.</p>
                    <?php if ($explainMaster): ?>
                    <p class="small mb-0 mt-2"><a href="index.php?page=analysis">Open business analysis</a> for charts and a projection of the whole shop.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.currentScript && document.currentScript.previousElementSibling;
    if (!root || !root.hasAttribute('data-explain-root')) return;
    if (root.dataset.bound === '1') return;
    root.dataset.bound = '1';
    document.addEventListener('DOMContentLoaded', function () {
    var btn = root.querySelector('[data-explain-btn]');
    var form = root.querySelector('[data-explain-form]');
    var modalEl = root.querySelector('[data-explain-modal]');
    var text = root.querySelector('[data-explain-text]');
    if (!btn || !form || !modalEl || !text || !window.bootstrap) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    btn.addEventListener('click', function () {
        if (btn.disabled) return;
        var label = btn.innerHTML;
        btn.disabled = true;
        text.classList.remove('text-danger');
        text.textContent = 'Writing a note from the totals on this screen…';
        modal.show();
        fetch(form.action, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams(new FormData(form)).toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.status === 'success') {
                text.textContent = data.text || '';
            } else {
                text.classList.add('text-danger');
                text.textContent = (data && data.msg) ? data.msg : 'The note could not be written.';
            }
        })
        .catch(function () {
            text.classList.add('text-danger');
            text.textContent = 'The note could not be written. Try again in a moment.';
        })
        .finally(function () {
            btn.disabled = false;
            btn.innerHTML = label;
        });
    });
    });
})();
</script>
