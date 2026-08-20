<?php
// src/templates/coordinator/select_team.php — Team picker UI for multi-team coordinators
// Variables: $available_teams, $is_switch (bool), $error (bool)

declare(strict_types=1);

require_once ROOT_PATH . '/src/templates/layout.php';

render_layout_head('Team auswählen');
?>
<div class="app">
    <header class="topbar">
        <img src="/logo" alt="" class="topbar-logo" onerror="this.style.display='none'" loading="eager">
        <span class="topbar-title">Team Manager</span>
        <button class="btn-theme" id="theme-toggle" aria-label="Dunkelmodus">
            <i class="bi bi-moon"></i>
        </button>
    </header>
    <main class="app-content">
        <div class="mb-4 text-center">
            <h1 class="h5 fw-bold mb-1"><?= $is_switch ? 'Team wechseln' : 'Team auswählen' ?></h1>
            <p class="text-muted small mb-0">
                <?php if ($is_switch): ?>
                    Wähle das Team, zu dem du wechseln möchtest.
                <?php else: ?>
                    Du verwaltest mehrere Teams. Wähle das Team für diese Sitzung.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3">Ungültiges Team. Bitte wähle aus der Liste.</div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($available_teams as $team): ?>
            <form method="POST" action="/coordinator/select-team">
                <?= csrf_field() ?>
                <input type="hidden" name="team_id" value="<?= e((string)$team['team_id']) ?>">
                <button type="submit" class="btn btn-primary w-100 min-touch text-start px-4">
                    <i class="bi bi-building me-2"></i><?= e($team['team_name']) ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>

        <?php if ($is_switch && !empty($_SESSION['team_id'])): ?>
        <div class="mt-4 text-center">
            <a href="/coordinator/members" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Zurück
            </a>
        </div>
        <?php endif; ?>
    </main>
</div>
<?php
render_layout_foot();
exit;
