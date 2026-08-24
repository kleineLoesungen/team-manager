<?php
// src/templates/coordinator/settings.php — Einstellungen page
// Variables: $columns (array), $system_columns (array), $ticker_tags (array), $error (string), $success (string)

// ── Section 1: Globale Spalten (preserved from columns.php) ──────────────────
?>
<div class="mb-3">
    <a href="/coordinator/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>

<h4 class="fw-semibold mb-3">Globale Spalten</h4>
<p class="text-muted mb-3">Globale Spalten erscheinen in allen Listen des Teams.</p>

<?php if (!empty($system_columns)): ?>
<h6 class="fw-semibold text-muted mb-2">Systemspalten</h6>
<div class="table-responsive mb-2">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Typ</th>
                <th>Reihenfolge</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($system_columns as $col): ?>
            <tr>
                <td>
                    <?= e($col['name']) ?>
                    <span class="badge bg-light text-dark border ms-2">
                        <i class="bi bi-lock-fill me-1"></i>System
                    </span>
                </td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                    </span>
                </td>
                <td class="text-muted small"><?= (int)$col['sort_order'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-muted small mb-4">
    <i class="bi bi-info-circle me-1"></i>
    Systemspalten werden vom Admin verwaltet und können hier nicht bearbeitet werden.
</p>
<?php endif; ?>

<?php if (empty($columns)): ?>
<div class="text-center py-4 mb-4">
    <p class="h5 text-muted">Noch keine eigenen globalen Spalten</p>
    <p class="text-muted">Team-eigene globale Spalten erscheinen in allen Listen deines Teams.</p>
</div>
<?php else: ?>
<h6 class="fw-semibold text-muted mb-2">Team-eigene Spalten</h6>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Typ</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($columns as $col): ?>
            <tr>
                <td><?= e($col['name']) ?></td>
                <td>
                    <span class="badge bg-light text-dark border">
                        <?= $col['data_type'] === 'boolean' ? 'Ja/Nein' : 'Zahl' ?>
                    </span>
                </td>
                <td class="text-end">
                    <?php if ($delete_pending_col_id !== null && $delete_pending_col_id === (int)$col['id']): ?>
                    <form method="POST" action="/coordinator/settings" class="d-inline-flex gap-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_column">
                        <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                        <input type="hidden" name="confirm" value="1">
                        <span class="text-danger small">Spalte und alle Einträge löschen?</span>
                        <button type="submit" class="btn btn-sm btn-danger">Ja</button>
                        <a href="/coordinator/settings" class="btn btn-sm btn-outline-secondary">Nein</a>
                    </form>
                    <?php else: ?>
                    <form method="POST" action="/coordinator/settings" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_column">
                        <input type="hidden" name="column_id" value="<?= (int)$col['id'] ?>">
                        <input type="hidden" name="confirm" value="0">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Löschen</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Create global column form — action updated for new URL in Phase 7 -->
<div class="card shadow-sm mb-5" style="max-width: 500px;">
    <div class="card-header fw-semibold">Neue globale Spalte anlegen</div>
    <div class="card-body">
        <form method="POST" action="/coordinator/settings/columns/create">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="col_name" class="form-label">Name</label>
                <input type="text" id="col_name" name="name"
                       class="form-control" maxlength="100" required
                       placeholder="z.B. Tore">
            </div>
            <div class="mb-3">
                <label class="form-label">Typ</label>
                <div class="d-flex gap-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="data_type" id="type_boolean"
                               value="boolean" checked>
                        <label class="form-check-label" for="type_boolean">Ja/Nein</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="data_type" id="type_number"
                               value="number">
                        <label class="form-check-label" for="type_number">Zahl</label>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Spalte anlegen</button>
        </form>
    </div>
</div>

<hr class="my-5">

<?php
// ── Section 2: Ticker-Tags (new in Phase 7) ───────────────────────────────────
$color_labels = [
    'success'   => 'Grün (Erfolg)',
    'warning'   => 'Gelb (Hinweis)',
    'danger'    => 'Rot (Warnung)',
    'primary'   => 'Blau (Standard)',
    'secondary' => 'Grau (Neutral)',
];
?>
<h4 class="fw-semibold mb-2">Ticker-Tags</h4>
<p class="text-muted mb-3">Team-weit verfügbare Tags für Nachrichten. Koordinator definiert Tags hier; Nachrichten können optional mit einem Tag versehen werden.</p>

<?php if (empty($ticker_tags)): ?>
<div class="text-center py-4 mb-4">
    <p class="text-muted">Noch keine Tags konfiguriert. Leg einen Tag an, um Ticker-Nachrichten zu kategorisieren.</p>
</div>
<?php else: ?>
<div class="table-responsive mb-4">
    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Name</th>
                <th>Farbe</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ticker_tags as $tag): ?>
            <tr>
                <td>
                    <span class="badge bg-<?= e($tag['color']) ?> me-2"><?= e($tag['label']) ?></span>
                </td>
                <td class="text-muted small"><?= e($color_labels[$tag['color']] ?? $tag['color']) ?></td>
                <td class="text-end">
                    <form method="POST" action="/coordinator/settings" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_tag">
                        <input type="hidden" name="tag_id" value="<?= (int)$tag['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('Tag «<?= e(addslashes($tag['label'])) ?>» löschen?')">
                            Löschen
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Create ticker tag form -->
<div class="card shadow-sm" style="max-width: 500px;">
    <div class="card-header fw-semibold">Neues Tag anlegen</div>
    <div class="card-body">
        <form method="POST" action="/coordinator/settings">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_tag">
            <div class="mb-3">
                <label for="tag_label" class="form-label">Tag-Name</label>
                <input type="text" id="tag_label" name="label"
                       class="form-control" maxlength="50" required
                       placeholder='z.B. "Tor"'>
                <div class="form-text">Max. 50 Zeichen.</div>
            </div>
            <div class="mb-3">
                <label for="tag_color" class="form-label">Farbe</label>
                <select id="tag_color" name="color" class="form-select">
                    <option value="success">Grün (Erfolg)</option>
                    <option value="warning">Gelb (Hinweis)</option>
                    <option value="danger">Rot (Warnung)</option>
                    <option value="primary">Blau (Standard)</option>
                    <option value="secondary" selected>Grau (Neutral)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Tag anlegen</button>
        </form>
    </div>
</div>

<div class="mt-4">
    <a href="/coordinator/profile" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Zurück zu Profil
    </a>
</div>
