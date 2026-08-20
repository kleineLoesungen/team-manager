<?php
// src/templates/layout.php — Shared HTML layout functions

declare(strict_types=1);

function render_layout_head(string $title = 'Team Manager'): void {
    static $brand_color = null;
    if ($brand_color === null) {
        try {
            $pdo  = get_db();
            $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'app_color'");
            $stmt->execute();
            $raw  = $stmt->fetchColumn() ?: '#2563eb';
            $brand_color = preg_match('/^#[0-9a-fA-F]{6}$/', $raw) ? $raw : '#2563eb';
        } catch (Throwable) {
            $brand_color = '#2563eb';
        }
    }
    $safe_color = htmlspecialchars($brand_color, ENT_QUOTES);
    $full_title = $title !== 'Team Manager' ? e($title) . ' — Team Manager' : 'Team Manager';
    ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <!-- FOUC prevention: apply saved theme before any CSS renders -->
    <script>
    (function(){
        var t = localStorage.getItem('tm-theme') || 'light';
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.setAttribute('data-bs-theme', t);
    }());
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?= $full_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet">
    <style>
        /* ── Design tokens — light (default) ──────────────────────────────── */
        :root {
            --brand:    <?= $safe_color ?>;   /* used ONLY in .tab-item.is-on */

            --bg:       #F2F2F7;
            --surface:  #FFFFFF;
            --surface-2:#F2F2F7;
            --line:     rgba(60, 60, 67, .15);

            --t1:       #000000;
            --t2:       rgba(60, 60, 67, .85);
            --t3:       rgba(60, 60, 67, .65);

            --ok:       #1A7F3C;  --ok-bg:   #E5F4EC;
            --warn:     #92510A;  --warn-bg: #FEF0C7;
            --bad:      #B91C1C;  --bad-bg:  #FEE2E2;
            --blue:     #1D4ED8;  --blue-bg: #DBEAFE;

            --tab-bg:   rgba(249, 249, 249, .94);
            --tab-h:    49px;
            --safe-b:   env(safe-area-inset-bottom, 0px);
            --bar-h:    calc(var(--tab-h) + var(--safe-b));
            --topbar-h: 50px;
            --pad:      16px;
            --r:        12px;
        }

        /* ── Dark mode — applied when data-theme="dark" is on <html> ──────── */
        [data-theme="dark"] {
            --bg:       #000000;
            --surface:  #1C1C1E;
            --surface-2:#2C2C2E;
            --line:     rgba(255, 255, 255, .10);

            --t1:       #FFFFFF;
            --t2:       rgba(235, 235, 245, .80);
            --t3:       rgba(235, 235, 245, .38);

            --ok:       #30D158;  --ok-bg:   #0D2818;
            --warn:     #FFB340;  --warn-bg: #2A1A00;
            --bad:      #FF453A;  --bad-bg:  #330A08;
            --blue:     #93C5FD;  --blue-bg: #1E3A5F;

            --tab-bg:   rgba(28, 28, 30, .94);
        }

        /* ── Base ──────────────────────────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; }
        html { -webkit-text-size-adjust: 100%; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', system-ui, sans-serif;
            background: var(--bg);
            color: var(--t1);
            margin: 0;
            overscroll-behavior-y: none;
        }

        /* ── App shell ─────────────────────────────────────────────────────── */
        .app          { max-width: 540px; margin: 0 auto; min-height: 100dvh; }
        .app-content  { padding: var(--pad); padding-bottom: calc(var(--bar-h) + var(--pad) + 8px); }

        /* ── Topbar ────────────────────────────────────────────────────────── */
        .topbar {
            position: sticky; top: 0; z-index: 200;
            height: var(--topbar-h);
            background: var(--surface);
            border-bottom: .5px solid var(--line);
            display: flex; align-items: center; gap: 10px;
            padding: 0 var(--pad);
        }
        .topbar-logo    { height: 28px; border-radius: 5px; flex-shrink: 0; object-fit: contain; }
        .topbar-title   { font-size: 17px; font-weight: 600; color: var(--t1); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .topbar-context { font-size: 12px; color: var(--t3); white-space: nowrap; flex-shrink: 0; }
        .btn-theme {
            background: none; border: none; cursor: pointer;
            color: var(--t2); border-radius: 8px; padding: 5px 6px;
            flex-shrink: 0; line-height: 1; font-size: 18px;
        }
        .btn-theme:hover { background: var(--surface-2); }

        /* ── Bottom tab bar — brand color ONLY here ────────────────────────── */
        .tabbar {
            position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
            width: 100%; max-width: 540px; z-index: 200;
            background: var(--tab-bg);
            backdrop-filter: blur(20px) saturate(1.8);
            -webkit-backdrop-filter: blur(20px) saturate(1.8);
            border-top: .5px solid var(--line);
            display: flex; padding-bottom: var(--safe-b);
        }
        .tab-item {
            flex: 1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 3px;
            padding: 9px 0 4px; height: var(--tab-h);
            text-decoration: none; color: var(--t3);
            transition: color .14s;
            background: none; border: none; cursor: pointer;
        }
        .tab-item.is-on            { color: var(--brand); } /* ← sole usage of --brand */
        .tab-item i[class*="bi"]   { font-size: 24px; line-height: 1; display: block; }
        .tab-label                 { font-size: 10px; font-weight: 500; line-height: 1; }

        /* ── Cards ─────────────────────────────────────────────────────────── */
        .card         { background: var(--surface) !important; border: none !important; border-radius: var(--r) !important; box-shadow: none !important; }
        .card:hover   { transform: none !important; box-shadow: none !important; }
        .card-header  { background: var(--surface) !important; border-bottom: .5px solid var(--line) !important; font-weight: 600; color: var(--t1); }
        .card-body    { color: var(--t1); }
        .card-footer  { background: var(--surface) !important; border-top: .5px solid var(--line) !important; color: var(--t2); }
        .card-title   { color: var(--t1); }
        .card-text    { color: var(--t2); }

        /* ── List group — iOS hairline style ───────────────────────────────── */
        .list-group         { border-radius: var(--r) !important; overflow: hidden; background: var(--surface); }
        .list-group-item    { background: var(--surface) !important; color: var(--t1) !important; border: none !important; border-bottom: .5px solid var(--line) !important; padding: 12px var(--pad) !important; }
        .list-group-item:last-child { border-bottom: none !important; }
        .list-group-item-action:hover { background: var(--surface-2) !important; }
        .list-group-item.active { background: var(--surface-2) !important; color: var(--t1) !important; border-color: transparent !important; }

        /* ── Badges — semantic, no brand ───────────────────────────────────── */
        .badge {
            font-size: 11.5px !important; font-weight: 600 !important;
            padding: 3px 8px !important; border-radius: 20px !important; border: none !important;
        }
        .badge.bg-success,  .badge.bg-success-subtle  { background-color: var(--ok-bg)    !important; color: var(--ok)  !important; }
        .badge.bg-danger,   .badge.bg-danger-subtle   { background-color: var(--bad-bg)   !important; color: var(--bad) !important; }
        .badge.bg-warning                              { background-color: var(--warn-bg)  !important; color: var(--warn)!important; }
        .badge.bg-secondary,.badge.bg-secondary-subtle { background-color: var(--surface-2)!important; color: var(--t3)  !important; }
        .badge.bg-primary,  .badge.bg-primary-subtle  { background-color: var(--blue-bg)  !important; color: var(--blue) !important; }
        .badge.text-success-emphasis   { color: var(--ok)  !important; }
        .badge.text-danger-emphasis    { color: var(--bad) !important; }
        .badge.text-secondary-emphasis { color: var(--t3)  !important; }
        .badge.text-primary-emphasis   { color: var(--t2)  !important; }
        .badge.text-dark               { color: var(--t1)  !important; }
        .badge-ok   { background: var(--ok-bg)    !important; color: var(--ok)  !important; }
        .badge-warn { background: var(--warn-bg)  !important; color: var(--warn) !important; }
        .badge-bad  { background: var(--bad-bg)   !important; color: var(--bad)  !important; }
        .badge-dim  { background: var(--surface-2)!important; color: var(--t3)   !important; }

        /* ── Buttons — one system, two weights ─────────────────────────────── */
        .btn {
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            border-radius: 10px !important;
            font-weight: 500 !important;
            letter-spacing: .01em;
            border: none !important;
            transition: opacity .15s, transform .1s !important;
        }
        .btn:active { transform: scale(.96) !important; opacity: .85 !important; }
        .min-touch  { min-height: 44px; }

        /* Primary — medium gray in light / light gray in dark */
        .btn-primary, .btn-dark {
            background: #4B5563 !important;
            color: #FFFFFF !important;
        }
        [data-theme="dark"] .btn-primary,
        [data-theme="dark"] .btn-dark {
            background: #D1D1D6 !important;
            color: #1C1C1E !important;
        }
        .btn-primary:hover, .btn-dark:hover { opacity: .85 !important; }

        /* Tonal — all secondary/neutral variants use the same surface-2 slab */
        .btn-secondary,
        .btn-outline-secondary,
        .btn-outline-primary,
        .btn-light {
            background: var(--surface-2) !important;
            color: var(--t1) !important;
        }
        .btn-secondary:hover,
        .btn-outline-secondary:hover,
        .btn-outline-primary:hover,
        .btn-light:hover { opacity: .75 !important; }

        /* Destructive — subtle red tint, red text */
        .btn-outline-danger {
            background: var(--bad-bg) !important;
            color: var(--bad) !important;
        }
        .btn-outline-danger:hover { opacity: .8 !important; }

        /* Solid danger (confirm / danger-zone pages only) */
        .btn-danger {
            background: var(--bad) !important;
            color: #fff !important;
        }
        .btn-danger:hover { opacity: .85 !important; }

        /* Positive — subtle green tint */
        .btn-outline-success {
            background: var(--ok-bg) !important;
            color: var(--ok) !important;
        }
        .btn-outline-success:hover { opacity: .8 !important; }
        .btn-success {
            background: var(--ok) !important;
            color: #fff !important;
        }

        /* Warning */
        .btn-outline-warning {
            background: var(--warn-bg) !important;
            color: var(--warn) !important;
        }
        .btn-warning {
            background: var(--warn) !important;
            color: #fff !important;
        }

        /* Link button — plain text action */
        .btn-link {
            background: none !important;
            color: var(--t2) !important;
            text-decoration: none !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
        }
        .btn-link:hover { color: var(--t1) !important; }

        /* ── Form controls — readable in both themes ───────────────────────── */
        .form-control, .form-select, .form-control-sm, .form-select-sm {
            background-color: var(--surface) !important;
            color: var(--t1) !important;
            border-color: var(--line) !important;
            border-radius: 10px !important;
        }
        .form-control::placeholder, .form-select::placeholder { color: var(--t3) !important; opacity: 1; }
        .form-label { color: var(--t1); }
        .form-text  { color: var(--t3) !important; }
        .input-group-text {
            background-color: var(--surface-2) !important;
            border-color: var(--line) !important;
            color: var(--t3) !important;
        }
        .form-check-label { color: var(--t1); }
        .form-select option { background: var(--surface); color: var(--t1); }
        .form-control-plaintext { color: var(--t1) !important; }

        /* ── Alerts — semantic token mapping ───────────────────────────────── */
        .alert            { border: none !important; border-radius: var(--r) !important; }
        .alert-success    { background: var(--ok-bg)    !important; color: var(--ok)  !important; }
        .alert-danger     { background: var(--bad-bg)   !important; color: var(--bad) !important; }
        .alert-warning    { background: var(--warn-bg)  !important; color: var(--warn)!important; }
        .alert-info       { background: var(--surface-2)!important; color: var(--t2)  !important; }
        .alert-secondary  { background: var(--surface-2)!important; color: var(--t3)  !important; }
        .alert-light      { background: var(--surface-2)!important; color: var(--t2)  !important; }
        .alert-link       { color: inherit !important; text-decoration: underline; }
        .alert i[class*="bi"] { vertical-align: middle; }

        /* ── Tables ────────────────────────────────────────────────────────── */
        .table {
            --bs-table-bg:          var(--surface);
            --bs-table-color:       var(--t1);
            --bs-table-border-color:var(--line);
            --bs-table-striped-bg:  var(--surface-2);
            --bs-table-striped-color: var(--t1);
            --bs-table-hover-bg:    var(--surface-2);
            --bs-table-hover-color: var(--t1);
        }
        .table > :not(caption) > * > * {
            border-right: none; border-left: none;
            color: var(--t1);
            background-color: var(--surface);
        }
        .table-light, .table-secondary { --bs-table-bg: var(--surface-2); --bs-table-color: var(--t2); }
        .table > thead > tr > th { border-bottom-width: 1px; color: var(--t2); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        .table > tfoot > tr > td { border-top: 1px solid var(--line); border-bottom: none; }

        /* ── Modals ────────────────────────────────────────────────────────── */
        .modal-content   { background: var(--surface) !important; color: var(--t1); border: none !important; border-radius: var(--r) !important; }
        .modal-header    { border-bottom: .5px solid var(--line) !important; }
        .modal-footer    { border-top: .5px solid var(--line) !important; }
        .modal-title     { color: var(--t1); }

        /* ── Text & utility overrides ──────────────────────────────────────── */
        .text-muted    { color: var(--t3) !important; }
        .text-dark     { color: var(--t1) !important; }
        .text-body     { color: var(--t1) !important; }
        .bg-light      { background-color: var(--surface-2) !important; }
        .bg-white      { background-color: var(--surface)   !important; }

        /* ── Misc ──────────────────────────────────────────────────────────── */
        hr             { border-color: var(--line); opacity: 1; }
        .border        { border-color: var(--line) !important; }
        [class*="border-"]:not([class*="border-0"]) { border-color: var(--line) !important; }
        code           { color: var(--bad); background: var(--bad-bg); padding: 1px 5px; border-radius: 4px; }
        .credential-block { font-family: 'SF Mono', Menlo, Consolas, monospace; background: var(--surface-2); border-radius: var(--r); padding: var(--pad); color: var(--t1); }
        [data-save-scroll] { cursor: pointer; }

        /* ── Collapse ──────────────────────────────────────────────────────── */
        .collapse.show, .collapsing { color: var(--t1); }

        /* ── Dropdown ──────────────────────────────────────────────────────── */
        .dropdown-menu  { background: var(--surface) !important; border: .5px solid var(--line) !important; border-radius: var(--r) !important; }
        .dropdown-item  { color: var(--t1) !important; }
        .dropdown-item:hover, .dropdown-item:focus { background: var(--surface-2) !important; }
        .dropdown-divider { border-color: var(--line) !important; }
    </style>
    <link rel="icon" href="/logo">
</head>
<body>
<?php
}

function render_layout_foot(): void {
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
            crossorigin="anonymous"></script>
    <script>
    (function(){
        /* scroll restore */
        var skey = 'scroll:' + location.pathname.replace(/\?.*$/, '');
        var ssaved = sessionStorage.getItem(skey);
        if (ssaved !== null) { window.scrollTo(0, +ssaved); sessionStorage.removeItem(skey); }
        document.addEventListener('click', function(e) {
            var a = e.target.closest('[data-save-scroll]');
            if (a) sessionStorage.setItem('scroll:' + location.pathname.replace(/\?.*$/, ''), window.scrollY);
        });

        /* theme toggle */
        function tmApply(t) {
            document.documentElement.setAttribute('data-theme', t);
            document.documentElement.setAttribute('data-bs-theme', t);
            localStorage.setItem('tm-theme', t);
            var btn = document.getElementById('theme-toggle');
            if (!btn) return;
            var ico = btn.querySelector('i');
            if (ico) ico.className = t === 'dark' ? 'bi bi-sun' : 'bi bi-moon';
            btn.setAttribute('aria-label', t === 'dark' ? 'Hellmodus' : 'Dunkelmodus');
        }
        /* sync icon to current state */
        tmApply(localStorage.getItem('tm-theme') || 'light');

        var tmBtn = document.getElementById('theme-toggle');
        if (tmBtn) {
            tmBtn.addEventListener('click', function() {
                var cur = localStorage.getItem('tm-theme') || 'light';
                tmApply(cur === 'dark' ? 'light' : 'dark');
            });
        }
    }());
    </script>
</body>
</html>
    <?php
}

function render_navbar(): void {
    $team_name = htmlspecialchars($_SESSION['team_name'] ?? 'Team Manager', ENT_QUOTES);
    ?>
    <header class="topbar">
        <img src="/logo" alt="" class="topbar-logo" onerror="this.style.display='none'" loading="eager">
        <span class="topbar-title"><?= $team_name ?></span>
        <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
            <i class="bi bi-moon"></i>
        </button>
    </header>
    <div style="max-width:540px;margin:0 auto;padding:16px 16px 32px;">
    <?php
}

function render_login_page(string $error = '', string $message = ''): void {
    render_layout_head('Anmelden');
    require ROOT_PATH . '/src/templates/login.php';
    render_layout_foot();
    exit;
}
