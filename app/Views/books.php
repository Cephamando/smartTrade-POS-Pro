<style>
    @media print {
        body * { visibility: hidden; }
        #bookLive, #bookLive * { visibility: visible; }
        #bookLive { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3 no-print">
    <div>
        <h3 class="fw-bold m-0"><i class="bi bi-journal-bookmark text-success me-2"></i> Books of account</h3>
        <div class="text-muted small">The book updates as soon as you change the book, the dates, or the store.</div>
    </div>
    <button type="button" class="btn btn-dark fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="card shadow-sm border-0 mb-4 no-print">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" id="bookFilters" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="books">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">BOOK</label>
                <select name="book" class="form-select fw-bold">
                    <?php foreach ($catalog as $key => $meta): ?>
                        <option value="<?= htmlspecialchars($key) ?>" <?= $book === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">FROM</label>
                <input type="text" name="start" class="form-control datepicker fw-bold" value="<?= htmlspecialchars($start) ?>" autocomplete="off">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">TO</label>
                <input type="text" name="end" class="form-control datepicker fw-bold" value="<?= htmlspecialchars($end) ?>" autocomplete="off">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">STORE</label>
                <select name="location_id" class="form-select fw-bold">
                    <option value="0">All stores</option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>" <?= (int)$locationId === (int)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<div id="bookLive">
    <?php include __DIR__ . '/partials/book_sheet.php'; ?>
</div>

<script>
(function () {
    var form = document.getElementById('bookFilters');
    var live = document.getElementById('bookLive');
    if (!form || !live) return;
    var timer = 0;

    function loadBook() {
        var params = new URLSearchParams(new FormData(form));
        var shown = new URLSearchParams(params);
        shown.delete('fragment');
        history.replaceState(null, '', 'index.php?' + shown.toString());
        params.set('fragment', '1');
        live.style.opacity = '0.45';
        fetch('index.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.html) {
                    live.innerHTML = data.html;
                }
            })
            .catch(function () {})
            .finally(function () { live.style.opacity = '1'; });
    }

    form.addEventListener('change', function () {
        clearTimeout(timer);
        timer = setTimeout(loadBook, 150);
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        loadBook();
    });
})();
</script>
