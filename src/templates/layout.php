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


function render_login_page(string $error = '', string $message = ''): void {
    render_layout_head('Anmelden');
    require ROOT_PATH . '/src/templates/login.php';
    render_layout_foot();
    exit;
}
