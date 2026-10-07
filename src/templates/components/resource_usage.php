<?php
// src/templates/components/resource_usage.php — Auslastung der Ressourcen (alle Teams)
// Für Koordinatoren und Mitglieder. Filter je Ressource (?r=id), ab heute nach Tagen gruppiert,
// Überschneidungen gelb markiert. Mit gewählter Ressource: Kalender-Abo (ics) dieser Ressource.

declare(strict_types=1);

/** Everything the page needs: resources, selected one, usage by day, ics link. */
function resource_usage_page_data(PDO $pdo): array {
    $resources = resources_active($pdo);
    $ids       = array_map('intval', array_column($resources, 'id'));
    $selected  = (int)($_GET['r'] ?? 0);
    $selected  = in_array($selected, $ids, true) ? $selected : null;
    $today     = (new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
    $token     = $selected !== null ? resources_calendar_token($pdo, $selected) : null;

    return [
        'resources' => $resources,
        'selected'  => $selected,
        'days'      => resources_usage($pdo, $selected !== null ? [$selected] : $ids, $today),   // nur Ressourcen der Abteilung
        'ics_url'   => $token ? absolute_url('/ics/resource/' . $token . '.ics') : null,
    ];
}

function render_resource_usage(array $page, string $base): void {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $selected = $page['selected'];
    $name     = null;
    foreach ($page['resources'] as $r) {
        if ((int)$r['id'] === $selected) $name = $r['name'];
    }
    ?>
    <?php render_page_header('Ressourcen', str_starts_with($base, '/coordinator') ? '/coordinator/contents' : '/member/contents'); ?>

    <?php if (!$page['resources']): ?>
        <?php render_empty('box-seam', 'Keine Ressourcen', 'Der Admin hat noch keine Ressourcen angelegt.'); ?>
        <?php return; ?>
    <?php endif; ?>

    <?php
    $pills = [['label' => 'Alle', 'url' => $base, 'active' => $selected === null]];
    foreach ($page['resources'] as $r) {
        $pills[] = ['label' => $r['name'], 'url' => $base . '?r=' . (int)$r['id'], 'active' => (int)$r['id'] === $selected];
    }
    render_filter_pills($pills);
    ?>

    <p class="text-muted small mb-3">Belegung aller Teams ab heute. Einträge, die das eigene Team nicht sehen darf, erscheinen als „Belegt“.</p>

    <?php if (!$page['days']): ?>
        <?php render_empty('calendar-check', 'Nichts belegt', ($name ? $name . ' ist' : 'Die Ressourcen sind') . ' ab heute frei.'); ?>
    <?php else: ?>
        <?php foreach ($page['days'] as $date => $rows):
            render_collection_group(dashboard_day_label($date), function () use ($rows, $selected) { ?>
            <div class="list-group">
                <?php foreach ($rows as $row):
                    $tag = $row['url'] ? 'a' : 'div'; ?>
                <<?= $tag ?> <?= $row['url'] ? 'href="' . e($row['url']) . '"' : '' ?>
                    class="list-group-item <?= $row['url'] ? 'list-group-item-action' : '' ?> d-flex align-items-center gap-3">
                    <span class="text-nowrap small text-muted tm-usage-time"><?= e(resources_slot_time($row)) ?></span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block fw-semibold text-break"><?= e($row['label']) ?></span>
                        <span class="d-flex align-items-center gap-2 flex-wrap small">
                            <?php render_badge('dim', $row['team_name'], 'bi-people'); ?>
                            <?php if ($selected === null) render_badge('dim', $row['resource_name'], 'bi-box-seam'); ?>
                            <?php if ($row['overlap']) render_badge('warn', 'Überschneidung', 'bi-exclamation-triangle'); ?>
                        </span>
                    </span>
                    <?php if ($row['url']): ?><i class="bi bi-chevron-right text-muted" aria-hidden="true"></i><?php endif; ?>
                </<?= $tag ?>>
                <?php endforeach; ?>
            </div>
        <?php });
        endforeach; ?>
    <?php endif; ?>

    <?php if ($page['ics_url']): ?>
    <div class="card mt-4">
        <div class="card-header d-flex align-items-center gap-2">
            <i class="bi bi-calendar2-check" aria-hidden="true"></i>
            <span class="fw-semibold">Kalender: <?= e($name) ?></span>
        </div>
        <div class="card-body">
            <p class="text-body-secondary small mb-3">Alle Belegungen dieser Ressource in deinem Kalender. Private Einträge erscheinen als „Belegt“.</p>
            <div class="input-group mb-3">
                <input type="text" id="ics-url-resource" class="form-control font-monospace" value="<?= e($page['ics_url']) ?>" readonly>
                <button class="btn btn-outline-secondary" type="button" title="Link kopieren"
                        onclick="navigator.clipboard.writeText(document.getElementById('ics-url-resource').value).then(()=>{this.textContent='✓';setTimeout(()=>{this.innerHTML='<i class=\'bi bi-clipboard\'></i>';},1500)})">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
            <a href="<?= e(webcal_url($page['ics_url'])) ?>" class="btn btn-outline-primary min-touch">
                <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Kalender abonnieren
            </a>
        </div>
    </div>
    <?php elseif ($selected === null): ?>
    <p class="text-muted small mt-4"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Wähle eine Ressource, um ihren Kalender zu abonnieren.</p>
    <?php endif; ?>
    <?php
}
