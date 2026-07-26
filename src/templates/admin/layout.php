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
                    <li class="nav-item px-3 pt-2 pb-1">
                        <span class="text-uppercase text-muted fw-semibold" style="font-size:0.68rem;letter-spacing:.06em">Verwaltung</span>
                    </li>
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
                    <li class="nav-item px-3 pt-3 pb-1">
                        <span class="text-uppercase text-muted fw-semibold" style="font-size:0.68rem;letter-spacing:.06em">System</span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'settings' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/admin/settings">
                            <i class="bi bi-gear-fill me-2"></i>Einstellungen
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $active === 'notify' ? 'active fw-bold bg-primary text-white rounded' : 'text-dark' ?> px-3 py-2"
                           href="/admin/notify">
                            <i class="bi bi-envelope me-2"></i>Benachrichtigung
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- Mobile top tabs -->
            <div class="d-md-none w-100 mobile-tab-bar">
                <div class="d-flex">
                    <a class="mobile-tab-link <?= $active === 'teams' ? 'active' : '' ?>" href="/admin/teams">
                        <i class="bi bi-people-fill tab-icon"></i><span>Teams</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'coordinators' ? 'active' : '' ?>" href="/admin/coordinators">
                        <i class="bi bi-person-badge tab-icon"></i><span>Koordinatoren</span>
                    </a>
                    <div style="width:1px;background:var(--bs-border-color);margin:6px 0;flex-shrink:0;"></div>
                    <a class="mobile-tab-link <?= $active === 'settings' ? 'active' : '' ?>" href="/admin/settings">
                        <i class="bi bi-gear-fill tab-icon"></i><span>Einstellungen</span>
                    </a>
                    <a class="mobile-tab-link <?= $active === 'notify' ? 'active' : '' ?>" href="/admin/notify">
                        <i class="bi bi-envelope tab-icon"></i><span>Benachrichtigung</span>
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
