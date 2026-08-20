<?php
// src/templates/public/ticker_overview.php — Public ticker list (no auth)
// Variables: $teams_with_tickers (array of {team, tickers[]}), $app_title (string)
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <script>(function(){var t=localStorage.getItem('tm-theme')||'light';document.documentElement.setAttribute('data-theme',t);document.documentElement.setAttribute('data-bs-theme',t);}());</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Live-Ticker — <?= e($app_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg:       #F2F2F7;
            --surface:  #FFFFFF;
            --surface-2:#F2F2F7;
            --line:     rgba(60,60,67,.15);
            --t1:       #000000;
            --t2:       rgba(60,60,67,.85);
            --t3:       rgba(60,60,67,.65);
            --ok:       #1A7F3C;  --ok-bg:   #E5F4EC;
            --bad:      #B91C1C;  --bad-bg:  #FEE2E2;
            --warn:     #92510A;  --warn-bg: #FEF0C7;
            --blue:     #1D4ED8;  --blue-bg: #DBEAFE;
            --topbar-h: 50px;
            --pad:      16px;
            --r:        12px;
        }
        [data-theme="dark"] {
            --bg:       #000000;
            --surface:  #1C1C1E;
            --surface-2:#2C2C2E;
            --line:     rgba(255,255,255,.10);
            --t1:       #FFFFFF;
            --t2:       rgba(235,235,245,.80);
            --t3:       rgba(235,235,245,.38);
            --ok:       #30D158;  --ok-bg:   #0D2818;
            --bad:      #FF453A;  --bad-bg:  #330A08;
            --warn:     #FFB340;  --warn-bg: #2A1A00;
            --blue:     #93C5FD;  --blue-bg: #1E3A5F;
        }
        *,*::before,*::after { box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Helvetica Neue', system-ui, sans-serif; background: var(--bg); color: var(--t1); margin: 0; }
        .app { max-width: 540px; margin: 0 auto; min-height: 100dvh; }
        .app-content { padding: var(--pad); padding-bottom: 32px; }
        .topbar { position: sticky; top: 0; z-index: 200; height: var(--topbar-h); background: var(--surface); border-bottom: .5px solid var(--line); display: flex; align-items: center; gap: 10px; padding: 0 var(--pad); }
        .topbar-title { font-size: 17px; font-weight: 600; color: var(--t1); flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .btn-theme { background: none; border: none; cursor: pointer; color: var(--t2); border-radius: 8px; padding: 5px 6px; flex-shrink: 0; line-height: 1; font-size: 18px; }
        .btn-theme:hover { background: var(--surface-2); }
        .card { background: var(--surface) !important; border: none !important; border-radius: var(--r) !important; box-shadow: none !important; }
        .list-group { border-radius: var(--r) !important; overflow: hidden; background: var(--surface); }
        .list-group-item { background: var(--surface) !important; color: var(--t1) !important; border: none !important; border-bottom: .5px solid var(--line) !important; padding: 12px var(--pad) !important; }
        .list-group-item:last-child { border-bottom: none !important; }
        .list-group-item-action:hover { background: var(--surface-2) !important; }
        .badge { font-size: 11.5px !important; font-weight: 600 !important; padding: 3px 8px !important; border-radius: 20px !important; border: none !important; }
        .badge.bg-success  { background-color: var(--ok-bg)     !important; color: var(--ok)   !important; }
        .badge.bg-secondary{ background-color: var(--surface-2) !important; color: var(--t3)   !important; }
        .badge.bg-primary  { background-color: var(--blue-bg)   !important; color: var(--blue) !important; }
        .btn { display: inline-flex !important; align-items: center !important; border-radius: 10px !important; font-weight: 500 !important; border: none !important; transition: opacity .15s !important; }
        .btn-outline-secondary { background: var(--surface-2) !important; color: var(--t1) !important; }
        .btn-outline-secondary:hover { opacity: .75 !important; }
        .text-muted { color: var(--t3) !important; }
        .text-body  { color: var(--t1) !important; }
        .collapse.show, .collapsing { color: var(--t1); }
        .btn-link { background: none !important; color: var(--t2) !important; text-decoration: none !important; padding-left: 0 !important; padding-right: 0 !important; border: none !important; }
        .btn-link:hover { color: var(--t1) !important; }
        .collapse-icon { transition: transform .2s; }
        [aria-expanded="true"] .collapse-icon { transform: rotate(90deg); }
    </style>
</head>
<body>
<div class="app">

    <header class="topbar">
        <span class="topbar-title"><i class="bi bi-megaphone me-2"></i><?= e($app_title) ?></span>
        <a href="/login" class="btn btn-outline-secondary btn-sm">Anmelden</a>
        <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
            <i class="bi bi-moon"></i>
        </button>
    </header>

    <main class="app-content">

        <?php if (empty($teams_with_tickers)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-megaphone d-block mb-2" style="font-size:2rem;"></i>
            <p class="mb-1">Keine Ticker vorhanden</p>
            <p class="small mb-0">Es sind derzeit keine Live-Ticker aktiv.</p>
        </div>
        <?php else: ?>

        <?php foreach ($teams_with_tickers as $entry):
            $team   = $entry['team'];
            $tickers = $entry['tickers'];
            $active = array_values(array_filter($tickers, fn($t) => $t['status'] === 'active'));
            $closed = array_values(array_filter($tickers, fn($t) => $t['status'] === 'closed'));
        ?>

        <h2 class="h6 fw-semibold text-muted mb-2 <?= $entry !== reset($teams_with_tickers) ? 'mt-4' : '' ?>"><?= e($team['name']) ?></h2>

        <?php if (!empty($active)): ?>
        <div class="list-group mb-3">
            <?php foreach ($active as $t): ?>
            <a href="/ticker/<?= (int)$t['id'] ?>"
               class="list-group-item list-group-item-action text-decoration-none">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="flex-grow-1 me-2 min-w-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                            <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                            <span class="badge bg-success">Live</span>
                        </div>
                        <?php if ($t['description']): ?>
                        <p class="mb-1 text-muted small text-truncate"><?= e($t['description']) ?></p>
                        <?php endif; ?>
                        <p class="mb-0 text-muted small">
                            <?php if ($t['event_date']): ?>
                            <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                            <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                            &nbsp;·&nbsp;
                            <?php endif; ?>
                            <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?>
                        </p>
                    </div>
                    <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($closed)): ?>
        <?php $collapse_id = 'closed_' . (int)$team['id']; ?>
        <div class="mb-3">
            <button class="btn btn-link btn-sm text-muted px-0 py-1 d-flex align-items-center gap-1"
                    type="button" data-bs-toggle="collapse"
                    data-bs-target="#<?= $collapse_id ?>" aria-expanded="false">
                <i class="bi bi-chevron-right small collapse-icon"></i>
                Abgeschlossen (<?= count($closed) ?>)
            </button>
            <div class="collapse" id="<?= $collapse_id ?>">
                <div class="list-group mt-2">
                    <?php foreach ($closed as $t): ?>
                    <a href="/ticker/<?= (int)$t['id'] ?>"
                       class="list-group-item list-group-item-action text-decoration-none">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1 me-2 min-w-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                                    <span class="fw-semibold text-body"><?= e($t['name']) ?></span>
                                    <span class="badge bg-secondary">Geschlossen</span>
                                </div>
                                <p class="mb-0 text-muted small">
                                    <?php if ($t['event_date']): ?>
                                    <i class="bi bi-calendar3 me-1"></i><?= e(date('d.m.Y', strtotime($t['event_date']))) ?>
                                    <?php if ($t['start_time']): ?>, <?= e(substr($t['start_time'], 0, 5)) ?> Uhr<?php endif; ?>
                                    &nbsp;·&nbsp;
                                    <?php endif; ?>
                                    <i class="bi bi-chat-dots me-1"></i><?= (int)$t['message_count'] ?>
                                </p>
                            </div>
                            <i class="bi bi-chevron-right text-muted flex-shrink-0"></i>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>
        <?php endif; ?>

        <p class="text-muted text-center small mt-4">
            <a href="/login" style="color: var(--t3)">Anmelden</a> · <?= e($app_title) ?>
        </p>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>
<script>
(function(){
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
    tmApply(localStorage.getItem('tm-theme') || 'light');
    var btn = document.getElementById('theme-toggle');
    if (btn) btn.addEventListener('click', function() {
        var cur = localStorage.getItem('tm-theme') || 'light';
        tmApply(cur === 'dark' ? 'light' : 'dark');
    });
}());
</script>
</body>
</html>
