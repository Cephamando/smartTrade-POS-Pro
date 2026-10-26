<?php
/** @var array<string, float|int> $tradeFigures */
/** @var array<string, float|int> $businessValue */
/** @var array<string, mixed> $figureFilters */
$tradeFigures = $tradeFigures ?? [];
$businessValue = $businessValue ?? [];
$figureFilters = $figureFilters ?? [];
$money = static function (float $amount): string {
    return 'ZMW ' . number_format($amount, 2);
};
$salesTabs = [
    ['key' => 'gross', 'title' => 'Gross sales', 'hint' => 'Total sales before discounts and refunds', 'amount' => (float)($tradeFigures['gross'] ?? 0)],
    ['key' => 'refunds', 'title' => 'Refunds', 'hint' => 'Items returned by customers', 'amount' => (float)($tradeFigures['refunds'] ?? 0)],
    ['key' => 'discounts', 'title' => 'Discounts', 'hint' => 'Total discounts applied', 'amount' => (float)($tradeFigures['discounts'] ?? 0)],
    ['key' => 'net', 'title' => 'Net sales', 'hint' => 'Gross sales minus discounts and refunds', 'amount' => (float)($tradeFigures['net'] ?? 0)],
    ['key' => 'profit', 'title' => 'Gross profit', 'hint' => 'Net sales minus the cost price of the items', 'amount' => (float)($tradeFigures['profit'] ?? 0)],
    ['key' => 'cost', 'title' => 'Cost', 'hint' => 'Cost price, tax, and payment fees', 'amount' => (float)($tradeFigures['cost'] ?? 0)],
];
$valueTabs = [
    ['key' => 'assets', 'title' => 'Asset cost', 'hint' => 'What the assets still owned cost to buy', 'amount' => (float)($businessValue['asset_cost'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'depreciation', 'title' => 'Depreciation', 'hint' => 'Straight-line depreciation charged so far', 'amount' => (float)($businessValue['depreciation'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'stock', 'title' => 'Stock at cost', 'hint' => 'Goods on hand at their cost price', 'amount' => (float)($businessValue['stock'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'cash', 'title' => 'Cash', 'hint' => 'Cash takings minus till payouts, plus or minus the drawer count', 'amount' => (float)($businessValue['cash'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'mobile', 'title' => 'Mobile money', 'hint' => 'Flutterwave money still belonging to the shop, after fees and transfers out', 'amount' => (float)($businessValue['mobile'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'bank', 'title' => 'Bank', 'hint' => 'Paid bank transfers, cheques, and delivery-app takings', 'amount' => (float)($businessValue['bank'] ?? 0), 'sub' => 'Position today'],
    ['key' => 'debtors', 'title' => 'Debtors', 'hint' => 'Customer bills that are not paid yet', 'amount' => (float)($businessValue['debtors'] ?? 0), 'sub' => 'Owed to the shop'],
    ['key' => 'liabilities', 'title' => 'Liabilities', 'hint' => 'Unpaid supplier bills and wages not yet paid. Taken off the total', 'amount' => (float)($businessValue['liabilities'] ?? 0), 'sub' => 'Owed by the shop'],
    ['key' => 'business', 'title' => 'Total business value', 'hint' => 'Book value, stock, cash, mobile money, bank, and debtors, minus liabilities', 'amount' => (float)($businessValue['business'] ?? 0), 'sub' => 'After liabilities'],
];
?>
<style>
    .loy-metric-tabs { display: flex; border-bottom: 1px solid #e0e0e0; margin-bottom: 20px; background: #fff; padding: 0 20px; overflow-x: auto; }
    .loy-metric-tab { flex: 1; min-width: 140px; padding: 20px 15px; cursor: pointer; border: 0; border-bottom: 3px solid transparent; background: #fff; text-align: left; transition: 0.2s; }
    .loy-metric-tab:hover { background-color: #f8f9fa; }
    .loy-metric-tab.active { border-bottom-color: #4caf50; }
    .loy-metric-title { font-size: 0.85rem; color: #6c757d; font-weight: 500; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
    .loy-metric-value { font-size: 1.8rem; font-weight: 400; color: #212529; margin-bottom: 4px; line-height: 1; }
    .loy-metric-sub { font-size: 0.75rem; color: #6c757d; }
    @media screen and (max-width: 991.98px) {
        .loy-metric-tab { padding: 14px 12px; }
        .loy-metric-value { font-size: 1.35rem; }
    }
</style>
<div class="loy-metric-tabs shadow-sm">
    <?php foreach ($salesTabs as $index => $tab): ?>
    <button type="button" class="loy-metric-tab<?= $index === 0 ? ' active' : '' ?>" data-figure="metric" data-metric="<?= htmlspecialchars($tab['key']) ?>">
        <div class="loy-metric-title"><?= htmlspecialchars($tab['title']) ?> <i class="bi bi-info-circle" title="<?= htmlspecialchars($tab['hint']) ?>"></i></div>
        <div class="loy-metric-value"><?= $money($tab['amount']) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </button>
    <?php endforeach; ?>
</div>
<div class="loy-metric-tabs shadow-sm">
    <?php foreach ($valueTabs as $tab): ?>
    <button type="button" class="loy-metric-tab" data-figure="value" data-metric="<?= htmlspecialchars($tab['key']) ?>">
        <div class="loy-metric-title"><?= htmlspecialchars($tab['title']) ?> <i class="bi bi-info-circle" title="<?= htmlspecialchars($tab['hint']) ?>"></i></div>
        <div class="loy-metric-value"><?= $money($tab['amount']) ?></div>
        <div class="loy-metric-sub"><?= htmlspecialchars((string)$tab['sub']) ?></div>
    </button>
    <?php endforeach; ?>
</div>
<p class="small px-3"><a href="index.php?page=assets">Open the asset register</a></p>
<div class="modal fade" id="ownerDrillModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="ownerDrillTitle">Detail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="ownerDrillNote"></p>
                <dl class="row small mb-3" id="ownerDrillFacts"></dl>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light" id="ownerDrillHead"></thead>
                        <tbody id="ownerDrillBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="ownerDrillBack" disabled>Back</button>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    if (window.ownerFigureDrill) {
        return;
    }
    window.ownerFigureDrill = true;
    const filters = <?= json_encode([
        'start' => (string)($figureFilters['start'] ?? date('Y-m-d')),
        'end' => (string)($figureFilters['end'] ?? date('Y-m-d')),
        'daypart' => (string)($figureFilters['daypart'] ?? ''),
        'location' => (int)($figureFilters['location'] ?? 0),
        'employee_id' => (int)($figureFilters['employee'] ?? ($figureFilters['employee_id'] ?? 0)),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const stack = [];
    const title = document.getElementById('ownerDrillTitle');
    const note = document.getElementById('ownerDrillNote');
    const facts = document.getElementById('ownerDrillFacts');
    const head = document.getElementById('ownerDrillHead');
    const body = document.getElementById('ownerDrillBody');
    const back = document.getElementById('ownerDrillBack');
    const modalEl = document.getElementById('ownerDrillModal');
    let modal = null;

    function cell(tag, text, align) {
        const el = document.createElement(tag);
        el.textContent = text == null ? '' : String(text);
        if (align === 'end') {
            el.className = 'text-end';
        }
        return el;
    }

    function render(data) {
        title.textContent = data.title || 'Detail';
        note.textContent = data.note || '';
        facts.replaceChildren();
        (data.facts || []).forEach(function (fact) {
            if (!fact || !fact.value) {
                return;
            }
            const label = document.createElement('dt');
            label.className = 'col-sm-3 text-muted';
            label.textContent = fact.label || '';
            const value = document.createElement('dd');
            value.className = 'col-sm-9';
            value.textContent = fact.value;
            facts.appendChild(label);
            facts.appendChild(value);
        });
        const columns = data.columns || [];
        const headerRow = document.createElement('tr');
        columns.forEach(function (column) {
            headerRow.appendChild(cell('th', column.label, column.align));
        });
        head.replaceChildren(headerRow);
        body.replaceChildren();
        if (!columns.length) {
            return;
        }
        if (!(data.rows || []).length) {
            const empty = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = columns.length;
            td.className = 'text-center text-muted py-3';
            td.textContent = 'Nothing is in this figure.';
            empty.appendChild(td);
            body.appendChild(empty);
            return;
        }
        data.rows.forEach(function (row) {
            const tr = document.createElement('tr');
            if (row.drill) {
                tr.style.cursor = 'pointer';
                tr.addEventListener('click', function () { openDrill(row.drill, true); });
            }
            columns.forEach(function (column) {
                tr.appendChild(cell('td', row.cells ? row.cells[column.key] : '', column.align));
            });
            body.appendChild(tr);
        });
    }

    function openDrill(extra, keep) {
        const payload = Object.assign({}, filters, extra || {});
        if (keep) {
            stack.push(Object.assign({}, payload));
        }
        back.disabled = stack.length < 2;
        const form = new URLSearchParams();
        form.set('figure_drill', '1');
        Object.keys(payload).forEach(function (key) {
            if (payload[key] !== undefined && payload[key] !== null) {
                form.set(key, payload[key]);
            }
        });
        note.textContent = 'Opening…';
        fetch('index.php?page=reports', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: form.toString()
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (!data || data.status !== 'ok') {
                note.textContent = (data && data.msg) ? data.msg : 'The detail could not be opened.';
                return;
            }
            render(data);
        }).catch(function () {
            note.textContent = 'The detail could not be opened.';
        });
        if (!modal && window.bootstrap) {
            modal = new bootstrap.Modal(modalEl);
        }
        if (modal) {
            modal.show();
        }
    }

    document.addEventListener('click', function (event) {
        const el = event.target.closest('[data-figure]');
        if (!el || event.target.closest('.bi-info-circle')) {
            return;
        }
        const group = el.closest('.loy-metric-tabs');
        if (group) {
            group.querySelectorAll('.loy-metric-tab').forEach(function (tab) { tab.classList.remove('active'); });
            el.classList.add('active');
        }
        stack.length = 0;
        const extra = { level: el.getAttribute('data-figure') };
        ['metric', 'settlement-id', 'asset-id'].forEach(function (name) {
            const value = el.getAttribute('data-' + name);
            if (value) {
                extra[name.replace(/-/g, '_')] = value;
            }
        });
        openDrill(extra, true);
    });

    if (back) {
        back.addEventListener('click', function () {
            if (stack.length < 2) {
                return;
            }
            stack.pop();
            openDrill(stack.pop(), true);
        });
    }
})();
</script>
