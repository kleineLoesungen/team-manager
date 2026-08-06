<?php
// src/templates/coordinator/members.php — Merged member + player list
// Variables: $members, $unlinked_members, $linkable_players, $error, $success
declare(strict_types=1);

$active_members   = array_values(array_filter($members, fn($m) => $m['is_active']));
$inactive_members = array_values(array_filter($members, fn($m) => !$m['is_active']));
?>
<?php if ($error):   ?><div class="alert alert-danger mb-3"><?= $error ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success mb-3"><?= $success ?></div><?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($active_members) ?> aktive Mitglieder</span>
    <a href="/coordinator/members/create" class="btn btn-primary min-touch">
        <i class="bi bi-plus-lg me-1"></i>Neues Mitglied
    </a>
</div>


<!-- Active members -->
<?php if (empty($active_members) && empty($inactive_members)): ?>
<div class="text-center py-5">
    <p class="h5 text-muted">Noch keine Mitglieder im Team</p>
    <p class="text-muted">Lege das erste Mitglied an.</p>
</div>
<?php else: ?>

<div class="list-group mb-4">
    <?php foreach ($active_members as $m): ?>
    <div class="list-group-item px-3 py-3">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="flex-grow-1 min-w-0">
                <div class="fw-semibold"><?= e($m['last_name'] . ', ' . $m['first_name']) ?></div>
                <div class="text-muted small"><code>@<?= e($m['username']) ?></code>
                    <?php if (!empty($m['email'])): ?>
                    &nbsp;·&nbsp;<i class="bi bi-envelope"></i> <?= e($m['email']) ?>
                    <?php endif; ?>
                </div>
                <?php if ($m['player_id']): ?>
                <div class="mt-1">
                    <a href="/coordinator/players/<?= (int)$m['player_id'] ?>"
                       class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle text-decoration-none">
                        <i class="bi bi-person-vcard me-1"></i><?= e($m['player_last'] . ', ' . $m['player_first']) ?>
                        <?php if (!empty($m['club_name'])): ?>
                        <span class="opacity-75 fw-normal"> · <?= e($m['club_name']) ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php elseif (!empty($linkable_players)): ?>
                <div class="mt-1">
                    <div class="dropdown d-inline-block">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-link-45deg me-1"></i>Spieler verknüpfen
                        </button>
                        <ul class="dropdown-menu" style="max-height:260px;overflow-y:auto">
                            <?php foreach ($linkable_players as $lp): ?>
                            <li>
                                <form method="POST" action="/coordinator/players/<?= (int)$lp['id'] ?>/link-user">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                                    <input type="hidden" name="_back" value="/coordinator/members">
                                    <button type="submit" class="dropdown-item">
                                        <?= e($lp['last_name'] . ', ' . $lp['first_name']) ?>
                                        <?php if (!empty($lp['club_name'])): ?>
                                        <small class="text-muted"> · <?= e($lp['club_name']) ?></small>
                                        <?php endif; ?>
                                    </button>
                                </form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <span class="badge bg-success flex-shrink-0">Aktiv</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reset-password"
                  onsubmit="return confirm('Das Passwort wird zurückgesetzt und einmalig angezeigt.')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary min-touch">
                    <i class="bi bi-key me-1"></i>Passwort
                </button>
            </form>
            <a href="/coordinator/members/<?= (int)$m['id'] ?>/edit-email"
               class="btn btn-sm btn-outline-secondary min-touch">
                <i class="bi bi-envelope me-1"></i>E-Mail
            </a>
            <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/deactivate"
                  onsubmit="return confirm('Mitglied deaktivieren?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-danger min-touch">
                    Deaktivieren
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Inactive members -->
<?php if (!empty($inactive_members)): ?>
<details class="mt-2">
    <summary class="text-muted small mb-3" style="cursor:pointer;list-style:none;">
        <i class="bi bi-chevron-right me-1"></i>Inaktive Mitglieder (<?= count($inactive_members) ?>)
    </summary>
    <div class="list-group mt-2">
        <?php foreach ($inactive_members as $m): ?>
        <div class="list-group-item px-3 py-3 opacity-75">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-muted"><?= e($m['last_name'] . ', ' . $m['first_name']) ?></div>
                    <div class="text-muted small"><code>@<?= e($m['username']) ?></code></div>
                    <?php if ($m['player_id']): ?>
                    <div class="mt-1">
                        <a href="/coordinator/players/<?= (int)$m['player_id'] ?>"
                           class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle text-decoration-none">
                            <i class="bi bi-person-vcard me-1"></i><?= e($m['player_last'] . ', ' . $m['player_first']) ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <span class="badge bg-secondary flex-shrink-0">Inaktiv</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <form method="POST" action="/coordinator/members/<?= (int)$m['id'] ?>/reactivate"
                      onsubmit="return confirm('Mitglied reaktivieren?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-success min-touch">
                        Reaktivieren
                    </button>
                </form>
                <a href="/coordinator/members/<?= (int)$m['id'] ?>/edit-email"
                   class="btn btn-sm btn-outline-secondary min-touch">
                    <i class="bi bi-envelope me-1"></i>E-Mail
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</details>
<?php endif; ?>

<?php endif; ?>
