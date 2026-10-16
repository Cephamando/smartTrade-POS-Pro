<?php
$chart = json_encode([
    'labels' => $picture['labels'],
    'actual' => $picture['actual'],
    'projected' => $picture['projected'],
    'paymentLabels' => $picture['paymentLabels'],
    'paymentAmounts' => $picture['paymentAmounts'],
    'financeLabels' => $picture['financeLabels'],
    'financeAmounts' => $picture['financeAmounts'],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
?>
<script src="vendor/chartjs/chart.umd.min.js"></script>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><i class="bi bi-graph-up-arrow text-success"></i> Business analysis</h3>
        <p class="text-muted small mb-0">Charts for the period, then a projection of the next 7 days from weekday averages. The written note opens in a window.</p>
    </div>
    <button type="button" class="btn btn-dark fw-bold" id="openAnalysisChat" <?= empty($explainReady) ? 'disabled' : '' ?>>
        <i class="bi bi-stars"></i> Discuss these figures
    </button>
</div>
<?php if (empty($explainReady)): ?>
<p class="small text-muted">Save a Gemini key under Settings, Guide, to discuss the charts.</p>
<?php endif; ?>

<form method="GET" action="index.php" class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light row g-2 align-items-end">
        <input type="hidden" name="page" value="analysis">
        <div class="col-md-3">
            <label class="form-label small fw-bold">From</label>
            <input type="text" name="start" class="form-control datepicker" value="<?= htmlspecialchars($start) ?>" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">To</label>
            <input type="text" name="end" class="form-control datepicker" value="<?= htmlspecialchars($end) ?>" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Store</label>
            <select name="location" class="form-select">
                <option value="">All stores</option>
                <?php foreach ($locations as $loc): ?>
                <option value="<?= (int)$loc['id'] ?>" <?= (string)$locationId === (string)$loc['id'] ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-success fw-bold w-100">Update charts</button>
        </div>
    </div>
</form>

<div class="row g-3 mb-3">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="fw-bold">Daily takings and the next 7 days</h6>
                <p class="small text-muted">The dashed stretch is a projection: each coming day is the average of that weekday in this period. It is not a promise.</p>
                <canvas id="analysisSales" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h6 class="fw-bold">How receipts were paid</h6>
                <canvas id="analysisPay" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h6 class="fw-bold">Money in and the main costs</h6>
                <canvas id="analysisFinance" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="analysisChat" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Discuss the business</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="analysisThread" class="small" style="white-space: pre-wrap;"></div>
                <p class="small text-muted mt-3 mb-2">Questions stay on this server and are answered only from the totals behind these charts. Projections are weekday averages, not promises. Names, passwords, and keys are not part of the conversation.</p>
                <form id="analysisAsk" class="d-flex gap-2">
                    <input type="text" name="question" class="form-control" maxlength="400" placeholder="Ask about these totals" autocomplete="off">
                    <button class="btn btn-dark fw-bold" type="submit">Ask</button>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="analysisClear">Clear conversation</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var data = <?= $chart ?: '{}' ?>;
    if (window.Chart) {
        new Chart(document.getElementById('analysisSales'), {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [
                    { label: 'Paid receipts', data: data.actual || [], borderColor: '#2e7d32', backgroundColor: 'rgba(46,125,50,0.15)', spanGaps: false, tension: 0.2 },
                    { label: 'Projection', data: data.projected || [], borderColor: '#ef6c00', borderDash: [6, 4], pointRadius: 2, spanGaps: false, tension: 0.2 }
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
        });
        new Chart(document.getElementById('analysisPay'), {
            type: 'doughnut',
            data: { labels: data.paymentLabels || [], datasets: [{ data: data.paymentAmounts || [] }] },
            options: { plugins: { legend: { position: 'bottom' } } }
        });
        new Chart(document.getElementById('analysisFinance'), {
            type: 'bar',
            data: { labels: data.financeLabels || [], datasets: [{ label: 'ZMW', data: data.financeAmounts || [], backgroundColor: '#1565c0' }] },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
    var openBtn = document.getElementById('openAnalysisChat');
    var modalEl = document.getElementById('analysisChat');
    var thread = document.getElementById('analysisThread');
    var form = document.getElementById('analysisAsk');
    var clearer = document.getElementById('analysisClear');
    if (!openBtn || !modalEl || !thread || !form || !clearer || !window.bootstrap) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var started = false;
    function post(body) {
        return fetch('index.php?page=analysis', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) { return r.json(); });
    }
    function addBlock(label, value, danger) {
        var block = document.createElement('div');
        block.className = 'mb-3' + (danger ? ' text-danger' : '');
        if (label) {
            var who = document.createElement('div');
            who.className = 'fw-bold';
            who.textContent = label;
            block.appendChild(who);
        }
        var body = document.createElement('div');
        body.textContent = value;
        block.appendChild(body);
        thread.appendChild(block);
        thread.parentElement.scrollTop = thread.parentElement.scrollHeight;
    }
    function opening() {
        if (started) return;
        started = true;
        thread.textContent = 'Reading the totals…';
        var params = new URLSearchParams();
        params.set('explain_analysis', '1');
        params.set('start', <?= json_encode($start) ?>);
        params.set('end', <?= json_encode($end) ?>);
        params.set('location', <?= json_encode((string)$locationId) ?>);
        params.set('question', '');
        post(params.toString()).then(function (data) {
            thread.textContent = '';
            if (data && data.status === 'success') {
                addBlock('Analysis', data.text || '', false);
            } else {
                started = false;
                addBlock('', (data && data.msg) ? data.msg : 'The note could not be written.', true);
            }
        }).catch(function () {
            started = false;
            thread.textContent = '';
            addBlock('', 'The note could not be written. Try again in a moment.', true);
        });
    }
    openBtn.addEventListener('click', function () {
        if (openBtn.disabled) return;
        modal.show();
        opening();
    });
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        var input = form.querySelector('input[name="question"]');
        var question = (input.value || '').trim();
        if (!question) return;
        input.value = '';
        addBlock('You', question, false);
        var params = new URLSearchParams();
        params.set('explain_analysis', '1');
        params.set('start', <?= json_encode($start) ?>);
        params.set('end', <?= json_encode($end) ?>);
        params.set('location', <?= json_encode((string)$locationId) ?>);
        params.set('question', question);
        post(params.toString()).then(function (data) {
            if (data && data.status === 'success') {
                addBlock('Analysis', data.text || '', false);
            } else {
                addBlock('', (data && data.msg) ? data.msg : 'The note could not be written.', true);
            }
        }).catch(function () {
            addBlock('', 'The note could not be written. Try again in a moment.', true);
        });
    });
    clearer.addEventListener('click', function () {
        var params = new URLSearchParams();
        params.set('clear_analysis', '1');
        post(params.toString()).then(function () {
            thread.textContent = '';
            started = false;
            opening();
        });
    });
    });
})();
</script>
