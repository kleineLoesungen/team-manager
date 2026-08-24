<?php
// src/templates/admin/layout.php — Admin area layout (mobile-first)

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full admin page.
 * @param string   $title  Page title
 * @param string   $active Active tab key: 'teams', 'coordinators', 'players', 'clubs',
 *                         'settings', 'attributes', 'notify'
 * @param callable $body   Outputs main content HTML
 */
function render_admin_page(string $title, string $active, callable $body): void {
    render_layout_head($title);

    $tab = match(true) {
        $active === 'teams'                                                => 'teams',
        $active === 'coordinators'                                         => 'coordinators',
        $active === 'players'                                              => 'players',
        $active === 'clubs'                                                => 'clubs',
        default                                                            => 'settings',
    };
    ?>
    <div class="app">

        <header class="topbar">
            <img src="/logo" alt="" class="topbar-logo"
                 onerror="this.style.display='none'" loading="eager">
            <span class="topbar-title">Team Manager</span>
            <span class="topbar-context">Admin</span>
            <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
                <i class="bi bi-moon"></i>
            </button>
        </header>

        <main class="app-content">
            <?php $body(); ?>
        </main>

        <nav class="tabbar" aria-label="Hauptnavigation">
            <a href="/admin/teams"
               class="tab-item <?= $tab === 'teams' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'teams' ? 'page' : 'false' ?>">
                <i class="bi bi-people-fill"></i>
                <span class="tab-label">Teams</span>
            </a>
            <a href="/admin/coordinators"
               class="tab-item <?= $tab === 'coordinators' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'coordinators' ? 'page' : 'false' ?>">
                <i class="bi bi-person-badge"></i>
                <span class="tab-label">Koordinatoren</span>
            </a>
            <a href="/admin/players"
               class="tab-item <?= $tab === 'players' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'players' ? 'page' : 'false' ?>">
                <i class="bi bi-person-vcard"></i>
                <span class="tab-label">Mitglieder</span>
            </a>
            <a href="/admin/clubs"
               class="tab-item <?= $tab === 'clubs' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'clubs' ? 'page' : 'false' ?>">
                <i class="bi bi-building"></i>
                <span class="tab-label">Klubs</span>
            </a>
            <a href="/admin/settings"
               class="tab-item <?= $tab === 'settings' ? 'is-on' : '' ?>"
               aria-current="<?= $tab === 'settings' ? 'page' : 'false' ?>">
                <i class="bi bi-gear-fill"></i>
                <span class="tab-label">Einstellungen</span>
            </a>
        </nav>

    </div>
    <?php
    render_layout_foot();
    exit;
}
