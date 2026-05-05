<?php
// src/templates/admin/layout.php — Admin area layout wrapper
// Provides render_admin_page() which wraps content in sidebar layout.

declare(strict_types=1);

require_once dirname(__DIR__) . '/layout.php';

/**
 * Render a full admin page.
 * @param string $title   Page title
 * @param string $active  Active nav item: 'teams' or 'coaches'
 * @param callable $body  Function that outputs the main content HTML
 */
function render_admin_page(string $title, string $active, callable $body): void {
    render_layout_head($title);
    render_navbar();
    ?>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-none d-md-block bg-light sidebar py-3 border-end"
                 style="min-height: calc(100vh - 56px);">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'teams' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/admin/teams">
                            <i class="bi bi-people-fill me-2"></i>Teams
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'coordinators' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/admin/coordinators">
                            <i class="bi bi-person-badge me-2"></i>Koordinatoren
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'settings' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/admin/settings">
                            <i class="bi bi-gear-fill me-2"></i>Einstellungen
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile top tabs -->
            <div class="d-md-none w-100 border-bottom bg-light">
                <div class="d-flex">
                    <a class="flex-fill text-center py-2 <?= $active === 'teams' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/admin/teams">Teams</a>
                    <a class="flex-fill text-center py-2 <?= $active === 'coordinators' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/admin/coordinators">Koordinatoren</a>
                    <a class="flex-fill text-center py-2 <?= $active === 'settings' ? 'border-bottom border-primary text-primary fw-bold' : 'text-dark' ?>"
                       href="/admin/settings">Einstellungen</a>
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
