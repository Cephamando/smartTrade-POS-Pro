<?php
$part = $pwaStaffPart ?? 'bar';
unset($pwaStaffPart);
if ($part === 'head'): ?>
    <link rel="manifest" href="app.webmanifest">
    <meta name="theme-color" content="#1c2128">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="smartTrade">
    <link rel="apple-touch-icon" href="pwa/app-icon-192.png">
<?php else: ?>
<div id="pwaInstall" data-dismiss="app-pwa-dismiss" hidden style="background:#1c2128;color:#fffaf4;padding:8px 12px;gap:8px;align-items:center;justify-content:space-between;flex-wrap:wrap;">
    <span class="small">Install smartTrade on this device. On iPhone, use Share, then Add to Home Screen.</span>
    <span style="display:flex;gap:8px;flex:0 0 auto;">
        <button type="button" class="btn btn-warning btn-sm fw-bold" data-install hidden>Install</button>
        <button type="button" class="btn btn-outline-light btn-sm" data-dismiss-install>Not now</button>
    </span>
</div>
<?php endif; ?>
