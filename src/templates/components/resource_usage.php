<?php
// src/templates/components/resource_usage.php — Auslastung der Ressourcen (alle Teams)
// Für Koordinatoren und Mitglieder. Filter je Ressource (?r=id). Ab heute in Abschnitten
// „Nächste 7 Tage“, „Nächste 4 Wochen“ und — nachgeladen in Schritten von 2 Wochen (?wochen=6, 8 …)
// — „Danach“, darin nach Tagen gruppiert; Überschneidungen gelb markiert. Das Nachladen ist ein
// Link mit Sprungmarke auf den Anfang der neuen Wochen (ohne JavaScript).
// Mit gewählter Ressource: Kalender-Abo (ics) dieser Ressource.

declare(strict_types=1);

const RESOURCE_USAGE_WEEKS     = 4;    // sichtbar ohne Nachladen
const RESOURCE_USAGE_MORE      = 2;    // je „Weitere Einträge anzeigen“
const RESOURCE_USAGE_MAX_WEEKS = 104;  // Obergrenze für ?wochen=

/** Everything the page needs: resources, selected one, usage by day in range, ics link. */
function resource_usage_page_data(PDO $pdo, bool $guest = false): array {
    $resources = $guest ? resources_for_guests($pdo) : resources_active($pdo);
    $ids       = array_map('intval', array_column($resources, 'id'));
    $selected  = (int)($_GET['r'] ?? 0);
    $selected  = in_array($selected, $ids, true) ? $selected : null;
    $today     = new DateTimeImmutable('today', new DateTimeZone('Europe/Berlin'));
    $weeks     = (int)($_GET['wochen'] ?? RESOURCE_USAGE_WEEKS);
    $weeks     = min(RESOURCE_USAGE_MAX_WEEKS, max(RESOURCE_USAGE_WEEKS, $weeks));
    $weeks    -= ($weeks - RESOURCE_USAGE_WEEKS) % RESOURCE_USAGE_MORE;   // 4, 6, 8 …
    $to        = $today->modify('+' . ($weeks * 7) . ' days')->format('Y-m-d');
    $shown     = $selected !== null ? [$selected] : $ids;                 // nur Ressourcen der Abteilung
    $token     = ($selected !== null && !$guest) ? resources_calendar_token($pdo, $selected) : null;

    return [
        'resources' => $resources,
        'selected'  => $selected,
        'today'     => $today->format('Y-m-d'),
        'weeks'     => $weeks,
        'to'        => $to,
        'days'      => resources_usage($pdo, $shown, $today->format('Y-m-d'), $to, 500, $guest),
        'guest'     => $guest,
        'has_more'  => $weeks < RESOURCE_USAGE_MAX_WEEKS && resources_booked_from($pdo, $shown, $to),
        'ics_url'   => $token ? absolute_url('/ics/resource/' . $token . '.ics') : null,
    ];
}

/** One day's bookings as a collection group (heading = day label). */
function render_resource_usage_day(string $date, array $rows, ?int $selected): void {
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
}

function render_resource_usage(array $page, string $base): void {
    require_once ROOT_PATH . '/src/db/dashboard.php';
    $selected = $page['selected'];
    $name     = null;
    foreach ($page['resources'] as $r) {
        if ((int)$r['id'] === $selected) $name = $r['name'];
    }
    ?>
    <?php render_page_header('Ressourcen', !empty($page['guest']) ? null : (str_starts_with($base, '/coordinator') ? '/coordinator/contents' : '/member/contents')); ?>

    <?php if (!$page['resources']): ?>
        <?php render_empty('box-seam', 'Keine Ressourcen', !empty($page['guest']) ? 'Es sind keine Ressourcen für Gäste freigegeben.' : 'Der Admin hat noch keine Ressourcen angelegt.'); ?>
        <?php return; ?>
    <?php endif; ?>

    <?php
    $pills = [['label' => 'Alle', 'url' => $base, 'active' => $selected === null]];
    foreach ($page['resources'] as $r) {
        $pills[] = ['label' => $r['name'], 'url' => $base . '?r=' . (int)$r['id'], 'active' => (int)$r['id'] === $selected];
    }
    render_filter_pills($pills);
    ?>

    <p class="text-muted small mb-3"><?= !empty($page['guest'])
        ? 'Belegung ab heute. Einträge, die nicht für Gäste freigegeben sind, erscheinen als „Belegt“.'
        : 'Belegung aller Teams ab heute. Einträge, die das eigene Team nicht sehen darf, erscheinen als „Belegt“.' ?></p>

    <?php
    $tz     = new DateTimeZone('Europe/Berlin');
    $today  = new DateTimeImmutable($page['today'], $tz);
    $day7   = $today->modify('+7 days')->format('Y-m-d');
    $day28  = $today->modify('+' . (RESOURCE_USAGE_WEEKS * 7) . ' days')->format('Y-m-d');
    $parts  = ['next7' => [], 'next4w' => [], 'later' => []];
    foreach ($page['days'] as $date => $rows) {
        $parts[$date < $day7 ? 'next7' : ($date < $day28 ? 'next4w' : 'later')][$date] = $rows;
    }
    // Sprungmarken: Anfang jedes nachgeladenen 2-Wochen-Blocks (Ziel von „Weitere Einträge anzeigen“)
    $marks = [];
    for ($w = RESOURCE_USAGE_WEEKS; $w < $page['weeks']; $w += RESOURCE_USAGE_MORE) {
        $marks[] = $today->modify('+' . ($w * 7) . ' days')->format('Y-m-d');
    }
    $section = function (string $id, string $title, array $days, string $empty) use ($selected) { ?>
    <section class="mb-4" aria-labelledby="<?= $id ?>">
        <h2 class="h3 mb-0" id="<?= $id ?>"><?= e($title) ?></h2>
        <?php if (!$days): ?><p class="text-muted small mt-2 mb-0"><?= e($empty) ?></p><?php endif; ?>
        <?php foreach ($days as $date => $rows) render_resource_usage_day($date, $rows, $selected); ?>
    </section>
    <?php };
    $section('usage-next7', 'Nächste 7 Tage', $parts['next7'], 'In den nächsten 7 Tagen ist nichts belegt.');
    $section('usage-next4w', 'Nächste 4 Wochen', $parts['next4w'], 'Danach bis ' . $today->modify('+27 days')->format('d.m.') . ' ist nichts belegt.');
    ?>

    <?php if ($page['weeks'] > RESOURCE_USAGE_WEEKS): ?>
    <section class="mb-4" aria-labelledby="usage-later">
        <h2 class="h3 mb-0" id="usage-later">Danach</h2>
        <?php foreach ($parts['later'] as $date => $rows):
            while ($marks && $marks[0] <= $date): ?><div id="ab-<?= e(array_shift($marks)) ?>"></div><?php endwhile;
            render_resource_usage_day($date, $rows, $selected);
        endforeach;
        foreach ($marks as $m): ?><div id="ab-<?= e($m) ?>"></div><?php endforeach; ?>
        <?php if (!$parts['later']): ?><p class="text-muted small mt-2 mb-0">Bis <?= e((new DateTimeImmutable($page['to'], $tz))->modify('-1 day')->format('d.m.Y')) ?> ist nichts weiter belegt.</p><?php endif; ?>
    </section>
    <?php endif; ?>

    <?php if ($page['has_more']):
        $more_to = (new DateTimeImmutable($page['to'], $tz))->modify('+' . (RESOURCE_USAGE_MORE * 7 - 1) . ' days');
        $query   = array_filter(['r' => $selected, 'wochen' => $page['weeks'] + RESOURCE_USAGE_MORE]); ?>
    <div class="d-grid mb-4">
        <a href="<?= e($base . '?' . http_build_query($query) . '#ab-' . $page['to']) ?>" class="btn btn-outline-secondary min-touch">
            <i class="bi bi-chevron-down me-1" aria-hidden="true"></i>Weitere Einträge anzeigen
        </a>
        <span class="small text-muted text-center mt-1">die nächsten 2 Wochen, bis <?= e(dashboard_day_label($more_to->format('Y-m-d'))) ?></span>
    </div>
    <?php else: ?>
    <p class="text-muted small text-center mb-4">Keine weiteren Belegungen.</p>
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
    <?php elseif ($selected === null && empty($page['guest'])): ?>
    <p class="text-muted small mt-4"><i class="bi bi-calendar-plus me-1" aria-hidden="true"></i>Wähle eine Ressource, um ihren Kalender zu abonnieren.</p>
    <?php endif; ?>
    <?php
}
