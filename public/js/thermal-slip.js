(function () {
    var printing = false;

    function mmPerPx(doc) {
        var probe = doc.createElement('div');
        probe.style.cssText = 'position:absolute;left:0;top:0;height:100mm;width:0;margin:0;padding:0;border:0;visibility:hidden;';
        doc.body.appendChild(probe);
        var px = probe.getBoundingClientRect().height;
        probe.remove();
        return px > 0 ? (100 / px) : (25.4 / 96);
    }

    function boxHeightPx(el) {
        if (!el) return 0;
        var rect = el.getBoundingClientRect();
        var cs = el.ownerDocument.defaultView.getComputedStyle(el);
        var mt = parseFloat(cs.marginTop) || 0;
        var mb = parseFloat(cs.marginBottom) || 0;
        var px = Math.ceil(rect.height + mt + mb);
        if (px < 1) px = el.offsetHeight || el.scrollHeight || 0;
        return px;
    }

    function heightMm(doc, el) {
        var mm = Math.ceil(boxHeightPx(el) * mmPerPx(doc) * 2) / 2 + 0.5;
        if (mm < 10) mm = 10;
        if (mm > 800) mm = 800;
        return mm;
    }

    function pageCss(mm) {
        var size = '80mm ' + mm.toFixed(1) + 'mm';
        return '@page{margin:0;size:' + size + ';}'
            + '@page thermal{margin:0;size:' + size + ';}'
            + '@media print{@page{margin:0;size:' + size + ';}'
            + '@page thermal{margin:0;size:' + size + ';}'
            + '#thermal-print-host{page:thermal;}}';
    }

    function applyPageSize(doc, mm) {
        var style = doc.getElementById('thermal-page-size');
        if (!style) {
            style = doc.createElement('style');
            style.id = 'thermal-page-size';
        }
        style.textContent = pageCss(mm);
        doc.documentElement.appendChild(style);
    }

    function fitThermalPage() {
        applyPageSize(document, heightMm(document, document.body));
    }

    function whenSlipReady(done) {
        var finish = function () {
            requestAnimationFrame(function () { requestAnimationFrame(done); });
        };
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(finish).catch(finish);
            return;
        }
        finish();
    }

    function headMarkup(doc) {
        var html = '';
        var nodes = doc.head ? doc.head.children : [];
        for (var i = 0; i < nodes.length; i++) {
            var tag = nodes[i].tagName;
            if (tag === 'LINK' || tag === 'STYLE') html += nodes[i].outerHTML;
        }
        return html;
    }

    function slipMarkup(doc) {
        var html = '';
        var nodes = doc.body.children;
        for (var i = 0; i < nodes.length; i++) {
            var tag = nodes[i].tagName;
            if (tag === 'SCRIPT' || tag === 'LINK' || tag === 'STYLE') continue;
            html += nodes[i].outerHTML;
        }
        return html;
    }

    function rememberStyle(el) {
        return {
            el: el,
            style: el.getAttribute('style')
        };
    }

    function restoreStyle(saved) {
        if (!saved || !saved.el) return;
        if (saved.style === null) saved.el.removeAttribute('style');
        else saved.el.setAttribute('style', saved.style);
    }

    function hideSiblings(doc, host) {
        var saved = [];
        var kids = doc.body.children;
        for (var i = 0; i < kids.length; i++) {
            if (kids[i] === host) continue;
            saved.push(rememberStyle(kids[i]));
            kids[i].style.setProperty('display', 'none', 'important');
        }
        return saved;
    }

    function whenPainted(doc, root, done) {
        var left = 0;
        var finished = false;
        function finish() {
            if (finished) return;
            finished = true;
            var fonts = doc.fonts && doc.fonts.ready ? doc.fonts.ready : Promise.resolve();
            fonts.then(function () {
                requestAnimationFrame(function () { requestAnimationFrame(done); });
            }).catch(function () {
                requestAnimationFrame(done);
            });
        }
        function mark() {
            left -= 1;
            if (left <= 0) finish();
        }
        var links = root.querySelectorAll('link[rel="stylesheet"]');
        var images = root.querySelectorAll('img');
        for (var i = 0; i < links.length; i++) {
            if (links[i].sheet) continue;
            left += 1;
            links[i].addEventListener('load', mark);
            links[i].addEventListener('error', mark);
        }
        for (var j = 0; j < images.length; j++) {
            if (images[j].complete) continue;
            left += 1;
            images[j].addEventListener('load', mark);
            images[j].addEventListener('error', mark);
        }
        if (left === 0) finish();
        else setTimeout(finish, 700);
    }

    function printOnParent() {
        var parentWin = window.parent;
        var parentDoc = parentWin.document;
        var host = parentDoc.getElementById('thermal-print-host');
        if (!host) {
            host = parentDoc.createElement('div');
            host.id = 'thermal-print-host';
            parentDoc.body.appendChild(host);
        }
        host.style.cssText = 'position:fixed;left:-200mm;top:0;width:80mm;height:auto;visibility:hidden;';
        var shadow = host.shadowRoot || host.attachShadow({ mode: 'open' });
        shadow.innerHTML = headMarkup(document) + '<div class="slip-sheet">' + slipMarkup(document) + '</div>';

        whenPainted(parentDoc, shadow, function () {
            host.style.visibility = 'visible';
            var sheet = shadow.querySelector('.slip-sheet') || host;
            var mm = heightMm(parentDoc, sheet);
            applyPageSize(parentDoc, mm);

            var saved = [rememberStyle(parentDoc.documentElement), rememberStyle(parentDoc.body)].concat(hideSiblings(parentDoc, host));
            parentDoc.documentElement.style.setProperty('height', 'auto', 'important');
            parentDoc.documentElement.style.setProperty('min-height', '0', 'important');
            parentDoc.documentElement.style.setProperty('margin', '0', 'important');
            parentDoc.documentElement.style.setProperty('background', '#fff', 'important');
            parentDoc.body.style.setProperty('display', 'block', 'important');
            parentDoc.body.style.setProperty('height', 'auto', 'important');
            parentDoc.body.style.setProperty('min-height', '0', 'important');
            parentDoc.body.style.setProperty('margin', '0', 'important');
            parentDoc.body.style.setProperty('padding', '0', 'important');
            parentDoc.body.style.setProperty('overflow', 'hidden', 'important');
            parentDoc.body.style.setProperty('background', '#fff', 'important');
            host.style.setProperty('position', 'static', 'important');
            host.style.setProperty('left', 'auto', 'important');
            host.style.setProperty('width', '80mm', 'important');
            host.style.setProperty('height', 'auto', 'important');
            host.style.setProperty('visibility', 'visible', 'important');
            host.style.setProperty('page', 'thermal', 'important');

            var previousTitle = parentDoc.title;
            parentDoc.title = '\u00a0';

            function cleanup() {
                parentWin.removeEventListener('afterprint', cleanup);
                parentDoc.title = previousTitle;
                for (var i = 0; i < saved.length; i++) restoreStyle(saved[i]);
                if (host.parentNode) host.parentNode.removeChild(host);
                var pageStyle = parentDoc.getElementById('thermal-page-size');
                if (pageStyle && pageStyle.parentNode) pageStyle.parentNode.removeChild(pageStyle);
                printing = false;
            }
            parentWin.addEventListener('afterprint', cleanup);
            requestAnimationFrame(function () {
                parentWin.print();
            });
        });
    }

    function printHere() {
        var previousTitle = document.title;
        document.title = '\u00a0';
        function cleanup() {
            window.removeEventListener('afterprint', cleanup);
            document.title = previousTitle;
            printing = false;
        }
        window.addEventListener('afterprint', cleanup);
        fitThermalPage();
        requestAnimationFrame(function () {
            fitThermalPage();
            requestAnimationFrame(function () { window.print(); });
        });
    }

    window.fitThermalPage = fitThermalPage;
    window.printThermalSlip = function () {
        if (printing) return;
        printing = true;
        whenSlipReady(function () {
            if (window.parent && window.parent !== window) {
                try {
                    printOnParent();
                    return;
                } catch (err) {
                    printing = false;
                }
            }
            printHere();
        });
    };
    window.addEventListener('beforeprint', fitThermalPage);
})();
