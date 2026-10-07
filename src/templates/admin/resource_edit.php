<?php
// src/templates/admin/resource_edit.php — Admin: rename a resource
// Variables: $resource (id, name, department_id), $error (string), $departments
?>
<?php if ($error !== ''): render_flash('error', $error); endif; ?>

<?php render_page_header('Ressource bearbeiten', '/admin/resources'); ?>

<form method="POST" action="/admin/resources/<?= (int)$resource['id'] ?>/edit">
    <?= csrf_field() ?>
    <div class="mb-4">
        <label for="resource_name" class="form-label fw-semibold">Name</label>
        <input type="text" id="resource_name" name="name" class="form-control"
               value="<?= e($resource['name']) ?>" required maxlength="100" autofocus>
        <div class="form-text">Der neue Name erscheint überall, auch bei bestehenden Belegungen.</div>
    </div>
    <?php render_department_select($departments, (int)$resource['department_id'],
        'Nur Teams dieser Abteilung können die Ressource belegen. Bestehende Belegungen bleiben erhalten.'); ?>
    <div class="d-grid gap-3">
        <button type="submit" class="btn btn-primary min-touch">Ressource speichern</button>
        <a href="/admin/resources" class="btn btn-outline-secondary min-touch">Abbrechen</a>
    </div>
</form>
