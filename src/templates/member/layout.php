<?php
// src/templates/player/layout.php — Player area layout wrapper
// Provides render_player_page() which wraps content in Bootstrap 5 layout.
// Per D-12: separate player layout, own navigation, single 'Listen' nav item.
// Phase 4 adds 'stats' nav item for statistics aggregation.

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full player page.
 * @param string $title   Page title (German)
 * @param string $active  Active nav item — 'lists', 'stats', 'profile', 'ticker'
 * @param callable $body  Function that outputs the main content HTML
 */
function render_player_page(string $title, string $active, callable $body): void {
    render_layout_head($title);
    render_navbar();
    ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar (desktop) -->
            <nav class="col-md-3 col-lg-2 d-none d-md-block bg-light sidebar py-3 border-end"
                 style="min-height: calc(100vh - 56px);">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'lists' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/member/lists">
                            <i class="bi bi-collection me-2"></i>Inhalte
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'ticker' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/member/ticker">
                            <i class="bi bi-megaphone me-2"></i>Ticker
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'stats' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/member/stats">
                            <i class="bi bi-graph-up me-2"></i>Statistik
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'profile' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/member/profile">
                            <i class="bi bi-person-circle me-2"></i>Team-Profil
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'player_profile' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/member/player-profile">
                            <i class="bi bi-person-badge me-2"></i>Spielerprofil
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile top tabs -->
            <div class="d-md-none w-100 mobile-tab-bar">
                <div class="d-flex">
                    <a class="mobile-tab-link <?= $active === 'lists' ? 'active' : '' ?>" href="/member/lists">
                        <i class="bi bi-collection tab-icon"></i><span>Inhalte</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'ticker' ? 'active' : '' ?>" href="/member/ticker">
                        <i class="bi bi-megaphone tab-icon"></i><span>Ticker</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'stats' ? 'active' : '' ?>" href="/member/stats">
                        <i class="bi bi-graph-up tab-icon"></i><span>Statistik</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'profile' ? 'active' : '' ?>" href="/member/profile">
                        <i class="bi bi-person-circle tab-icon"></i><span>Team-Profil</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'player_profile' ? 'active' : '' ?>" href="/member/player-profile">
                        <i class="bi bi-person-badge tab-icon"></i><span>Spielerprofil</span>
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
