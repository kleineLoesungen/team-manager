# Phase 3: Lists, Columns & Cells — Context

**Gathered:** 2026-04-30
**Status:** Ready for planning

<domain>
## Phase Boundary

Trainer erstellen Listen mit konfigurierbaren Spalten (global auf Teamebene und lokal pro Liste), und Trainer/Spieler bearbeiten Zellen gemäß Sichtbarkeits-Regeln und Eigentümerschaft. Phase 3 baut auch den Spieler-Bereich (aktuell 404) mit Listenansicht und Zeilen-Editing.

Requirements: LIST-01, LIST-02, LIST-03, LIST-04, LIST-05, CELL-01, CELL-02, CELL-03, CELL-04

</domain>

<decisions>
## Implementation Decisions

### Listen-Übersicht (Coach)
- **D-01:** Listen-Übersicht `/coach/lists` zeigt Listen als **Bootstrap-Karten** (analog zur Spieler-Übersicht in Phase 2): Karten-Header = Listenname, Badges für Sichtbarkeits-State (public/protected/private), Spaltenanzahl, Aktionsbuttons ("Öffnen", "Einstellungen").
- **D-02:** Die Coach-Navigation bekommt zwei neue Einträge: **"Listen"** (`/coach/lists`) und **"Spalten"** (`/coach/columns`). `render_coach_page()` in `src/templates/coach/layout.php` muss aktualisiert werden.

### Listen-Detail-Ansicht (Tabelle)
- **D-03:** Geöffnete Liste wird als **HTML-Tabelle** dargestellt: Spieler als Zeilen, Spalten (global + lokal) als Spalten. Globale Spalten kommen zuerst (da statistisch relevant), dann lokale.
- **D-04:** Mobile: **horizontale Scrollbar** (Bootstrap `table-responsive` / `overflow-x: auto`). Keine fixierte erste Spalte.
- **D-05:** Empty State: **leere Tabelle mit allen Spielern** — keine Placeholder-Texte. Trainer sieht die vollständige Tabellenstruktur sofort, alle Zellen leer.

### Zellen-Bearbeitung
- **D-06:** Zellen werden über eine **eigene Bearbeitungsseite** bearbeitet: `GET /coach/lists/{id}/rows/{player_id}/edit` und `POST /coach/lists/{id}/rows/{player_id}/edit`. 1 Handler-Datei für alle Zeilen/Listen-Kombinationen, nicht eine pro Spieler.
- **D-07:** In der Listen-Tabelle hat jede Spieler-Zeile am Ende einen **"Bearbeiten"-Button**, der zur Bearbeitungsseite navigiert. Kein Inline-Editing, kein JavaScript benötigt.
- **D-08:** Zugriffsregeln exakt nach Requirements:
  - CELL-01: Spieler bearbeitet nur eigene Zeile, nur in public Listen
  - CELL-02: Trainer bearbeitet alle Zeilen in public + protected Listen
  - CELL-03: Private Listen für Spieler vollständig unsichtbar
  - CELL-04: Spieler sieht alle Zeilen einer sichtbaren Liste, editiert aber nur seine eigene

### Spalten-Verwaltung
- **D-09:** **Globale Spalten** werden auf einer eigenen Seite `/coach/columns` verwaltet — separater Nav-Eintrag "Spalten". Dort legt der Coach globale Spalten an (Name + Typ: boolean oder Zahl). Globale Spalten gehören zum Team, nicht zu einer bestimmten Liste.
- **D-10:** **Lokale Spalten** werden direkt auf der Listen-Detail-Seite angelegt — "Spalte hinzufügen"-Button in der Tabellenansicht der geöffneten Liste. Typ: boolean, Zahl oder Text.
- **D-11:** **Listen-Erstellungsformular** (`/coach/lists/create`) enthält drei Bereiche:
  1. Name der Liste (Textfeld, Pflicht)
  2. Sichtbarkeits-State (Radio oder Select: public / protected / private)
  3. Globale Spalten auswählen: Checkboxen für alle verfügbaren globalen Spalten des Teams — welche sollen in dieser Liste erscheinen?
  Lokale Spalten werden NACH der Erstellung hinzugefügt (direkt auf der Detail-Seite).

### Spieler-Bereich
- **D-12:** Eigenes `/player`-Layout analog zum `/coach`-Layout: `src/templates/player/layout.php` mit `render_player_page()`. Eigene Navigation: nur "Listen" (eine Nav-Position). Login-Redirect für Spieler: `/player/lists`.
- **D-13:** Spieler-Startseite `/player/lists` zeigt alle **public Listen** seines Teams als Bootstrap-Karten. Geschützte (protected) und private Listen sind nicht sichtbar.
- **D-14:** In der Listen-Ansicht sieht der Spieler **alle Zeilen** (alle Spieler) der Liste — per CELL-04. Nur seine eigene Zeile hat einen "Bearbeiten"-Button. Andere Zeilen sind read-only.
- **D-15:** Zeilen-Bearbeitung für Spieler folgt demselben Muster wie für Trainer: eigene Bearbeitungsseite `GET /player/lists/{id}/rows/{player_id}/edit`. Spieler kann nur seine eigene Zeile aufrufen — serverseitige Prüfung: `$_SESSION['user_id'] === $player_id`.

### Claude's Discretion
- Genaues DB-Schema für EAV: `lists`, `columns`, `cells`-Tabellen (Typen, Constraints, RLS-Policies)
- Spalten-Reihenfolge in der Tabelle (globale zuerst, dann lokale — sortiert nach `sort_order` oder `created_at`)
- Validierung der Zellwerte nach `data_type` (boolean: 0/1, Zahl: integer/float, Text: max. Länge)
- Erfolgs-Feedback nach Zell-Speicherung (PRG-Redirect mit `?success=1` Banner)
- HTML `<select>` vs. Radio-Buttons für Sichtbarkeits-State im Formular

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen
- `.planning/REQUIREMENTS.md` — LIST-01 bis LIST-05, CELL-01 bis CELL-04 (Phase-3-Anforderungen)
- `.planning/PROJECT.md` — Stack-Constraints (PHP + PostgreSQL, kein JS-Framework, Mobile-first)

### Phase-1/2-Entscheidungen (gelten weiterhin)
- `.planning/phases/01-foundation/01-CONTEXT.md` — Sicherheits-Patterns, RLS-Kontext, Session-Handling
- `.planning/phases/02-team-player-mgmt/02-CONTEXT.md` — Coach-Layout (`render_coach_page`), PRG-Pattern, Bootstrap-Karten, `<details>`-Gruppen

### Bestehende Codebase (für Integration)
- `database/schema.sql` — Bestehende Tabellen (`teams`, `users`); neue Tabellen für Phase 3 ergänzen
- `database/rls_policies.sql` — RLS-Pattern; neue Policies für `lists`/`columns`/`cells` analog anlegen
- `src/templates/coach/layout.php` — `render_coach_page()` — Nav-Erweiterung um "Listen" und "Spalten"
- `src/auth/session.php` — `require_coach()` und `require_auth()` — Basis für Player-Middleware
- `public/index.php` — Router — neue Routen für /coach/lists/*, /coach/columns/*, /player/*

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_coach_page(string $title, string $active, callable $body)` — wrapper für alle Coach-Seiten; `$active` muss 'lists' und 'columns' als neue Werte unterstützen
- `src/templates/admin/credential_modal.php` — nicht relevant für Phase 3
- `generate_unique_username()`, `generate_random_password()` — nicht relevant für Phase 3
- Bootstrap 5 `table-responsive` — für horizontale Scrollbar der Listen-Tabelle
- Bootstrap 5 `badge` — für Sichtbarkeits-State-Badges auf Listen-Karten
- `e()` helper in `helpers.php` — Pflicht für alle User-Daten-Ausgaben

### Established Patterns
- **Handler-Files in `src/coach/` bzw. `src/admin/`**: GET = Seite rendern, POST = verarbeiten, dann PRG-Redirect
- **PRG via `?error=` / `?success=`**: Fehlermeldungen per Query-Parameter nach Redirect
- **`require_coach()` setzt RLS-Kontext**: `set_team_context()` + `reset_rls_context()` bereits korrekt implementiert
- **EAV-Pattern** (aus STATE.md Architektur): `columns`-Tabelle für Struktur, `cells`-Tabelle für Daten

### Integration Points
- `public/index.php` — Router: neue Routen hinzufügen:
  - `/coach/lists` (GET)
  - `/coach/lists/create` (GET/POST)
  - `/coach/lists/{id}` (GET — Listen-Detail-Tabelle)
  - `/coach/lists/{id}/rows/{player_id}/edit` (GET/POST — Zeilen-Formular)
  - `/coach/lists/{id}/columns/create` (POST — lokale Spalte hinzufügen)
  - `/coach/lists/{id}/settings` (GET/POST — Sichtbarkeit ändern, LIST-05)
  - `/coach/columns` (GET — globale Spalten-Übersicht)
  - `/coach/columns/create` (POST — globale Spalte anlegen)
  - `/player/lists` (GET — Spieler-Startseite)
  - `/player/lists/{id}` (GET — Listen-Tabelle für Spieler)
  - `/player/lists/{id}/rows/{player_id}/edit` (GET/POST — Spieler bearbeitet eigene Zeile)
- `src/auth/login_handler.php` — Zeile `redirect('/player')` → `redirect('/player/lists')` (aktuell 404)
- `database/schema.sql` — neue Tabellen: `lists`, `columns`, `cells`

</code_context>

<specifics>
## Specific Ideas

- Listen-Erstellungsformular hat drei Bereiche auf einmal: Name + Sichtbarkeit + globale Spalten auswählen (nicht nur Name + Sichtbarkeit zuerst)
- Spieler sieht alle Zeilen der Tabelle (nicht nur seine eigene) — nur sein "Bearbeiten"-Button ist aktiv

</specifics>

<deferred>
## Deferred Ideas

- **Optionale Listen-Metadaten** (Datum, Startzeit, Endzeit, Notizen/Beschreibung): Backlog 999.2 — nicht Phase 3
- **Zeilen-Sichtbarkeitskontrolle** (alle Zeilen vs. nur eigene Zeile): Backlog 999.2 — nicht Phase 3
- **Spalten-Reihenfolge ändern**: v2-Anforderung (LIST-V2-01) — nicht Phase 3
- **Globale Spalte nachträglich typ-ändern**: v2-Anforderung — nicht Phase 3

</deferred>

---

*Phase: 03-lists-columns-cells*
*Context gathered: 2026-04-30*
