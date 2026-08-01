<?php
// src/templates/admin/attributes.php — Admin: Player attribute groups + nested attributes
// Variables: $groups (array keyed by group_id), $error (string)
?>
<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <span class="text-muted"><?= count($groups) ?> Gruppe<?= count($groups) !== 1 ? 'n' : '' ?></span>
</div>

<!-- Neue Gruppe erstellen -->
<div class="card mb-4">
    <div class="card-header fw-semibold">Neue Gruppe hinzufügen</div>
    <div class="card-body">
        <form method="POST" action="/admin/attributes/groups/create" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-sm-6">
                <label class="form-label mb-1 small">Gruppenname</label>
                <input type="text" class="form-control" name="name" maxlength="100" required
                       placeholder="z.B. Kontakt, Spielerprofil …">
            </div>
            <div class="col-6 col-sm-3">
                <label class="form-label mb-1 small">Reihenfolge</label>
                <input type="number" class="form-control" name="sort_order" value="0" min="0">
            </div>
            <div class="col-6 col-sm-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus-lg me-1"></i>Erstellen
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($groups)): ?>
<div class="alert alert-info">
    Noch keine Gruppen vorhanden. Erstelle oben eine neue Gruppe.
</div>
<?php else: ?>

<?php foreach ($groups as $group): ?>
<div class="card mb-4">
    <!-- Card header: group name + edit/delete forms -->
    <div class="card-header">
        <div class="d-flex flex-wrap align-items-center gap-2 justify-content-between">
            <span class="fw-semibold"><?= e($group['name']) ?> <span class="text-muted small">(Reihenfolge: <?= (int)$group['sort_order'] ?>)</span></span>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <!-- Inline edit form -->
                <form method="POST" action="/admin/attributes/groups/<?= (int)$group['id'] ?>/edit"
                      class="d-flex gap-2 align-items-center">
                    <?= csrf_field() ?>
                    <input type="text" class="form-control form-control-sm" name="name"
                           value="<?= e($group['name']) ?>" maxlength="100" required
                           style="min-width:140px">
                    <input type="number" class="form-control form-control-sm" name="sort_order"
                           value="<?= (int)$group['sort_order'] ?>" min="0" style="width:70px">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Speichern</button>
                </form>
                <!-- Delete group -->
                <form method="POST" action="/admin/attributes/groups/<?= (int)$group['id'] ?>/delete"
                      onsubmit="return confirm('Gruppe und alle ihre Attribute löschen?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="card-body">
        <!-- Attribute list -->
        <?php if (!empty($group['attributes'])): ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th>
                        <th class="text-center">Sichtbar</th>
                        <th class="text-center">Editierbar</th>
                        <th>Reihenfolge</th>
                        <th>Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($group['attributes'] as $attr): ?>
                    <tr>
                        <!-- Inline edit form for this attribute -->
                        <form method="POST"
                              action="/admin/attributes/<?= (int)$group['id'] ?>/attributes/<?= (int)$attr['id'] ?>/edit">
                            <?= csrf_field() ?>
                            <td>
                                <input type="text" class="form-control form-control-sm" name="name"
                                       value="<?= e($attr['name']) ?>" maxlength="100" required
                                       style="min-width:120px">
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           name="visible_to_player" role="switch"
                                           <?= $attr['visible_to_player'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input" type="checkbox"
                                           name="editable_by_player" role="switch"
                                           <?= $attr['editable_by_player'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td>
                                <input type="number" class="form-control form-control-sm" name="sort_order"
                                       value="<?= (int)$attr['sort_order'] ?>" min="0" style="width:70px">
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Speichern</button>
                        </form>
                                    <!-- Delete attribute (separate form) -->
                                    <form method="POST"
                                          action="/admin/attributes/<?= (int)$group['id'] ?>/attributes/<?= (int)$attr['id'] ?>/delete"
                                          onsubmit="return confirm('Attribut \"<?= e($attr['name']) ?>\" löschen?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-3">Noch keine Attribute in dieser Gruppe.</p>
        <?php endif; ?>

        <!-- Neues Attribut erstellen -->
        <div class="border-top pt-3">
            <p class="fw-semibold small mb-2">Neues Attribut hinzufügen</p>
            <form method="POST" action="/admin/attributes/<?= (int)$group['id'] ?>/attributes/create"
                  class="row g-2 align-items-end">
                <?= csrf_field() ?>
                <div class="col-12 col-sm-4">
                    <label class="form-label mb-1 small">Name</label>
                    <input type="text" class="form-control form-control-sm" name="name"
                           maxlength="100" required placeholder="z.B. Telefon, Geburtsdatum …">
                </div>
                <div class="col-6 col-sm-2">
                    <label class="form-label mb-1 small">Reihenfolge</label>
                    <input type="number" class="form-control form-control-sm" name="sort_order" value="0" min="0">
                </div>
                <div class="col-6 col-sm-2">
                    <label class="form-label mb-1 small">Sichtbar</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="visible_to_player"
                               role="switch" id="visible_new_<?= (int)$group['id'] ?>" checked>
                        <label class="form-check-label small" for="visible_new_<?= (int)$group['id'] ?>">Spieler</label>
                    </div>
                </div>
                <div class="col-6 col-sm-2">
                    <label class="form-label mb-1 small">Editierbar</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" name="editable_by_player"
                               role="switch" id="editable_new_<?= (int)$group['id'] ?>">
                        <label class="form-check-label small" for="editable_new_<?= (int)$group['id'] ?>">Spieler</label>
                    </div>
                </div>
                <div class="col-6 col-sm-2">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i>Hinzufügen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
