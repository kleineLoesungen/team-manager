# Phase 6: Calendar — Lists with Date, Location & ICS Export - Context

**Gathered:** 2026-07-14
**Status:** Ready for planning

<domain>
## Phase Boundary

Listen bekommen ein optionales Ort-Feld (zusätzlich zum bestehenden Datum-Feld). Eine navigierbare Timeline-Ansicht erscheint als Standard-Tab auf der Inhalte-Seite (/coordinator/lists und /member/lists) — datierte Einträge chronologisch nach Woche oder Monat, undatierte Einträge im eigenen Abschnitt darunter. Koordinatoren und Mitglieder sehen jeweils ihren Bereich. Eine öffentlich abrufbare ICS-Datei pro Team ermöglicht das Abonnieren in Kalender-Apps.

Nicht in Scope: neue Kalender-Typen, Events ohne Listenbezug, separate Kalender-Seite (es ist ein Tab auf der bestehenden Seite).

</domain>

<decisions>
## Implementation Decisions

### Kalenderansicht-Stil
- **D-01:** Sortierte **Timeline/Listenansicht** — kein visuelles Kalender-Grid. PHP/HTML, kein JS-Framework, kein AJAX.
- **D-02:** **Woche/Monat-Switcher** mit Vor/Zurück-Pfeilen: Navigation via GET-Parameter (z.B. `?view=week&offset=0`). Server-side Rendering, kein AJAX.
- **D-03:** Standard-Ansicht beim Öffnen der Seite: **aktuelle Woche**.
- **D-04:** Datierte Einträge chronologisch innerhalb der gewählten Periode; undatierte Einträge im eigenen Abschnitt **"Ohne Datum"** darunter (nicht im Woche/Monat-Bereich).
- **D-05:** Jeder Kalender-Eintrag zeigt: **Datum + Name + Sichtbarkeits-Badge + Ort** (falls vorhanden). Link führt zur Listen-Detail-Seite.

### Kalender-Einstiegspunkt
- **D-06:** Kein neuer Nav-Eintrag. Stattdessen: **Tab-Switcher** oben auf der bestehenden Inhalte-Seite (`/coordinator/lists` und `/member/lists`). Tabs: "Kalender" | "Liste".
- **D-07:** **Standard-Tab = Kalender** (aktuelle Woche). "Liste"-Tab zeigt die klassische Karten-Übersicht wie bisher.
- **D-08:** Gleiches Tab-Muster für Mitglieder auf `/member/lists` — Kalender-Tab ist Standard, zeigt nur **public Listen**.
- **D-09:** Koordinator-Kalender zeigt public + protected + private Listen (alle sichtbaren Listen mit Datum).

### ICS-Export
- **D-10:** **Eine ICS-Datei pro Team**, öffentlich abrufbar — kein Token, kein Login erforderlich.
- **D-11:** URL-Schema: `/ics/{team_id}.ics`
- **D-12:** **On-demand generiert** — PHP generiert die Datei dynamisch bei jedem Request. Kein Caching (10 Teams × 15 Mitglieder = vernachlässigbare Last).
- **D-13:** ICS enthält: **public + protected Listen mit Datum**. Private Listen werden nicht exportiert.
- **D-14:** **ICS-Link oberhalb der Kalenderansicht** auf dem Kalender-Tab anzeigen — mit kurzem Hinweis "In Kalender-App abonnieren" (Bootstrap `alert-info` oder kleiner `<p class="text-muted">`).

### Ort-Feld
- **D-15:** **Freitext, ein Feld** (`location VARCHAR(255) NULL`), optional. Beispiel: "Sportplatz Mitte", "Turnhalle Schule". Kein strukturiertes Adressfeld.
- **D-16:** Ort erscheint überall: Erstellen-/Bearbeiten-Formular, Listen-Karte (falls vorhanden), Kalender-Eintrag, und im Mitglieder-Kalender (public Listen).
- **D-17:** Ort wird als **ICS LOCATION-Feld** in VEVENT übernommen (falls vorhanden).

### Claude's Discretion
- Tab-Switcher-Design: Bootstrap `.nav-tabs` oder `.btn-group` als Toggle — passend zum bestehenden Style
- Wochentags-Gruppierung: genaue HTML-Struktur (z.B. Tagesüberschrift als `<h6>` + darunter Einträge)
- ICS VEVENT-Format: DTSTART/DTEND als `DATE` (ganztägig, Format YYYYMMDD) da nur `date` ohne Uhrzeit
- `DTSTAMP`, `UID`, `PRODID` im ICS-Header nach RFC 5545
- Idempotente Schema-Migration für `location`-Spalte (`ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL`)
- Neue Route für ICS im Router (`public/index.php`) — ohne Auth-Middleware

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen & Projektkontext
- `.planning/ROADMAP.md` — Phase 6 Goal (vollständige Beschreibung des Kalender-Features)
- `.planning/PROJECT.md` — Stack: PHP+PostgreSQL, kein JS-Framework, Mobile-first, Hetzner Shared Hosting
- `.planning/REQUIREMENTS.md` — Stack-Constraints, Out-of-Scope-Definitionen

### Bestehende Codebase (Integration-Punkte)
- `database/schema.sql` — `lists`-Tabelle: `date DATE NULL` existiert bereits (Zeile 63), `location`-Spalte fehlt noch; `teams`-Tabelle für ICS-Route
- `public/index.php` — Router: neue Route `/ics/{team_id}.ics` hinzufügen (kein Auth-Guard)
- `src/coordinator/lists_handler.php` — Bestehende Listen-Übersicht-Logik; hier Tab-Switcher und Kalender-Logik integrieren
- `src/templates/coordinator/lists.php` — Bestehende Listen-Karten-Template; Tab-Switcher + Kalender-View hinzufügen
- `src/member/lists_handler.php` — Mitglieder-Listen-Handler; gleicher Tab-Switcher
- `src/templates/member/lists.php` — Mitglieder-Listen-Template
- `src/templates/coordinator/list_form.php` — Bestehendes Datum-Feld (Zeile 25-26); `location`-Feld ergänzen
- `src/coordinator/list_create_handler.php` — Bestehende `date`-Verarbeitung (Zeile 33-59); `location`-Handling analog ergänzen
- `src/utils/helpers.php` — `e()`, `redirect()` — Pflicht für alle Ausgaben
- `src/auth/session.php` — `require_coordinator()`, `require_member()` — nicht für ICS-Route verwenden

### Prior-Phase-Patterns (gelten weiterhin)
- `.planning/phases/01-foundation/01-CONTEXT.md` — CSRF-Pattern, PRG-Pattern, Session-Handling, RLS-Setup
- `.planning/phases/03-lists-columns-cells/03-CONTEXT.md` — Bootstrap-Karten-Design, Mobile-first-Tabellen, `render_coordinator_page()`
- `.planning/phases/05-email-notifications/05-CONTEXT.md` — Patterns aus der jüngsten Phase (Routing, PRG, helpers)

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_coordinator_page(string $title, string $active, callable $body)` — Wrapper für alle Koordinator-Seiten; `$active = 'lists'` bleibt gleich
- `render_member_page()` — analoger Wrapper für Mitglieder-Seiten
- Bootstrap `.nav-tabs` oder `.btn-group` — für Tab-Switcher "Kalender | Liste"
- `e()` — Pflicht für `$item['location']` und alle User-Daten-Ausgaben
- Bootstrap `badge` (bg-success/bg-warning/bg-secondary) — bereits für Sichtbarkeits-Badges in Verwendung; im Kalender-Eintrag wiederverwenden
- `bi bi-calendar3` Bootstrap-Icon — bereits für Datum auf Listen-Karten in Verwendung (lists.php)
- `bi bi-geo-alt` — für Ort-Anzeige konsistent mit bestehenden Icons

### Established Patterns
- **PRG-Pattern:** POST → Redirect → GET. Gilt auch für Listenbearbeitung mit neuem `location`-Feld
- **Idempotentes Schema:** `ADD COLUMN IF NOT EXISTS` statt Migration-Dateien (bestehende Konvention in schema.sql)
- **Sortierung nach Datum:** bereits in `lists_handler.php` via `usort()` mit `null`-Handling implementiert — für Kalender-Tab erweitern
- **Mobile-first:** Kalender-Timeline muss auf Smartphone-Breite funktionieren (kein Grid das zusammenbricht)
- **GET-Parameter für Filter:** bestehende Pattern in Stats-Seite (Listen-Filter via GET)
- **Kein AJAX:** Server-side Rendering für alle Interaktionen (Tab-Wechsel = Link/Form-Submit mit GET)

### Integration Points
- `public/index.php` — neue Route: `/ics/(\d+)\.ics` → `src/ics_handler.php` (ohne Auth-Guard)
- `database/schema.sql` — `ALTER TABLE lists ADD COLUMN IF NOT EXISTS location VARCHAR(255) NULL;`
- `src/coordinator/lists_handler.php` — Tab-Logik: `$view = $_GET['view'] ?? 'calendar'`, `$offset = (int)($_GET['offset'] ?? 0)`; Datierte/undatierte Einträge trennen
- `src/templates/coordinator/lists.php` — Tab-Switcher-HTML + Kalender-Timeline-HTML + "Ohne Datum"-Abschnitt
- `src/member/lists_handler.php` — analoger Tab-Logic-Block (nur public Listen)
- `src/templates/member/lists.php` — analoger Tab-Switcher + Kalender-Timeline
- `src/templates/coordinator/list_form.php` — `location`-Textfeld nach dem `date`-Feld einfügen
- `src/coordinator/list_create_handler.php` — `$location = trim($_POST['location'] ?? '')` + in INSERT/UPDATE
- `src/coordinator/list_settings_handler.php` (falls existent) — `location`-Feld im Update-Formular ergänzen

</code_context>

<specifics>
## Specific Ideas

- ICS-Link mit Hinweis "In Kalender-App abonnieren" über der Kalenderansicht — kleiner, nicht aufdringlicher Hinweis
- ICS-URL öffentlich ohne Token: `/ics/{team_id}.ics` — bewusste Entscheidung für Einfachheit (keine sensiblen Daten in ICS, da nur public+protected Listen)
- Navigation im Kalender: Vor/Zurück-Pfeile + aktueller Zeitraum-Label (z.B. "14.–20. Juli 2026") als Überschrift über den Einträgen
- Woche/Monat-Toggle: kleines Button-Pair ("Woche | Monat") neben oder unter den Navigations-Pfeilen

</specifics>

<deferred>
## Deferred Ideas

- Keine Deferred Ideas — Diskussion blieb im Phase-6-Scope

</deferred>

---

*Phase: 06-calendar-lists-with-date-location-ics-export*
*Context gathered: 2026-07-14*
