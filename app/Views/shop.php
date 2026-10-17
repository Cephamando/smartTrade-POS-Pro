<?php
$title = ($settings['shop_title'] ?? '') ?: ($settings['business_name'] ?? 'Order online');
$storeName = 'Queen Bees Restaurants';
$shopIcon = function (string $cat): string {
    $c = strtolower($cat);
    foreach ([
        'beer' => 'bi-cup-straw', 'cider' => 'bi-cup-straw', 'wine' => 'bi-cup', 'champagne' => 'bi-cup',
        'cocktail' => 'bi-tropical-storm', 'mocktail' => 'bi-tropical-storm', 'shot' => 'bi-droplet',
        'whisky' => 'bi-moisture', 'vodka' => 'bi-moisture', 'gin' => 'bi-moisture', 'rum' => 'bi-moisture',
        'brandy' => 'bi-moisture', 'cognac' => 'bi-moisture', 'liqueur' => 'bi-moisture',
        'food' => 'bi-egg-fried', 'meal' => 'bi-egg-fried', 'burger' => 'bi-egg-fried',
        'juice' => 'bi-cup-hot', 'soda' => 'bi-cup-hot', 'soft' => 'bi-cup-hot', 'mixer' => 'bi-cup-hot',
        'shisha' => 'bi-wind', 'coffee' => 'bi-cup-hot',
    ] as $word => $icon) {
        if (str_contains($c, $word)) {
            return $icon;
        }
    }
    return 'bi-basket3';
};
$money = fn($v) => 'K' . number_format((float)$v, 2);
$fx = $fx ?? ['rates' => []];
$fxRates = [];
foreach (($fx['rates'] ?? []) as $code => $row) {
    $per = (float)($row['zmw_per_unit'] ?? 0);
    if ($per > 0 && preg_match('/^[A-Z]{3}$/', (string)$code)) {
        $fxRates[(string)$code] = $per;
    }
}
$currencyBar = static function () use ($fxRates): string {
    if (!$fxRates) {
        return '';
    }
    $html = '<div class="cur-tabs" role="tablist" aria-label="Display currency"><button type="button" class="cur-tab is-on" data-cur="ZMW" onclick="setShopCurrency(\'ZMW\')">ZMW</button>';
    foreach (array_keys($fxRates) as $code) {
        $safe = htmlspecialchars($code, ENT_QUOTES);
        $html .= '<button type="button" class="cur-tab" data-cur="' . $safe . '" onclick="setShopCurrency(\'' . $safe . '\')">' . $safe . '</button>';
    }
    return $html . '</div>';
};
$fxMoney = static function ($amount) use ($money): string {
    $n = (float)$amount;
    return '<span class="js-money" data-zmw="' . htmlspecialchars((string)$n, ENT_QUOTES) . '">' . $money($n) . '</span>';
};
$shopTable = $shopTable ?? null;
$shopTables = $shopTables ?? [];
$reserveOn = !empty($reserveOn);
$reserveAmount = (float)($reserveAmount ?? 0);
$reserveLabel = trim((string)($reserveLabel ?? '')) ?: 'Table reservation';
$logoUrl = \App\Helpers\Media::url((string)($settings['logo_path'] ?? ''));
$bannerUrl = \App\Helpers\Media::url((string)($settings['shop_banner_path'] ?? ''));
$cartCount = $cartCount ?? 0;
$u = fn(string $v, array $q = []) => 'index.php?page=shop&view=' . $v . ($q ? '&' . http_build_query($q) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($storeName) ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,600;0,700;1,600;1,700&family=Source+Sans+3:wght@400;600;700&display=swap">
    <style>
        :root { --accent: #111111; --dark: #0c0c0e; --on-accent: #ffffff; --sun: #e2b04a; --gold: #c9a24a; --ink: #141414; --paper: #ffffff; --card: #ffffff; }
        * { -webkit-tap-highlight-color: transparent; }
        body { margin: 0; background: #fff8e4; color: var(--ink); font-family: "Source Sans 3", "Segoe UI", sans-serif; padding-bottom: 110px; }
        body.is-home { padding-bottom: 0; background: #0b0b0d; color: #f6ead0; }
        h1, h2, h3, h4, h5, .page-title, .display-name { font-family: "Playfair Display", Georgia, serif; }
        a { color: inherit; }
        .shop-nav { position: sticky; top: 0; z-index: 1040; background: #0b0b0d; color: #e8c56b; box-shadow: none; }
        .shop-nav a { color: #e8c56b; text-decoration: none; }
        .brand-mark { width: 38px; height: 38px; border-radius: 12px; background: #1a1408; color: #e8c56b; border: 1px solid #c9a24a; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .nav-link-soft { font-weight: 700; letter-spacing: .08em; text-transform: uppercase; font-size: .78rem; color: #e8c56b; }
        .nav-join { background: linear-gradient(180deg, #f3c65a, #c8881a); color: #1a1206; border-radius: 999px; padding: .38rem 1rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: .78rem; border: 1px solid #f8e2a2; }
        .nav-join:hover { background: linear-gradient(180deg, #ffe08a, #d9a02a); color: #1a1206; }
        .nav-link-soft:hover, .nav-link-soft.active { color: #fff4d2; }
        .btn-accent { background: #111; border: 0; color: #fff; font-weight: 700; }
        .btn-accent:hover, .btn-accent:focus { background: #000; color: #fff; }
        .shop-wrap { position: relative; max-width: 1180px; }
        .landing-screen { position: relative; height: calc(100vh - 62px); min-height: 560px; margin: -.5rem calc(50% - 50vw) 0; color: #f6ead0; display: flex; flex-direction: column; padding: 6px max(16px, calc(50vw - 620px)) 0; box-sizing: border-box; overflow: visible; background-color: #0b0b0d; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='84' height='48' viewBox='0 0 84 48'%3E%3Cpath d='M21 1 L41 12 L41 34 L21 45 L1 34 L1 12 Z M63 1 L83 12 L83 34 L63 45 L43 34 L43 12 Z' fill='none' stroke='%23d4a84a' stroke-opacity='0.16' stroke-width='1'/%3E%3C/svg%3E"); }
        .landing-screen h1 { font-weight: 700; font-size: clamp(2.8rem, 6.4vw, 5.2rem); letter-spacing: -.02em; line-height: .95; margin: 0 0 8px; background: linear-gradient(180deg, #fff1c4 0%, #e8c15a 38%, #a56b12 78%); -webkit-background-clip: text; background-clip: text; color: transparent; filter: drop-shadow(0 10px 18px rgba(0,0,0,.45)); }
        .landing-screen .tagline { font-size: clamp(1rem, 1.8vw, 1.25rem); margin: 0 auto; max-width: 36rem; color: #f0d7a0; }
        .landing-stage { position: absolute; top: 0; bottom: 0; left: 50%; width: 100vw; margin: 0 0 0 -50vw; z-index: 0; display: flex; flex-direction: column; overflow: hidden; }
        .landing-photo { position: absolute; inset: 0; background: #0b0b0d; }
        .landing-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 62%; display: block; }
        .landing-photo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(11,11,13,1) 0%, rgba(11,11,13,.92) 14%, rgba(48,28,8,.55) 32%, rgba(20,10,4,.08) 52%, rgba(0,0,0,0) 64%); pointer-events: none; }
        .landing-stage-copy { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; justify-content: space-between; padding: 176px max(16px, calc(50vw - 560px)) 28px; }
        .landing-about { max-width: 760px; margin: 56px auto; text-align: center; padding: 0 18px; color: #f6ead0; }
        .landing-about h2 { font-size: clamp(2.4rem, 5vw, 3.8rem); font-weight: 700; margin-bottom: 12px; color: #e8c56b; }
        .landing-about p { font-size: 1.2rem; }
        .menu-showcase { background: #14110e; color: #f6ead0; margin: 0 calc(50% - 50vw); padding: 42px max(16px, calc(50vw - 590px)) 28px; }
        .menu-showcase h2 { font-size: clamp(2.6rem, 5vw, 4rem); font-weight: 700; line-height: .95; color: #e8c56b; }
        .menu-showcase .btn-accent { background: linear-gradient(180deg, #f3c65a, #c48416); color: #1a1206; border: 1px solid #f8e2a2; }
        .dish-card { display: block; background: #fff; border-radius: 28px; overflow: hidden; text-decoration: none; color: inherit; height: 100%; box-shadow: 0 12px 28px rgba(0,0,0,.08); }
        .dish-card img, .dish-card .dish-fallback { width: 100%; height: 190px; object-fit: cover; display: block; background: #111; }
        .dish-card .dish-fallback { color: var(--sun); display: flex; align-items: center; justify-content: center; font-size: 2.4rem; }
        .dish-card div { padding: 14px 16px 18px; text-align: center; }
        .dish-card strong { display: block; font-family: "Playfair Display", Georgia, serif; font-size: 1.25rem; }
        .dish-card.alt { background: var(--sun); }
        .dish-strip { position: relative; z-index: 3; isolation: isolate; flex: 0 0 168px; height: 168px; min-height: 168px; margin: 0; overflow: visible; background: transparent; perspective: 1400px; }
        .dish-flip { position: relative; width: 100%; height: 100%; transform-style: preserve-3d; transition: transform .9s cubic-bezier(.45, .05, .2, 1); }
        .dish-flip.is-spot { transform: rotateX(180deg); }
        .dish-face { position: absolute; inset: 0; overflow: hidden; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .dish-face-menu { transform: translateZ(2px); }
        .dish-face-spot { transform: rotateX(180deg); pointer-events: none; }
        .dish-strip.is-flat { perspective: none; }
        .dish-strip.is-flat .dish-flip { transform: none; transform-style: flat; }
        .dish-strip.is-flat .dish-face,
        .dish-strip.is-flat .dish-face-menu { transform: none; backface-visibility: visible; -webkit-backface-visibility: visible; }
        .dish-flip.is-spot .dish-face-menu { pointer-events: none; }
        .dish-flip.is-spot .dish-face-spot { pointer-events: auto; }
        .dish-track { display: flex; height: 100%; width: max-content; align-items: center; gap: 16px; padding: 8px 0; animation: event-advert-scroll 42s linear infinite; }
        .dish-face-spot .dish-track { animation-duration: 36s; }
        .dish-track.is-still { animation: none; width: 100%; justify-content: center; }
        .dish-strip:hover .dish-track, .dish-strip:focus-within .dish-track { animation-play-state: paused; }
        .menu-slide, .spot-card { position: relative; display: block; width: 228px; height: 132px; flex: 0 0 228px; margin: 0; padding: 0; border: 2px solid #c9a24a; border-radius: 16px; overflow: hidden; background: #1a140c; box-shadow: 0 10px 24px rgba(0,0,0,.45); color: #fff; text-decoration: none; }
        button.spot-card { cursor: pointer; font: inherit; text-align: left; }
        .menu-slide img, .spot-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .spot-card::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 54%; background: linear-gradient(to top, rgba(0,0,0,.62), transparent); pointer-events: none; }
        .event-chip { position: absolute; left: 10px; right: 10px; bottom: 8px; z-index: 1; display: inline-flex; align-items: center; gap: 6px; width: max-content; max-width: calc(100% - 20px); background: linear-gradient(180deg, #f3c65a, #c48416); color: #1a1206; border-radius: 999px; padding: 5px 10px; font-weight: 800; font-size: .68rem; letter-spacing: .02em; box-shadow: 0 8px 18px rgba(0,0,0,.35); }
        .event-chip i { font-size: .85rem; }
        #eventReg .modal-content { background: #fff8e4; color: #141414; border: 2px solid #c9a24a; border-radius: 22px; }
        #eventReg .modal-body { position: relative; padding: 16px; }
        #eventReg .btn-close { position: sticky; float: right; z-index: 2; margin: 0 0 -28px; background-color: #f3c65a; border-radius: 50%; padding: .65rem; opacity: 1; }
        #eventReg .event-reg-cover { width: 100%; max-height: 240px; object-fit: cover; border-radius: 18px; display: block; }
        #eventReg .shop-hero { margin-top: 12px; }
        #eventReg label.border { background: #fff; }
        .account-toggle { border: 0; background: transparent; padding: 0; display: inline-flex; align-items: center; gap: .3rem; }
        .account-menu { border: 0; border-radius: 16px; padding: 8px; min-width: 196px; box-shadow: 0 16px 36px rgba(0,0,0,.14); }
        .account-menu .dropdown-item { border-radius: 10px; font-weight: 700; padding: .55rem .75rem; }
        .account-menu .dropdown-item:hover, .account-menu .dropdown-item:focus { background: var(--sun); color: #111; }
        .account-menu form { margin: 0; }
        .club-hero { background: var(--sun); color: var(--ink); margin: -.5rem calc(50% - 50vw) 0; padding: 18px max(18px, calc(50vw - 560px)) 42px; text-align: center; }
        .club-hero h1 { font-weight: 700; font-size: clamp(2.8rem, 7vw, 5.4rem); letter-spacing: -.03em; line-height: .95; margin: 0 0 12px; }
        .club-hero .tagline { font-size: clamp(1.15rem, 2vw, 1.55rem); margin: 0 auto 22px; max-width: 36rem; }
        .club-hero-photo { width: min(920px, 100%); height: min(420px, 52vw); object-fit: cover; border-radius: 36px; display: block; margin: 8px auto 22px; }
        .hero-pills { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }
        .hero-pill { background: linear-gradient(180deg, #f3c65a, #c48416); color: #1a1206; border: 1px solid #f8e2a2; border-radius: 999px; padding: .55rem 1.15rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; font-size: .78rem; text-decoration: none; box-shadow: 0 8px 18px rgba(0,0,0,.35); }
        .hero-pill:hover { background: linear-gradient(180deg, #ffe08a, #d9a02a); color: #1a1206; }
        .club-hero .event-advert, .club-hero .promo-banner { margin-top: 22px; text-align: left; }
        .shop-board { display: block; margin-top: 18px; }
        .menu-top { background: var(--sun); margin: -.5rem calc(50% - 50vw) 18px; padding: 28px max(16px, calc(50vw - 560px)) 22px; }
        .menu-top h2 { font-size: clamp(2.6rem, 6vw, 4.2rem); font-weight: 700; line-height: .95; margin: 0; }
        .cat-panel { position: sticky; top: 72px; background: #111; color: #fff; border-radius: 28px; padding: 12px; box-shadow: 0 10px 28px rgba(0, 0, 0, .12); max-height: calc(100vh - 96px); overflow: auto; }
        .cat-panel-title { font-family: "Playfair Display", Georgia, serif; font-size: 1.15rem; font-weight: 700; color: var(--sun); padding: 8px 10px 10px; letter-spacing: 0; text-transform: none; }
        .cat-tab, .cat-group-btn { display: flex; width: 100%; justify-content: space-between; align-items: center; gap: 8px; border: 0; background: transparent; border-radius: 999px; padding: .55rem .85rem; font-weight: 700; color: #fff; text-align: left; }
        .cat-tab .count, .cat-group-btn .count { opacity: .7; font-size: .78rem; font-weight: 700; }
        .cat-tab.active, .cat-group-btn.active { background: var(--sun); color: #111; }
        .cat-tab.active .count, .cat-group-btn.active .count { opacity: .85; }
        .cat-tab:hover, .cat-group-btn:hover { background: rgba(255,255,255,.1); }
        .cat-tab.active:hover, .cat-group-btn.active:hover { background: var(--sun); }
        .cat-children { padding: 2px 0 6px 10px; }
        .cat-child { font-weight: 600; font-size: .92rem; }
        .cat-chevron { font-size: .75rem; opacity: .8; transition: transform .15s ease; }
        .cat-group-btn[aria-expanded="true"] .cat-chevron { transform: rotate(180deg); }
        body.is-menu .item-face span { background: var(--sun); color: #111; border-radius: 999px; padding: 2px 8px; }
        .cat-panel hr { margin: 8px 6px; border-color: rgba(246,201,14,.35); opacity: 1; }
        .shop-hero { margin: 8px 0 16px; padding: 36px 28px; border-radius: 32px; color: var(--ink); background: var(--sun); box-shadow: none; overflow: hidden; position: relative; text-align: center; }
        .shop-hero .eyebrow { letter-spacing: .18em; text-transform: uppercase; font-size: .72rem; font-weight: 700; }
        .shop-hero h1 { font-weight: 700; letter-spacing: -.03em; margin: 6px 0 8px; font-size: clamp(2.2rem, 5vw, 3.6rem); }
        .shop-hero p { margin: 0 auto; max-width: 36rem; }
        .search-pill { background: #fff; border-radius: 999px; box-shadow: 0 8px 24px rgba(0, 0, 0, .06); overflow: hidden; border: 1px solid #efe7c4; }
        .search-pill .form-control, .search-pill .input-group-text { border: 0; background: transparent; }
        .search-pill .form-control:focus { box-shadow: none; }
        .store-kicker { margin: 18px 0 10px; letter-spacing: .18em; text-transform: uppercase; font-size: .72rem; font-weight: 800; }
        .store-tabs { display: flex; gap: 10px; justify-content: flex-start; overflow-x: auto; padding: 2px 2px 8px; scroll-snap-type: x proximity; scrollbar-width: none; }
        .store-tabs::-webkit-scrollbar { display: none; }
        .store-tab { flex: 0 0 auto; scroll-snap-align: center; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; color: #1a1206; background: rgba(255,255,255,.42); border: 1px solid rgba(26,18,6,.14); border-radius: 999px; padding: .62rem 1.05rem; font-weight: 800; white-space: nowrap; }
        .store-tab i { color: #8a5a10; }
        .store-tab.is-on { background: #111; color: var(--sun); border-color: #111; box-shadow: 0 10px 22px rgba(0,0,0,.18); }
        .store-tab.is-on i { color: var(--sun); }
        .store-tab:hover { background: #fff; color: #1a1206; }
        .store-tab.is-on:hover { background: #111; color: var(--sun); }
        .menu-tools { display: flex; flex-direction: column; gap: 12px; margin-bottom: 8px; }
        .ebay-bar { display: flex; gap: 10px; align-items: stretch; }
        .cat-drop { position: relative; flex: 0 0 auto; }
        .cat-drop-btn { height: 100%; min-height: 46px; border: 1px solid #efe7c4; background: #fff; border-radius: 999px; padding: 0 16px; font-weight: 800; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
        .cat-drop-btn[aria-expanded="true"] { background: #111; color: var(--sun); }
        .cat-menu { position: absolute; z-index: 30; top: calc(100% + 8px); left: 0; width: min(860px, calc(100vw - 32px)); background: #fff; border-radius: 18px; box-shadow: 0 18px 48px rgba(0,0,0,.16); padding: 16px 18px 18px; max-height: min(70vh, 520px); overflow: auto; }
        .cat-menu[hidden] { display: none; }
        .cat-all { display: block; width: 100%; text-align: left; border: 0; background: transparent; font-weight: 800; padding: 6px 0 12px; margin-bottom: 8px; border-bottom: 1px solid #f3edd4; }
        .cat-columns { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px 28px; }
        .cat-group-btn, .cat-child { display: block; width: 100%; text-align: left; border: 0; background: transparent; padding: 3px 0; border-radius: 0; color: #3d3428; }
        .cat-group-btn { font-weight: 800; color: #111; margin-bottom: 4px; }
        .cat-child { font-weight: 600; padding-left: 14px; }
        .cat-child::before { content: "›"; margin-right: 6px; color: #c9a24a; font-weight: 800; }
        .cat-child .count, .cat-all .count { margin-left: 6px; font-size: .78rem; opacity: .55; font-weight: 700; }
        .cat-all:hover, .cat-group-btn:hover, .cat-child:hover, .cat-all.active, .cat-group-btn.active, .cat-child.active { color: #8a5a10; background: transparent; }
        .avail-tabs { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .avail-tab { border: 1px solid #efe7c4; background: #fff; color: #5c5344; border-radius: 999px; padding: .5rem .95rem; font-weight: 800; box-shadow: 0 8px 18px rgba(0,0,0,.04); white-space: nowrap; flex: 0 0 auto; }
        .avail-tab span { margin-left: 6px; font-size: .78rem; opacity: .65; }
        .avail-tab.is-on { background: #111; color: var(--sun); border-color: #111; }
        .avail-tab.is-on span { opacity: .9; }
        .cur-tabs { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
        .cur-tab { border: 1px solid #efe7c4; background: #fff; color: #5c5344; border-radius: 999px; padding: .4rem .8rem; font-weight: 800; font-size: .82rem; letter-spacing: .04em; }
        .cur-tab.is-on { background: #111; color: var(--sun); border-color: #111; }
        @media (min-width: 768px) { .store-tabs { justify-content: center; flex-wrap: wrap; overflow: visible; } }
        .item-card { border: 0; border-radius: 28px; background: #fff; box-shadow: 0 12px 30px rgba(0, 0, 0, .08); overflow: hidden; }
        .menu-rail { margin: 6px 0 28px; }
        .menu-rail.is-empty { display: none; }
        #menuRails.is-side { display: flex; flex-wrap: wrap; justify-content: center; align-items: flex-start; gap: 28px 48px; }
        #menuRails.is-side .menu-rail { margin: 0; }
        #menuRails.is-side .rail-track.is-one .dish-tile,
        #menuRails.is-side .rail-track.is-pair .dish-tile,
        #menuRails.is-side .rail-track.is-trio .dish-tile,
        #menuRails.is-side .rail-track.is-quad .dish-tile { flex-basis: 176px; }
        #menuRails.is-side .dish-photo { height: 148px; }
        .rail-title { margin: 0 0 14px; text-align: center; font-style: italic; font-weight: 600; font-size: clamp(1.7rem, 3vw, 2.35rem); }
        .rail-kicker { display: block; margin-bottom: 2px; font-family: "Source Sans 3", "Segoe UI", sans-serif; font-style: normal; font-size: .72rem; font-weight: 800; letter-spacing: .16em; text-transform: uppercase; color: #8a5a10; }
        .rail-kicker[hidden] { display: none; }
        .menu-crumb { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; margin: 2px 0 14px; font-weight: 700; color: #5c5344; }
        .menu-crumb[hidden] { display: none; }
        .menu-crumb button { border: 0; background: transparent; padding: 0; font-weight: 800; color: #8a5a10; }
        .menu-crumb button:hover { text-decoration: underline; }
        .menu-crumb .crumb-here { color: #111; font-weight: 800; }
        .menu-crumb .crumb-sep { opacity: .4; }
        .rail-frame { position: relative; }
        .rail-track { display: flex; gap: 16px; overflow-x: auto; scroll-snap-type: x mandatory; scrollbar-width: none; padding: 6px 4px 18px; }
        .rail-track.is-few { justify-content: center; overflow: visible; flex-wrap: wrap; scroll-snap-type: none; }
        .rail-track.is-one .dish-tile { flex-basis: min(280px, 100%); }
        .rail-track.is-one .dish-photo { height: 210px; }
        .rail-track.is-pair .dish-tile { flex-basis: 220px; }
        .rail-track.is-pair .dish-photo { height: 180px; }
        .rail-track.is-trio .dish-tile { flex-basis: 200px; }
        .rail-track.is-quad .dish-tile { flex-basis: 190px; }
        .rail-track::-webkit-scrollbar { display: none; }
        .dish-tile { flex: 0 0 176px; scroll-snap-align: start; background: #fff; border-radius: 22px; box-shadow: 0 12px 28px rgba(40, 24, 4, .08); overflow: hidden; text-align: center; }
        .dish-photo { height: 148px; background: #161616 center/cover no-repeat; position: relative; }
        .dish-photo i { position: absolute; right: 12px; top: 10px; font-size: 2.6rem; color: rgba(255,255,255,.28); }
        .dish-chip { position: absolute; left: 10px; bottom: 10px; background: var(--sun); color: #111; border-radius: 999px; padding: 2px 8px; font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
        .dish-copy { padding: 12px 12px 14px; }
        .dish-tile .item-name { font-size: 1.12rem; min-height: 2.4em; }
        .dish-tile .price { display: block; margin-top: 4px; }
        .dish-add { width: 36px; height: 36px; margin-top: 10px; border: 0; border-radius: 50%; background: #111; color: var(--sun); font-size: 1.15rem; line-height: 1; box-shadow: 0 8px 16px rgba(0,0,0,.16); }
        .dish-add:hover { background: #000; color: #ffe08a; }
        .dish-sold { display: inline-block; margin-top: 10px; color: #8a7b62; font-size: .78rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .dish-tile.unavailable { opacity: .72; }
        .rail-next { position: absolute; right: -4px; top: 58px; z-index: 2; width: 52px; height: 52px; border: 0; border-radius: 50%; background: #fff; color: #111; box-shadow: 0 10px 24px rgba(0,0,0,.16); font-size: 1.35rem; }
        .rail-next:hover { background: var(--sun); }
        .rail-next[hidden] { display: none; }
        .menu-pager { display: flex; flex-direction: column; align-items: center; gap: 10px; margin: 22px 0 6px; }
        .menu-pager[hidden] { display: none; }
        .menu-pager-status { margin: 0; font-weight: 700; color: #5c5344; }
        .menu-pager-pages { display: flex; flex-wrap: wrap; justify-content: center; align-items: center; gap: 8px; }
        .menu-page { min-width: 44px; height: 44px; padding: 0 14px; border-radius: 999px; border: 1px solid #c9a24a; background: #fff; color: #1a1206; font-weight: 800; }
        .menu-page.is-on { background: linear-gradient(180deg, #f3c65a, #c48416); border-color: #f8e2a2; }
        .menu-page:disabled { opacity: .38; }
        .menu-page-gap { min-width: 24px; text-align: center; font-weight: 800; color: #8a7b62; }
        #menuGrid { scroll-margin-top: 84px; }
        .item-card.unavailable { opacity: .62; }
        .item-face { height: 168px; display: flex; align-items: flex-end; padding: 12px 14px; color: #fff; background: #111; position: relative; }
        .item-face.item-photo { height: 210px; background-size: cover; background-position: center; }
        .item-face.item-photo span { background: rgba(20, 10, 4, .45); border-radius: 999px; padding: 2px 8px; }
        .promo-banner { display: block; width: min(920px, 100%); max-height: 220px; object-fit: cover; border-radius: 28px; margin: 18px auto 0; }
        .event-advert { overflow: hidden; margin: 22px auto 0; border-radius: 28px; background: #fff; color: #111; max-width: 920px; }
        .event-advert-track { display: flex; width: max-content; animation: event-advert-scroll 32s linear infinite; }
        .event-advert:hover .event-advert-track, .event-advert:focus-within .event-advert-track { animation-play-state: paused; }
        .event-advert-card { display: flex; align-items: center; gap: 12px; min-width: 340px; padding: 10px 16px; color: inherit; text-decoration: none; border-right: 1px solid #efe7c4; }
        .event-advert-card img { width: 84px; height: 52px; object-fit: cover; border-radius: 12px; flex-shrink: 0; }
        .event-advert-card strong { display: block; font-family: "Playfair Display", Georgia, serif; }
        .event-advert-card span { display: block; font-size: .78rem; opacity: .75; }
        @keyframes event-advert-scroll { from { transform: translateX(0); } to { transform: translateX(-50%); } }
        @media (prefers-reduced-motion: reduce) { .event-advert { overflow-x: auto; } .event-advert-track { animation: none; } }
        .item-face i { position: absolute; right: 10px; top: 8px; font-size: 3.2rem; opacity: .22; }
        .item-face span { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .item-name { font-family: "Playfair Display", Georgia, serif; font-size: 1.25rem; font-weight: 700; line-height: 1.15; }
        .price { color: var(--ink); font-weight: 700; }
        .menu-heading { font-size: clamp(2.4rem, 5vw, 3.6rem); font-weight: 700; line-height: .95; margin: 0 0 16px; }
        .cart-bar { position: fixed; left: 50%; transform: translateX(-50%); bottom: 14px; width: min(680px, calc(100% - 20px)); background: #111; color: #fff; border-radius: 999px; padding: 8px 8px 8px 18px; box-shadow: 0 16px 40px rgba(0, 0, 0, .28); z-index: 1030; }
        .cart-bar .btn-accent { background: #fff; color: #111; }
        .cart-bar .btn-accent:hover { background: var(--sun); color: #111; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .step { width: 36px; height: 36px; border-radius: 50%; background: #f6f1df; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .step.done { background: var(--sun); color: #111; }
        .form-control, .form-select { border-radius: 14px; border-color: #eadfb0; background: #fff; }
        .form-control:focus, .form-select:focus { border-color: var(--sun); box-shadow: 0 0 0 .2rem rgba(246, 201, 14, .35); }
        .page-title { font-weight: 700; letter-spacing: -.03em; }
        body.is-home .shop-foot { background: #0b0b0d; color: #f6ead0; }
        body.is-home .shop-foot a { color: #e8c56b; }
        .shop-foot { background: var(--sun); color: #141414; text-align: center; padding: 56px 16px 72px; margin: 36px calc(50% - 50vw) -110px; }
        .shop-foot h2 { font-size: clamp(2.4rem, 6vw, 4rem); font-weight: 700; margin: 0 0 18px; }
        .shop-foot p { margin: 0 0 14px; }
        .shop-foot strong { font-family: "Playfair Display", Georgia, serif; font-size: 1.35rem; }
        .list-group-item { border-color: #f3edd4; }
        @media (prefers-reduced-motion: reduce) { .dish-flip { transition: none; } }
        @media (max-width: 576px) { .landing-screen { height: auto; min-height: calc(100vh - 54px); overflow: visible; } .landing-screen h1 { font-size: 2.3rem; } .landing-stage-copy { padding-top: 126px; } .dish-strip { flex-basis: 118px; height: 118px; min-height: 118px; } .menu-slide, .spot-card { width: 168px; height: 96px; flex-basis: 168px; } .event-chip { font-size: .62rem; padding: 4px 8px; } .club-hero { padding-top: 8px; } .club-hero h1 { font-size: 2.6rem; } .club-hero-photo { height: 220px; border-radius: 24px; } .shop-hero { padding: 22px 18px; border-radius: 22px; } .shop-hero h1 { font-size: 2rem; } .ebay-bar { flex-direction: column; } .cat-drop-btn { width: 100%; justify-content: space-between; } .cat-columns { grid-template-columns: 1fr 1fr; } .cat-menu { width: calc(100vw - 24px); } .store-tab, .avail-tab { padding: .45rem .75rem; font-size: .82rem; } .avail-tabs { flex-wrap: nowrap; justify-content: flex-start; overflow-x: auto; scrollbar-width: none; } .avail-tabs::-webkit-scrollbar { display: none; } .dish-tile { flex-basis: 148px; } .rail-next { width: 42px; height: 42px; top: 52px; } .shop-foot { margin-bottom: -110px; } }
        body.is-menu { background: #f4f7fa; }
        body.is-menu .menu-top { background: transparent; color: #1c2430; text-align: left; margin: 0 0 8px; padding: 18px 0 8px; }
        body.is-menu .menu-top h2 { font-family: "Source Sans 3", "Segoe UI", sans-serif; font-size: 1.45rem; font-weight: 700; letter-spacing: 0; line-height: 1.2; color: #1c2430; }
        body.is-menu .store-kicker { color: #8b95a1; text-align: left; }
        body.is-menu .store-tabs { justify-content: flex-start; }
        body.is-menu .menu-crumb { justify-content: flex-start; }
        body.is-menu .rail-title { margin: 18px 2px 12px; text-align: left; font-family: "Source Sans 3", "Segoe UI", sans-serif; font-style: normal; font-weight: 700; font-size: .78rem; letter-spacing: .16em; text-transform: uppercase; color: #8b95a1; }
        body.is-menu .rail-kicker { color: #8b95a1; letter-spacing: .16em; }
        body.is-menu .rail-track,
        body.is-menu .rail-track.is-few,
        body.is-menu #menuRails.is-side .rail-track { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 26px 18px; overflow: visible; scroll-snap-type: none; justify-content: stretch; padding: 0 2px 8px; }
        body.is-menu .dish-tile,
        body.is-menu .rail-track.is-one .dish-tile,
        body.is-menu .rail-track.is-pair .dish-tile,
        body.is-menu .rail-track.is-trio .dish-tile,
        body.is-menu .rail-track.is-quad .dish-tile,
        body.is-menu #menuRails.is-side .rail-track .dish-tile { flex: none; width: auto; background: transparent; border-radius: 0; box-shadow: none; overflow: visible; text-align: left; }
        body.is-menu .dish-photo,
        body.is-menu .rail-track.is-one .dish-photo,
        body.is-menu .rail-track.is-pair .dish-photo,
        body.is-menu #menuRails.is-side .dish-photo { height: auto; aspect-ratio: 1.45 / 1; border-radius: 18px; background-color: #e7eef3; overflow: hidden; }
        body.is-menu .dish-photo i { color: #9aa6b2; right: 14px; top: 12px; opacity: 1; }
        body.is-menu .dish-chip { display: none; }
        body.is-menu .dish-copy { position: relative; padding: 10px 36px 0 2px; }
        body.is-menu .dish-tile .item-name { font-family: "Source Sans 3", "Segoe UI", sans-serif; font-size: .98rem; font-weight: 700; line-height: 1.25; color: #1c2430; min-height: 0; }
        body.is-menu .dish-tile .price { display: block; margin-top: 2px; color: #8b95a1; font-weight: 600; font-size: .9rem; }
        body.is-menu .dish-add { position: absolute; right: 0; top: 8px; width: 28px; height: 28px; margin: 0; background: #fff; color: #1c2430; border: 1px solid #e3eaf0; box-shadow: 0 4px 12px rgba(28, 36, 48, .08); font-size: .85rem; }
        body.is-menu .dish-add:hover { background: #1c2430; color: #fff; }
        body.is-menu .dish-sold { margin-top: 6px; color: #8b95a1; }
        body.is-menu .rail-next { display: none; }
        body.is-menu .shop-foot { background: #eef2f6; color: #1c2430; }
        @media (max-width: 720px) { body.is-menu .rail-track, body.is-menu .rail-track.is-few, body.is-menu #menuRails.is-side .rail-track { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 12px; } }
    </style>
</head>
<body class="<?= $view === 'home' ? 'is-home' : ($view === 'menu' ? 'is-menu' : '') ?>">
<nav class="shop-nav py-2">
    <div class="container shop-wrap d-flex justify-content-between align-items-center gap-2">
        <a href="<?= $u('home') ?>" class="d-flex align-items-center gap-2 text-truncate">
            <?php if ($logoUrl !== ''): ?>
                <img id="shopLogo" src="<?= htmlspecialchars($logoUrl) ?>" alt="" style="height:38px;width:38px;object-fit:cover;border-radius:12px;background:#fff;">
            <?php else: ?>
                <span class="brand-mark"><?= htmlspecialchars(strtoupper(substr($storeName, 0, 1))) ?></span>
            <?php endif; ?>
            <span class="fw-bold text-truncate"><?= htmlspecialchars($storeName) ?></span>
        </a>
        <div class="d-flex align-items-center gap-3 small flex-shrink-0">
            <a href="<?= $u('home') ?>" class="nav-link-soft<?= $view === 'home' ? ' active' : '' ?>">Home</a>
            <a href="<?= $u('menu') ?>" class="nav-link-soft<?= $view === 'menu' ? ' active' : '' ?>">Menu</a>
            <a href="<?= $u('events') ?>" class="nav-link-soft<?= ($view === 'events' || $view === 'event') ? ' active' : '' ?>">Events</a>
            <a href="<?= $u('cart') ?>" class="nav-link-soft position-relative"><i class="bi bi-bag"></i><?php if ($cartCount): ?> <span class="badge rounded-pill text-dark" style="background:var(--sun)"><?= (int)$cartCount ?></span><?php endif; ?></a>
            <?php if ($customer): ?>
                <a href="<?= $u('orders') ?>" class="nav-link-soft"><i class="bi bi-receipt"></i> <span class="d-none d-sm-inline">Orders</span></a>
                <div class="dropdown">
                    <button class="nav-link-soft account-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end account-menu">
                        <li><a class="dropdown-item" href="<?= $u('account') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= $u('account') ?>#settings"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="<?= $u('account') ?>">
                                <input type="hidden" name="logout" value="1">
                                <button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Log out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            <?php else: ?>
                <a href="<?= $u('login') ?>" class="nav-link-soft">Sign in</a>
                <a href="<?= $u('register') ?>" class="nav-join">Join</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container py-2 shop-wrap">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> shadow-sm"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($view === 'closed'): ?>
    <section class="shop-hero text-center">
        <div class="eyebrow">Tonight's kitchen</div>
        <h1>Online ordering is closed</h1>
        <p class="mx-auto"><?= !empty($settings['shop_phone']) ? 'Come and see us in store, or call ' . htmlspecialchars($settings['shop_phone']) . '.' : 'Come and see us in store. We will be taking orders here again soon.' ?></p>
    </section>

<?php elseif ($view === 'home'): ?>
    <?php
        $intro = trim((string)($settings['shop_intro'] ?? ''));
        if ($intro === '') {
            $intro = 'Delicious food for every mood';
        }
        $categoryCards = [];
        $seenCats = [];
        foreach ($products ?? [] as $p) {
            $catName = (string)$p['category'];
            $photo = \App\Helpers\Media::url((string)($p['image_path'] ?? ''));
            if ($photo === '' || isset($seenCats[$catName])) {
                continue;
            }
            $seenCats[$catName] = true;
            $label = strcasecmp($catName, 'FOOD') === 0 ? 'SNACK' : $catName;
            $categoryCards[] = ['id' => (int)$p['id'], 'name' => $label, 'photo' => $photo, 'cat' => $catName];
            if (count($categoryCards) >= 7) {
                break;
            }
        }
        $menuSlides = [];
        foreach ($products ?? [] as $p) {
            $photo = \App\Helpers\Media::url((string)($p['image_path'] ?? ''));
            if ($photo === '') {
                continue;
            }
            $menuSlides[] = [
                'src' => $photo,
                'name' => (string)$p['name'],
                'href' => $u('menu', ['cat' => (string)$p['category']]),
            ];
        }
        $eventSlides = [];
        foreach ($events ?? [] as $ev) {
            $eventSlides[] = $ev;
        }
        $promoSlides = [];
        foreach (\App\Helpers\Media::folder('promo') as $src) {
            $promoSlides[] = [
                'src' => $src,
                'name' => 'Slideshow',
                'href' => $u('menu'),
            ];
        }
        $scrollSlides = $promoSlides ?: $menuSlides;
        $spotSlides = [];
        foreach ($eventSlides as $ev) {
            $spotSlides[] = ['kind' => 'event', 'event' => $ev];
        }
        $fillStrip = function (array $rows): array {
            if (!$rows) {
                return [];
            }
            $filled = $rows;
            $pool = $rows;
            while (count($filled) < 6) {
                $filled = array_merge($filled, $pool);
            }
            return $filled;
        };
        $scrollLoop = $fillStrip($scrollSlides);
        $spotScroll = count($spotSlides) > 2;
        $spotLoop = $spotScroll ? $fillStrip($spotSlides) : $spotSlides;
    ?>
    <section class="landing-screen text-center">
        <?php if ($scrollLoop || $spotLoop): ?>
        <div class="dish-strip<?= empty($spotLoop) ? ' is-flat' : '' ?>" id="dishStrip" aria-label="Menu pictures">
            <div class="dish-flip" id="dishFlip">
                <?php if ($scrollLoop): ?>
                <div class="dish-face dish-face-menu">
                    <div class="dish-track">
                        <?php for ($copy = 0; $copy < 2; $copy++): foreach ($scrollLoop as $slide): ?>
                            <a class="menu-slide" href="<?= htmlspecialchars($slide['href']) ?>">
                                <img src="<?= htmlspecialchars($slide['src']) ?>" alt="<?= htmlspecialchars($slide['name']) ?>">
                            </a>
                        <?php endforeach; endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($spotLoop): ?>
                <div class="dish-face dish-face-spot">
                    <div class="dish-track<?= $spotScroll ? '' : ' is-still' ?>">
                        <?php for ($copy = 0; $copy < ($spotScroll ? 2 : 1); $copy++): foreach ($spotLoop as $slide): ?>
                            <?php if ($slide['kind'] === 'event'):
                                $ev = $slide['event'];
                                $cover = \App\Helpers\Media::url((string)($ev['cover_path'] ?? ''));
                            ?>
                            <button type="button" class="spot-card" data-event-slug="<?= htmlspecialchars((string)$ev['slug']) ?>" aria-label="Promo | click here for details">
                                <?php if ($cover !== ''): ?><img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars((string)$ev['title']) ?>"><?php endif; ?>
                                <span class="event-chip"><i class="bi bi-stars"></i> Promo | click here for details</span>
                            </button>
                            <?php endif; ?>
                        <?php endforeach; endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="landing-stage">
            <div class="landing-photo">
                <?php if ($bannerUrl !== ''): ?><img id="shopBanner" src="<?= htmlspecialchars($bannerUrl) ?>" alt=""><?php endif; ?>
            </div>
            <div class="landing-stage-copy">
                <div>
                    <h1><?= htmlspecialchars($storeName) ?></h1>
                    <p class="tagline"><?= htmlspecialchars($intro) ?></p>
                </div>
                <div class="hero-pills">
                    <a class="hero-pill" href="<?= $u('menu') ?>">View our menu</a>
                    <a class="hero-pill" href="<?= $u('events') ?>">Events</a>
                    <a class="hero-pill" href="#about">About us</a>
                </div>
            </div>
        </div>
    </section>
    <section class="landing-about" id="about">
        <h2>About us</h2>
        <p><?= htmlspecialchars($intro) ?></p>
    </section>
    <?php if ($categoryCards): ?>
    <section class="menu-showcase">
        <div class="row g-4 align-items-center">
            <div class="col-lg-3">
                <h2>Order online</h2>
                <a class="btn btn-accent rounded-0 px-4 mt-2" href="<?= $u('menu') ?>">View full menu</a>
            </div>
            <?php foreach (array_slice($categoryCards, 0, 3) as $card): ?>
            <div class="col-6 col-lg-3">
                <a class="dish-card" data-id="<?= (int)$card['id'] ?>" href="<?= $u('menu', ['cat' => $card['cat']]) ?>">
                    <img src="<?= htmlspecialchars($card['photo']) ?>" alt="">
                    <div><strong><?= htmlspecialchars($card['name']) ?></strong></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($categoryCards) > 3): ?>
        <div class="row g-3 mt-1">
            <?php foreach (array_slice($categoryCards, 3) as $i => $card): ?>
            <div class="col-6 col-md-3">
                <a class="dish-card<?= $i % 2 === 0 ? ' alt' : '' ?>" data-id="<?= (int)$card['id'] ?>" href="<?= $u('menu', ['cat' => $card['cat']]) ?>">
                    <img src="<?= htmlspecialchars($card['photo']) ?>" alt="">
                    <div><strong><?= htmlspecialchars($card['name']) ?></strong></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

<?php elseif ($view === 'menu'): ?>
    <?php
        $shopLocations = $shopLocations ?? [];
        $shopLocationId = (int)($shopLocationId ?? 0);
        $shopLocationName = '';
        foreach ($shopLocations as $locRow) {
            if ((int)$locRow['id'] === $shopLocationId) {
                $shopLocationName = (string)$locRow['name'];
                break;
            }
        }
        $shopFamily = function (string $name, string $parent): string {
            $blob = strtoupper(trim($parent . ' ' . $name));
            if (preg_match('/FOOD|MEAL|SNACK|BURGER/', $blob)) return 'Food';
            if (preg_match('/BEER|CIDER/', $blob)) return 'Beer & cider';
            if (preg_match('/WINE|CHAMPAGNE|SPARKLING/', $blob)) return 'Wine';
            if (preg_match('/COCKTAIL|MOCKTAIL|SHOT/', $blob)) return 'Cocktails';
            if (preg_match('/JUICE|MIXER|SODA|SOFT|WATER/', $blob)) return 'Soft drinks';
            if (preg_match('/BRANDY|COGNAC|GIN|LIQUEUR|RUM|VODKA|WHISK|TEQUILA/', $blob)) return 'Spirits';
            if (str_contains($blob, 'SHISHA')) return 'Shisha';
            return 'More';
        };
        $groups = [];
        $inStockCount = 0;
        foreach ($products as $i => $p) {
            $family = $shopFamily((string)$p['category'], (string)($p['parent_name'] ?? ''));
            $products[$i]['family'] = $family;
            $groups[$family][(string)$p['category']] = ($groups[$family][(string)$p['category']] ?? 0) + 1;
            if (!empty($p['available'])) {
                $inStockCount++;
            }
        }
        $soldOutCount = count($products) - $inStockCount;
        $menuGroups = [];
        foreach (['Food', 'Beer & cider', 'Wine', 'Spirits', 'Cocktails', 'Soft drinks', 'Shisha', 'More'] as $familyName) {
            if (empty($groups[$familyName])) continue;
            ksort($groups[$familyName]);
            $menuGroups[$familyName] = $groups[$familyName];
        }
    ?>
    <section class="menu-top">
        <h2>Order online</h2>
        <?php if ($shopLocations): ?>
        <p class="store-kicker">Choose a store</p>
        <div class="store-tabs" role="tablist" aria-label="Store">
            <?php foreach ($shopLocations as $locRow):
                $storeOn = (int)$locRow['id'] === $shopLocationId;
            ?>
            <a class="store-tab<?= $storeOn ? ' is-on' : '' ?>" role="tab" aria-selected="<?= $storeOn ? 'true' : 'false' ?>" href="index.php?page=shop&amp;view=menu&amp;loc=<?= (int)$locRow['id'] ?>" onclick="return goStore(event, this)"><i class="bi bi-geo-alt-fill" aria-hidden="true"></i><span><?= htmlspecialchars($locRow['name']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <div class="shop-board">
    <div class="menu-tools">
        <div class="ebay-bar">
            <div class="cat-drop">
                <button type="button" class="cat-drop-btn" aria-expanded="false" aria-haspopup="true" onclick="toggleCatMenu(this)">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                    <span id="catDropLabel">Shop by category</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="cat-menu" hidden>
                    <button type="button" class="cat-all active" data-cat="" onclick="pickAll(this)">All categories <span class="count"><?= count($products) ?></span></button>
                    <div class="cat-columns">
                        <?php foreach ($menuGroups as $familyName => $kids): ?>
                        <div>
                            <button type="button" class="cat-group-btn" data-group="<?= htmlspecialchars($familyName) ?>" onclick="pickGroup(this)"><?= htmlspecialchars($familyName) ?></button>
                            <?php foreach ($kids as $cat => $n): ?>
                            <button type="button" class="cat-child" data-cat="<?= htmlspecialchars($cat) ?>" data-group="<?= htmlspecialchars($familyName) ?>" onclick="pickCat(this)"><?= htmlspecialchars($cat) ?> <span class="count"><?= (int)$n ?></span></button>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <label class="visually-hidden" for="menuSearch">Search the menu</label>
            <div class="input-group search-pill flex-grow-1">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="menuSearch" class="form-control" placeholder="Search the menu…" oninput="filterMenu()">
            </div>
        </div>
        <div class="avail-tabs" role="tablist" aria-label="Availability">
            <button type="button" class="avail-tab is-on" data-avail="" role="tab" aria-selected="true" onclick="setAvail(this)">All <span><?= count($products) ?></span></button>
            <button type="button" class="avail-tab" data-avail="1" role="tab" aria-selected="false" onclick="setAvail(this)">In stock <span><?= (int)$inStockCount ?></span></button>
            <button type="button" class="avail-tab" data-avail="0" role="tab" aria-selected="false" onclick="setAvail(this)">Sold out <span><?= (int)$soldOutCount ?></span></button>
        </div>
        <?= $currencyBar() ?>
    </div>
    <nav class="menu-crumb" id="menuCrumb" aria-label="Breadcrumb" hidden></nav>
    <div id="menuRails">
        <?php if (!$products): ?><div class="text-center text-muted py-5">The menu is being updated. Please check back shortly.</div><?php endif; ?>
        <?php
        $rails = [];
        foreach ($products as $p) {
            $rails[(string)($p['family'] ?? 'More')][] = $p;
        }
        foreach ($menuGroups as $familyName => $kids):
            if (empty($rails[$familyName])) continue;
        ?>
        <section class="menu-rail" data-group="<?= htmlspecialchars($familyName) ?>">
            <h3 class="rail-title"><span class="rail-kicker" hidden></span><span class="rail-name"><?= htmlspecialchars($familyName) ?></span></h3>
            <div class="rail-frame">
                <div class="rail-track">
                    <?php foreach ($rails[$familyName] as $p): ?>
                    <?php $photo = \App\Helpers\Media::url((string)($p['image_path'] ?? '')); ?>
                    <article class="menu-item dish-tile<?= !empty($p['available']) ? '' : ' unavailable' ?>" data-id="<?= (int)$p['id'] ?>" data-cat="<?= htmlspecialchars($p['category']) ?>" data-group="<?= htmlspecialchars((string)($p['family'] ?? '')) ?>" data-available="<?= !empty($p['available']) ? '1' : '0' ?>" data-name="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'] . ' ' . $p['sku'] . ' ' . $shopLocationName)) ?>">
                        <div class="dish-photo"<?php if ($photo !== ''): ?> style="background-image:url('<?= htmlspecialchars($photo, ENT_QUOTES) ?>')"<?php endif; ?>>
                            <?php if ($photo === ''): ?><i class="bi <?= $shopIcon((string)$p['category']) ?>"></i><?php endif; ?>
                            <span class="dish-chip"><?= htmlspecialchars($p['category'] ?: 'Menu') ?></span>
                        </div>
                        <div class="dish-copy">
                            <div class="item-name"><?= htmlspecialchars($p['name']) ?></div>
                            <span class="price js-money" data-zmw="<?= htmlspecialchars((string)$p['price']) ?>"><?= $money($p['price']) ?></span>
                            <?php if ($p['available']): ?>
                            <button type="button" class="dish-add" aria-label="Add <?= htmlspecialchars($p['name']) ?>" onclick="addToCart(<?= (int)$p['id'] ?>, this)"><i class="bi bi-plus-lg"></i></button>
                            <?php else: ?><span class="dish-sold">Sold out</span><?php endif; ?>
                        </div>
                    </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="rail-next" aria-label="More <?= htmlspecialchars($familyName) ?>" onclick="scrollRail(this)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </section>
        <?php endforeach; ?>
        <div class="text-center text-muted py-5 d-none" id="menuEmpty">Nothing matches that search.</div>
    </div>
    </div>
    <div class="cart-bar <?= $cartCount ? '' : 'd-none' ?>" id="cartBar">
        <div class="d-flex justify-content-between align-items-center">
            <div><span class="badge rounded-pill text-dark" style="background:var(--sun)" id="cartBarCount"><?= (int)$cartCount ?></span> in your bag · <strong id="cartBarTotal" class="js-money" data-zmw="<?= htmlspecialchars((string)$cartTotal) ?>"><?= $money($cartTotal) ?></strong></div>
            <a href="<?= $u('cart') ?>" class="btn btn-accent rounded-pill px-4">View bag <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <script>
    function clearCatActive() { document.querySelectorAll('.cat-all, .cat-group-btn, .cat-child').forEach(function (b) { b.classList.remove('active'); }); }
    function closeCatMenu() {
        var menu = document.querySelector('.cat-menu');
        var btn = document.querySelector('.cat-drop-btn');
        if (menu) menu.hidden = true;
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }
    function toggleCatMenu(btn) {
        var menu = btn.parentElement.querySelector('.cat-menu');
        var open = menu.hidden;
        menu.hidden = !open;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    function chooseCat(btn, label) {
        clearCatActive();
        btn.classList.add('active');
        var name = document.getElementById('catDropLabel');
        if (name) name.textContent = label;
        closeCatMenu();
        filterMenu();
    }
    function pickAll(btn) { chooseCat(btn, 'Shop by category'); }
    function pickGroup(btn) { chooseCat(btn, btn.dataset.group || 'Shop by category'); }
    function pickCat(btn) { chooseCat(btn, (btn.dataset.group ? btn.dataset.group + ' › ' : '') + (btn.dataset.cat || 'Shop by category')); }
    function crumbGroup(name) {
        var target = Array.from(document.querySelectorAll('.cat-group-btn')).find(function (b) { return b.dataset.group === name; });
        if (target) pickGroup(target);
    }
    function renderCrumb(cat, group) {
        var nav = document.getElementById('menuCrumb');
        if (!nav) return;
        if (!cat && !group) { nav.hidden = true; nav.innerHTML = ''; return; }
        nav.innerHTML = '';
        var all = document.createElement('button');
        all.type = 'button';
        all.textContent = 'Menu';
        all.addEventListener('click', function () { pickAll(document.querySelector('.cat-all')); });
        nav.appendChild(all);
        function sep() {
            var s = document.createElement('span');
            s.className = 'crumb-sep';
            s.setAttribute('aria-hidden', 'true');
            s.textContent = '/';
            nav.appendChild(s);
        }
        if (group) {
            sep();
            if (cat) {
                var parent = document.createElement('button');
                parent.type = 'button';
                parent.textContent = group;
                parent.addEventListener('click', function () { crumbGroup(group); });
                nav.appendChild(parent);
            } else {
                var here = document.createElement('span');
                here.className = 'crumb-here';
                here.setAttribute('aria-current', 'page');
                here.textContent = group;
                nav.appendChild(here);
            }
        }
        if (cat) {
            sep();
            var current = document.createElement('span');
            current.className = 'crumb-here';
            current.setAttribute('aria-current', 'page');
            current.textContent = cat;
            nav.appendChild(current);
        }
        nav.hidden = false;
    }
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.cat-drop')) closeCatMenu();
    });
    function goStore(e, link) {
        var avail = ((document.querySelector('.avail-tab.is-on') || {}).dataset || {}).avail || '';
        if (avail !== '1' && avail !== '0') return true;
        e.preventDefault();
        window.location = link.getAttribute('href') + '&avail=' + avail;
        return false;
    }
    function setAvail(btn) {
        document.querySelectorAll('.avail-tab').forEach(function (tab) {
            var on = tab === btn;
            tab.classList.toggle('is-on', on);
            tab.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (btn.dataset.avail === '1' || btn.dataset.avail === '0') url.searchParams.set('avail', btn.dataset.avail);
            else url.searchParams.delete('avail');
            window.history.replaceState(null, '', url);
        }
        filterMenu();
    }
    function scrollRail(btn) {
        var track = btn.parentElement.querySelector('.rail-track');
        if (!track) return;
        var card = track.querySelector('.menu-item:not(.d-none)');
        var step = card ? card.getBoundingClientRect().width + 16 : 192;
        var atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;
        track.scrollTo({ left: atEnd ? 0 : track.scrollLeft + step * 2, behavior: 'smooth' });
    }
    function filterMenu() {
        const child = document.querySelector('.cat-child.active');
        const groupBtn = document.querySelector('.cat-group-btn.active');
        const cat = child ? (child.dataset.cat || '') : '';
        const group = child ? (child.dataset.group || '') : (groupBtn ? (groupBtn.dataset.group || '') : '');
        renderCrumb(cat, group);
        const avail = ((document.querySelector('.avail-tab.is-on') || {}).dataset || {}).avail || '';
        const words = (document.getElementById('menuSearch').value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
        var shown = 0;
        document.querySelectorAll('.menu-item').forEach(function (el) {
            const okCat = (cat === '' && group === '') || (cat !== '' && el.dataset.cat === cat) || (cat === '' && group !== '' && el.dataset.group === group);
            const okStock = avail === '' || el.dataset.available === avail;
            const hay = el.dataset.name || '';
            const okQ = !words.length || words.every(function (w) { return hay.indexOf(w) !== -1; });
            const ok = okCat && okStock && okQ;
            el.classList.toggle('d-none', !ok);
            if (ok) shown++;
        });
        document.querySelectorAll('.menu-rail').forEach(function (rail) {
            var visible = rail.querySelectorAll('.menu-item:not(.d-none)');
            rail.classList.toggle('is-empty', visible.length === 0);
            var titleName = rail.querySelector('.rail-name');
            var kicker = rail.querySelector('.rail-kicker');
            if (titleName) titleName.textContent = (cat && rail.dataset.group === group) ? cat : (rail.dataset.group || '');
            if (kicker) {
                var showSub = cat !== '' && rail.dataset.group === group;
                kicker.textContent = showSub ? (rail.dataset.group || '') : '';
                kicker.hidden = !showSub;
            }
            var track = rail.querySelector('.rail-track');
            var next = rail.querySelector('.rail-next');
            if (!track || !next) return;
            track.classList.remove('is-few', 'is-one', 'is-pair', 'is-trio', 'is-quad');
            if (!visible.length) { next.hidden = true; return; }
            var count = visible.length;
            if (count === 1) track.classList.add('is-few', 'is-one');
            else if (count === 2) track.classList.add('is-few', 'is-pair');
            else if (count === 3) track.classList.add('is-few', 'is-trio');
            else if (count === 4) track.classList.add('is-few', 'is-quad');
            track.scrollLeft = 0;
            track.getBoundingClientRect();
            if (count >= 4 && track.scrollWidth > track.clientWidth + 8) track.classList.remove('is-few', 'is-quad');
            next.hidden = track.classList.contains('is-few') || track.scrollWidth <= track.clientWidth + 8;
        });
        var openRails = Array.from(document.querySelectorAll('.menu-rail:not(.is-empty)'));
        var sideBySide = openRails.length > 1 && openRails.every(function (rail) {
            return rail.querySelectorAll('.menu-item:not(.d-none)').length <= 4;
        });
        var menuRails = document.getElementById('menuRails');
        if (menuRails) menuRails.classList.toggle('is-side', sideBySide);
        const empty = document.getElementById('menuEmpty');
        if (empty) {
            empty.textContent = avail === '1' ? 'Nothing is in stock at this store.' : (avail === '0' ? 'Nothing is sold out at this store.' : 'Nothing matches that search.');
            empty.classList.toggle('d-none', shown !== 0);
        }
    }
    (function () {
        const params = new URLSearchParams(window.location.search);
        const avail = params.get('avail');
        if (avail === '1' || avail === '0') {
            const availBtn = document.querySelector('.avail-tab[data-avail="' + avail + '"]');
            if (availBtn) setAvail(availBtn);
        }
        const rail = document.querySelector('.store-tabs');
        const currentStore = document.querySelector('.store-tab.is-on');
        if (rail && currentStore) rail.scrollLeft = Math.max(0, currentStore.offsetLeft - (rail.clientWidth - currentStore.offsetWidth) / 2);
        const want = params.get('cat');
        if (!want) { filterMenu(); return; }
        const btn = Array.from(document.querySelectorAll('.cat-child')).find(function (b) { return b.dataset.cat === want; });
        if (btn) pickCat(btn);
        else filterMenu();
    })();
    function addToCart(pid, btn) { btn.disabled = true; const body = new URLSearchParams({ cart_add: pid, qty: 1, ajax: 1 }); fetch('index.php?page=shop&view=menu', { method: 'POST', body: body, credentials: 'same-origin' }).then(r => r.json()).then(function (res) { btn.disabled = false; if (res.status !== 'ok') { alert(res.msg || 'Could not add'); return; } document.getElementById('cartBar').classList.remove('d-none'); document.getElementById('cartBarCount').textContent = res.count; var total = document.getElementById('cartBarTotal'); total.dataset.zmw = String(res.total); total.textContent = window.formatShopMoney ? formatShopMoney(res.total) : ('K' + Number(res.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })); btn.innerHTML = '<i class="bi bi-check-lg"></i>'; setTimeout(function () { btn.innerHTML = '<i class="bi bi-plus-lg"></i>'; }, 900); }).catch(function () { btn.disabled = false; }); }
    </script>

<?php elseif ($view === 'cart'): ?>
    <h4 class="page-title mb-3">Your bag</h4>
    <?php if (!$cart): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-basket fs-1"></i><p class="mt-2">Nothing here yet.</p><a href="<?= $u('menu') ?>" class="btn btn-accent rounded-pill px-4">Browse the menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('cart') ?>" id="bagForm">
        <input type="hidden" name="cart_update" value="1">
        <div class="card item-card mb-3">
            <ul class="list-group list-group-flush">
            <?php foreach ($cart as $line): ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                    <div class="flex-grow-1"><div class="fw-bold"><?= htmlspecialchars($line['name']) ?></div><div class="small text-muted"><span class="js-money" data-zmw="<?= htmlspecialchars((string)$line['price']) ?>"><?= $money($line['price']) ?></span> each</div></div>
                    <input type="number" min="0" name="qty[<?= (int)$line['id'] ?>]" value="<?= (int)$line['qty'] ?>" class="form-control text-center" style="width:80px" onchange="this.form.submit()">
                    <div class="price text-end js-money" style="min-width:90px" data-zmw="<?= htmlspecialchars((string)($line['price'] * $line['qty'])) ?>"><?= $money($line['price'] * $line['qty']) ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
            <?php $bagDue = $cartTotal + (($shopTable && $reserveOn) ? $reserveAmount : 0); ?>
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between"><span>Subtotal</span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$cartTotal) ?>"><?= $money($cartTotal) ?></span></div>
                <?php if ($shopTable && $reserveOn && $reserveAmount > 0): ?>
                <div class="d-flex justify-content-between text-muted"><span><?= htmlspecialchars($reserveLabel) ?></span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$reserveAmount) ?>"><?= $money($reserveAmount) ?></span></div>
                <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$bagDue) ?>"><?= $money($bagDue) ?></span></div>
                <?php else: ?>
                <div class="d-flex justify-content-between fw-bold fs-5"><span>Total</span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$cartTotal) ?>"><?= $money($cartTotal) ?></span></div>
                <?php endif; ?>
                <?= $currencyBar() ?>
            </div>
        </div>
    </form>
        <form method="POST" action="<?= $u('cart') ?>" id="seatForm" class="card item-card mb-3">
            <div class="card-body">
                <input type="hidden" name="choose_seat" value="1">
                <input type="hidden" name="reserve_table" id="reserveFlag" value="">
                <label class="form-label fw-bold small text-muted">LOCATION</label>
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <?php foreach ($shopLocations as $locRow): ?>
                    <label class="btn btn-outline-dark rounded-pill<?= (int)$locRow['id'] === $shopLocationId ? ' active' : '' ?>">
                        <input type="radio" class="btn-check" name="seat_location" value="<?= (int)$locRow['id'] ?>" <?= (int)$locRow['id'] === $shopLocationId ? 'checked' : '' ?> onchange="this.form.submit()">
                        <?= htmlspecialchars($locRow['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <label class="form-label fw-bold small text-muted" for="seatTable">TABLE</label>
                <?php if (!$shopTables): ?>
                    <p class="text-muted small mb-0">No free tables at this store. You can still collect the order.</p>
                    <input type="hidden" name="seat_table" value="0">
                <?php else: ?>
                    <select name="seat_table" id="seatTable" class="form-select no-search">
                        <option value="0">No table</option>
                        <?php
                        $seatGroups = [];
                        foreach ($shopTables as $seat) {
                            $seatGroups[$seat['zone_name'] ?: 'Floor'][] = $seat;
                        }
                        foreach ($seatGroups as $zone => $seats): ?>
                        <optgroup label="<?= htmlspecialchars((string)$zone) ?>">
                            <?php foreach ($seats as $seat): ?>
                            <option value="<?= (int)$seat['id'] ?>" <?= $shopTable && (int)$shopTable['id'] === (int)$seat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($seat['table_name']) ?> · <?= (int)$seat['capacity'] ?> seats</option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
        </form>
        <?php if ($shopTables): ?>
        <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
        <script>
        (function () {
            var seat = document.getElementById('seatTable');
            var form = document.getElementById('seatForm');
            var flag = document.getElementById('reserveFlag');
            if (!seat || !form) return;
            var previous = seat.value;
            var fee = <?= json_encode($reserveAmount) ?>;
            var label = <?= json_encode($reserveLabel) ?>;
            seat.addEventListener('change', function () {
                var chosen = seat.value;
                if (chosen === '0' || !(fee > 0)) {
                    flag.value = '';
                    form.submit();
                    return;
                }
                var name = seat.options[seat.selectedIndex].text;
                var money = window.formatShopMoney ? formatShopMoney(fee) : ('K' + Number(fee).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                Swal.fire({
                    title: label,
                    text: name + ' adds ' + money + ' to the bill.',
                    icon: 'question',
                    showCancelButton: true,
                    reverseButtons: true,
                    confirmButtonText: 'Add to bill',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#111111'
                }).then(function (result) {
                    if (!result.isConfirmed) {
                        seat.value = previous;
                        return;
                    }
                    flag.value = '1';
                    form.submit();
                });
            });
        })();
        </script>
        <?php endif; ?>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $u('menu') ?>" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-plus"></i> Add more</a>
            <button type="submit" class="btn btn-outline-secondary rounded-pill" form="bagForm">Update</button>
            <button type="submit" name="cart_clear" value="1" class="btn btn-outline-danger rounded-pill" form="bagForm" formnovalidate>Clear</button>
            <a href="<?= $u('checkout') ?>" class="btn btn-accent rounded-pill px-4 ms-auto">Checkout <i class="bi bi-arrow-right"></i></a>
        </div>
    <?php if ($minOrder > 0 && $cartTotal < $minOrder): ?><div class="alert alert-warning mt-3 small">Minimum order is <span class="js-money" data-zmw="<?= htmlspecialchars((string)$minOrder) ?>"><?= $money($minOrder) ?></span>.</div><?php endif; ?>
    <?php endif; ?>

<?php elseif ($view === 'checkout'): ?>
    <h4 class="page-title mb-3">Checkout</h4>
    <?php if ($shopTable): ?><p class="text-muted">Table <?= htmlspecialchars((string)$shopTable['zone_name'] . ' · ' . (string)$shopTable['table_name']) ?>. <a href="<?= $u('cart') ?>">Change</a></p><?php endif; ?>
    <?php if (!$cart): ?><div class="alert alert-warning">Your basket is empty. <a href="<?= $u('menu') ?>">Back to menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('checkout') ?>" class="row g-3">
        <input type="hidden" name="place_order" value="1">
        <div class="col-lg-7">
            <div class="card item-card mb-3"><div class="card-body">
                <label class="form-label fw-bold small text-muted">HOW WOULD YOU LIKE IT?</label>
                <div class="d-flex gap-2 mb-3">
                    <input type="radio" class="btn-check" name="order_type" id="otPickup" value="pickup" checked onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otPickup"><i class="bi bi-bag"></i> Collect in store</label>
                    <?php if ($deliveryOn): ?><input type="radio" class="btn-check" name="order_type" id="otDelivery" value="delivery" onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otDelivery"><i class="bi bi-bicycle"></i> Delivery<?php if ($deliveryFee > 0): ?> (+<span class="js-money" data-zmw="<?= htmlspecialchars((string)$deliveryFee) ?>"><?= $money($deliveryFee) ?></span>)<?php endif; ?></label><?php endif; ?>
                </div>
                <?php if ($shopTable && $reserveAmount > 0): ?>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="reserve_table" value="1" id="reserveTable" <?= $reserveOn ? 'checked' : '' ?> onchange="paintCheckout()">
                    <label class="form-check-label" for="reserveTable"><?= htmlspecialchars($reserveLabel) ?> · <?= $fxMoney($reserveAmount) ?></label>
                </div>
                <?php endif; ?>
                <div id="deliveryBox" class="d-none mb-3">
                    <label class="form-label fw-bold small text-muted">DELIVERY ADDRESS</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Street, area, landmarks"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">WHEN</label><input type="text" name="pickup_time" class="form-control" placeholder="ASAP or e.g. 13:30"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">PHONE</label><input type="text" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" disabled></div>
                    <div class="col-12"><label class="form-label fw-bold small text-muted">NOTES FOR THE KITCHEN</label><input type="text" name="notes" class="form-control" maxlength="300" placeholder="No onions, extra chilli…"></div>
                </div>
            </div></div>
            <div class="card item-card"><div class="card-body">
                <label class="form-label fw-bold small text-muted">PAYMENT</label>
                <?php if ($flwOn): ?>
                <input type="hidden" name="pay_mode" value="online">
                <div class="d-flex align-items-start gap-2">
                    <i class="bi bi-shield-check fs-4"></i>
                    <div>
                        <div class="fw-bold">Flutterwave</div>
                        <div class="small text-muted">Card or Zambia mobile money. The payment window opens on the next step.</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-danger small">Online payment is not available right now. Please contact the store.</div>
                <?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card item-card"><div class="card-body">
                <h6 class="fw-bold">Order summary</h6>
                <?php foreach ($cart as $line): ?><div class="d-flex justify-content-between small"><span><?= (int)$line['qty'] ?> × <?= htmlspecialchars($line['name']) ?></span><span class="js-money" data-zmw="<?= htmlspecialchars((string)($line['price'] * $line['qty'])) ?>"><?= $money($line['price'] * $line['qty']) ?></span></div><?php endforeach; ?>
                <hr>
                <?php $dueNow = $cartTotal + (($shopTable && $reserveOn) ? $reserveAmount : 0); ?>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$cartTotal) ?>"><?= $money($cartTotal) ?></span></div>
                <div class="d-flex justify-content-between text-muted d-none" id="feeRow"><span>Delivery</span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$deliveryFee) ?>"><?= $money($deliveryFee) ?></span></div>
                <div class="d-flex justify-content-between text-muted<?= ($shopTable && $reserveOn) ? '' : ' d-none' ?>" id="reserveRow"><span><?= htmlspecialchars($reserveLabel) ?></span><span class="js-money" data-zmw="<?= htmlspecialchars((string)$reserveAmount) ?>"><?= $money($reserveAmount) ?></span></div>
                <div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>Total</span><span id="grandTotal" class="js-money" data-zmw="<?= htmlspecialchars((string)$dueNow) ?>" data-sub="<?= $cartTotal ?>" data-fee="<?= $deliveryFee ?>" data-reserve="<?= htmlspecialchars((string)$reserveAmount) ?>"><?= $money($dueNow) ?></span></div>
                <div class="small text-muted mt-1" id="chargeNote" hidden>Charged in <?= $money($dueNow) ?>. The other figure is a guide at today's rate.</div>
                <?= $currencyBar() ?>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3" <?= $flwOn ? '' : 'disabled' ?>>Pay with Flutterwave</button>
                <a href="<?= $u('cart') ?>" class="btn btn-link w-100 text-muted small">Back to basket</a>
            </div></div>
        </div>
    </form>
    <script>
    function paintCheckout() {
        var d = document.getElementById('otDelivery');
        var deliveryOn = d && d.checked;
        var box = document.getElementById('deliveryBox');
        if (box) box.classList.toggle('d-none', !deliveryOn);
        var feeRow = document.getElementById('feeRow');
        if (feeRow) feeRow.classList.toggle('d-none', !deliveryOn);
        var reserve = document.getElementById('reserveTable');
        var reserveOn = reserve && reserve.checked;
        var reserveRow = document.getElementById('reserveRow');
        if (reserveRow) reserveRow.classList.toggle('d-none', !reserveOn);
        var g = document.getElementById('grandTotal');
        if (!g) return;
        var t = parseFloat(g.dataset.sub) + (deliveryOn ? parseFloat(g.dataset.fee || '0') : 0) + (reserveOn ? parseFloat(g.dataset.reserve || '0') : 0);
        g.dataset.zmw = String(t);
        g.textContent = window.formatShopMoney ? formatShopMoney(t) : ('K' + t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
        var note = document.getElementById('chargeNote');
        if (note) note.innerHTML = 'Charged in K' + t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '. The other figure is a guide at today\'s rate.';
    }
    function toggleDelivery() { paintCheckout(); }
    </script>
    <?php endif; ?>

<?php elseif ($view === 'orders'): ?>
    <h4 class="page-title mb-3">My orders</h4>
    <?php if (empty($orders)): ?><div class="text-center text-muted py-5">No orders yet. <a href="<?= $u('menu') ?>">Order something tasty</a>.</div><?php endif; ?>
    <div class="list-group shadow-sm">
    <?php foreach ($orders ?? [] as $o): $live = $o['status'] === 'pending'; ?>
        <a href="<?= $u('order', ['id' => $o['id']]) ?>" class="list-group-item list-group-item-action py-3">
            <div class="d-flex justify-content-between"><strong>Order #<?= (int)$o['id'] ?></strong><span class="price"><?= $money($o['final_total']) ?></span></div>
            <div class="small text-muted"><?= date('d M Y H:i', strtotime($o['created_at'])) ?> · <?= (int)$o['item_count'] ?> item(s) · <?= strtoupper(htmlspecialchars($o['order_type'] ?? 'pickup')) ?></div>
            <div class="mt-1"><span class="badge <?= $live ? ((int)$o['items_cooking'] > 0 ? 'bg-warning text-dark' : 'bg-info text-dark') : ($o['status'] === 'completed' ? 'bg-success' : 'bg-secondary') ?>"><?= $live ? ((int)$o['items_cooking'] > 0 ? 'Being prepared' : 'Ready for collection') : ucfirst($o['status']) ?></span> <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-light text-dark border' ?>"><?= $o['payment_status'] === 'paid' ? 'Paid' : 'Unpaid' ?></span></div>
        </a>
    <?php endforeach; ?>
    </div>

<?php elseif ($view === 'order' && !empty($order)): ?>
    <?php $o = $order; $cooking = count(array_filter($o['items'], fn($i) => $i['status'] !== 'ready')); $done = $o['status'] !== 'pending'; ?>
    <a href="<?= $u('orders') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> All orders</a>
    <div class="card item-card mt-2 mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div><h4 class="fw-bold mb-0">Order #<?= (int)$o['id'] ?></h4><div class="small text-muted"><?= date('d M Y H:i', strtotime($o['created_at'])) ?> · <?= strtoupper(htmlspecialchars($o['order_type'] ?? 'pickup')) ?></div></div>
            <span class="badge fs-6 <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $o['payment_status'] === 'paid' ? 'PAID' : 'UNPAID' ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 my-4">
            <span class="step done">1</span><div class="flex-grow-1 border-top <?= $cooking === 0 || $done ? 'border-warning border-2' : '' ?>"></div>
            <span class="step <?= $cooking === 0 || $done ? 'done' : '' ?>">2</span><div class="flex-grow-1 border-top <?= $done ? 'border-warning border-2' : '' ?>"></div>
            <span class="step <?= $done ? 'done' : '' ?>">3</span>
        </div>
        <div class="d-flex justify-content-between small text-muted mb-3"><span>Received</span><span><?= $cooking === 0 || $done ? 'Ready' : 'Being prepared' ?></span><span><?= $done ? ucfirst($o['status']) : 'Collected' ?></span></div>
        <table class="table table-sm mb-0">
            <?php foreach ($o['items'] as $i): ?><tr><td><?= (int)$i['quantity'] ?> × <?= htmlspecialchars($i['name']) ?> <?php if (!$done): ?><span class="status-dot <?= $i['status'] === 'ready' ? 'bg-success' : 'bg-warning' ?>" title="<?= htmlspecialchars($i['status']) ?>"></span><?php endif; ?></td><td class="text-end"><?= $money($i['price'] * $i['quantity']) ?></td></tr><?php endforeach; ?>
            <?php if ((float)$o['surcharge_amount'] > 0): ?><tr><td class="text-muted"><?= htmlspecialchars($o['surcharge_reason'] ?: 'Fee') ?></td><td class="text-end"><?= $money($o['surcharge_amount']) ?></td></tr><?php endif; ?>
            <tr class="fw-bold fs-5"><td>Total</td><td class="text-end"><?= $money($o['final_total']) ?></td></tr>
        </table>
        <?php if ($o['order_notes']): ?><div class="small text-muted mt-2"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($o['order_notes']) ?></div><?php endif; ?>
        <?php if ($flwOn && ($o['payment_status'] ?? '') !== 'paid' && ($o['status'] ?? '') === 'pending'): ?>
            <a href="<?= $u('pay', ['id' => (int)$o['id']]) ?>" class="btn btn-accent rounded-pill px-4 mt-3">Pay with Flutterwave</a>
        <?php endif; ?>
        <?php if ($o['gateway_ref']): ?><div class="small text-muted">Payment ref <?= htmlspecialchars($o['gateway_ref']) ?></div><?php endif; ?>
        <?php if ($o['zra_rcpt_no']): ?><div class="small text-muted">ZRA receipt no <?= htmlspecialchars($o['zra_rcpt_no']) ?></div><?php endif; ?>
    </div></div>
    <?php if (!$done): ?><p class="text-center small text-muted">This page refreshes automatically.</p><script>setTimeout(function () { location.reload(); }, 30000);</script><?php endif; ?>
    <?php if (!empty($settings['shop_phone'])): ?><p class="text-center small">Questions? Call <a href="tel:<?= htmlspecialchars($settings['shop_phone']) ?>"><?= htmlspecialchars($settings['shop_phone']) ?></a></p><?php endif; ?>

<?php elseif ($view === 'pay' && !empty($order) && !empty($checkout)): ?>
    <?php $o = $order; ?>
    <a href="<?= $u('order', ['id' => (int)$o['id']]) ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> Order #<?= (int)$o['id'] ?></a>
    <div class="card item-card mt-2 mb-3"><div class="card-body">
        <h4 class="fw-bold mb-1">Pay with Flutterwave</h4>
        <p class="text-muted small mb-3">Card or Zambia mobile money. This is the only way to pay for an online order.</p>
        <div class="d-flex justify-content-between fw-bold fs-5"><span>Order #<?= (int)$o['id'] ?></span><span><?= $money($o['final_total']) ?></span></div>
        <p class="small mt-3 mb-0" id="payStatus">Opening the Flutterwave window…</p>
        <button type="button" class="btn btn-accent rounded-pill px-4 mt-3" id="payLaunch">Open Flutterwave</button>
    </div></div>
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <script>
    (function () {
        var checkout = <?= json_encode($checkout, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var statusUrl = <?= json_encode($u('pay_status', ['id' => (int)$o['id']])) ?>;
        var orderUrl = <?= json_encode($u('order', ['id' => (int)$o['id']])) ?>;
        var status = document.getElementById('payStatus');
        var ticks = 0;
        function openShopPay() {
            if (typeof FlutterwaveCheckout !== 'function') {
                status.textContent = 'The Flutterwave window did not load. Check your connection, then try again.';
                return;
            }
            status.textContent = 'Complete the payment in the Flutterwave window. This page updates when Flutterwave confirms it.';
            FlutterwaveCheckout(Object.assign({}, checkout, {
                callback: function () {
                    status.textContent = 'Payment sent. This page updates when Flutterwave confirms it.';
                },
                onclose: function () {
                    status.textContent = 'If you finished paying, stay on this page. It updates when Flutterwave confirms it.';
                }
            }));
        }
        document.getElementById('payLaunch').addEventListener('click', openShopPay);
        openShopPay();
        var poll = setInterval(function () {
            ticks++;
            if (ticks > 90) { clearInterval(poll); return; }
            fetch(statusUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
                if (res && res.payment_status === 'paid') {
                    clearInterval(poll);
                    window.location.href = orderUrl;
                }
            }).catch(function () {});
        }, 4000);
    })();
    </script>

<?php elseif ($view === 'login'): ?>
    <div class="col-md-6 col-lg-5 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-3">Sign in</h4>
            <form method="POST" action="<?= $u('login') ?>">
                <input type="hidden" name="login" value="1">
                <div class="mb-3"><label class="form-label fw-bold small text-muted">MOBILE NUMBER</label><input type="tel" name="phone" class="form-control form-control-lg" placeholder="097 123 4567" required autofocus></div>
                <div class="mb-3"><label class="form-label fw-bold small text-muted">PIN / PASSWORD</label><input type="password" name="password" class="form-control form-control-lg" required></div>
                <button class="btn btn-accent w-100 rounded-pill py-2">Sign in</button>
            </form>
            <p class="text-center small mt-3 mb-0">New here? <a href="<?= $u('register') ?>">Create an account</a></p>
        </div></div>
    </div>

<?php elseif ($view === 'register'): ?>
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-1">Create your account</h4>
            <p class="text-muted small">Just your name and mobile number — we'll use it to let you know when your order is ready.</p>
            <form method="POST" action="<?= $u('register') ?>">
                <input type="hidden" name="register" value="1">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label fw-bold small text-muted">FULL NAME</label><input type="text" name="name" class="form-control form-control-lg" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">MOBILE NUMBER</label><input type="tel" name="phone" class="form-control form-control-lg" placeholder="097 123 4567" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL (optional)</label><input type="email" name="email" class="form-control form-control-lg"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">CHOOSE A PIN / PASSWORD</label><input type="password" name="password" class="form-control form-control-lg" minlength="4" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">DELIVERY ADDRESS (optional)</label><input type="text" name="address" class="form-control form-control-lg"></div>
                </div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3">Register</button>
            </form>
            <p class="text-center small mt-3 mb-0">Already registered? <a href="<?= $u('login') ?>">Sign in</a></p>
        </div></div>
    </div>

<?php elseif ($view === 'account' && $customer): ?>
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-person-circle"></i> My details</h4>
            <form method="POST" action="<?= $u('account') ?>">
                <input type="hidden" name="update_profile" value="1">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label fw-bold small text-muted">NAME</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">MOBILE</label><input type="text" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label fw-bold small text-muted">DELIVERY ADDRESS</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($customer['address'] ?? '') ?>"></div>
                    <div class="col-12" id="settings"><label class="form-label fw-bold small text-muted">NEW PIN / PASSWORD (leave blank to keep)</label><input type="password" name="new_password" class="form-control" minlength="4"></div>
                </div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3">Save</button>
            </form>
            <form method="POST" action="<?= $u('account') ?>" class="mt-2"><input type="hidden" name="logout" value="1"><button class="btn btn-outline-secondary w-100 rounded-pill">Sign out</button></form>
        </div></div>
    </div>

<?php elseif ($view === 'events'): ?>
    <section class="shop-hero">
        <div class="eyebrow">Functions</div>
        <h1>Events</h1>
        <p>Private dinners, tastings, and celebrations. Ask for a place, then keep the link we give you.</p>
    </section>
    <?php if (empty($events)): ?>
        <div class="card item-card"><div class="card-body p-4 text-muted">No events are open right now.</div></div>
    <?php else: ?>
        <div class="row g-3">
        <?php foreach ($events as $ev):
            $cover = \App\Helpers\Media::url((string)($ev['cover_path'] ?? ''));
            $when = $ev['starts_at'] ? date('D j M Y, H:i', strtotime((string)$ev['starts_at'])) : '';
        ?>
            <div class="col-md-6">
                <a href="<?= $u('event', ['slug' => $ev['slug']]) ?>" class="card item-card h-100 text-decoration-none text-reset">
                    <?php if ($cover !== ''): ?><img src="<?= htmlspecialchars($cover) ?>" alt="" style="width:100%;height:160px;object-fit:cover;border-radius:22px 22px 0 0;"><?php endif; ?>
                    <div class="card-body">
                        <div class="small text-muted"><?= htmlspecialchars($when) ?><?= !empty($ev['location_name']) ? ' · ' . htmlspecialchars($ev['location_name']) : '' ?></div>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($ev['title']) ?></h5>
                        <p class="mb-0 text-muted"><?= htmlspecialchars((string)($ev['summary'] ?? '')) ?></p>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php elseif ($view === 'event'): ?>
    <?php if (empty($event)): ?>
        <section class="shop-hero"><h1>Event not found</h1><p>This function is private, closed, or the link is wrong.</p></section>
        <a href="<?= $u('events') ?>" class="btn btn-accent rounded-pill">All events</a>
    <?php else:
        $cover = \App\Helpers\Media::url((string)($event['cover_path'] ?? ''));
        $when = $event['starts_at'] ? date('D j M Y, H:i', strtotime((string)$event['starts_at'])) : '';
        $groups = [];
        foreach ($options as $opt) {
            $groups[$opt['group_name'] ?: 'Choice'][] = $opt;
        }
    ?>
        <?php if ($cover !== ''): ?><img src="<?= htmlspecialchars($cover) ?>" alt="" class="w-100 mb-3" style="max-height:320px;object-fit:cover;border-radius:28px;"><?php endif; ?>
        <section class="shop-hero">
            <div class="eyebrow"><?= htmlspecialchars($when) ?></div>
            <h1><?= htmlspecialchars($event['title']) ?></h1>
            <p><?= htmlspecialchars((string)($event['summary'] ?? '')) ?></p>
            <?= $currencyBar() ?>
        </section>
        <?php if (trim((string)($event['story'] ?? '')) !== ''): ?>
            <div class="card item-card mb-3"><div class="card-body p-4" style="white-space:pre-wrap"><?= htmlspecialchars((string)$event['story']) ?></div></div>
        <?php endif; ?>
        <?php if ($groups): ?>
            <div class="card item-card mb-3"><div class="card-body p-4">
                <h5 class="fw-bold">What you can choose</h5>
                <?php foreach ($groups as $group => $rows): ?>
                    <div class="fw-bold small text-muted mt-2"><?= htmlspecialchars((string)$group) ?></div>
                    <ul class="mb-0">
                    <?php foreach ($rows as $opt): ?>
                        <li><?= htmlspecialchars($opt['name']) ?><?php if ((float)$opt['price'] > 0): ?> · <?= $fxMoney($opt['price']) ?><?php endif; ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <div class="card item-card"><div class="card-body p-4">
            <?php $copy = \App\Models\Event::signupCopy((string)($event['signup_mode'] ?? 'apply')); ?>
            <h5 class="fw-bold"><?= htmlspecialchars($copy['heading']) ?></h5>
            <p class="text-muted small"><?= htmlspecialchars($copy['lead']) ?><?php if ((float)$event['deposit_amount'] > 0): ?> A deposit of <?= $fxMoney($event['deposit_amount']) ?> is collected on your private page first.<?php endif; ?><?php if (!empty($tiers)): ?> Tier prices are per person.<?php endif; ?></p>
            <form method="POST" action="<?= $u('event', ['slug' => $event['slug']]) ?>">
                <input type="hidden" name="event_apply" value="1">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($event['slug']) ?>">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">NAME</label><input name="name" class="form-control" required value="<?= htmlspecialchars((string)($customer['name'] ?? '')) ?>"></div>
                    <div class="col-md-3"><label class="form-label fw-bold small text-muted">PARTY</label><input type="number" name="party_size" class="form-control" min="1" max="50" value="1" required></div>
                    <div class="col-md-3"><label class="form-label fw-bold small text-muted">PHONE</label><input name="phone" class="form-control" value="<?= htmlspecialchars((string)($customer['phone'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)($customer['email'] ?? '')) ?>"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">NOTE</label><input name="note" class="form-control" placeholder="Allergies, celebration, seating"></div>
                </div>
                <?php if (!empty($tiers)): ?>
                <div class="fw-bold mt-3 mb-1">Tier</div>
                <?php foreach ($tiers as $tier):
                    $taken = \App\Models\Event::tierTaken((int)$tier['id']);
                    $full = (int)$tier['capacity'] > 0 && $taken >= (int)$tier['capacity'];
                ?>
                <label class="d-flex justify-content-between border rounded-3 px-3 py-2 mb-2">
                    <span><input type="radio" name="tier_id" value="<?= (int)$tier['id'] ?>" required <?= $full ? 'disabled' : '' ?>> <?= htmlspecialchars($tier['name']) ?><?= $full ? ' (full)' : '' ?></span>
                    <span><?= $fxMoney($tier['price']) ?> each</span>
                </label>
                <?php endforeach; ?>
                <?php endif; ?>
                <button class="btn btn-accent rounded-pill px-4 mt-3"><?= htmlspecialchars($copy['verb']) ?></button>
            </form>
        </div></div>
    <?php endif; ?>

<?php elseif ($view === 'rsvp'): ?>
    <?php if (empty($guest) || empty($event)): ?>
        <section class="shop-hero"><h1>Invitation not found</h1><p>Ask the restaurant for a new link.</p></section>
    <?php else:
        $when = $event['starts_at'] ? date('D j M Y, H:i', strtotime((string)$event['starts_at'])) : '';
        $open = ($event['status'] ?? '') === 'open';
        $groups = [];
        foreach ($options as $opt) {
            $groups[$opt['group_name'] ?: 'Choice'][] = $opt;
        }
        $picked = $picked ?? [];
    ?>
        <section class="shop-hero">
            <div class="eyebrow"><?= htmlspecialchars($when) ?></div>
            <h1><?= htmlspecialchars($event['title']) ?></h1>
            <p>Hello <?= htmlspecialchars($guest['name']) ?>. This page is only yours.</p>
            <?= $currencyBar() ?>
        </section>
        <div class="card item-card mb-3"><div class="card-body p-4">
            <div class="d-flex justify-content-between"><span>Reply</span><strong><?= htmlspecialchars((string)$guest['status']) ?></strong></div>
            <?php if (!empty($guest['tier_name'])): ?>
            <div class="d-flex justify-content-between"><span>Tier</span><strong><?= htmlspecialchars((string)$guest['tier_name']) ?> · <?= $fxMoney($guest['tier_price'] ?? 0) ?> each</strong></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span>Paid</span><strong><?= $fxMoney($paid ?? 0) ?></strong></div>
            <?php if (($charge['amount'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between"><span><?= htmlspecialchars(ucfirst((string)$charge['purpose'])) ?> due</span><strong><?= $fxMoney($charge['amount']) ?></strong></div>
            <?php endif; ?>
        </div></div>
        <?php if ($open): ?>
        <form method="POST" action="<?= $u('rsvp', ['token' => $guest['token']]) ?>" class="card item-card mb-3">
            <div class="card-body p-4">
                <input type="hidden" name="event_reply" value="1">
                <input type="hidden" name="token" value="<?= htmlspecialchars($guest['token']) ?>">
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">CAN YOU ATTEND?</label>
                        <select name="reply" class="form-select">
                            <option value="accepted" <?= $guest['status'] === 'declined' ? '' : 'selected' ?>>Yes, I'll be there</option>
                            <option value="declined" <?= $guest['status'] === 'declined' ? 'selected' : '' ?>>No, I can't</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-bold small text-muted">PARTY</label>
                        <input type="number" name="party_size" class="form-control" min="1" max="50" value="<?= (int)$guest['party_size'] ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">NOTE</label>
                        <input name="note" class="form-control" value="<?= htmlspecialchars((string)($guest['note'] ?? '')) ?>">
                    </div>
                </div>
                <?php if (!empty($tiers)): ?>
                    <div class="fw-bold mb-1">Tier</div>
                    <?php foreach ($tiers as $tier):
                        $taken = \App\Models\Event::tierTaken((int)$tier['id'], (int)$guest['id']);
                        $selected = (int)($guest['tier_id'] ?? 0) === (int)$tier['id'];
                        $full = (int)$tier['capacity'] > 0 && $taken >= (int)$tier['capacity'];
                    ?>
                        <label class="d-flex justify-content-between border rounded-3 px-3 py-2 mb-2">
                            <span><input type="radio" name="tier_id" value="<?= (int)$tier['id'] ?>" <?= $selected ? 'checked' : '' ?> <?= $full && !$selected ? 'disabled' : '' ?>> <?= htmlspecialchars($tier['name']) ?><?= $full && !$selected ? ' (full)' : '' ?></span>
                            <span><?= $fxMoney($tier['price']) ?> each</span>
                        </label>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php foreach ($groups as $group => $rows): ?>
                    <div class="fw-bold mb-1"><?= htmlspecialchars((string)$group) ?></div>
                    <?php foreach ($rows as $opt):
                        $full = (int)$opt['capacity'] > 0 && \App\Models\Event::optionTaken((int)$opt['id'], (int)$guest['id']) >= (int)$opt['capacity'];
                    ?>
                        <label class="d-flex justify-content-between border rounded-3 px-3 py-2 mb-2">
                            <span><input type="radio" name="option_id[<?= htmlspecialchars((string)$group) ?>]" value="<?= (int)$opt['id'] ?>" <?= in_array((int)$opt['id'], $picked, true) ? 'checked' : '' ?> <?= $full && !in_array((int)$opt['id'], $picked, true) ? 'disabled' : '' ?>> <?= htmlspecialchars($opt['name']) ?><?= $full ? ' (full)' : '' ?></span>
                            <span><?= (float)$opt['price'] > 0 ? $fxMoney($opt['price']) : '' ?></span>
                        </label>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <button class="btn btn-accent rounded-pill px-4 mt-2">Save reply</button>
            </div>
        </form>
        <?php endif; ?>
        <?php if (($charge['amount'] ?? 0) > 0 && !in_array($guest['status'], ['declined', 'cancelled'], true)): ?>
        <form method="POST" action="<?= $u('rsvp', ['token' => $guest['token']]) ?>" class="card item-card">
            <div class="card-body p-4 d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold">Pay <?= htmlspecialchars((string)$charge['purpose']) ?></div>
                    <div class="text-muted small"><?= $fxMoney($charge['amount']) ?> through Flutterwave. Cash can be taken at the restaurant.</div>
                    <div class="small text-muted" id="chargeNote" hidden>Charged in <?= $money($charge['amount']) ?>. The other figure is a guide at today's rate.</div>
                </div>
                <input type="hidden" name="event_pay" value="1">
                <input type="hidden" name="token" value="<?= htmlspecialchars($guest['token']) ?>">
                <button class="btn btn-accent rounded-pill px-4">Pay now</button>
            </div>
        </form>
        <?php endif; ?>
        <?php if (!empty($payments)): ?>
            <ul class="list-group mt-3">
            <?php foreach ($payments as $pay): ?>
                <li class="list-group-item d-flex justify-content-between">
                    <span><?= htmlspecialchars($pay['purpose'] . ' · ' . $pay['method']) ?></span>
                    <span><?= $fxMoney($pay['amount']) ?> · <?= htmlspecialchars($pay['status']) ?></span>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>
</div>
<footer class="shop-foot">
    <h2>Swing by soon!</h2>
    <p><strong><?= htmlspecialchars($settings['business_name'] ?? $title) ?></strong></p>
    <?php if (!empty($settings['shop_phone'])): ?><p>Phone number<br><a href="tel:<?= htmlspecialchars($settings['shop_phone']) ?>"><?= htmlspecialchars($settings['shop_phone']) ?></a></p><?php endif; ?>
</footer>
<?php if ($view === 'home' && !empty($eventSlides)): ?>
<div class="modal fade" id="eventReg" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-body">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php foreach ($eventSlides as $ev):
                    $cover = \App\Helpers\Media::url((string)($ev['cover_path'] ?? ''));
                    $when = !empty($ev['starts_at']) ? date('D j M Y, H:i', strtotime((string)$ev['starts_at'])) : '';
                    $regOptions = \App\Models\Event::options((int)$ev['id']);
                    $regTiers = \App\Models\Event::tiers((int)$ev['id']);
                    $groups = [];
                    foreach ($regOptions as $opt) {
                        $groups[$opt['group_name'] ?: 'Choice'][] = $opt;
                    }
                    $copy = \App\Models\Event::signupCopy((string)($ev['signup_mode'] ?? 'apply'));
                ?>
                <div class="event-reg-panel" data-slug="<?= htmlspecialchars((string)$ev['slug']) ?>" hidden>
                    <?php if ($cover !== ''): ?><img class="event-reg-cover" src="<?= htmlspecialchars($cover) ?>" alt=""><?php endif; ?>
                    <section class="shop-hero">
                        <div class="eyebrow"><?= htmlspecialchars($when) ?></div>
                        <h1><?= htmlspecialchars((string)$ev['title']) ?></h1>
                        <p><?= htmlspecialchars((string)($ev['summary'] ?? '')) ?></p>
                        <?= $currencyBar() ?>
                    </section>
                    <?php if (trim((string)($ev['story'] ?? '')) !== ''): ?>
                        <div class="card item-card mb-3"><div class="card-body p-4" style="white-space:pre-wrap"><?= htmlspecialchars((string)$ev['story']) ?></div></div>
                    <?php endif; ?>
                    <?php if ($groups): ?>
                        <div class="card item-card mb-3"><div class="card-body p-4">
                            <h5 class="fw-bold">What you can choose</h5>
                            <?php foreach ($groups as $group => $rows): ?>
                                <div class="fw-bold small text-muted mt-2"><?= htmlspecialchars((string)$group) ?></div>
                                <ul class="mb-0">
                                <?php foreach ($rows as $opt): ?>
                                    <li><?= htmlspecialchars($opt['name']) ?><?php if ((float)$opt['price'] > 0): ?> · <?= $fxMoney($opt['price']) ?><?php endif; ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        </div></div>
                    <?php endif; ?>
                    <div class="card item-card"><div class="card-body p-4">
                        <h5 class="fw-bold"><?= htmlspecialchars($copy['heading']) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars($copy['lead']) ?><?php if ((float)$ev['deposit_amount'] > 0): ?> A deposit of <?= $fxMoney($ev['deposit_amount']) ?> is collected on your private page first.<?php endif; ?><?php if ($regTiers): ?> Tier prices are per person.<?php endif; ?></p>
                        <form method="POST" action="<?= $u('event', ['slug' => $ev['slug']]) ?>">
                            <input type="hidden" name="event_apply" value="1">
                            <input type="hidden" name="slug" value="<?= htmlspecialchars((string)$ev['slug']) ?>">
                            <div class="row g-2">
                                <div class="col-md-6"><label class="form-label fw-bold small text-muted">NAME</label><input name="name" class="form-control" required value="<?= htmlspecialchars((string)($customer['name'] ?? '')) ?>"></div>
                                <div class="col-md-3"><label class="form-label fw-bold small text-muted">PARTY</label><input type="number" name="party_size" class="form-control" min="1" max="50" value="1" required></div>
                                <div class="col-md-3"><label class="form-label fw-bold small text-muted">PHONE</label><input name="phone" class="form-control" value="<?= htmlspecialchars((string)($customer['phone'] ?? '')) ?>"></div>
                                <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars((string)($customer['email'] ?? '')) ?>"></div>
                                <div class="col-md-6"><label class="form-label fw-bold small text-muted">NOTE</label><input name="note" class="form-control" placeholder="Allergies, celebration, seating"></div>
                            </div>
                            <?php if ($regTiers): ?>
                            <div class="fw-bold mt-3 mb-1">Tier</div>
                            <?php foreach ($regTiers as $tier):
                                $taken = \App\Models\Event::tierTaken((int)$tier['id']);
                                $full = (int)$tier['capacity'] > 0 && $taken >= (int)$tier['capacity'];
                            ?>
                            <label class="d-flex justify-content-between border rounded-3 px-3 py-2 mb-2">
                                <span><input type="radio" name="tier_id" value="<?= (int)$tier['id'] ?>" required <?= $full ? 'disabled' : '' ?>> <?= htmlspecialchars($tier['name']) ?><?= $full ? ' (full)' : '' ?></span>
                                <span><?= $fxMoney($tier['price']) ?> each</span>
                            </label>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            <button class="btn btn-accent rounded-pill px-4 mt-3"><?= htmlspecialchars($copy['verb']) ?></button>
                        </form>
                    </div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var flip = document.getElementById('dishFlip');
    var bar = document.getElementById('dishStrip');
    if (!flip || !bar) return;
    var spot = flip.querySelector('.dish-face-spot');
    var menu = flip.querySelector('.dish-face-menu');
    var modalEl = document.getElementById('eventReg');
    var modal = modalEl && typeof bootstrap !== 'undefined' ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var timer = null;
    var showingSpot = false;
    var menuWait = 14000;
    var spotWait = 7000;

    function arm(ms) {
        clearTimeout(timer);
        if (!spot || !menu || reduce || (modalEl && modalEl.classList.contains('show'))) return;
        timer = setTimeout(function () {
            showingSpot = !showingSpot;
            flip.classList.toggle('is-spot', showingSpot);
            arm(showingSpot ? spotWait : menuWait);
        }, ms);
    }

    bar.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-event-slug]');
        if (!btn || !modal || !modalEl) return;
        var slug = btn.getAttribute('data-event-slug');
        modalEl.querySelectorAll('.event-reg-panel').forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-slug') !== slug;
        });
        modal.show();
    });
    bar.addEventListener('mouseenter', function () { clearTimeout(timer); });
    bar.addEventListener('mouseleave', function () { arm(showingSpot ? spotWait : menuWait); });
    bar.addEventListener('focusin', function () { clearTimeout(timer); });
    bar.addEventListener('focusout', function () { arm(showingSpot ? spotWait : menuWait); });
    if (modalEl) {
        modalEl.addEventListener('show.bs.modal', function () { clearTimeout(timer); });
        modalEl.addEventListener('hidden.bs.modal', function () { arm(showingSpot ? spotWait : menuWait); });
    }
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) clearTimeout(timer);
        else arm(showingSpot ? spotWait : menuWait);
    });
    arm(menuWait);
})();
</script>
<script>
(function () {
    if (!document.body.classList.contains('is-home') && !document.body.classList.contains('is-menu')) return;
    var seen = '';

    function withVersion(src, v) {
        if (!src) return '';
        return src + (String(src).indexOf('?') === -1 ? '?v=' + v : '');
    }
    function paintImg(img, src, v) {
        if (!img || !src) return;
        var next = withVersion(src, v);
        if (img.getAttribute('src') !== next) img.src = next;
    }
    function paintLogo(logo) {
        var img = document.getElementById('shopLogo');
        var mark = document.querySelector('.brand-mark');
        if (logo.src) {
            if (!img) {
                img = document.createElement('img');
                img.id = 'shopLogo';
                img.alt = '';
                img.style.cssText = 'height:38px;width:38px;object-fit:cover;border-radius:12px;background:#fff;';
                if (mark && mark.parentNode) mark.parentNode.replaceChild(img, mark);
            }
            paintImg(img, logo.src, logo.v);
        }
    }
    function paintBanner(banner) {
        var stage = document.querySelector('.landing-photo');
        if (!stage || !banner.src) return;
        var img = document.getElementById('shopBanner');
        if (!img) {
            img = document.createElement('img');
            img.id = 'shopBanner';
            img.alt = '';
            stage.appendChild(img);
        }
        paintImg(img, banner.src, banner.v);
    }
    function paintSlides(slides) {
        var track = document.querySelector('.dish-face-menu .dish-track');
        if (!track) return;
        var key = slides.map(function (s) { return s.id + ':' + s.src + ':' + s.v; }).join('|');
        if (track.dataset.key === key) return;
        var rows = slides.slice();
        var pool = slides.slice();
        while (rows.length && rows.length < 6) rows = rows.concat(pool);
        var incoming = rows.concat(rows);
        var existing = Array.prototype.map.call(track.querySelectorAll('img'), function (img) {
            return (img.getAttribute('src') || '').split('?')[0];
        });
        var same = existing.length === incoming.length && existing.every(function (src, i) { return src === incoming[i].src; });
        if (same) {
            Array.prototype.forEach.call(track.querySelectorAll('img'), function (img, i) {
                paintImg(img, incoming[i].src, incoming[i].v);
            });
            track.dataset.key = key;
            return;
        }
        track.dataset.key = key;
        track.textContent = '';
        incoming.forEach(function (slide) {
            var link = document.createElement('a');
            link.className = 'menu-slide';
            link.href = 'index.php?page=shop&view=menu&cat=' + encodeURIComponent(slide.cat || '');
            var img = document.createElement('img');
            img.src = withVersion(slide.src, slide.v);
            img.alt = slide.name || '';
            link.appendChild(img);
            track.appendChild(link);
        });
    }
    function paintEvents(events) {
        events.forEach(function (ev) {
            var card = document.querySelector('.spot-card[data-event-slug="' + ev.slug.replace(/"/g, '') + '"]');
            if (!card || !ev.src) return;
            var img = card.querySelector('img');
            if (!img) {
                img = document.createElement('img');
                img.alt = ev.title || '';
                card.insertBefore(img, card.firstChild);
            }
            paintImg(img, ev.src, ev.v);
        });
    }
    function paintItems(items) {
        var byId = {};
        items.forEach(function (row) { byId[String(row.id)] = row; });
        document.querySelectorAll('.menu-item[data-id]').forEach(function (card) {
            var row = byId[card.dataset.id];
            var face = card.querySelector('.item-face');
            if (!row || !face) return;
            if (row.src) {
                face.classList.add('item-photo');
                var url = withVersion(row.src, row.v).replace(/'/g, '%27');
                face.style.backgroundImage = "linear-gradient(to top, rgba(20,10,4,.45), transparent 55%), url('" + url + "')";
                var icon = face.querySelector('i');
                if (icon) icon.remove();
            } else if (face.classList.contains('item-photo')) {
                face.classList.remove('item-photo');
                face.style.backgroundImage = '';
            }
        });
        document.querySelectorAll('.dish-card[data-id]').forEach(function (card) {
            var row = byId[card.dataset.id];
            if (row && row.src) paintImg(card.querySelector('img'), row.src, row.v);
        });
    }
    function apply(data) {
        if (!data || data.status !== 'ok') return;
        var key = JSON.stringify([data.logo, data.banner, data.slides, data.items, data.events]);
        if (key === seen) return;
        seen = key;
        if (data.logo) paintLogo(data.logo);
        if (data.banner) paintBanner(data.banner);
        if (data.slides) paintSlides(data.slides);
        if (data.events) paintEvents(data.events);
        if (data.items) paintItems(data.items);
    }
    function pull() {
        if (document.hidden) return;
        fetch('index.php?page=shop&view=face', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(apply)
            .catch(function () {});
    }
    try {
        var channel = new BroadcastChannel('smartrade-shop');
        channel.onmessage = pull;
    } catch (e) {}
    document.addEventListener('visibilitychange', pull);
    setInterval(pull, 8000);
})();
</script>
<script>
window.shopFx = <?= json_encode($fxRates, JSON_UNESCAPED_SLASHES) ?>;
window.formatShopMoney = function (amount) {
    var zmw = Number(amount) || 0;
    var code = window.shopCur || 'ZMW';
    var per = window.shopFx && window.shopFx[code];
    if (code === 'ZMW' || !per) return 'K' + zmw.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return code + ' ' + (zmw / per).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
window.setShopCurrency = function (code) {
    if (code !== 'ZMW' && !(window.shopFx && window.shopFx[code])) code = 'ZMW';
    window.shopCur = code;
    try { localStorage.setItem('shopCur', code); } catch (e) {}
    document.querySelectorAll('.js-money').forEach(function (el) {
        el.textContent = formatShopMoney(el.dataset.zmw);
    });
    document.querySelectorAll('.cur-tab').forEach(function (btn) {
        var on = btn.dataset.cur === code;
        btn.classList.toggle('is-on', on);
        btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    var note = document.getElementById('chargeNote');
    if (note) note.hidden = code === 'ZMW';
};
(function () {
    var saved = 'ZMW';
    try { saved = localStorage.getItem('shopCur') || 'ZMW'; } catch (e) {}
    setShopCurrency(saved);
})();
</script>
</body>
</html>
