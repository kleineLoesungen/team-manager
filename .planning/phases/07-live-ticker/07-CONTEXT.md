# Phase 7: Live-Ticker — Öffentlicher Event-Ticker mit Kurznachrichten - Context

**Gathered:** 2026-07-26
**Status:** Ready for planning

<domain>
## Phase Boundary

Koordinatoren legen Ticker für Events (Turnier, Spiel, Veranstaltung) an. Koordinatoren und per Checkbox freigegebene Mitglieder können Kurznachrichten (max. 280 Zeichen) mit optionalem Tag/Kategorie posten, bearbeiten und löschen. Der Ticker ist ohne Login öffentlich lesbar und lädt sich alle 5 Sekunden per Vanilla-JS automatisch neu (nur bei aktivem Ticker). Aktive und abgeschlossene Ticker erscheinen auf der Login-Seite und auf einer öffentlichen Übersichtsseite. Nur Koordinatoren können Ticker erstellen, schließen und löschen.

Zusätzlich in Scope (user-entschieden): Die "Spalten"-Seite wird zu "Einstellungen" umbenannt; ein neuer Abschnitt "Ticker-Tags" erlaubt team-weite Tag-Konfiguration.

Nicht in Scope: Push-Notifications, Live-WebSockets, Reaktionen auf Nachrichten, Ticker-Suche.

</domain>

<decisions>
## Implementation Decisions

### Auto-Reload-Technik
- **D-01:** Auto-Reload via **Vanilla-JS** — `setTimeout(() => location.reload(), 5000)` auf der öffentlichen Ticker-Ansicht. Kein Meta-Refresh (würde Scroll-Position zurücksetzen).
- **D-02:** **Stiller Hinweis** — statischer Text "Wird automatisch aktualisiert…" in gedämpfter Farbe (`text-muted` o.ä.) unterhalb des Ticker-Headers. Kein Countdown-Zähler.
- **D-03:** Auto-Reload läuft **nur bei aktivem Ticker** — bei Status `closed` kein JS-Reload. Unterscheidung per Ticker-`status`-Feld aus der DB.
- **D-04:** Posten ist **ausschließlich im Auth-Bereich** möglich (Koordinator-Bereich + Member-Bereich). Die öffentliche Ansicht ist reine Lese-Ansicht — kein Post-Formular, auch wenn man eingeloggt ist.

### Nachrichten-Darstellung
- **D-05:** **Neueste oben** (reverse chronological) — neue Nachrichten erscheinen direkt oben, kein Scrollen nötig.
- **D-06:** Jede Nachricht zeigt: **Timestamp (Uhrzeit)** (automatisch gesetzt, editierbar) + optionaler **Tag/Kategorie als farbiges Bootstrap-Badge**. Kein Autor-Name.
- **D-07:** **Max. 280 Zeichen** pro Nachricht (VARCHAR(280) in DB + JS-Zeichenzähler im Formular).
- **D-08:** Tags sind **optional** — Nachricht ohne Tag erscheint ohne Badge.

### Tags / Kategorien
- **D-09:** Tag-Liste wird **team-weit auf der Einstellungs-Seite konfiguriert** — Koordinator legt eigene Tags mit Namen und Farbe an (z.B. "Tor" = grün, "Pause" = gelb, "Karte" = rot).
- **D-10:** Die bestehende **"Spalten"-Seite wird zu "Einstellungen" umbenannt**: Nav-Link, Seiten-Titel und URL ändern sich (`/coordinator/settings` statt `/coordinator/columns`). "Spalten" wird ein Abschnitt innerhalb der Einstellungs-Seite. Neuer Abschnitt "Ticker-Tags" kommt hinzu.

### Mitglieder-Freigabe (TICKER-03)
- **D-11:** Freigabe via **Checkboxen im Ticker-Edit-Formular** — beim Erstellen/Bearbeiten eines Tickers wählt der Koordinator aus der Mitgliederliste aus, wer posten darf. Freigabe ist pro Ticker, nicht global.
- **D-12:** Berechtigungen: **Koordinator + freigegebene Mitglieder** können alle Nachrichten **erstellen, bearbeiten und löschen** — kein Row-Level-Ownership. Kein "nur eigene Nachrichten"-Mechanismus.

### Mitglieder-Post-Bereich
- **D-13:** Freigegebene Mitglieder posten im **Mitglieder-Bereich** (`/member/ticker`). Eigene Ticker-Seite im Member-Bereich, neuer Nav-Eintrag.
- **D-14:** Mitglieder-Ticker-Seite: **Ticker-Feed + Post-Formular kombiniert** — Mitglied sieht den Live-Feed (analog zur öffentlichen Ansicht) und kann direkt darunter/darüber Nachrichten posten.

### Claude's Discretion
- Coordinator-Einstiegspunkt: Neuer Nav-Eintrag "Ticker" in Sidebar + Mobile-Tab (analog zu bestehenden Einträgen)
- DB-Schema: `tickers`-Tabelle (id, team_id, name, description, status, created_at) + `ticker_messages`-Tabelle (id, ticker_id, tag_id, message, timestamp, created_at) + `ticker_members`-Join-Tabelle (ticker_id, user_id) + `ticker_tags`-Tabelle (id, team_id, label, color, sort_order)
- URL-Schema: `/coordinator/ticker` (Liste), `/coordinator/ticker/{id}` (Detail + Nachrichten-Post), `/ticker` (öffentliche Übersicht), `/ticker/{id}` (öffentliche Ticker-Ansicht)
- Öffentliche Übersicht (`/ticker`): zeigt aktive Ticker zuerst, dann abgeschlossene — kein Login nötig
- Login-Seite-Link: Position und Design des Ticker-Links auf der Login-Seite
- Tag-Farben: Bootstrap-Klassen-Mapping im Code (z.B. `bg-success`, `bg-warning`, `bg-danger`)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen & Projektkontext
- `.planning/ROADMAP.md` — Phase 7 Goal (vollständige Ticker-Beschreibung) + Requirement-IDs TICKER-01 bis TICKER-06
- `.planning/REQUIREMENTS.md` — Stack-Constraints (PHP+PostgreSQL, kein JS-Framework), Out-of-Scope-Definitionen
- `.planning/PROJECT.md` — Stack: PHP+PostgreSQL, kein JS-Framework, Mobile-first, Hetzner Shared Hosting

### Bestehende Codebase (Integration-Punkte)
- `database/schema.sql` — Bestehende Tabellen-Struktur; neue `tickers`, `ticker_messages`, `ticker_members`, `ticker_tags`-Tabellen hier ergänzen (IF NOT EXISTS)
- `database/rls_policies.sql` — RLS-Patterns für bestehende Tabellen; neue Policies für Ticker-Tabellen analog ergänzen
- `public/index.php` — Router: neue Routen für `/coordinator/ticker*`, `/member/ticker*`, `/ticker*`, `/coordinator/settings` (ersetzt `/coordinator/columns`)
- `src/ics_handler.php` — **Modell für public endpoints**: kein Auth-Guard, `set_team_context($pdo, $team_id)` für team-isolierten DB-Zugriff ohne Session
- `src/coordinator/columns_handler.php` — Umbenennen zu `settings_handler.php`; bestehende Spalten-Logik bleibt, neue Ticker-Tags-Section kommt hinzu
- `src/templates/coordinator/layout.php` — Nav-Erweiterung: neuer "Ticker"-Eintrag + "Spalten" → "Einstellungen" umbenennen
- `src/templates/member/layout.php` — Nav-Erweiterung: neuer "Ticker"-Eintrag für Member
- `src/templates/login.php` — Ticker-Links (öffentliche Übersicht + aktive Ticker) einfügen
- `src/utils/helpers.php` — `e()`, `redirect()` — Pflicht für alle Ausgaben
- `src/auth/session.php` — `require_coordinator()`, `require_member()` — nicht für public Ticker-Routen verwenden

### Security & Pattern Referenzen (aus früheren Phasen)
- `.planning/phases/01-foundation/01-CONTEXT.md` — CSRF-Pattern, PRG-Pattern, Session-Handling, Zwei-Schritt-Confirm für destruktive Aktionen (Ticker löschen)
- `.planning/phases/06-calendar-lists-with-date-location-ics-export/06-CONTEXT.md` — Server-side Rendering ohne AJAX, GET-Parameter-Navigation, public ICS-Endpoint-Pattern

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_coordinator_page(string $title, string $active, callable $body)` — Wrapper für alle Koordinator-Seiten; `$active = 'ticker'` für neue Ticker-Seiten
- `render_member_page()` — analoger Wrapper für Mitglieder-Seiten; `$active = 'ticker'` hinzufügen
- `e()` — Pflicht für alle User-Daten-Ausgaben (Ticker-Name, Nachrichten-Text, Tag-Labels)
- Bootstrap `badge` (bg-success/bg-warning/bg-danger/bg-primary/bg-secondary) — bereits für Sichtbarkeits-Badges in Verwendung; für Ticker-Tags übernehmen
- `csrf_field()` / `require_csrf()` — Pflicht auf allen POST-Formularen (Ticker-Erstellen, Nachricht-Posten, Tag-Konfiguration)
- Bootstrap `alert alert-success` / `alert alert-danger` — Erfolgs- und Fehlermeldungen nach PRG-Redirect
- `mobile-tab-bar` + `mobile-tab-link` Pattern — in `coordinator/layout.php` und `member/layout.php` für neuen "Ticker"-Tab erweitern

### Established Patterns
- **PRG-Pattern:** POST → Redirect → GET. Gilt für Ticker erstellen/schließen, Nachricht posten/bearbeiten/löschen, Tag konfigurieren
- **Zwei-Schritt-Confirm (JS-frei):** Gefahrenzone-Card + Bestätigungs-Seite für Ticker löschen (analog zu bestehenden destruktiven Aktionen)
- **Idempotentes Schema:** `CREATE TABLE IF NOT EXISTS`, `ALTER TABLE ... ADD COLUMN IF NOT EXISTS`
- **Kein AJAX:** Server-side Rendering, alle Interaktionen via normalen Formular-Submits und GET-Requests
- **Public Endpoint Pattern** (`ics_handler.php`): kein `require_coordinator()`, `set_team_context()` mit team_id aus URL-Parameter, HTTP 404 bei ungültiger team_id
- **RLS via set_team_context():** Auch für public Ticker-Ansicht: `set_team_context($pdo, $team_id)` setzt `app.current_team_id`

### Integration Points
- `public/index.php` — neue Routen:
  - `/coordinator/ticker` GET — Ticker-Übersicht (Koordinator)
  - `/coordinator/ticker/new` GET/POST — Ticker erstellen
  - `/coordinator/ticker/{id}` GET/POST — Ticker-Detail + Nachrichten posten/verwalten
  - `/coordinator/ticker/{id}/close` POST — Ticker schließen
  - `/coordinator/ticker/{id}/delete` POST — Ticker löschen (Zwei-Schritt-Confirm)
  - `/coordinator/settings` GET — neue Einstellungs-Seite (ersetzt `/coordinator/columns`)
  - `/member/ticker` GET — Member-Ticker-Übersicht (nur freigegebene Ticker)
  - `/member/ticker/{id}` GET/POST — Ticker-Feed + Post-Formular (Member)
  - `/ticker` GET — öffentliche Ticker-Übersicht (kein Auth)
  - `/ticker/{id}` GET — öffentliche Ticker-Ansicht (kein Auth)
- `database/schema.sql` — neue Tabellen: `tickers`, `ticker_messages`, `ticker_members`, `ticker_tags`
- `src/coordinator/columns_handler.php` → `src/coordinator/settings_handler.php` umbenennen
- Layout-Dateien: Nav-Link "Spalten" → "Einstellungen", neuer "Ticker"-Eintrag

</code_context>

<specifics>
## Specific Ideas

- Auto-Reload nur bei aktivem Ticker: `<?php if ($ticker['status'] === 'active'): ?>` → `<script>setTimeout(() => location.reload(), 5000);</script>`
- Zeichenzähler im Post-Formular: kleines Vanilla-JS `oninput` Counter (z.B. "42/280") — minimales JS, kein Framework
- "Spalten"-Umbenennung zu "Einstellungen" — URL-Redirect von `/coordinator/columns` auf `/coordinator/settings` für Rückwärtskompatibilität falls noch irgendwo verlinkt
- Öffentliche Ticker-Übersichtsseite unter `/ticker` — zeigt aktive Ticker als prominente Karten oben, abgeschlossene darunter in gedämpfter Darstellung

</specifics>

<deferred>
## Deferred Ideas

- Push-Notifications bei neuen Nachrichten — eigene Phase
- WebSockets für Echtzeit-Updates — out of scope (kein JS-Framework, Shared Hosting)
- Reaktionen/Likes auf Nachrichten — eigene Phase
- Ticker-Suche oder -Filterung — backlog

</deferred>

---

*Phase: 07-live-ticker*
*Context gathered: 2026-07-26*
