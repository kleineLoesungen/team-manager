---
phase: quick
plan: 260502-wfw
type: execute
wave: 1
depends_on: []
files_modified:
  - src/templates/coach/list_detail.php
  - src/templates/player/list_detail.php
  - src/coach/stats_handler.php
  - src/templates/coach/stats.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "Gesamt-Zeile erscheint unter allen Spielerzeilen in der Listenansicht (Coach und Spieler)"
    - "Gesamt-Zeile summiert Zahlenspalten korrekt und zählt Ja/Nein-Spalten (Anzahl '1'-Werte)"
    - "Textspalten in der Gesamt-Zeile bleiben leer"
    - "Coach kann auf der Statistikseite eine globale Spalte auswählen — Rangliste zeigt nur diese Spalte"
    - "Kein col_filter (default) zeigt alle Spalten wie bisher"
  artifacts:
    - path: "src/templates/coach/list_detail.php"
      provides: "tfoot Gesamt-Zeile"
    - path: "src/templates/player/list_detail.php"
      provides: "tfoot Gesamt-Zeile"
    - path: "src/coach/stats_handler.php"
      provides: "$col_filter GET-Parameter + Übergabe an Template"
    - path: "src/templates/coach/stats.php"
      provides: "Spalten-Dropdown + gefilterter Spaltenausdruck"
  key_links:
    - from: "stats_handler.php"
      to: "stats.php"
      via: "$col_filter in use-Closure"
      pattern: "col_filter"
    - from: "stats.php ranking_sort_url()"
      to: "GET params"
      via: "col_filter als hidden input / URL-Parameter"
      pattern: "col_filter"
---

<objective>
Zwei unabhängige UI-Verbesserungen:

1. Gesamt-Zeile (`<tfoot>`) am Ende der Listenansicht (Coach + Spieler) mit Summe (Zahl), Anzahl (Ja/Nein), leer (Text).
2. Spalten-Dropdown auf der Coach-Statistik-Rangliste: filtert Rangliste auf eine globale Spalte (4 Zeitfenster) oder zeigt alle.

Purpose: Schnellüberblick über Gesamtwerte pro Liste; Fokus auf einzelne Kennzahl in der Rangliste ohne horizontales Scrollen.
Output: Geänderte Templates + Handler, keine neuen Dateien, keine SQL-Änderungen.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@.planning/ROADMAP.md

Key facts already loaded:
- $cells[player_id][col_id] = value (string|null), available in both list_detail templates
- $columns[]['data_type'] ∈ {'boolean','number','text'}
- boolean cells: value = '1' (true) or '0'/''/null (false) — count where === '1'
- number cells: numeric string or null/'' — sum non-empty values
- Coach template wraps table in <form> with input cells; totals row reads $cells map (not POST inputs)
- Player template: show_all_rows controls whether $players has 1 or N rows — totals computed over $players/visible rows
- Stats handler: $global_columns, $sort_col_id, $sort_win, $valid_col_ids already present
- ranking_sort_url() helper defined in stats.php — must preserve col_filter param
</context>

<tasks>

<task type="auto">
  <name>Task 1: Gesamt-Zeile in Coach- und Spieler-Listenansicht</name>
  <files>src/templates/coach/list_detail.php, src/templates/player/list_detail.php</files>
  <action>
In BEIDEN Templates eine `<tfoot>`-Zeile direkt nach `</tbody>` (und vor `</table>`) einfügen.

**Berechnung (PHP in Template, nach dem tbody-Block):**

Iteriere `$columns` und berechne pro Spalte über alle `$players`:
- `data_type === 'number'`: Summe aller `$cells[$pid][$cid]` wo Wert nicht null/leer ist. Nutze `is_numeric()` zur Absicherung. Ergebnis als Zahl formatieren (ganzzahlig wenn floor == n, sonst 2 Dezimalstellen mit `,`/`.`).
- `data_type === 'boolean'`: Anzahl Einträge wo `$cells[$pid][$cid] === '1'`. Ausgabe als Integer.
- `data_type === 'text'`: leerer String.

**Coach-Template (`src/templates/coach/list_detail.php`):**
- Gesamt-Zeile nur rendern wenn `!empty($players) && !empty($columns)` (bereits in `<?php else: ?>` Block).
- Erste Zelle: `<td class="text-nowrap fw-bold">Gesamt</td>` — kein Input-Feld.
- Pro Spalte: `<td class="text-nowrap fw-bold">` + berechneter Wert + `</td>`. Kein Input-Element in der tfoot-Zeile.
- tfoot mit `class="table-light"`.

**Player-Template (`src/templates/player/list_detail.php`):**
- Erste Spalte nur wenn `$list['show_all_rows']` (gleiche Bedingung wie in thead/tbody).
- Erste Zelle: `<td class="text-nowrap fw-bold">Gesamt</td>` (nur wenn show_all_rows).
- Wenn show_all_rows=false: nur eine Zeile (eigene Zeile) — Gesamt-Zeile trotzdem anzeigen (zeigt eigene Werte als Summe), erste Spalten-Zelle entfällt da kein Spieler-Name-Column.
- Kein "Bearbeiten"-Button in tfoot (`$can_edit`-Spalte: leere `<td></td>`).
- tfoot mit `class="table-light"`.

**Keine Änderungen an Handlern.**
  </action>
  <verify>
1. Coach-Listenansicht aufrufen — unter letztem Spieler erscheint "Gesamt"-Zeile mit korrekten Summen/Zählungen.
2. Spieler-Listenansicht (show_all_rows=true) aufrufen — Gesamt-Zeile erscheint.
3. Spieler-Listenansicht (show_all_rows=false, nur eigene Zeile) — Gesamt-Zeile erscheint ohne Spieler-Namensspalte.
4. Text-Spalten in Gesamt-Zeile sind leer.
5. PHP-Fehlerlog zeigt keine Warnungen.
  </verify>
  <done>Gesamt-Zeile in beiden Templates korrekt berechnet und gerendert; keine PHP-Notices.</done>
</task>

<task type="auto">
  <name>Task 2: Spalten-Dropdown auf Coach-Statistik-Rangliste</name>
  <files>src/coach/stats_handler.php, src/templates/coach/stats.php</files>
  <action>
**Handler (`src/coach/stats_handler.php`):**

Nach den bestehenden `$sort_win`/`$sort_col_id`-Validierungen, `$col_filter` einlesen und validieren:

```php
// Validate col_filter (0 = alle Spalten, sonst column_id einer globalen Spalte)
$col_filter = isset($_GET['col_filter']) && $_GET['col_filter'] !== '' ? (int)$_GET['col_filter'] : 0;
if ($col_filter !== 0 && !in_array($col_filter, $valid_col_ids, true)) {
    $col_filter = 0;
}
```

`$col_filter` in die `use`-Closure von `render_coach_page()` aufnehmen:
```php
render_coach_page('Statistik', 'stats', function() use (
    $global_columns, $player_stats, $player_order,
    $available_lists, $filter_list_id, $filter_date_from, $filter_date_to, $filter_include_undated,
    $ranking, $ranking_order, $sort_col_id, $sort_win, $col_filter
) {
    require ROOT_PATH . '/src/templates/coach/stats.php';
});
```

**Template (`src/templates/coach/stats.php`):**

1. **Spalten-Dropdown** — direkt vor der `<h5>Rangliste</h5>`-Überschrift einfügen (nach der Spielerstatistiken-Tabelle):

```php
<form method="get" action="/coach/stats" class="mb-3 d-flex align-items-center gap-2 flex-wrap">
    <!-- Bestehende Filterparameter als hidden inputs erhalten -->
    <?php if ($filter_list_id): ?><input type="hidden" name="list_id" value="<?= (int)$filter_list_id ?>"><?php endif; ?>
    <?php if ($filter_date_from): ?><input type="hidden" name="date_from" value="<?= e($filter_date_from) ?>"><?php endif; ?>
    <?php if ($filter_date_to): ?><input type="hidden" name="date_to" value="<?= e($filter_date_to) ?>"><?php endif; ?>
    <?php if ($filter_include_undated): ?><input type="hidden" name="include_undated" value="1"><?php endif; ?>
    <!-- Aktuelle Sortierparameter erhalten -->
    <input type="hidden" name="sort_col" value="<?= (int)$sort_col_id ?>">
    <input type="hidden" name="sort_win" value="<?= e($sort_win) ?>">
    <label for="col_filter_select" class="form-label mb-0 small fw-medium">Spalte:</label>
    <select name="col_filter" id="col_filter_select" class="form-select form-select-sm" style="max-width:200px;" onchange="this.form.submit()">
        <option value="">Alle Spalten</option>
        <?php foreach ($global_columns as $col): ?>
            <option value="<?= (int)$col['id'] ?>" <?= $col_filter === (int)$col['id'] ? 'selected' : '' ?>>
                <?= e($col['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>
```

2. **Rangliste filtern** — in der Ranglisten-Tabelle (thead + tbody) die äußere `foreach ($global_columns as $col)` mit einem Filter versehen:

Im thead-Block (colspan-Header-Zeile und Zeitfenster-Unterzeile):
```php
<?php foreach ($global_columns as $col): ?>
    <?php if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue; ?>
    ...
<?php endforeach; ?>
```

Im tbody-Block (Datenzellen):
```php
<?php foreach ($global_columns as $col): ?>
    <?php if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue; ?>
    ...
<?php endforeach; ?>
```

3. **ranking_sort_url()-Helfer anpassen** — `col_filter` im URL-Parameter erhalten:

```php
function ranking_sort_url(int $col_id, string $win, array $current_get): string {
    $params = array_filter([
        'list_id'         => $current_get['list_id']    ?? '',
        'date_from'       => $current_get['date_from']  ?? '',
        'date_to'         => $current_get['date_to']    ?? '',
        'include_undated' => ($current_get['include_undated'] ?? '') ? '1' : '',
        'col_filter'      => $current_get['col_filter'] ?? '',
        'sort_col'        => $col_id,
        'sort_win'        => $win,
    ], fn($v) => $v !== '');
    return '/coach/stats?' . http_build_query($params);
}
```

4. **Spielerstatistiken-Tabelle** (obere Tabelle, nicht die Rangliste) — diese ebenfalls nach `$col_filter` filtern: in beiden foreach-Blöcken (thead + tbody) `if ($col_filter !== 0 && (int)$col['id'] !== $col_filter) continue;` einfügen, damit die Ansicht konsistent ist.
  </action>
  <verify>
1. /coach/stats aufrufen ohne col_filter — Rangliste zeigt alle Spalten wie bisher.
2. Dropdown auswählen — Seite lädt neu, Rangliste zeigt nur die 4 Zeitfenster-Spalten der gewählten Spalte.
3. Sortier-Links in der gefilterten Ansicht erhalten col_filter im URL.
4. Zurücksetzen (Alle Spalten) zeigt wieder alle Spalten.
5. Bestehende Filterparameter (Datum, Liste) bleiben nach col_filter-Wechsel erhalten.
  </verify>
  <done>Spalten-Dropdown funktioniert; Rangliste und Spielerstatistiken-Tabelle filtern korrekt; URL-State vollständig erhalten.</done>
</task>

</tasks>

<verification>
- Coach: /coach/lists/{id} — Gesamt-Zeile sichtbar, Werte plausibel
- Player: /player/lists/{id} (show_all_rows=true und =false) — Gesamt-Zeile korrekt
- Coach: /coach/stats — Dropdown vorhanden, Filterung funktioniert, kein JS-Fehler
- PHP-Fehlerlog zeigt keine Notices/Warnings
</verification>

<success_criteria>
- Gesamt-Zeile summiert Zahlen und zählt Booleans korrekt in beiden Listen-Views
- Textspalten in Gesamt-Zeile bleiben leer
- Spalten-Dropdown auf Statistikseite filtert Rangliste (und Spielerstatistiken) auf eine Spalte
- Alle bestehenden Filter-/Sortierparameter bleiben beim Spalten-Dropdown-Wechsel erhalten
- Kein Framework-Eingriff, kein JS-Framework, kein SQL-Query-Änderung
</success_criteria>

<output>
After completion, create `.planning/quick/260502-wfw-list-view-totals-row-stats-ranking-colum/260502-wfw-SUMMARY.md`
</output>
