<?php
// src/templates/coordinator/select_team.php — Team picker UI for multi-team coordinators
// Variables: $available_teams, $is_switch (bool), $error (bool), $form_action, $back_url
// Shared by coordinators (/coordinator/select-team) and members (/member/switch-team).

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
                <?php elseif (($_SESSION['role'] ?? '') === 'coordinator'): ?>
                    Du verwaltest mehrere Teams. Wähle das Team für diese Sitzung.
                <?php endif; ?>
            </p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger mb-3">Ungültiges Team. Bitte wähle aus der Liste.</div>
        <?php endif; ?>

        <div class="d-flex flex-column gap-3">
            <?php foreach ($available_teams as $team): ?>
            <?php $is_current = (int)$team['team_id'] === (int)($_SESSION['team_id'] ?? 0) && empty($_SESSION['pending_team_pick']); ?>
            <form method="POST" action="<?= e($form_action) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="team_id" value="<?= e((string)$team['team_id']) ?>">
                <button type="submit" class="btn <?= $is_current ? 'btn-outline-secondary' : 'btn-primary' ?> w-100 min-touch text-start px-4" <?= $is_current ? 'disabled' : '' ?>>
                    <i class="bi bi-building me-2"></i><?= e($team['team_name']) ?><?= $is_current ? ' · aktuell' : '' ?>
                </button>
            </form>
            <?php endforeach; ?>
        </div>

        <?php if ($is_switch && !empty($_SESSION['team_id'])): ?>
        <div class="mt-4 text-center">
            <a href="<?= e($back_url) ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Zurück
            </a>
        </div>
        <?php endif; ?>
    </main>
</div>
<?php
render_layout_foot();
exit;
