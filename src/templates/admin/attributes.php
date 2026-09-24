<?php
// src/templates/admin/attributes.php — Admin: Player attribute groups + nested attributes
// Variables: $groups (array keyed by group_id), $error (string)
?>
<?php if (!empty($_GET['success'])): ?>
<?php render_flash('success', 'Aktion erfolgreich.'); ?>
<?php endif; ?>
<?php if (!empty($error)): ?>
<?php render_flash('error', $error); ?>
<?php endif; ?>

<?php render_page_header('Attribute', '/admin/settings'); ?>

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
                <label class="form-label mb-1">Gruppenname</label>
                <input type="text" class="form-control" name="name" maxlength="100" required
                       placeholder="z.B. Kontakt, Mitgliedsprofil …">
            </div>
            <div class="col-6 col-sm-3">
                <label class="form-label mb-1">Reihenfolge</label>
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
<?php render_empty('list-task', 'Noch keine Attributgruppen', 'Erstelle oben eine neue Gruppe, um Attribute zu verwalten.'); ?>
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
                    <input type="text" class="form-control" name="name"
                           value="<?= e($group['name']) ?>" maxlength="100" required>
                    <input type="number" class="form-control" name="sort_order"
                           value="<?= (int)$group['sort_order'] ?>" min="0">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Speichern</button>
                </form>
                <!-- Delete group -->
                <form method="POST" action="/admin/attributes/groups/<?= (int)$group['id'] ?>/delete">
                    <?= csrf_field() ?>
                    <input type="hidden" name="confirm_delete" value="1">
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
        <div class="list-group list-group-flush mb-3">
            <?php foreach ($group['attributes'] as $attr): ?>
            <details class="tm-attr-item">
                <summary class="list-group-item d-flex align-items-center gap-2 py-2">
                    <span class="flex-grow-1 fw-medium"><?= e($attr['name']) ?></span>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis">
                        <?= $attr['data_type'] === 'date' ? 'Datum' : 'Text' ?>
                    </span>
                    <?php if ($attr['visible_to_player']): ?>
                    <span class="badge bg-success-subtle text-success-emphasis">Sichtbar</span>
                    <?php endif; ?>
                    <?php if ($attr['editable_by_player']): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis">Editierbar</span>
                    <?php endif; ?>
                    <i class="bi bi-chevron-down text-muted small tm-attr-chevron"></i>
                </summary>
                <div class="tm-attr-edit bg-body-tertiary px-3 py-3 border-top">
                    <form method="POST"
                          action="/admin/attributes/<?= (int)$group['id'] ?>/attributes/<?= (int)$attr['id'] ?>/edit">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="save">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name"
                                       value="<?= e($attr['name']) ?>" maxlength="100" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Typ</label>
                                <select class="form-select" name="data_type">
                                    <option value="text" <?= ($attr['data_type'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text</option>
                                    <option value="date" <?= ($attr['data_type'] ?? 'text') === 'date' ? 'selected' : '' ?>>Datum</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Reihenfolge</label>
                                <input type="number" class="form-control" name="sort_order"
                                       value="<?= (int)$attr['sort_order'] ?>" min="0">
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           name="visible_to_player" id="vis_<?= (int)$attr['id'] ?>"
                                           <?= $attr['visible_to_player'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="vis_<?= (int)$attr['id'] ?>">Sichtbar</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           name="editable_by_player" id="edit_<?= (int)$attr['id'] ?>"
                                           <?= $attr['editable_by_player'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="edit_<?= (int)$attr['id'] ?>">Editierbar</label>
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    <i class="bi bi-floppy me-1"></i>Speichern
                                </button>
                                <button type="submit" class="btn btn-link btn-sm text-danger px-0"
                                        onclick="this.form.querySelector('[name=action]').value='delete';return confirm('Attribut „<?= e($attr['name']) ?>" löschen?')">
                                    <i class="bi bi-trash me-1"></i>Löschen
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </details>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-muted small mb-3">Noch keine Attribute in dieser Gruppe.</p>
        <?php endif; ?>

        <!-- Neues Attribut erstellen -->
        <div class="border-top pt-3">
            <p class="fw-semibold small mb-3">Neues Attribut hinzufügen</p>
            <form method="POST" action="/admin/attributes/<?= (int)$group['id'] ?>/attributes/create">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12 col-sm-6">
                        <label class="form-label" for="attr_name_<?= (int)$group['id'] ?>">Name</label>
                        <input type="text" class="form-control" name="name"
                               id="attr_name_<?= (int)$group['id'] ?>"
                               maxlength="100" required placeholder="z.B. Geburtsdatum …">
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label" for="attr_type_<?= (int)$group['id'] ?>">Typ</label>
                        <select class="form-select" name="data_type" id="attr_type_<?= (int)$group['id'] ?>">
                            <option value="text">Text</option>
                            <option value="date">Datum</option>
                        </select>
                    </div>
                    <div class="col-6 col-sm-3">
                        <label class="form-label" for="attr_order_<?= (int)$group['id'] ?>">Reihenfolge</label>
                        <input type="number" class="form-control" name="sort_order"
                               id="attr_order_<?= (int)$group['id'] ?>" value="0" min="0">
                    </div>
                    <div class="col-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="visible_to_player"
                                   role="switch" id="visible_new_<?= (int)$group['id'] ?>" checked>
                            <label class="form-check-label" for="visible_new_<?= (int)$group['id'] ?>">Für Mitglied sichtbar</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="editable_by_player"
                                   role="switch" id="editable_new_<?= (int)$group['id'] ?>">
                            <label class="form-check-label" for="editable_new_<?= (int)$group['id'] ?>">Von Mitglied editierbar</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary min-touch">
                            <i class="bi bi-plus-lg me-1"></i>Attribut hinzufügen
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
