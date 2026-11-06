(function () {
    function mmPerPx() {
        var probe = document.createElement('div');
        probe.style.cssText = 'position:absolute;left:0;top:0;height:100mm;width:0;margin:0;padding:0;border:0;visibility:hidden;';
        document.body.appendChild(probe);
        var px = probe.getBoundingClientRect().height;
        probe.remove();
        return px > 0 ? (100 / px) : (25.4 / 96);
    }

    function contentHeightPx() {
        var body = document.body;
        var top = body.getBoundingClientRect().top;
        var bottom = top;
        var nodes = body.children;
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i];
            if (!node || node.tagName === 'SCRIPT' || node.tagName === 'LINK' || node.tagName === 'STYLE') {
                continue;
            }
            var rect = node.getBoundingClientRect();
            var margin = parseFloat(window.getComputedStyle(node).marginBottom) || 0;
            var edge = rect.bottom + margin;
            if (edge > bottom) bottom = edge;
        }
        var px = Math.ceil(bottom - top);
        if (px < 1) px = body.offsetHeight;
        return px;
    }

    function fitThermalPage() {
        var mm = Math.ceil(contentHeightPx() * mmPerPx() * 2) / 2 + 0.5;
        if (mm < 10) mm = 10;
        var style = document.getElementById('thermal-page-size');
        if (!style) {
            style = document.createElement('style');
            style.id = 'thermal-page-size';
            document.head.appendChild(style);
        }
        style.textContent = '@page{margin:0;size:80mm ' + mm.toFixed(1) + 'mm;}';
    }

    function whenSlipReady(done) {
        var finished = false;
        function once() {
            if (finished) return;
            finished = true;
            done();
        }
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(once).catch(once);
            setTimeout(once, 400);
            return;
        }
        once();
    }

    window.fitThermalPage = fitThermalPage;
    window.printThermalSlip = function () {
        whenSlipReady(function () {
            fitThermalPage();
            requestAnimationFrame(function () {
                fitThermalPage();
                requestAnimationFrame(function () {
                    window.print();
                });
            });
        });
    };
    window.addEventListener('beforeprint', fitThermalPage);
})();
