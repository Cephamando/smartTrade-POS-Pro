<?php if (!empty($_SESSION['training_mode'])): ?>
<style>
    body { padding-top: 52px !important; }
    #trainingBar {
        position: fixed; top: 0; left: 0; right: 0; z-index: 2000;
        background: #7a1f1f; color: #fff; min-height: 52px;
        display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 8px 14px;
        box-shadow: 0 2px 8px rgba(0,0,0,.35);
    }
    #trainingBar strong { letter-spacing: .04em; white-space: nowrap; }
    #trainingCoach { flex: 1 1 280px; font-size: .95rem; line-height: 1.35; }
    #trainingBar button, #trainingGo {
        background: #fff; color: #7a1f1f; border: 0; border-radius: 6px;
        font-weight: 700; padding: 6px 12px; white-space: nowrap; text-decoration: none;
    }
    #trainingGo { background: transparent; color: #fff; border: 1px solid #fff; }
</style>
<div id="trainingBar" role="status">
    <strong id="trainingLabel">TRAINING MODE</strong>
    <span id="trainingCoach">Practice only. Live sales and stock are not changed.</span>
    <a id="trainingGo" href="index.php?page=pos" hidden>Open this step</a>
    <form method="post" action="index.php?page=training" class="m-0">
        <button type="submit" name="end_training" value="1">End Training</button>
    </form>
</div>
<script>
(function () {
    var coach = document.getElementById('trainingCoach');
    function tick() {
        fetch('index.php?page=training&pulse=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (!data || !data.active) return;
                var label = document.getElementById('trainingLabel');
                if (label && data.course) label.textContent = data.course;
                var text = data.title ? data.title + '. ' + (data.message || '') : (data.message || '');
                if (data.step && data.of) text = 'Step ' + data.step + ' of ' + data.of + ': ' + text;
                coach.textContent = text;
                var go = document.getElementById('trainingGo');
                if (!go) return;
                if (data.done || !data.page) {
                    go.hidden = true;
                    return;
                }
                var url = 'index.php?page=' + encodeURIComponent(data.page);
                Object.keys(data.query || {}).forEach(function (key) {
                    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(data.query[key]);
                });
                go.href = url;
                go.hidden = false;
            })
            .catch(function () {});
    }
    tick();
    setInterval(tick, 4000);
})();
</script>
<?php endif; ?>
