# Phase 2: Team & Player Management — Context

**Gathered:** 2026-04-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Trainer-Bereich mit Spieler-Verwaltung: Spieler anlegen (TEAM-04) mit auto-generierten Zugangsdaten, Spieler deaktivieren/reaktivieren, und Passwort eines Spielers zurücksetzen (AUTH-03). Der Admin-Bereich bleibt unverändert. Spieler-Login-Ziel (Phase 2+) und Listen-Verwaltung sind Phase 3.

Requirements: TEAM-04, AUTH-03

Note: Spieler-Deaktivierung/-Reaktivierung ist explizit Teil dieser Phase, obwohl keine eigene Anforderungsnummer existiert — logisch zusammen mit Spieler anlegen, minimaler Mehraufwand, D-03 (Soft Delete) bereits implementiert.

</domain>

<decisions>
## Implementation Decisions

### Coach-Bereich Struktur
- **D-01:** Eigenes `/coach`-Layout analog zum `/admin`-Bereich. Separates Layout-Template `src/templates/coach/layout.php` mit eigener Navigation und Bootstrap 5. Kein Sharing mit dem allgemeinen `layout.php`.
- **D-02:** Nach dem Login landet ein Trainer bei `/coach/players`. `login_handler.php` erhält rollenbasierten Redirect: `role === 'coach'` → `/coach/players`, `role === 'player'` → `/player` (Phase 3+, Platzhalter-404 für jetzt).
- **D-03:** Navigation im Coach-Bereich zeigt in Phase 2 nur einen Punkt: **Spieler**. Listen und Statistik werden in Phase 3 bzw. 4 als weitere Nav-Einträge ergänzt — kein Platzhalter-Nav jetzt.
- **D-04:** `require_coach()` Middleware-Funktion analog zu `require_admin()`: prüft `$_SESSION['role'] === 'coach'`, setzt RLS-Kontext via `set_team_context($pdo, $_SESSION['team_id'])`. Redirectet bei Fehler nach `/login`.

### Spieler-Ansicht
- **D-05:** Spieler werden als **Bootstrap-Karten** dargestellt (nicht Tabelle). Jede Karte zeigt: Vor-/Nachname als Kartenüberschrift, Benutzername (`@mm4821`), Status-Badge, und Aktionsbuttons.
- **D-06:** Aktionen sind **inline in der Karte** — kein separates Spieler-Detail-Seite. Aktionsbuttons pro Karte: "Passwort zurücksetzen" und "Deaktivieren" (bzw. "Reaktivieren"). "Neuen Spieler anlegen"-Button als prominenter Button oben auf der Seite.
- **D-07:** Aktive und inaktive Spieler in **getrennten Gruppen**: Aktive Spieler oben (immer sichtbar), inaktive Spieler in einem einklappbaren Abschnitt unten (`<details>`/`<summary>` HTML-Element, kein JavaScript nötig). Analoges Muster zum Backlog-Vorschlag für Admin-Coach-Gruppierung (Phase 999.1).

### Zugangsdaten-Anzeige
- **D-08:** Credential-Modal-Pattern aus Phase 1 (`src/templates/admin/credential_modal.php`) wird für den Coach-Bereich **wiederverwendet**. Dieselbe 60s-Auto-Close-Logik, `Cache-Control: no-store`-Header. Nach Schließen: Redirect zurück zu `/coach/players`.
- **D-09:** POST-redirect-GET-Pattern für Fehler via `?error=`-Parameter, analog zum Admin-Bereich.

### Claude's Discretion
- Genaue HTML/Bootstrap-Klassen der Karten (card-body, card-footer, etc.)
- Routing-Struktur für Coach-Aktionen: z.B. `/coach/players/{id}/reset-password` und `/coach/players/{id}/deactivate`
- Implementierungsdetail für einklappbare Inaktiv-Gruppe: `<details>`-Tag bevorzugt (kein JS)
- CSRF-Token-Placement bei Inline-POST-Formularen in Karten

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen
- `.planning/REQUIREMENTS.md` — TEAM-04, AUTH-03 (Phase-2-Anforderungen)
- `.planning/PROJECT.md` — Stack-Constraints, Sprache, Mobile-first

### Phase-1-Entscheidungen (gelten weiterhin)
- `.planning/phases/01-foundation/01-CONTEXT.md` — D-11 (Benutzername-Format), D-12 (Vor-/Nachname-Felder), D-13 (Benutzername unveränderlich), D-03 (Soft Delete via is_active)

### Bestehende Patterns zum Wiederverwenden
- `src/utils/helpers.php` — `generate_unique_username()`, `generate_random_password()` bereits implementiert
- `src/templates/admin/credential_modal.php` — Credential-Display-Pattern, direkt wiederverwendbar
- `src/templates/admin/layout.php` + `src/admin/*_handler.php` — Strukturmuster für eigenes Layout + Handler-Files
- `src/auth/session.php` — `require_admin()` als Vorlage für `require_coach()`

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `generate_unique_username(PDO, string, string): string` — prüft Kollisionen, bis zu 10 Versuche
- `generate_random_password(int): string` — sichere Zeichenmenge, konfusierende Zeichen ausgeschlossen
- `credential_modal.php` — benötigt `$credential_username`, `$credential_password`, `$redirect_url`
- `render_admin_page(string $title, callable $body)` — Callable-Body-Pattern; analoges `render_coach_page()` bauen
- `set_team_context(PDO, int)` in `connection.php` — setzt `app.current_team_id` für RLS

### Integration Points
- `public/index.php` — Router erhält neue Routen: `/coach`, `/coach/players`, `/coach/players/create`, `/coach/players/{id}/reset-password`, `/coach/players/{id}/deactivate`
- `src/auth/login_handler.php` — Zeile mit `redirect('/dashboard')` → rollenbasierter Redirect (coach → `/coach/players`, player → `/player`)
- `src/auth/session.php` — neue `require_coach()` Funktion ergänzen

### Established Patterns
- Handler-Files in `src/admin/` als Vorlage: GET rendert Seite, POST verarbeitet Formular, dann PRG
- Credential-Modal wird direkt gerendert (kein Redirect nach POST bei Erfolg)
- `$_REQUEST['action']` + `$_REQUEST['{entity}_id']` für parametrisierte Action-Handler

</code_context>

<specifics>
## Specific Ideas

- Karten-Layout für Spieler gibt Platz für spätere Erweiterungen (Foto-Phase V2, Statistik-Vorschau Phase 4)
- `<details>`/`<summary>` für inaktive Spieler: kein JavaScript, kein Bootstrap Collapse nötig, State bleibt beim Page-Reload nicht erhalten (bewusst akzeptiert)
- Coach sieht nur die Spieler seines eigenen Teams — RLS + `set_team_context()` erzwingt das auf DB-Ebene

</specifics>

<deferred>
## Deferred Ideas

- Spieler-Profilbild-Upload: v2-Anforderung (EXT-V2-02), nicht Phase 2
- Spieler-Detail-Seite: kein Bedarf in Phase 2, kann in Phase 3 entstehen wenn Spieler ihr eigenes Profil sehen
- Spieler-Suchfunktion / Filter: erst relevant wenn Teams groß werden — Out of scope Phase 2

</deferred>

---

*Phase: 02-team-player-mgmt*
*Context gathered: 2026-04-29*
