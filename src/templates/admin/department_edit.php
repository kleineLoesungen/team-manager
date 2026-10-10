<?php
// src/templates/admin/department_edit.php — Admin: edit a department (name, symbol)
// Variables: $department (id, name, icon), $error (string)
?>
<?php if ($error !== ''): render_flash('error', $error); endif; ?>

<?php render_page_header('Abteilung bearbeiten', '/admin/departments'); ?>

<form method="POST" action="/admin/departments/<?= (int)$department['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label for="department_name" class="form-label fw-semibold">Name</label>
        <input type="text" id="department_name" name="name" class="form-control"
               value="<?= e($department['name']) ?>" required maxlength="100" autofocus>
        <div class="form-text">Der neue Name erscheint überall, auch im öffentlichen Ticker.</div>
    </div>
    <?php render_icon_picker('icon', DEPARTMENT_ICONS, (string)($department['icon'] ?? ''), 'Symbol',
        'Steht in den Kalender-Abos der Teams vor jedem Eintrag, z. B. „⚽ U13 - Training“.'); ?>
    <div class="d-grid gap-3">
        <button type="submit" class="btn btn-primary min-touch">Abteilung speichern</button>
        <a href="/admin/departments" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>
