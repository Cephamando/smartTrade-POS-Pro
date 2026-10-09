<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-upc-scan text-primary"></i> UNSPSC Classification</h3>
        <p class="text-muted mb-0">Import UNSPSC commodity codes, then search and link them to products or services.</p>
    </div>
    <span class="badge bg-dark fs-6"><?= (int)$classCount ?> codes</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 h-100 border-top border-warning border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="bi bi-upload"></i> Import codes</h5>
                <div class="alert alert-info small mb-3">
                    Reads the official UNSPSC workbook sheets
                    <strong>SEGMENT</strong>, <strong>FAMILY</strong>, <strong>CLASS</strong>, and <strong>COMMODITY</strong>
                    (Version, Key, Segment/Family/Class/Commodity titles).
                    ZRA invoices need an <strong>8-digit Commodity</strong> code.
                </div>
                <?php if (!empty($bundledFile)): ?>
                <form method="POST" class="js-confirm-form mb-3"
                    data-swal-title="Import bundled UNSPSC file?"
                    data-swal-text="This loads <?= htmlspecialchars($bundledName) ?> from docker/storage/other. It can take a minute."
                    data-swal-confirm="Import"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#0d6efd">
                    <input type="hidden" name="import_unspsc_bundled" value="1">
                    <button class="btn btn-warning fw-bold w-100">
                        <i class="bi bi-file-earmark-spreadsheet"></i>
                        Import <?= htmlspecialchars($bundledName) ?>
                    </button>
                </form>
                <div class="text-center text-muted small mb-3">or upload another file</div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" class="js-confirm-form"
                    data-swal-title="Import UNSPSC codes?"
                    data-swal-text="Existing codes with the same number will be updated. A full UNSPSC workbook can take a minute."
                    data-swal-confirm="Import"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#0d6efd">
                    <input type="hidden" name="import_unspsc" value="1">
                    <input type="file" name="unspsc_file" class="form-control mb-3" accept=".csv,.xlsx,.xls" required>
                    <button class="btn btn-outline-warning fw-bold w-100">Upload &amp; import</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><i class="bi bi-link-45deg"></i> Link a product or service</h5>
                <p class="small text-muted">Search by commodity title (for example beer, potato) or by 8-digit code, then assign it to a catalog item.</p>
                <form method="POST">
                    <input type="hidden" name="assign_unspsc" value="1">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PRODUCT / SERVICE</label>
                        <select name="product_id" class="form-select" required>
                            <option value="">Select item…</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['id'] ?>">
                                    <?= htmlspecialchars($p['name']) ?>
                                    <?= !empty($p['sku']) ? '(' . htmlspecialchars($p['sku']) . ')' : '' ?>
                                    <?= !empty($p['unspsc_code']) ? ' — ' . htmlspecialchars($p['unspsc_code']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">UNSPSC CODE</label>
                        <input type="hidden" name="unspsc_code" id="linkUnspscCode" required>
                        <input type="search" class="form-control" id="linkUnspscSearch" placeholder="Search title, class, or code…" autocomplete="off">
                        <div id="linkUnspscResults" class="list-group mt-2 shadow-sm" style="max-height: 280px; overflow-y: auto;"></div>
                        <div class="small text-muted mt-2" id="linkUnspscChosen">No code selected.</div>
                    </div>
                    <button class="btn btn-primary fw-bold w-100" type="submit">Assign code</button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <input type="hidden" name="page" value="unspsc">
            <div class="col-md-8">
                <label class="form-label small fw-bold text-muted">SEARCH CODES</label>
                <input type="search" name="q" class="form-control" value="<?= htmlspecialchars($q) ?>" placeholder="Commodity title, class, or 8-digit code">
            </div>
            <div class="col-md-4">
                <button class="btn btn-dark w-100 fw-bold">Search</button>
            </div>
        </form>
        <div class="small text-muted mb-2">
            <?= number_format((int)$list['total']) ?> matching code(s)
            · page <?= (int)$list['page'] ?> of <?= (int)$list['pages'] ?>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Level</th>
                        <th class="text-center">Linked items</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($list['rows'] as $row): ?>
                    <tr>
                        <td class="fw-bold font-monospace"><?= htmlspecialchars($row['item_cls_cd']) ?></td>
                        <td>
                            <?= htmlspecialchars($row['item_cls_nm']) ?>
                            <?php if (!empty($row['item_cls_path'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars($row['item_cls_path']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= (int)$row['item_cls_lvl'] >= 4 ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= htmlspecialchars($row['item_cls_lvl_label'] ?? (string)$row['item_cls_lvl']) ?>
                            </span>
                        </td>
                        <td class="text-center"><?= (int)$row['linked_count'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list['rows'])): ?>
                    <tr><td colspan="4" class="text-center text-muted py-4">No codes found. Import the UNSPSC Excel file to populate this list.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($list['pages'] > 1): ?>
        <?php
            $cur = (int)$list['page'];
            $pages = (int)$list['pages'];
            $from = max(1, $cur - 4);
            $to = min($pages, $from + 8);
            $from = max(1, $to - 8);
        ?>
        <nav class="mt-3">
            <ul class="pagination pagination-sm justify-content-center mb-0">
                <li class="page-item <?= $cur <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php?page=unspsc&q=<?= urlencode($q) ?>&p=<?= max(1, $cur - 1) ?>">Prev</a>
                </li>
                <?php for ($i = $from; $i <= $to; $i++): ?>
                <li class="page-item <?= $i === $cur ? 'active' : '' ?>">
                    <a class="page-link" href="index.php?page=unspsc&q=<?= urlencode($q) ?>&p=<?= $i ?>"><?= $i ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?= $cur >= $pages ? 'disabled' : '' ?>">
                    <a class="page-link" href="index.php?page=unspsc&q=<?= urlencode($q) ?>&p=<?= min($pages, $cur + 1) ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
</div>

<script>
(function () {
    const search = document.getElementById('linkUnspscSearch');
    const results = document.getElementById('linkUnspscResults');
    const hidden = document.getElementById('linkUnspscCode');
    const chosen = document.getElementById('linkUnspscChosen');
    let timer = null;

    function escapeHtml(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function renderItem(item) {
        const path = item.item_cls_path ? '<div class="small text-muted">' + escapeHtml(item.item_cls_path) + '</div>' : '';
        const badge = item.item_cls_lvl_label
            ? '<span class="badge ' + ((item.item_cls_lvl >= 4) ? 'bg-primary' : 'bg-secondary') + '">' + escapeHtml(item.item_cls_lvl_label) + '</span>'
            : '';
        const used = item.favourite
            ? '<span class="badge bg-warning text-dark">Used' + (item.uses ? ' ' + escapeHtml(item.uses) : '') + '</span>'
            : '';
        return '<div class="d-flex justify-content-between gap-2 align-items-start">'
            + '<div><strong class="font-monospace">' + escapeHtml(item.item_cls_cd) + '</strong>'
            + '<div>' + escapeHtml(item.item_cls_nm || '') + '</div>'
            + path + '</div><div class="d-flex flex-column gap-1 align-items-end">' + used + badge + '</div></div>';
    }

    function render(items) {
        results.innerHTML = '';
        if (!items.length) {
            results.innerHTML = '<div class="list-group-item small text-muted">No matching codes.</div>';
            return;
        }
        if (items.length && items.every(function (item) { return item.favourite; })) {
            const head = document.createElement('div');
            head.className = 'list-group-item small fw-bold text-muted';
            head.textContent = 'Favourites · previously used';
            results.appendChild(head);
        }
        items.forEach(function (item) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'list-group-item list-group-item-action';
            btn.innerHTML = renderItem(item);
            btn.addEventListener('click', function () {
                hidden.value = item.item_cls_cd;
                chosen.textContent = 'Selected: ' + item.item_cls_cd + ' — ' + (item.item_cls_nm || '');
                results.innerHTML = '';
                search.value = item.item_cls_cd + ' ' + (item.item_cls_nm || '');
            });
            results.appendChild(btn);
        });
    }

    function loadSearch(q) {
        clearTimeout(timer);
        timer = setTimeout(function () {
            fetch('index.php?page=unspsc&ajax=search&q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (data) { render(data.results || []); })
                .catch(function () { results.innerHTML = ''; });
        }, q ? 250 : 0);
    }
    search.addEventListener('focus', function () {
        if (!search.value.trim()) loadSearch('');
    });
    search.addEventListener('input', function () {
        hidden.value = '';
        chosen.textContent = 'No code selected.';
        loadSearch(search.value.trim());
    });
})();
</script>
