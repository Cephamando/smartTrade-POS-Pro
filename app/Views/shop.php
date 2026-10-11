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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Source+Sans+3:wght@400;600;700&display=swap">
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
        .landing-screen { height: calc(100vh - 62px); min-height: 560px; margin: -.5rem calc(50% - 50vw) 0; color: #f6ead0; display: flex; flex-direction: column; padding: 6px max(16px, calc(50vw - 620px)) 0; box-sizing: border-box; overflow: visible; background-color: #0b0b0d; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='84' height='48' viewBox='0 0 84 48'%3E%3Cpath d='M21 1 L41 12 L41 34 L21 45 L1 34 L1 12 Z M63 1 L83 12 L83 34 L63 45 L43 34 L43 12 Z' fill='none' stroke='%23d4a84a' stroke-opacity='0.16' stroke-width='1'/%3E%3C/svg%3E"); }
        .landing-screen h1 { font-weight: 700; font-size: clamp(2.8rem, 6.4vw, 5.2rem); letter-spacing: -.02em; line-height: .95; margin: 0 0 8px; background: linear-gradient(180deg, #fff1c4 0%, #e8c15a 38%, #a56b12 78%); -webkit-background-clip: text; background-clip: text; color: transparent; filter: drop-shadow(0 10px 18px rgba(0,0,0,.45)); }
        .landing-screen .tagline { font-size: clamp(1rem, 1.8vw, 1.25rem); margin: 0 auto; max-width: 36rem; color: #f0d7a0; }
        .landing-stage { position: relative; flex: 1 1 auto; min-height: 0; margin: -8px calc(50% - 50vw) 0; display: flex; flex-direction: column; overflow: hidden; }
        .landing-photo { position: absolute; inset: 0; background: #0b0b0d; }
        .landing-photo img { width: 100%; height: 100%; object-fit: cover; object-position: center 62%; display: block; }
        .landing-photo::after { content: ""; position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(11,11,13,1) 0%, rgba(11,11,13,.92) 14%, rgba(48,28,8,.55) 32%, rgba(20,10,4,.08) 52%, rgba(0,0,0,0) 64%); pointer-events: none; }
        .landing-stage-copy { position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column; justify-content: space-between; padding: 8px max(16px, calc(50vw - 560px)) 36px; }
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
        .promo-nav { position: relative; z-index: 2; flex: 0 0 auto; height: 168px; margin: 4px 0 2px; overflow: visible; background: transparent; perspective: 1400px; }
        .ad-flip { position: relative; width: 100%; height: 100%; transform-style: preserve-3d; transition: transform .9s cubic-bezier(.45, .05, .2, 1); }
        .ad-flip.is-spot { transform: rotateX(180deg); }
        .ad-face { position: absolute; inset: 0; overflow: hidden; backface-visibility: hidden; -webkit-backface-visibility: hidden; }
        .ad-face-menu { transform: rotateX(0deg); }
        .ad-face-spot { transform: rotateX(180deg); pointer-events: none; }
        .ad-flip.is-spot .ad-face-menu { pointer-events: none; }
        .ad-flip.is-spot .ad-face-spot { pointer-events: auto; }
        .promo-nav-track { display: flex; height: 100%; width: max-content; align-items: center; gap: 16px; padding: 8px 0; animation: event-advert-scroll 42s linear infinite; }
        .ad-face-spot .promo-nav-track { animation-duration: 36s; }
        .promo-nav-track.is-still { animation: none; width: 100%; justify-content: center; }
        .promo-nav:hover .promo-nav-track, .promo-nav:focus-within .promo-nav-track { animation-play-state: paused; }
        .menu-slide, .spot-card { position: relative; display: block; width: 228px; height: 132px; flex: 0 0 228px; margin: 0; padding: 0; border: 2px solid #c9a24a; border-radius: 16px; overflow: hidden; background: #1a140c; box-shadow: 0 10px 24px rgba(0,0,0,.45); color: #fff; text-decoration: none; }
        button.spot-card { cursor: pointer; font: inherit; text-align: left; }
        .menu-slide img, .spot-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .spot-card::after { content: ""; position: absolute; left: 0; right: 0; bottom: 0; height: 54%; background: linear-gradient(to top, rgba(0,0,0,.62), transparent); pointer-events: none; }
        .promo-chip { position: absolute; left: 10px; right: 10px; bottom: 8px; z-index: 1; display: inline-flex; align-items: center; gap: 6px; width: max-content; max-width: calc(100% - 20px); background: linear-gradient(180deg, #f3c65a, #c48416); color: #1a1206; border-radius: 999px; padding: 5px 10px; font-weight: 800; font-size: .68rem; letter-spacing: .02em; box-shadow: 0 8px 18px rgba(0,0,0,.35); }
        .promo-chip i { font-size: .85rem; }
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
        .shop-board { display: grid; grid-template-columns: minmax(168px, 240px) minmax(0, 1fr); gap: 18px; align-items: start; margin-top: 28px; }
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
        .item-card { border: 0; border-radius: 28px; background: #fff; box-shadow: 0 12px 30px rgba(0, 0, 0, .08); overflow: hidden; }
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
        @media (prefers-reduced-motion: reduce) { .ad-flip { transition: none; } }
        @media (max-width: 576px) { .landing-screen { height: auto; min-height: calc(100vh - 54px); overflow: visible; } .landing-screen h1 { font-size: 2.3rem; } .landing-stage { min-height: 70vh; margin-top: 0; } .promo-nav { height: 118px; } .menu-slide, .spot-card { width: 168px; height: 96px; flex-basis: 168px; } .promo-chip { font-size: .62rem; padding: 4px 8px; } .club-hero { padding-top: 8px; } .club-hero h1 { font-size: 2.6rem; } .club-hero-photo { height: 220px; border-radius: 24px; } .shop-hero { padding: 22px 18px; border-radius: 22px; } .shop-hero h1 { font-size: 2rem; } .shop-board { grid-template-columns: minmax(118px, 42%) minmax(0, 1fr); gap: 10px; } .cat-panel { top: 64px; padding: 8px; border-radius: 16px; } .cat-tab { font-size: .82rem; padding: .45rem .5rem; } .shop-foot { margin-bottom: -110px; } }
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
        $scrollSlides = $menuSlides;
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
        <div class="promo-nav" id="adBar" aria-label="Menu and promotions">
            <div class="ad-flip" id="adFlip">
                <?php if ($scrollLoop): ?>
                <div class="ad-face ad-face-menu">
                    <div class="promo-nav-track">
                        <?php for ($copy = 0; $copy < 2; $copy++): foreach ($scrollLoop as $slide): ?>
                            <a class="menu-slide" href="<?= htmlspecialchars($slide['href']) ?>">
                                <img src="<?= htmlspecialchars($slide['src']) ?>" alt="<?= htmlspecialchars($slide['name']) ?>">
                            </a>
                        <?php endforeach; endfor; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($spotLoop): ?>
                <div class="ad-face ad-face-spot">
                    <div class="promo-nav-track<?= $spotScroll ? '' : ' is-still' ?>">
                        <?php for ($copy = 0; $copy < ($spotScroll ? 2 : 1); $copy++): foreach ($spotLoop as $slide): ?>
                            <?php if ($slide['kind'] === 'event'):
                                $ev = $slide['event'];
                                $cover = \App\Helpers\Media::url((string)($ev['cover_path'] ?? ''));
                            ?>
                            <button type="button" class="spot-card" data-event-slug="<?= htmlspecialchars((string)$ev['slug']) ?>" aria-label="Promo | click here for details">
                                <?php if ($cover !== ''): ?><img src="<?= htmlspecialchars($cover) ?>" alt="<?= htmlspecialchars((string)$ev['title']) ?>"><?php endif; ?>
                                <span class="promo-chip"><i class="bi bi-stars"></i> Promo | click here for details</span>
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
        foreach ($products as $i => $p) {
            $family = $shopFamily((string)$p['category'], (string)($p['parent_name'] ?? ''));
            $products[$i]['family'] = $family;
            $groups[$family][(string)$p['category']] = ($groups[$family][(string)$p['category']] ?? 0) + 1;
        }
        $menuGroups = [];
        foreach (['Food', 'Beer & cider', 'Wine', 'Spirits', 'Cocktails', 'Soft drinks', 'Shisha', 'More'] as $familyName) {
            if (empty($groups[$familyName])) continue;
            ksort($groups[$familyName]);
            $menuGroups[$familyName] = $groups[$familyName];
        }
    ?>
    <section class="menu-top text-center">
        <h2>Order online</h2>
    </section>
    <div class="shop-board">
        <aside class="cat-panel" aria-label="Menu categories">
            <div class="cat-panel-title">Categories</div>
            <button type="button" class="cat-tab cat-all active" data-cat="" onclick="pickAll(this)">All <span class="count"><?= count($products) ?></span></button>
            <?php foreach ($menuGroups as $familyName => $kids):
                $familyCount = array_sum($kids);
            ?>
            <div class="cat-group">
                <button type="button" class="cat-group-btn" data-group="<?= htmlspecialchars($familyName) ?>" aria-expanded="false" onclick="pickGroup(this)">
                    <span><?= htmlspecialchars($familyName) ?></span>
                    <span class="count"><?= (int)$familyCount ?> <i class="bi bi-chevron-down cat-chevron"></i></span>
                </button>
                <div class="cat-children" hidden>
                    <?php foreach ($kids as $cat => $n): ?>
                    <button type="button" class="cat-tab cat-child" data-cat="<?= htmlspecialchars($cat) ?>" data-group="<?= htmlspecialchars($familyName) ?>" onclick="pickCat(this)"><span><?= htmlspecialchars($cat) ?></span> <span class="count"><?= (int)$n ?></span></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <hr>
            <button type="button" class="cat-tab" id="stockChip" data-stock="" onclick="toggleStock(this)"><span>In stock</span></button>
        </aside>
        <div>
    <div class="row g-2 mb-3 align-items-center">
        <?php if (count($shopLocations) > 1): ?>
        <div class="col-12 col-md-4">
            <label class="form-label small fw-bold text-muted mb-1" for="shopLoc">STORE</label>
            <select id="shopLoc" class="form-select" onchange="if (this.value) window.location = 'index.php?page=shop&amp;loc=' + encodeURIComponent(this.value)">
                <?php foreach ($shopLocations as $locRow): ?>
                <option value="<?= (int)$locRow['id'] ?>" <?= (int)$locRow['id'] === $shopLocationId ? 'selected' : '' ?>><?= htmlspecialchars($locRow['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php elseif ($shopLocationName !== ''): ?>
        <div class="col-12"><span class="badge rounded-pill text-bg-light border"><i class="bi bi-geo-alt-fill"></i> <?= htmlspecialchars($shopLocationName) ?></span></div>
        <?php endif; ?>
        <div class="<?= count($shopLocations) > 1 ? 'col-12 col-md-8' : 'col-12' ?>">
            <label class="form-label small fw-bold text-muted mb-1" for="menuSearch">SEARCH</label>
            <div class="input-group search-pill">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="search" id="menuSearch" class="form-control" placeholder="Name, category or code…" oninput="filterMenu()">
            </div>
        </div>
    </div>
    <div class="row g-3" id="menuGrid">
        <?php if (!$products): ?><div class="col-12 text-center text-muted py-5">The menu is being updated. Please check back shortly.</div><?php endif; ?>
        <?php foreach ($products as $i => $p): ?>
        <div class="col-12 col-sm-6 menu-item<?= $i >= 8 ? ' d-none' : '' ?>" data-id="<?= (int)$p['id'] ?>" data-cat="<?= htmlspecialchars($p['category']) ?>" data-group="<?= htmlspecialchars((string)($p['family'] ?? '')) ?>" data-available="<?= !empty($p['available']) ? '1' : '0' ?>" data-name="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'] . ' ' . $p['sku'] . ' ' . $shopLocationName)) ?>">
            <div class="card item-card h-100 <?= $p['available'] ? '' : 'unavailable' ?>">
                <?php $photo = \App\Helpers\Media::url((string)($p['image_path'] ?? '')); ?>
                <?php if ($photo !== ''): ?>
                <div class="item-face item-photo" style="background-image:linear-gradient(to top, rgba(20,10,4,.45), transparent 55%), url('<?= htmlspecialchars($photo, ENT_QUOTES) ?>')"><span><?= htmlspecialchars($p['category'] ?: 'Menu') ?></span></div>
                <?php else: ?>
                <div class="item-face"><i class="bi <?= $shopIcon((string)$p['category']) ?>"></i><span><?= htmlspecialchars($p['category'] ?: 'Menu') ?></span></div>
                <?php endif; ?>
                <div class="card-body d-flex flex-column pt-3">
                    <div class="item-name flex-grow-1"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="price"><?= $money($p['price']) ?></span>
                        <?php if ($p['available']): ?>
                        <button class="btn btn-accent btn-sm rounded-pill px-3" onclick="addToCart(<?= (int)$p['id'] ?>, this)"><i class="bi bi-plus-lg"></i> Add</button>
                        <?php else: ?><span class="badge rounded-pill text-bg-secondary">Sold out</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col-12 text-center text-muted py-5 d-none" id="menuEmpty">Nothing matches that search.</div>
    </div>
    <nav class="menu-pager" id="menuPager" aria-label="Menu pages" hidden></nav>
    </div>
    </div>
    <div class="cart-bar <?= $cartCount ? '' : 'd-none' ?>" id="cartBar">
        <div class="d-flex justify-content-between align-items-center">
            <div><span class="badge rounded-pill text-dark" style="background:var(--sun)" id="cartBarCount"><?= (int)$cartCount ?></span> in your bag · <strong id="cartBarTotal"><?= $money($cartTotal) ?></strong></div>
            <a href="<?= $u('cart') ?>" class="btn btn-accent rounded-pill px-4">View bag <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <script>
    function clearCatActive() { document.querySelectorAll('.cat-all, .cat-group-btn, .cat-child').forEach(function (b) { b.classList.remove('active'); }); }
    function closeGroups() {
        document.querySelectorAll('.cat-group-btn').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
            var kids = b.parentElement.querySelector('.cat-children');
            if (kids) kids.hidden = true;
        });
    }
    function pickAll(btn) { clearCatActive(); closeGroups(); btn.classList.add('active'); filterMenu(); }
    function pickGroup(btn) {
        var wasOpen = btn.classList.contains('active') && btn.getAttribute('aria-expanded') === 'true';
        clearCatActive();
        closeGroups();
        if (wasOpen) {
            document.querySelector('.cat-all').classList.add('active');
        } else {
            btn.classList.add('active');
            btn.setAttribute('aria-expanded', 'true');
            btn.parentElement.querySelector('.cat-children').hidden = false;
        }
        filterMenu();
    }
    function pickCat(btn) {
        clearCatActive();
        closeGroups();
        btn.classList.add('active');
        var box = btn.closest('.cat-children');
        if (box) {
            box.hidden = false;
            var head = box.parentElement.querySelector('.cat-group-btn');
            if (head) head.setAttribute('aria-expanded', 'true');
        }
        filterMenu();
    }
    function toggleStock(btn) { const on = btn.dataset.stock !== '1'; btn.dataset.stock = on ? '1' : ''; btn.classList.toggle('active', on); filterMenu(); }
    var menuPage = 1;
    var menuPageSize = 8;
    function filterMenu(keepPage) {
        if (keepPage !== true) menuPage = 1;
        const child = document.querySelector('.cat-child.active');
        const groupBtn = document.querySelector('.cat-group-btn.active');
        const cat = child ? (child.dataset.cat || '') : '';
        const group = !child && groupBtn ? (groupBtn.dataset.group || '') : '';
        const stockOnly = (document.getElementById('stockChip') || {}).dataset ? document.getElementById('stockChip').dataset.stock === '1' : false;
        const words = (document.getElementById('menuSearch').value || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
        const matches = [];
        document.querySelectorAll('.menu-item').forEach(function (el) {
            const okCat = (cat === '' && group === '') || (cat !== '' && el.dataset.cat === cat) || (cat === '' && group !== '' && el.dataset.group === group);
            const okStock = !stockOnly || el.dataset.available === '1';
            const hay = el.dataset.name || '';
            const okQ = !words.length || words.every(function (w) { return hay.indexOf(w) !== -1; });
            if (okCat && okStock && okQ) matches.push(el);
            else el.classList.add('d-none');
        });
        const pages = Math.max(1, Math.ceil(matches.length / menuPageSize));
        if (menuPage > pages) menuPage = pages;
        const start = (menuPage - 1) * menuPageSize;
        matches.forEach(function (el, i) {
            el.classList.toggle('d-none', i < start || i >= start + menuPageSize);
        });
        const empty = document.getElementById('menuEmpty');
        if (empty) empty.classList.toggle('d-none', matches.length !== 0);
        renderMenuPager(matches.length, pages);
    }
    function menuPageWindow(pages) {
        const keep = {};
        [1, pages, menuPage - 1, menuPage, menuPage + 1].forEach(function (n) {
            if (n >= 1 && n <= pages) keep[n] = true;
        });
        const nums = Object.keys(keep).map(Number).sort(function (a, b) { return a - b; });
        const out = [];
        nums.forEach(function (n, i) {
            if (i && n - nums[i - 1] > 1) out.push(0);
            out.push(n);
        });
        return out;
    }
    function renderMenuPager(total, pages) {
        const nav = document.getElementById('menuPager');
        if (!nav) return;
        if (!total) {
            nav.hidden = true;
            nav.innerHTML = '';
            return;
        }
        const from = (menuPage - 1) * menuPageSize + 1;
        const to = Math.min(total, menuPage * menuPageSize);
        let html = '<p class="menu-pager-status">' + from + '–' + to + ' of ' + total + '</p>';
        if (pages > 1) {
            html += '<div class="menu-pager-pages">';
            html += '<button type="button" class="menu-page" data-page="' + (menuPage - 1) + '"' + (menuPage === 1 ? ' disabled' : '') + ' aria-label="Previous page">Prev</button>';
            menuPageWindow(pages).forEach(function (n) {
                if (!n) html += '<span class="menu-page-gap" aria-hidden="true">…</span>';
                else html += '<button type="button" class="menu-page' + (n === menuPage ? ' is-on' : '') + '" data-page="' + n + '" aria-label="Page ' + n + '"' + (n === menuPage ? ' aria-current="page"' : '') + '>' + n + '</button>';
            });
            html += '<button type="button" class="menu-page" data-page="' + (menuPage + 1) + '"' + (menuPage === pages ? ' disabled' : '') + ' aria-label="Next page">Next</button>';
            html += '</div>';
        }
        nav.innerHTML = html;
        nav.hidden = false;
    }
    document.getElementById('menuPager').addEventListener('click', function (e) {
        const btn = e.target.closest('[data-page]');
        if (!btn || btn.disabled) return;
        menuPage = Number(btn.dataset.page);
        filterMenu(true);
        const grid = document.getElementById('menuGrid');
        if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    (function () {
        const want = new URLSearchParams(window.location.search).get('cat');
        if (!want) { filterMenu(); return; }
        const btn = Array.from(document.querySelectorAll('.cat-child')).find(function (b) { return b.dataset.cat === want; });
        if (btn) pickCat(btn);
        else filterMenu();
    })();
    function addToCart(pid, btn) { btn.disabled = true; const body = new URLSearchParams({ cart_add: pid, qty: 1, ajax: 1 }); fetch('index.php?page=shop&view=menu', { method: 'POST', body: body, credentials: 'same-origin' }).then(r => r.json()).then(function (res) { btn.disabled = false; if (res.status !== 'ok') { alert(res.msg || 'Could not add'); return; } document.getElementById('cartBar').classList.remove('d-none'); document.getElementById('cartBarCount').textContent = res.count; document.getElementById('cartBarTotal').textContent = 'K' + Number(res.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); btn.innerHTML = '<i class="bi bi-check-lg"></i> Added'; setTimeout(function () { btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add'; }, 900); }).catch(function () { btn.disabled = false; }); }
    </script>

<?php elseif ($view === 'cart'): ?>
    <h4 class="page-title mb-3">Your bag</h4>
    <?php if (!$cart): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-basket fs-1"></i><p class="mt-2">Nothing here yet.</p><a href="<?= $u('menu') ?>" class="btn btn-accent rounded-pill px-4">Browse the menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('cart') ?>">
        <input type="hidden" name="cart_update" value="1">
        <div class="card item-card mb-3">
            <ul class="list-group list-group-flush">
            <?php foreach ($cart as $line): ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                    <div class="flex-grow-1"><div class="fw-bold"><?= htmlspecialchars($line['name']) ?></div><div class="small text-muted"><?= $money($line['price']) ?> each</div></div>
                    <input type="number" min="0" name="qty[<?= (int)$line['id'] ?>]" value="<?= (int)$line['qty'] ?>" class="form-control text-center" style="width:80px" onchange="this.form.submit()">
                    <div class="price text-end" style="min-width:90px"><?= $money($line['price'] * $line['qty']) ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white d-flex justify-content-between fw-bold fs-5"><span>Subtotal</span><span><?= $money($cartTotal) ?></span></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $u('menu') ?>" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-plus"></i> Add more</a>
            <button type="submit" class="btn btn-outline-secondary rounded-pill">Update</button>
            <button type="submit" name="cart_clear" value="1" class="btn btn-outline-danger rounded-pill" formnovalidate>Clear</button>
            <a href="<?= $u('checkout') ?>" class="btn btn-accent rounded-pill px-4 ms-auto">Checkout <i class="bi bi-arrow-right"></i></a>
        </div>
    </form>
    <?php if ($minOrder > 0 && $cartTotal < $minOrder): ?><div class="alert alert-warning mt-3 small">Minimum order is <?= $money($minOrder) ?>.</div><?php endif; ?>
    <?php endif; ?>

<?php elseif ($view === 'checkout'): ?>
    <h4 class="page-title mb-3">Checkout</h4>
    <?php if (!$cart): ?><div class="alert alert-warning">Your basket is empty. <a href="<?= $u('menu') ?>">Back to menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('checkout') ?>" class="row g-3">
        <input type="hidden" name="place_order" value="1">
        <div class="col-lg-7">
            <div class="card item-card mb-3"><div class="card-body">
                <label class="form-label fw-bold small text-muted">HOW WOULD YOU LIKE IT?</label>
                <div class="d-flex gap-2 mb-3">
                    <input type="radio" class="btn-check" name="order_type" id="otPickup" value="pickup" checked onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otPickup"><i class="bi bi-bag"></i> Collect in store</label>
                    <?php if ($deliveryOn): ?><input type="radio" class="btn-check" name="order_type" id="otDelivery" value="delivery" onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otDelivery"><i class="bi bi-bicycle"></i> Delivery<?= $deliveryFee > 0 ? ' (+' . $money($deliveryFee) . ')' : '' ?></label><?php endif; ?>
                </div>
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
                <?php if ($flwOn): ?><div class="form-check mb-2"><input class="form-check-input" type="radio" name="pay_mode" id="pmOnline" value="online" <?= !$counterOn ? 'checked' : '' ?>><label class="form-check-label" for="pmOnline"><i class="bi bi-phone"></i> Pay now — Mobile Money or Card <span class="badge bg-light text-dark border">Flutterwave</span></label></div><?php endif; ?>
                <?php if ($counterOn): ?><div class="form-check"><input class="form-check-input" type="radio" name="pay_mode" id="pmCounter" value="counter" checked><label class="form-check-label" for="pmCounter"><i class="bi bi-cash"></i> Pay at the counter / on delivery</label></div><?php endif; ?>
                <?php if (!$flwOn && !$counterOn): ?><div class="text-danger small">No payment option is enabled. Please contact the store.</div><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card item-card"><div class="card-body">
                <h6 class="fw-bold">Order summary</h6>
                <?php foreach ($cart as $line): ?><div class="d-flex justify-content-between small"><span><?= (int)$line['qty'] ?> × <?= htmlspecialchars($line['name']) ?></span><span><?= $money($line['price'] * $line['qty']) ?></span></div><?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= $money($cartTotal) ?></span></div>
                <div class="d-flex justify-content-between text-muted d-none" id="feeRow"><span>Delivery</span><span><?= $money($deliveryFee) ?></span></div>
                <div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>Total</span><span id="grandTotal" data-sub="<?= $cartTotal ?>" data-fee="<?= $deliveryFee ?>"><?= $money($cartTotal) ?></span></div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3" <?= (!$flwOn && !$counterOn) ? 'disabled' : '' ?>>Place order</button>
                <a href="<?= $u('cart') ?>" class="btn btn-link w-100 text-muted small">Back to basket</a>
            </div></div>
        </div>
    </form>
    <script>
    function toggleDelivery() { const d = document.getElementById('otDelivery'); const on = d && d.checked; document.getElementById('deliveryBox').classList.toggle('d-none', !on); document.getElementById('feeRow').classList.toggle('d-none', !on); const g = document.getElementById('grandTotal'); const t = parseFloat(g.dataset.sub) + (on ? parseFloat(g.dataset.fee) : 0); g.textContent = 'K' + t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
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
            <div class="mt-1"><span class="badge <?= $live ? ((int)$o['items_cooking'] > 0 ? 'bg-warning text-dark' : 'bg-info text-dark') : ($o['status'] === 'completed' ? 'bg-success' : 'bg-secondary') ?>"><?= $live ? ((int)$o['items_cooking'] > 0 ? 'Being prepared' : 'Ready for collection') : ucfirst($o['status']) ?></span> <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-light text-dark border' ?>"><?= $o['payment_status'] === 'paid' ? 'Paid' : 'Pay at counter' ?></span></div>
        </a>
    <?php endforeach; ?>
    </div>

<?php elseif ($view === 'order' && !empty($order)): ?>
    <?php $o = $order; $cooking = count(array_filter($o['items'], fn($i) => $i['status'] !== 'ready')); $done = $o['status'] !== 'pending'; ?>
    <a href="<?= $u('orders') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> All orders</a>
    <div class="card item-card mt-2 mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div><h4 class="fw-bold mb-0">Order #<?= (int)$o['id'] ?></h4><div class="small text-muted"><?= date('d M Y H:i', strtotime($o['created_at'])) ?> · <?= strtoupper(htmlspecialchars($o['order_type'] ?? 'pickup')) ?></div></div>
            <span class="badge fs-6 <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $o['payment_status'] === 'paid' ? 'PAID' : 'PAY AT COUNTER' ?></span>
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
        <?php if ($o['gateway_ref']): ?><div class="small text-muted">Payment ref <?= htmlspecialchars($o['gateway_ref']) ?></div><?php endif; ?>
        <?php if ($o['zra_rcpt_no']): ?><div class="small text-muted">ZRA receipt no <?= htmlspecialchars($o['zra_rcpt_no']) ?></div><?php endif; ?>
    </div></div>
    <?php if (!$done): ?><p class="text-center small text-muted">This page refreshes automatically.</p><script>setTimeout(function () { location.reload(); }, 30000);</script><?php endif; ?>
    <?php if (!empty($settings['shop_phone'])): ?><p class="text-center small">Questions? Call <a href="tel:<?= htmlspecialchars($settings['shop_phone']) ?>"><?= htmlspecialchars($settings['shop_phone']) ?></a></p><?php endif; ?>

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
                        <li><?= htmlspecialchars($opt['name']) ?><?php if ((float)$opt['price'] > 0): ?> · <?= $money($opt['price']) ?><?php endif; ?></li>
                    <?php endforeach; ?>
                    </ul>
                <?php endforeach; ?>
            </div></div>
        <?php endif; ?>
        <div class="card item-card"><div class="card-body p-4">
            <?php $copy = \App\Models\Event::signupCopy((string)($event['signup_mode'] ?? 'apply')); ?>
            <h5 class="fw-bold"><?= htmlspecialchars($copy['heading']) ?></h5>
            <p class="text-muted small"><?= htmlspecialchars($copy['lead']) ?><?php if ((float)$event['deposit_amount'] > 0): ?> A deposit of <?= $money($event['deposit_amount']) ?> is collected on your private page first.<?php endif; ?><?php if (!empty($tiers)): ?> Tier prices are per person.<?php endif; ?></p>
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
                    <span><?= $money($tier['price']) ?> each</span>
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
        </section>
        <div class="card item-card mb-3"><div class="card-body p-4">
            <div class="d-flex justify-content-between"><span>Reply</span><strong><?= htmlspecialchars((string)$guest['status']) ?></strong></div>
            <?php if (!empty($guest['tier_name'])): ?>
            <div class="d-flex justify-content-between"><span>Tier</span><strong><?= htmlspecialchars((string)$guest['tier_name']) ?> · <?= $money($guest['tier_price'] ?? 0) ?> each</strong></div>
            <?php endif; ?>
            <div class="d-flex justify-content-between"><span>Paid</span><strong><?= $money($paid ?? 0) ?></strong></div>
            <?php if (($charge['amount'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between"><span><?= htmlspecialchars(ucfirst((string)$charge['purpose'])) ?> due</span><strong><?= $money($charge['amount']) ?></strong></div>
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
                            <span><?= $money($tier['price']) ?> each</span>
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
                            <span><?= (float)$opt['price'] > 0 ? $money($opt['price']) : '' ?></span>
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
                    <div class="text-muted small"><?= $money($charge['amount']) ?> through Flutterwave. Cash can be taken at the restaurant.</div>
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
                    <span><?= $money($pay['amount']) ?> · <?= htmlspecialchars($pay['status']) ?></span>
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
                                    <li><?= htmlspecialchars($opt['name']) ?><?php if ((float)$opt['price'] > 0): ?> · <?= $money($opt['price']) ?><?php endif; ?></li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endforeach; ?>
                        </div></div>
                    <?php endif; ?>
                    <div class="card item-card"><div class="card-body p-4">
                        <h5 class="fw-bold"><?= htmlspecialchars($copy['heading']) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars($copy['lead']) ?><?php if ((float)$ev['deposit_amount'] > 0): ?> A deposit of <?= $money($ev['deposit_amount']) ?> is collected on your private page first.<?php endif; ?><?php if ($regTiers): ?> Tier prices are per person.<?php endif; ?></p>
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
                                <span><?= $money($tier['price']) ?> each</span>
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
    var flip = document.getElementById('adFlip');
    var bar = document.getElementById('adBar');
    if (!flip || !bar) return;
    var spot = flip.querySelector('.ad-face-spot');
    var menu = flip.querySelector('.ad-face-menu');
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
        var track = document.querySelector('.ad-face-menu .promo-nav-track');
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
</body>
</html>
