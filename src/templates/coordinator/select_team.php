<?php
// src/templates/coordinator/select_team.php — Team picker UI for multi-team coordinators
// Used for both /coordinator/select-team (post-login) and /coordinator/switch-team (in-session)

declare(strict_types=1);

require_once ROOT_PATH . '/src/templates/coordinator/layout.php';

render_layout_head('Team auswählen');
render_navbar();
?>
<div class="container" style="max-width:480px; padding-top:3rem; padding-bottom:3rem;">
    <div class="mb-4 text-center">
        <h1 class="h4 fw-semibold mb-1"><?= $is_switch ? 'Team wechseln' : 'Team auswählen' ?></h1>
        <p class="text-muted mb-0">
            <?php if ($is_switch): ?>
                Wähle das Team, zu dem du wechseln möchtest.
            <?php else: ?>
                Du verwaltest mehrere Teams. Wähle das Team für diese Sitzung.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger" role="alert">
        Ungültiges Team. Bitte wähle aus der Liste.
    </div>
    <?php endif; ?>

    <div class="d-flex flex-column gap-3">
        <?php foreach ($available_teams as $team): ?>
        <form method="POST" action="/coordinator/select-team">
            <?= csrf_field() ?>
            <input type="hidden" name="team_id" value="<?= e((string)$team['team_id']) ?>">
            <button type="submit" class="btn btn-primary w-100 text-start py-3 px-4">
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
</div>
<?php
render_layout_foot();
exit;
