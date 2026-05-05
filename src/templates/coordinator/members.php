<?php
// src/templates/coordinator/members.php — Player card list for coach area
// Per D-05: Bootstrap cards. Per D-06: actions inline in card.
// Per D-07: active players at top; inactive in <details> at bottom.

$active_players   = array_filter($players, fn($p) => $p['is_active']);
$inactive_players = array_filter($players, fn($p) => !$p['is_active']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_players) ?> aktive Mitglieder</span>
    <a href="/coordinator/members/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neues Mitglied anlegen
    </a>
</div>

<?php if (empty($active_players) && empty($inactive_players)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Mitglieder im Team</p>
    <p class="text-muted">Lege das erste Mitglied an.</p>
</div>

<?php else: ?>

<!-- Active players -->
<div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mb-4">
    <?php foreach ($active_players as $player): ?>
    <div class="col">
        <div class="card h-100 shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-1"><?= e($player['first_name'] . ' ' . $player['last_name']) ?></h5>
                <p class="card-text text-muted small mb-2">
                    <code>@<?= e($player['username']) ?></code>
                </p>
                <span class="badge bg-success">Aktiv</span>
            </div>
            <div class="card-footer bg-transparent d-flex gap-2">
                <form method="POST" action="/coordinator/members/<?= (int)$player['id'] ?>/reset-password"
                      onsubmit="return confirm('Das Passwort wird zurückgesetzt und einmalig angezeigt.')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-primary min-touch">
                        <i class="bi bi-key me-1"></i>Passwort zurücksetzen
                    </button>
                </form>
                <form method="POST" action="/coordinator/members/<?= (int)$player['id'] ?>/deactivate"
                      onsubmit="return confirm('Mitglied deaktivieren?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger min-touch">
                        Deaktivieren
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if (!empty($inactive_players)): ?>
<!-- Inactive players — collapsible per D-07 using <details>/<summary>, no JS needed -->
<details class="mt-2">
    <summary class="text-muted small mb-3" style="cursor:pointer; list-style:none;">
        <i class="bi bi-chevron-right me-1"></i>
        Inaktive Mitglieder (<?= count($inactive_players) ?>)
    </summary>
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-3 mt-1">
        <?php foreach ($inactive_players as $player): ?>
        <div class="col">
            <div class="card h-100 shadow-sm border-secondary opacity-75">
                <div class="card-body">
                    <h5 class="card-title mb-1 text-muted"><?= e($player['first_name'] . ' ' . $player['last_name']) ?></h5>
                    <p class="card-text text-muted small mb-2">
                        <code>@<?= e($player['username']) ?></code>
                    </p>
                    <span class="badge bg-secondary">Inaktiv</span>
                </div>
                <div class="card-footer bg-transparent">
                    <form method="POST" action="/coordinator/members/<?= (int)$player['id'] ?>/reactivate"
                          onsubmit="return confirm('Mitglied reaktivieren?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-outline-success min-touch">
                            Reaktivieren
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>
