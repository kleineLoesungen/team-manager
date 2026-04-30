# Phase 4: Statistics & Aggregation — Context

**Gathered:** 2026-04-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Coach kann pro Spieler eine Statistik-Seite abrufen, die alle globalen Spalten aggregiert (Summe für Zahlen-Spalten, Anzahl true-Werte für boolean-Spalten) über alle relevanten Listen. Zusätzlich gibt es eine Team-Rangliste, sortierbar nach einer wählbaren globalen Spalte. Spieler sehen eine eigene Statistikseite mit nur ihren eigenen Werten.

Requirements: STAT-01, STAT-02, STAT-03

</domain>

<decisions>
## Implementation Decisions

### Statistik-Darstellung (Coach)
- **D-01:** Coach-Statistikseite `/coach/stats` zeigt eine **Bootstrap-Tabelle**: Spieler als Zeilen, globale Spalten als Spalten. Horizontal scrollbar bei vielen Spalten (Bootstrap `table-responsive`) — konsistent mit Phase-3-Listenansicht.
- **D-02:** Coach sieht **alle Listentypen** (public, protected, private) in seiner Statistikberechnung — kein Ausschluss von privaten Listen für Coach-Kontext.
- **D-03:** Rangliste (STAT-03) erscheint **auf derselben Statistikseite** — kein eigener Nav-Eintrag. Zwei Bereiche auf `/coach/stats`: oben Spielerstatistiken-Tabelle, darunter Rangliste mit Spaltenauswahl-Dropdown.

### Spieler-Statistikseite
- **D-04:** Spieler hat eine eigene Statistikseite `/player/stats` — neuer Nav-Eintrag "Statistik" im Player-Bereich. Gleiche Tabellenstruktur wie Coach, aber **nur eine Zeile** (eigene Werte).
- **D-05:** Spieler-Statistik berücksichtigt **nur Werte aus public und protected Listen** — private Listen fließen für Spieler nicht in die Aggregation ein.

### Navigation
- **D-06:** Coach-Navigation erhält neuen Eintrag **"Statistik"** (`/coach/stats`). Player-Navigation erhält neuen Eintrag **"Statistik"** (`/player/stats`). `render_coach_page()` und `render_player_page()` werden entsprechend erweitert.

### Claude's Discretion
- Genaue SQL-Aggregationsabfragen (GROUP BY player_id, JOIN auf list_global_columns + cells)
- STAT-02 Filterung: Implementierungsdetails für Listen-Dropdown und Zeitraum-Filter (Datum bezieht sich auf `lists.created_at` oder `cells.updated_at` — Claude entscheidet)
- Rangliste: Spaltenauswahl-Dropdown über der Tabelle, GET-Parameter für aktive Spalte
- Empty State: Wenn Spieler keine Einträge hat (leere Werte anzeigen, kein Fehler)
- Formatierung von Zahlen (integer vs. float je nach Spalten-Typ)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen
- `.planning/REQUIREMENTS.md` — STAT-01 bis STAT-03 (Phase-4-Anforderungen)
- `.planning/PROJECT.md` — Stack-Constraints (PHP + PostgreSQL, kein JS-Framework, Mobile-first)

### Phase-1/2/3-Entscheidungen (gelten weiterhin)
- `.planning/phases/01-foundation/01-CONTEXT.md` — Sicherheits-Patterns, RLS-Kontext, Session-Handling
- `.planning/phases/03-lists-columns-cells/03-CONTEXT.md` — EAV-Schema-Entscheidungen, globale Spalten, Sichtbarkeitslogik

### Bestehende Codebase (für Integration)
- `database/schema.sql` — `lists`, `columns`, `cells`, `list_global_columns` Tabellen — Datenmodell für Aggregation
- `database/rls_policies.sql` — RLS-Patterns; Statistikabfragen nutzen Team-Kontext
- `src/db/visibility.php` — `can_view_list()` — Sichtbarkeitslogik (Spieler sieht nur public/protected)
- `src/db/connection.php` — `set_team_context()` setzt `app.current_role` und `app.current_user_id`
- `src/auth/session.php` — `require_coach()` und `require_player()` — Middleware-Basis
- `src/templates/coach/layout.php` — `render_coach_page()` — Nav-Erweiterung um "Statistik"
- `src/templates/player/layout.php` — `render_player_page()` — Nav-Erweiterung um "Statistik"
- `public/index.php` — Router — neue Routen für /coach/stats und /player/stats

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_coach_page(string $title, string $active, callable $body)` — wrapper für alle Coach-Seiten; `$active` muss 'stats' als neuen Wert unterstützen
- `render_player_page(string $title, string $active, callable $body)` — analog; 'stats' als neuer aktiver Nav-Eintrag
- Bootstrap 5 `table-responsive` — für horizontale Scrollbar der Statistiktabelle
- `e()` helper in `helpers.php` — Pflicht für alle User-Daten-Ausgaben

### Established Patterns
- **Tabellen für Datendarstellung**: Phase 3 Listendetail etabliert Bootstrap `table-responsive`
- **PRG-Pattern** für Filterformulare: GET-Parameter für Filter (kein POST), direkt in URL
- **require_coach() / require_player()** setzt RLS-Kontext: team_id + role + user_id in GUCs

### Integration Points
- `public/index.php` — Router: neue Routen hinzufügen:
  - `/coach/stats` (GET — Statistik + Rangliste)
  - `/player/stats` (GET — eigene Statistik)
- `src/templates/coach/layout.php` — Nav: "Statistik" nach "Listen" einfügen
- `src/templates/player/layout.php` — Nav: "Statistik" nach "Listen" einfügen
- Aggregationsabfrage: `JOIN columns ON (list_id IS NULL) JOIN list_global_columns ON ... JOIN cells ON ... WHERE player_id GROUP BY player_id, column_id`

</code_context>

<specifics>
## Specific Ideas

- Spieler-Sicht: Statistiktabelle zeigt **nur eine Zeile** — die eigene. Kein Vergleich mit anderen Spielern sichtbar.
- Coach-Sicht: Alle Spieler in einer Tabelle, jede globale Spalte eine Tabellenspalte. Spieler ohne Einträge erscheinen mit leeren Werten (NULL wird als "—" oder "0" dargestellt).
- Rangliste liegt **unterhalb** der Statistiktabelle auf derselben Seite, nicht als Tab.

</specifics>

<deferred>
## Deferred Ideas

- Zeitraum-Filter (STAT-02): detaillierte UX-Entscheidung (Datum-Picker vs. Vorperioden-Dropdown) — Claude entscheidet
- Statistik-Export (CSV) — Backlog EXT-V2-01
- Spalten-Typ-Änderung nachträglich — v2-Anforderung (LIST-V2-01)

</deferred>

---

*Phase: 04-statistics-aggregation*
*Context gathered: 2026-04-30*
