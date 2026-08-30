<?php
// src/templates/layout.php — Shared HTML layout functions

declare(strict_types=1);

require_once __DIR__ . '/components/partials.php';

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
          rel="stylesheet"
          integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC"
          crossorigin="anonymous">
    <link rel="stylesheet" href="/css/app.css">
    <style>:root{--brand:<?= $safe_color ?>;}</style>
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


/**
 * Unified layout for all roles. Replaces render_coach_page(), render_member_page(),
 * render_admin_page() as the canonical layout function.
 *
 * @param array    $opts  Keys: 'title' (string), 'role' (admin|coordinator|member|public),
 *                        'active' (string — active tab key), 'back' (string|null — back URL)
 * @param callable $body  Outputs the main content HTML
 */
function render_page(array $opts, callable $body): void {
    $title  = $opts['title']  ?? 'Team Manager';
    $role   = $opts['role']   ?? 'coordinator';
    $active = $opts['active'] ?? '';
    $back   = $opts['back']   ?? null;

    render_layout_head($title);

    $tab_maps = [
        'coordinator' => [
            'members' => ['href' => '/coordinator/members', 'icon' => 'bi-person-vcard',  'label' => 'Mitglieder'],
            'lists'   => ['href' => '/coordinator/lists',   'icon' => 'bi-collection',     'label' => 'Listen'],
            'ticker'  => ['href' => '/coordinator/ticker',  'icon' => 'bi-megaphone',      'label' => 'Ticker'],
            'stats'   => ['href' => '/coordinator/stats',   'icon' => 'bi-graph-up',       'label' => 'Statistik'],
            'profile' => ['href' => '/coordinator/profile', 'icon' => 'bi-person-circle',  'label' => 'Profil'],
        ],
        'member' => [
            'lists'   => ['href' => '/member/lists',   'icon' => 'bi-collection',   'label' => 'Listen'],
            'ticker'  => ['href' => '/member/ticker',  'icon' => 'bi-megaphone',     'label' => 'Ticker'],
            'stats'   => ['href' => '/member/stats',   'icon' => 'bi-graph-up',      'label' => 'Statistik'],
            'profile' => ['href' => '/member/profile', 'icon' => 'bi-person-circle', 'label' => 'Profil'],
        ],
        'admin' => [
            'teams'        => ['href' => '/admin/teams',        'icon' => 'bi-people-fill',  'label' => 'Teams'],
            'coordinators' => ['href' => '/admin/coordinators', 'icon' => 'bi-person-badge', 'label' => 'Koordinatoren'],
            'players'      => ['href' => '/admin/members',      'icon' => 'bi-person-vcard', 'label' => 'Mitglieder'],
            'clubs'        => ['href' => '/admin/clubs',        'icon' => 'bi-building',     'label' => 'Klubs'],
            'settings'     => ['href' => '/admin/settings',     'icon' => 'bi-gear-fill',    'label' => 'Einstellungen'],
        ],
    ];

    $team_name = htmlspecialchars($_SESSION['team_name'] ?? 'Team Manager', ENT_QUOTES);
    ?>
    <div class="app">
        <header class="topbar">
            <img src="/logo" alt="" class="topbar-logo" onerror="this.style.display='none'" loading="eager">
            <span class="topbar-title"><?= $team_name ?></span>
            <?php if ($back): ?>
            <a href="<?= htmlspecialchars($back, ENT_QUOTES) ?>" class="topbar-context text-decoration-none">
                <i class="bi bi-chevron-left"></i> Zurück
            </a>
            <?php else: ?>
            <span class="topbar-context"><?= e($title) ?></span>
            <?php endif; ?>
            <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
                <i class="bi bi-moon"></i>
            </button>
        </header>

        <main class="app-content">
            <?php $body(); ?>
        </main>

        <?php if ($role !== 'public' && isset($tab_maps[$role])): ?>
        <nav class="tabbar" aria-label="Hauptnavigation">
            <?php foreach ($tab_maps[$role] as $key => $tab): ?>
            <a href="<?= $tab['href'] ?>"
               class="tab-item <?= $active === $key ? 'is-on' : '' ?>"
               aria-current="<?= $active === $key ? 'page' : 'false' ?>">
                <i class="bi <?= $tab['icon'] ?>"></i>
                <span class="tab-label"><?= $tab['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
    </div>
    <?php
    render_layout_foot();
    exit;
}

function render_login_page(string $error = '', string $message = ''): void {
    render_layout_head('Anmelden');
    require ROOT_PATH . '/src/templates/login.php';
    render_layout_foot();
    exit;
}
