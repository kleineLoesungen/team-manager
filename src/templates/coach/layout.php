<?php
// src/templates/coach/layout.php — Coach area layout wrapper
// Provides render_coach_page() which wraps content in Bootstrap 5 layout.
// Per D-01: separate layout, no sharing with admin layout.
// Phase 3 adds 'lists' and 'columns' nav items (in addition to Phase 2 'players').
// Phase 4 adds 'stats' nav item for statistics aggregation.

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full coach page.
 * @param string $title   Page title (German)
 * @param string $active  Active nav item — 'players', 'lists', 'columns', or 'stats'
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
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'players' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coach/players">
                            <i class="bi bi-people-fill me-2"></i>Spieler
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'lists' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coach/lists">
                            <i class="bi bi-table me-2"></i>Listen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'columns' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coach/columns">
                            <i class="bi bi-layout-three-columns me-2"></i>Spalten
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'stats' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/coach/stats">
                            <i class="bi bi-graph-up me-2"></i>Statistik
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile top tabs -->
            <div class="d-md-none w-100 border-bottom bg-light">
                <div class="d-flex">
                    <a class="flex-fill text-center py-2 <?= $active === 'players' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/coach/players">Spieler</a>
                    <a class="flex-fill text-center py-2 <?= $active === 'lists' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/coach/lists">Listen</a>
                    <a class="flex-fill text-center py-2 <?= $active === 'columns' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/coach/columns">Spalten</a>
                    <a class="flex-fill text-center py-2 <?= $active === 'stats' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/coach/stats">Statistik</a>
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
