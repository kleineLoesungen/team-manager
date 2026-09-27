<?php
// src/templates/public/ticker_detail.php — Public ticker feed (no auth)
// Variables: $ticker (array), $messages (array), $app_title (string), $team (array)
require_once dirname(__DIR__, 2) . '/templates/layout.php';
render_page(['title' => e($ticker['name'] ?? 'Ticker'), 'role' => 'public'], function() use ($ticker, $messages, $app_title, $team) {
    ?>
    <div data-ticker-live>
    <?php render_ticker_viewers($ticker, null); ?>
    <div class="mb-3">
        <a href="/ticker" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Alle Ticker
        </a>
    </div>

    <?php if ($ticker['description']): ?>
    <p class="text-muted small mb-3"><?= e($ticker['description']) ?></p>
    <?php endif; ?>

    <div class="mb-4">
        <?php if ($ticker['status'] === 'active'): ?>
        <?php render_badge('ok', 'Live'); ?>
        <?php else: ?>
        <?php render_badge('dim', 'Geschlossen'); ?>
        <?php endif; ?>
    </div>

    <!-- Message feed (newest first) -->
    <p class="text-muted small mb-2">
        <?= count($messages) ?> <?= count($messages) === 1 ? 'Nachricht' : 'Nachrichten' ?>
    </p>

    <?php if (empty($messages)): ?>
    <?php render_empty('chat-dots', 'Noch keine Nachrichten', 'Noch wurden keine Nachrichten gepostet.'); ?>
    <?php else: ?>
    <div class="list-group mb-4">
        <?php foreach ($messages as $msg): ?>
        <div class="list-group-item">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="text-muted small fw-semibold"><?= e(substr($msg['timestamp'], 0, 5)) ?></span>
                <?php if ($msg['tag_id']): ?>
                <span class="badge bg-<?= e($msg['tag_color'] ?? 'secondary') ?>">
                    <?= e($msg['tag_label'] ?? '') ?>
                </span>
                <?php endif; ?>
            </div>
            <p><?= e($msg['message']) ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <p class="text-muted text-center small mt-4">
        <a href="/login" class="text-muted">Anmelden</a> · <?= e($app_title) ?>
    </p>
    </div>

    <?php if ($ticker['status'] === 'active'): ?>
    <noscript><meta http-equiv="refresh" content="30"></noscript>
    <script>
    (function () {
        // Neue Nachrichten im Hintergrund holen und nur den Inhalt tauschen: kein Flackern,
        // die Scroll-Position bleibt, und Zuschauer werden nicht bei jedem Neuladen neu gezählt.
        var live = document.querySelector('[data-ticker-live]');
        var timer = setInterval(refresh, 30000);
        function refresh() {
            if (document.visibilityState !== 'visible') return;
            fetch(location.href, { cache: 'no-store', credentials: 'same-origin' })
                .then(function (r) { return r.ok ? r.text() : null; })
                .then(function (html) {
                    if (!html) return;
                    var next = new DOMParser().parseFromString(html, 'text/html').querySelector('[data-ticker-live]');
                    if (!next) return;
                    live.innerHTML = next.innerHTML;
                    if (!live.querySelector('[data-ticker-ping]')) clearInterval(timer);   // Ticker beendet
                })
                .catch(function () {});
        }
        document.addEventListener('visibilitychange', refresh);
    })();
    </script>
    <?php endif; ?>
    <?php
});
