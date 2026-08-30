<?php
// src/templates/components/partials.php — Shared UI partial functions
// Included once from src/templates/layout.php before any template body executes.
// Never add require_once calls for this file in individual templates.
declare(strict_types=1);

/**
 * Flash alert for PRG flow.
 * @param string $type    'success' | 'error'
 * @param string $message Human-readable message
 */
function render_flash(string $type, string $message): void {
    $icon = $type === 'success' ? 'bi-check-circle' : 'bi-exclamation-circle';
    $cls  = $type === 'success' ? 'alert-success' : 'alert-danger';
    ?>
    <div class="alert <?= $cls ?> d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi <?= $icon ?>"></i>
        <span><?= htmlspecialchars($message, ENT_QUOTES) ?></span>
    </div>
    <?php
}

/**
 * Content-level page header (below topbar, inside main content).
 * @param string      $title       Page heading text
 * @param string|null $back_url    If set, shows a back button linking here
 * @param string|null $action_html Optional HTML for right-aligned action slot (button/dropdown)
 */
function render_page_header(string $title, ?string $back_url = null, ?string $action_html = null): void {
    ?>
    <div class="d-flex align-items-center gap-3 mb-3">
        <?php if ($back_url): ?>
        <a href="<?= htmlspecialchars($back_url, ENT_QUOTES) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Zurück
        </a>
        <?php endif; ?>
        <h1 class="h2 mb-0 flex-grow-1"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
        <?php if ($action_html): echo $action_html; endif; ?>
    </div>
    <?php
}

/**
 * Empty state. Replaces <div class="text-center py-5"> patterns.
 * @param string      $icon        Bootstrap Icons name WITHOUT 'bi-' prefix, e.g. 'collection'
 * @param string      $heading     Short heading text
 * @param string      $body_text   Explanatory text
 * @param string|null $action_html Optional HTML for a call-to-action button (use mt-3 class)
 */
function render_empty(string $icon, string $heading, string $body_text, ?string $action_html = null): void {
    ?>
    <div class="tm-empty">
        <i class="bi bi-<?= htmlspecialchars($icon, ENT_QUOTES) ?>"></i>
        <p class="fw-semibold mb-1"><?= htmlspecialchars($heading, ENT_QUOTES) ?></p>
        <p class="text-body-secondary small mb-0"><?= htmlspecialchars($body_text, ENT_QUOTES) ?></p>
        <?php if ($action_html): echo $action_html; endif; ?>
    </div>
    <?php
}

/**
 * Semantic status badge.
 * @param string $type  'ok' | 'warn' | 'bad' | 'dim' | 'info'
 * @param string $label Badge text
 */
function render_badge(string $type, string $label): void {
    $class = match($type) {
        'ok'   => 'badge-ok',
        'warn' => 'badge-warn',
        'bad'  => 'badge-bad',
        'dim'  => 'badge-dim',
        'info' => 'bg-primary-subtle text-primary-emphasis',
        default => 'badge-dim',
    };
    ?>
    <span class="badge <?= $class ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></span>
    <?php
}

/**
 * Grouped list-group section header with hairline separator.
 * @param string   $label      Section label (displayed as tm-group-label)
 * @param callable $items_body Outputs list-group-item elements
 */
function render_collection_group(string $label, callable $items_body): void {
    ?>
    <p class="tm-group-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></p>
    <?php $items_body(); ?>
    <?php
}

/**
 * Card container for a form section. Replaces <div class="card shadow-sm"> patterns.
 * @param string      $heading     Card header heading text
 * @param callable    $fields_body Outputs form field elements
 * @param string|null $footer_html Optional card footer HTML
 */
function render_form_section(string $heading, callable $fields_body, ?string $footer_html = null): void {
    ?>
    <div class="card mb-3">
        <div class="card-header">
            <h2 class="mb-0"><?= htmlspecialchars($heading, ENT_QUOTES) ?></h2>
        </div>
        <div class="card-body">
            <?php $fields_body(); ?>
        </div>
        <?php if ($footer_html): ?>
        <div class="card-footer"><?= $footer_html ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Single form field row: label + input + optional hint text.
 * $input_html must use form-control or form-select WITHOUT -sm suffix.
 * @param string      $label      Field label text
 * @param string      $input_html Raw HTML for the input element
 * @param string|null $hint       Optional hint text below input
 */
function render_form_field(string $label, string $input_html, ?string $hint = null): void {
    ?>
    <div class="mb-3">
        <label class="form-label"><?= htmlspecialchars($label, ENT_QUOTES) ?></label>
        <?= $input_html ?>
        <?php if ($hint): ?>
        <div class="form-text"><?= htmlspecialchars($hint, ENT_QUOTES) ?></div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Horizontal-scrolling matrix table for list detail views.
 * @param array         $columns      Array of column header labels (strings)
 * @param callable      $rows_body    Outputs <tr> elements for tbody
 * @param callable|null $footer_body  Optional: outputs <tr> elements for tfoot
 */
function render_matrix_table(array $columns, callable $rows_body, ?callable $footer_body = null): void {
    ?>
    <div class="tm-matrix">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <?php foreach ($columns as $col): ?>
                    <th><?= htmlspecialchars($col, ENT_QUOTES) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php $rows_body(); ?>
            </tbody>
            <?php if ($footer_body): ?>
            <tfoot>
                <?php $footer_body(); ?>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>
    <?php
}

/**
 * Horizontal filter bar with pill links.
 * @param array  $pills    Array of ['label' => string, 'url' => string, 'active' => bool]
 * @param string $base_url Base URL for constructing pill links (informational, pills provide full URLs)
 */
function render_filter_pills(array $pills, string $base_url = ''): void {
    ?>
    <div class="tm-filters mb-3">
        <?php foreach ($pills as $pill): ?>
        <a href="<?= htmlspecialchars($pill['url'], ENT_QUOTES) ?>"
           class="btn btn-sm <?= $pill['active'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
            <?= htmlspecialchars($pill['label'], ENT_QUOTES) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Danger zone card. Replaces inline danger zone patterns.
 * @param string $action_label  Text for the trigger button (e.g. "Liste löschen")
 * @param string $description   Short explanation of the destructive action
 * @param string $form_html     Full form HTML (must contain btn-outline-danger submit button)
 */
function render_danger_zone(string $action_label, string $description, string $form_html): void {
    ?>
    <div class="card tm-danger-zone mt-4">
        <div class="card-header">
            <h3 class="card-title mb-0">Gefahrenzone</h3>
        </div>
        <div class="card-body">
            <p class="card-text small"><?= htmlspecialchars($description, ENT_QUOTES) ?></p>
            <?= $form_html ?>
        </div>
    </div>
    <?php
}

/**
 * Sticky primary action bar above the bottom nav.
 * Injects has-actionbar class on body via inline script.
 * @param string $label   Button label (Verb + Noun, e.g. "Liste speichern")
 * @param string $form_id ID of the form element this button submits
 */
function render_action_bar(string $label, string $form_id): void {
    ?>
    <div class="tm-actionbar">
        <button type="submit" form="<?= htmlspecialchars($form_id, ENT_QUOTES) ?>" class="btn btn-primary">
            <?= htmlspecialchars($label, ENT_QUOTES) ?>
        </button>
    </div>
    <script>document.body.classList.add('has-actionbar');</script>
    <?php
}
