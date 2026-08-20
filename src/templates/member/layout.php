<?php
// src/templates/member/layout.php — Member area layout (mobile-first)

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full member page.
 * @param string   $title  Page title (German)
 * @param string   $active Active tab key: 'lists', 'ticker', 'stats', 'profile', 'player_profile'
 * @param callable $body   Outputs main content HTML
 */
function render_player_page(string $title, string $active, callable $body): void {
    render_layout_head($title);

    $tab = match(true) {
        $active === 'lists'                                                => 'lists',
        $active === 'ticker'                                               => 'ticker',
        $active === 'stats'                                                => 'stats',
        default                                                            => 'profile',
    };

    $team_name = htmlspecialchars($_SESSION['team_name'] ?? 'Team Manager', ENT_QUOTES);
    ?>
    <div class="app">

        <header class="topbar">
            <img src="/logo" alt="" class="topbar-logo"
                 onerror="this.style.display='none'" loading="eager">
            <span class="topbar-title"><?= $team_name ?></span>
            <span class="topbar-context"><?= e($title) ?></span>
            <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
                <i class="bi bi-moon"></i>
            </button>
        </header>

        <main class="app-content">
            <?php $body(); ?>
        </main>

        <nav class="tabbar" aria-label="Hauptnavigation">
            <a href="/member/lists"
               class="tab-item <?= $tab === 'lists' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'lists' ? 'page' : 'false' ?>">
                <i class="bi bi-collection"></i>
                <span class="tab-label">Listen</span>
            </a>
            <a href="/member/ticker"
               class="tab-item <?= $tab === 'ticker' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'ticker' ? 'page' : 'false' ?>">
                <i class="bi bi-megaphone"></i>
                <span class="tab-label">Ticker</span>
            </a>
            <a href="/member/stats"
               class="tab-item <?= $tab === 'stats' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'stats' ? 'page' : 'false' ?>">
                <i class="bi bi-graph-up"></i>
                <span class="tab-label">Statistik</span>
            </a>
            <a href="/member/profile"
               class="tab-item <?= $tab === 'profile' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'profile' ? 'page' : 'false' ?>">
                <i class="bi bi-person-circle"></i>
                <span class="tab-label">Profil</span>
            </a>
        </nav>

    </div>
    <?php
    render_layout_foot();
    exit;
}
