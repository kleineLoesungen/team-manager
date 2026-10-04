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
    <meta name="apple-mobile-web-app-title" content="Team Manager">
    <meta name="theme-color" content="#2f3640">
    <title><?= $full_title ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
          rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM"
          crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css"
          rel="stylesheet"
          integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq"
          crossorigin="anonymous">
    <?php
    // Cache-bust on file mtime: no build step means no hashed filenames, so without
    // this a changed stylesheet stays cached in browsers and proxies indefinitely.
    $_css = ROOT_PATH . '/public/css/app.css';
    $_cssv = is_file($_css) ? filemtime($_css) : '';
    ?>
    <link rel="stylesheet" href="/css/app.css<?= $_cssv ? '?v=' . $_cssv : '' ?>">
    <style>:root{--brand:<?= $safe_color ?>;}</style>
    <link rel="icon" href="/logo">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon-180.png">
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

        /* Service Worker (public/sw.js): Installierbarkeit + Offline-Hinweis, kein Daten-Cache */
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('/sw.js').catch(function() {});
            });
        }

        /* "App installieren" (render_install_app). beforeinstallprompt feuert früh
           und nur in Chromium-Browsern; es wird gemerkt, bis der Button getippt wird. */
        var tmPrompt = null;
        var tmCard = document.querySelector('[data-install]');
        var ua = navigator.userAgent;
        var isAndroid = /Android/i.test(ua);
        // iPadOS meldet sich als Mac; erkennbar nur an den Touchpunkten
        var isIos = !isAndroid && (/iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));
        var installed = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

        function tmInstallUi() {
            if (!tmCard) return;
            var btn = tmCard.querySelector('[data-install-btn]');
            var steps = tmCard.querySelectorAll('[data-install-steps]');
            if (installed || (!tmPrompt && !isIos && !isAndroid)) { tmCard.hidden = true; return; }
            tmCard.hidden = false;
            btn.hidden = false;
            // Ohne nativen Dialog: nur die Schritte der eigenen Plattform, eingeklappt
            var mine = isIos ? 'ios' : 'android';
            steps.forEach(function(s) { s.hidden = true; s.dataset.mine = (s.dataset.installSteps === mine) ? '1' : ''; });
        }
        if (tmCard) {
            tmCard.querySelector('[data-install-btn]').addEventListener('click', function() {
                var btn = this;
                if (tmPrompt) {
                    tmPrompt.prompt();
                    tmPrompt.userChoice.then(function(c) {
                        tmPrompt = null;
                        if (c.outcome === 'accepted') { installed = true; }
                        tmInstallUi();
                    });
                    return;
                }
                var open = btn.getAttribute('aria-expanded') !== 'true';
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                tmCard.querySelectorAll('[data-install-steps]').forEach(function(s) {
                    s.hidden = !(open && s.dataset.mine === '1');
                });
            });
        }
        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();          // eigener Button statt Browser-Leiste
            tmPrompt = e;
            tmInstallUi();
        });
        window.addEventListener('appinstalled', function() { installed = true; tmInstallUi(); });
        tmInstallUi();

        /* Punkt am App-Icon ("es gibt etwas Neues", gesetzt vom Service Worker bei Push)
           verschwindet, sobald die App geöffnet ist */
        function tmClearBadge() {
            if (navigator.clearAppBadge && document.visibilityState === 'visible') navigator.clearAppBadge().catch(function() {});
        }
        tmClearBadge();
        document.addEventListener('visibilitychange', tmClearBadge);

        /* "Ticker abonnieren" (render_ticker_push_toggle): erst dieses Gerät für Push
           registrieren, dann das Formular abschicken. Abbestellen braucht kein Gerät. */
        var tmPushForm = document.querySelector('[data-push-form]');
        if (tmPushForm) {
            var tmPushHint = tmPushForm.querySelector('[data-push-hint]');
            var tmCanPush = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
            var tmPushKey = tmPushForm.getAttribute('data-push-key');
            var tmB64u = function(buf) {
                return btoa(String.fromCharCode.apply(null, new Uint8Array(buf))).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            };
            var tmKeyBytes = function(b64u) {
                var s = atob(b64u.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - b64u.length % 4) % 4));
                return Uint8Array.from(s, function(c) { return c.charCodeAt(0); });
            };
            var tmRegisterDevice = function() {
                return navigator.serviceWorker.ready.then(function(reg) {
                    return reg.pushManager.getSubscription().then(function(sub) {
                        // Mit anderem Server-Schlüssel angelegt? Dann neu abonnieren.
                        var k = sub && sub.options && sub.options.applicationServerKey;
                        if (sub && k && tmB64u(k) !== tmPushKey) return sub.unsubscribe().then(function() { return null; });
                        return sub;
                    }).then(function(sub) {
                        return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: tmKeyBytes(tmPushKey) });
                    });
                }).then(function(sub) {
                    return fetch('/push/subscribe', {
                        method: 'POST', credentials: 'same-origin',
                        body: new URLSearchParams({
                            _csrf: tmPushForm.querySelector('[name=_csrf]').value,
                            subscription: JSON.stringify(sub)
                        })
                    }).then(function(r) { if (!r.ok) throw new Error('push/subscribe ' + r.status); });
                });
            };
            var tmPushFail = function(text) {
                tmPushHint.textContent = text;
                tmPushHint.classList.add('text-danger');
            };
            tmPushForm.addEventListener('submit', function(e) {
                if (tmPushForm.querySelector('[name=on]').value !== '1') return;
                e.preventDefault();
                if (!tmCanPush) {
                    tmPushFail(isIos
                        ? 'Auf dem iPhone gehen Benachrichtigungen nur in der installierten App. Installiere sie im Profil und öffne den Ticker dort.'
                        : 'Dieser Browser kann keine Benachrichtigungen empfangen.');
                    return;
                }
                Notification.requestPermission().then(function(p) {
                    if (p !== 'granted') throw new Error('permission ' + p);
                    return tmRegisterDevice();
                }).then(function() {
                    tmPushForm.submit();
                }).catch(function() {
                    tmPushFail(Notification.permission === 'denied'
                        ? 'Benachrichtigungen sind für diese Seite blockiert. Erlaube sie in den Einstellungen deines Browsers und tipp dann noch einmal.'
                        : 'Das hat nicht geklappt. Prüf deine Verbindung und tipp noch einmal.');
                });
            });
            // Schon abonniert und erlaubt: Gerät still auffrischen, Push-Endpunkte können wechseln
            if (tmPushForm.getAttribute('data-push-on') === '1' && tmCanPush && Notification.permission === 'granted') {
                tmRegisterDevice().catch(function() {});
            }
        }

        /* Live-Ticker: Zuschauer melden (render_ticker_viewers, src/db/ticker_viewers.php).
           Der Server erkennt den Browser an der bestehenden Sitzung; gemeldet wird nur,
           solange die Seite sichtbar ist. */
        var tmPingEl = document.querySelector('[data-ticker-ping]');
        if (tmPingEl && window.fetch) {
            var tmTicker = tmPingEl.getAttribute('data-ticker-ping');
            var tmPingTimer = null;
            var tmPing = function() {
                if (document.visibilityState !== 'visible') return;
                fetch('/ticker/' + tmTicker + '/ping', { method: 'POST', credentials: 'same-origin' }).then(function(r) { return r.ok ? r.json() : null; }).then(function(d) {
                    if (!d) return;
                    if (d.status !== 'active') { clearInterval(tmPingTimer); return; }
                    if (typeof d.active === 'number') {
                        document.querySelectorAll('[data-viewers-now]').forEach(function(e) { e.textContent = d.active; });
                        document.querySelectorAll('[data-viewers-max]').forEach(function(e) { e.textContent = d.max; });
                    }
                }).catch(function() {});
            };
            tmPing();
            tmPingTimer = setInterval(tmPing, 30000);
            document.addEventListener('visibilitychange', tmPing);
            // Zurück-Taste aus dem Seiten-Cache: Seite ist wieder sichtbar, gleich melden
            window.addEventListener('pageshow', function(e) { if (e.persisted) tmPing(); });
        }

        /* Live-Ticker: neue Einträge ohne Neuladen. Getauscht wird nur der Inhalt der
           [data-ticker-refresh]-Bereiche (Eingabefelder bleiben unberührt) — alle 30 s, sofort
           wenn die Seite wieder sichtbar wird (z. B. nach Tipp auf eine Benachrichtigung) und
           wenn der Service Worker eine Push-Nachricht meldet. Nur bei laufendem Ticker. */
        if (document.querySelector('[data-ticker-refresh]') && document.querySelector('[data-ticker-ping]')
            && window.fetch && window.DOMParser) {
            var tmRefreshTimer = null;
            var tmRefreshBusy = false;
            var tmRefresh = function() {
                if (document.visibilityState !== 'visible' || tmRefreshBusy) return;
                tmRefreshBusy = true;
                fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })
                    .then(function(r) { return r.ok && !r.redirected ? r.text() : null; })
                    .then(function(html) {
                        if (!html) return;
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        document.querySelectorAll('[data-ticker-refresh]').forEach(function(el) {
                            var next = doc.querySelector('[data-ticker-refresh="' + el.getAttribute('data-ticker-refresh') + '"]');
                            if (next) el.innerHTML = next.innerHTML;
                        });
                        if (!doc.querySelector('[data-ticker-ping]')) clearInterval(tmRefreshTimer);   // Ticker beendet
                    })
                    .catch(function() {})
                    .then(function() { tmRefreshBusy = false; });
            };
            tmRefreshTimer = setInterval(tmRefresh, 30000);
            document.addEventListener('visibilitychange', tmRefresh);
            window.addEventListener('pageshow', function(e) { if (e.persisted) tmRefresh(); });
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.addEventListener('message', function(e) {
                    if (e.data && e.data.type === 'ticker-push') tmRefresh();
                });
            }
        }

        /* Teilen (render_share_button): System-Teilen-Menü, sonst Link kopieren */
        document.addEventListener('click', function(e) {
            var b = e.target.closest ? e.target.closest('[data-share-url]') : null;
            if (!b) return;
            var url = b.getAttribute('data-share-url');
            var title = b.getAttribute('data-share-title') || document.title;
            var text = b.getAttribute('data-share-text') || title;
            if (navigator.share) {
                navigator.share({ title: title, text: text, url: url }).catch(function() {});
                return;
            }
            var label = b.querySelector('[data-share-label]');
            var done = function() {
                if (!label) return;
                var old = label.textContent;
                label.textContent = 'Link kopiert';
                setTimeout(function() { label.textContent = old; }, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(done).catch(function() { window.prompt('Link kopieren:', url); });
            } else {
                window.prompt('Link kopieren:', url);
            }
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

    // Teamwechsel: Teamname in der Kopfzeile antippbar, sobald es mehr als ein Team gibt
    $switch_url = null;
    if (in_array($role, ['coordinator', 'member'], true) && !empty($_SESSION['user_id']) && empty($_SESSION['pending_team_pick'])) {
        try {
            require_once ROOT_PATH . '/src/db/team_switch.php';
            if (count(team_switch_options(get_db())) > 1) {
                $switch_url = $role === 'coordinator' ? '/coordinator/switch-team' : '/member/switch-team';
            }
        } catch (Throwable $e) {
            error_log('team_switch_options: ' . $e->getMessage());
        }
    }

    // Punkt am Reiter "Ticker": laufender Ticker, den die Übersicht noch nicht gezeigt hat
    $ticker_new = false;
    if (in_array($role, ['coordinator', 'member'], true) && $active !== 'ticker'
        && !empty($_SESSION['user_id']) && !empty($_SESSION['team_id'])) {
        try {
            require_once ROOT_PATH . '/src/db/ticker_seen.php';
            $ticker_new = ticker_has_unseen(get_db(), (int)$_SESSION['user_id'], (int)$_SESSION['team_id']);
        } catch (Throwable $e) {
            error_log('ticker_has_unseen: ' . $e->getMessage());   // nie die Seite dafür scheitern lassen
        }
    }
    ?>
    <div class="app">
        <header class="topbar">
            <img src="/logo" alt="" class="topbar-logo" onerror="this.style.display='none'" loading="eager">
            <?php if ($switch_url): ?>
            <a href="<?= $switch_url ?>" class="topbar-title topbar-switch" aria-label="Team wechseln, aktuell <?= $team_name ?>"><?= $team_name ?><i class="bi bi-chevron-expand ms-1" aria-hidden="true"></i></a>
            <?php else: ?>
            <span class="topbar-title"><?= $team_name ?></span>
            <?php endif; ?>
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
                <?php if ($key === 'ticker' && $ticker_new): ?>
                <span class="tab-dot"><span class="visually-hidden">Neuer Ticker</span></span>
                <?php endif; ?>
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
