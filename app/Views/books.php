<style>
    @media print {
        body * { visibility: hidden; }
        #bookLive, #bookLive * { visibility: visible; }
        #bookLive { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
    .book-card.is-on { border: 2px solid #198754 !important; }
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
        <form id="bookFilters" class="row g-3 align-items-end" action="#" autocomplete="off" onsubmit="return false">
            <input type="hidden" name="page" value="books">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">BOOK</label>
                <select name="book" class="form-select fw-bold">
                    <?php $order = 0; foreach ($catalog as $key => $meta): ?>
                        <option value="<?= htmlspecialchars($key) ?>" data-order="<?= $order ?>" <?= $book === $key ? 'selected' : '' ?>><?= htmlspecialchars($meta['title']) ?></option>
                    <?php $order++; endforeach; ?>
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

<div class="row g-3 mb-4 no-print" id="bookCards">
    <?php $order = 0; foreach ($catalog as $key => $meta): ?>
        <div class="col-md-6 col-xl-4" data-book="<?= htmlspecialchars($key) ?>" data-order="<?= $order ?>">
            <button type="button" class="book-card card h-100 text-start shadow-sm border-0 w-100<?= $book === $key ? ' is-on' : '' ?>">
                <div class="card-body">
                    <h5 class="fw-bold text-dark"><?= htmlspecialchars($meta['title']) ?></h5>
                    <p class="text-muted mb-0"><?= htmlspecialchars($meta['blurb']) ?></p>
                </div>
            </button>
        </div>
    <?php $order++; endforeach; ?>
</div>

<div id="bookLive">
    <?php include __DIR__ . '/partials/book_sheet.php'; ?>
</div>

<script>
(function () {
    var form = document.getElementById('bookFilters');
    var live = document.getElementById('bookLive');
    var cards = document.getElementById('bookCards');
    if (!form || !live || !cards) return;
    var timer = 0;
    var request = 0;
    var sorting = false;

    form.submit = function () { loadBook(); };

    function markBook(key) {
        cards.querySelectorAll('[data-book]').forEach(function (card) {
            card.querySelector('.book-card').classList.toggle('is-on', card.dataset.book === key);
        });
    }

    function orderMenu(filled) {
        sorting = true;
        var rank = {};
        (filled || []).forEach(function (key, index) { rank[key] = index; });
        var nodes = Array.prototype.slice.call(cards.children);
        nodes.sort(function (a, b) {
            var ar = Object.prototype.hasOwnProperty.call(rank, a.dataset.book) ? rank[a.dataset.book] : 1000 + Number(a.dataset.order);
            var br = Object.prototype.hasOwnProperty.call(rank, b.dataset.book) ? rank[b.dataset.book] : 1000 + Number(b.dataset.order);
            return ar - br;
        });
        nodes.forEach(function (node) { cards.appendChild(node); });

        var sel = form.querySelector('[name="book"]');
        if (!sel) {
            sorting = false;
            return;
        }
        var current = sel.value;
        var options = Array.prototype.slice.call(sel.options);
        options.sort(function (a, b) {
            var ar = Object.prototype.hasOwnProperty.call(rank, a.value) ? rank[a.value] : 1000 + Number(a.dataset.order);
            var br = Object.prototype.hasOwnProperty.call(rank, b.value) ? rank[b.value] : 1000 + Number(b.dataset.order);
            return ar - br;
        });
        options.forEach(function (option) { sel.appendChild(option); });
        sel.value = current;
        if (sel.tomselect) {
            sel.tomselect.sync();
            sel.tomselect.setValue(current, true);
        }
        sorting = false;
    }

    function loadBook() {
        var params = new URLSearchParams(new FormData(form));
        var shown = new URLSearchParams(params);
        shown.delete('fragment');
        history.replaceState(null, '', 'index.php?' + shown.toString());
        params.set('fragment', '1');
        var ticket = ++request;
        live.style.opacity = '0.45';
        fetch('index.php?' + params.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (ticket !== request || !data || !data.html) return;
                live.innerHTML = data.html;
                if (data.book) markBook(data.book);
                orderMenu(data.filled || []);
            })
            .catch(function () {})
            .finally(function () {
                if (ticket === request) live.style.opacity = '1';
            });
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        event.stopPropagation();
        loadBook();
    }, true);
    form.addEventListener('change', function () {
        if (sorting) return;
        clearTimeout(timer);
        timer = setTimeout(loadBook, 120);
    });
    cards.addEventListener('click', function (event) {
        var card = event.target.closest('[data-book]');
        if (!card) return;
        var sel = form.querySelector('[name="book"]');
        if (!sel || sel.value === card.dataset.book) {
            markBook(card.dataset.book);
            return;
        }
        if (sel.tomselect) {
            sel.tomselect.setValue(card.dataset.book, false);
        } else {
            sel.value = card.dataset.book;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
})();
</script>
