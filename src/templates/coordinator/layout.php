<?php
// src/templates/coach/layout.php — Coach area layout wrapper
// Provides render_coach_page() which wraps content in Bootstrap 5 layout.
// Per D-01: separate layout, no sharing with admin layout.
// Phase 3 adds 'lists' and 'columns' nav items (in addition to Phase 2 'members').
// Phase 4 adds 'stats' nav item for statistics aggregation.

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full coordinator page.
 * @param string $title   Page title (German)
 * @param string $active  Active nav item — 'members', 'lists', 'settings', 'stats', 'logo', 'ticker'
 * @param callable $body  Function that outputs the main content HTML
 */
function render_coach_page(string $title, string $active, callable $body): void {
    render_layout_head($title);
    render_navbar();
    ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (desktop) -->
            <nav class="col-md-3 col-lg-2 d-none d-md-block bg-light sidebar py-3 border-end"
                 style="min-height: calc(100vh - 56px);">
                <ul class="nav flex-column">
                    <li class="nav-item px-3 pt-2 pb-1">
                        <span class="text-uppercase text-muted fw-semibold" style="font-size:0.68rem;letter-spacing:.06em">Team</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'members' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/members">
                            <i class="bi bi-people-fill me-2"></i>Mitglieder
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'players' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/players">
                            <i class="bi bi-person-vcard me-2"></i>Spieler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'stats' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/stats">
                            <i class="bi bi-graph-up me-2"></i>Statistik
                        </a>
                    </li>
                    <li class="nav-item px-3 pt-3 pb-1">
                        <span class="text-uppercase text-muted fw-semibold" style="font-size:0.68rem;letter-spacing:.06em">Inhalte</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'lists' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/lists">
                            <i class="bi bi-collection me-2"></i>Listen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'ticker' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/ticker">
                            <i class="bi bi-megaphone me-2"></i>Ticker
                        </a>
                    </li>
                    <li class="nav-item px-3 pt-3 pb-1">
                        <span class="text-uppercase text-muted fw-semibold" style="font-size:0.68rem;letter-spacing:.06em">Verwaltung</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'settings' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/settings">
                            <i class="bi bi-gear me-2"></i>Einstellungen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'logo' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coordinator/logo">
                            <i class="bi bi-image me-2"></i>Logo
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile top tabs -->
            <div class="d-md-none w-100 mobile-tab-bar">
                <div class="d-flex">
                    <a class="mobile-tab-link <?= $active === 'members' ? 'active' : '' ?>" href="/coordinator/members">
                        <i class="bi bi-people-fill tab-icon"></i><span>Mitglieder</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'players' ? 'active' : '' ?>" href="/coordinator/players">
                        <i class="bi bi-person-vcard tab-icon"></i><span>Spieler</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'stats' ? 'active' : '' ?>" href="/coordinator/stats">
                        <i class="bi bi-graph-up tab-icon"></i><span>Statistik</span>
                    </a>
                    <div style="width:1px;background:var(--bs-border-color);margin:6px 0;flex-shrink:0;"></div>
                    <a class="mobile-tab-link <?= $active === 'lists' ? 'active' : '' ?>" href="/coordinator/lists">
                        <i class="bi bi-collection tab-icon"></i><span>Listen</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'ticker' ? 'active' : '' ?>" href="/coordinator/ticker">
                        <i class="bi bi-megaphone tab-icon"></i><span>Ticker</span>
                    </a>
                    <div style="width:1px;background:var(--bs-border-color);margin:6px 0;flex-shrink:0;"></div>
                    <a class="mobile-tab-link <?= $active === 'settings' ? 'active' : '' ?>" href="/coordinator/settings">
                        <i class="bi bi-gear tab-icon"></i><span>Einstellungen</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'logo' ? 'active' : '' ?>" href="/coordinator/logo">
                        <i class="bi bi-image tab-icon"></i><span>Logo</span>
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <main class="col-md-9 col-lg-10 px-4 py-4">
                <h1 class="h4 fw-semibold mb-4"><?= e($title) ?></h1>
                <?php $body(); ?>
            </main>
        </div>
    </div>
    <?php
    render_layout_foot();
    exit;
}
