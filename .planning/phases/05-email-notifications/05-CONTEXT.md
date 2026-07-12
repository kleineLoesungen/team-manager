# Phase 5: Email Notifications — Context

**Gathered:** 2026-07-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Mitglieder können eine optionale E-Mail-Adresse in ihrem Profil hinterlegen. Koordinatoren können von einer Listen- oder Markdown-Datei-Seite aus eine Benachrichtigungsmail senden — mit individuellem Text und einem direkten Link zum Inhalt. Die Empfängergruppe richtet sich nach dem Sichtbarkeits-State: public/protected → alle Mitglieder mit E-Mail; private → nur Koordinatoren mit E-Mail. Eine Review-Seite zeigt vorher, wer die Mail nicht bekommt (fehlende E-Mail-Adresse), und eine Vorschau der Mail.

Zusätzlich: Admin kann eine informationsbasierte Nachricht an alle Koordinatoren senden (kein Listen-/Datei-Bezug). Koordinator-E-Mails werden nur vom Admin verwaltet.

</domain>

<decisions>
## Implementation Decisions

### Sende-Flow (Koordinator → Mitglieder)
- **D-01:** "Benachrichtigung senden"-Button erscheint direkt auf der **Listen-Detail-Seite** (`/coordinator/lists/{id}`) und der **Markdown-Datei-Seite** (`/coordinator/files/{id}`). Kontext (Link zum Inhalt, Titel) wird automatisch aus der aktuellen Seite befüllt. Kein separater "Senden"-Bereich im Menü.
- **D-02:** **Empfänger richten sich nach dem Sichtbarkeits-State der Liste/Datei:**
  - `public` oder `protected` → Mail geht an alle **Mitglieder** des Teams mit E-Mail-Adresse
  - `private` → Mail geht nur an **Koordinatoren** des Teams mit E-Mail-Adresse (Mitglieder haben keinen Zugriff auf den Inhalt)
  - Kein manuelles Abwählen einzelner Personen — der Sichtbarkeits-State bestimmt die Gruppe automatisch.
- **D-03:** **Review-Seite vor dem Versenden zeigt:**
  - Die **Empfängergruppe** (Mitglieder oder Koordinatoren — abhängig von D-02) mit Hinweis auf fehlende E-Mail-Adressen (wer nicht erreicht wird, namentlich aufgeführt)
  - **Vorschau der Mail** (Betreff, eigene Nachricht des Koordinators, Link zum Inhalt)
  - "Jetzt senden"-Button zum finalen Absenden
- **D-04:** Nach dem Senden: **PRG-Redirect zurück zur Ursprungsseite** (Listen-Detail oder Datei-Detail) mit grünem Erfolgs-Banner: "Benachrichtigung an X Mitglieder gesendet."

### Admin → Koordinatoren
- **D-05:** Admin-Panel erhält einen eigenen **"Koordinatoren benachrichtigen"**-Bereich. Admin kann eine freie Textnachricht (kein Listen-/Datei-Bezug) an alle Koordinatoren mit hinterlegter E-Mail-Adresse senden.
- **D-06:** **Koordinator-E-Mail** ist optional und wird ausschließlich vom Admin gesetzt/bearbeitet — im bestehenden Koordinator-Verwaltungsformular (Admin-Bereich). Koordinatoren selbst haben keinen Zugriff auf ihre E-Mail-Einstellung.

### Claude's Discretion
- **E-Mail-Infrastruktur:** PHP `mail()` oder PHPMailer+SMTP — Claude entscheidet basierend auf Hetzner Shared Hosting-Kompatibilität und Anforderungen. SMTP-Credentials ggf. in `config.php` als ENV-Variablen.
- **Mitglieder-Profil:** Neue Seite `/member/profile` (oder Inline auf Statistikseite) für Mitglieder, um E-Mail-Adresse zu hinterlegen. E-Mail ist optional und validiert (filter_var FILTER_VALIDATE_EMAIL).
- **E-Mail-Inhalt:** **Plain text, kein HTML, keine Formatierung.** Betreff-Schema (z. B. "[Teamname] Neue Nachricht von [Koordinator]"), Absender/Reply-To aus Config, kein Teamlogo im E-Mail-Body.
- **Szenario "keine Empfänger":** Wenn niemand in der Empfängergruppe eine E-Mail-Adresse hat, soll der "Benachrichtigung senden"-Button deaktiviert oder mit Hinweis versehen sein.
- **Fehlerbehandlung beim Senden:** Falls `mail()` fehlschlägt (false zurückgibt), Fehler-Banner auf der Review-Seite anzeigen.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen & Constraints
- `.planning/ROADMAP.md` — Phase 5 Goal (Email Notifications — Zieldefinition)
- `.planning/REQUIREMENTS.md` — Stack-Constraints, Out-of-Scope-Begründungen (E-Mail ursprünglich out of scope — Phase 5 überschreibt dies bewusst)
- `.planning/PROJECT.md` — Stack: PHP+PostgreSQL, kein JS-Framework, Mobile-first, Hetzner Shared Hosting

### Bestehende Codebase (für Integration)
- `database/schema.sql` — `users`-Tabelle (kein `email`-Feld vorhanden — ALTER TABLE nötig), `lists`-Tabelle (visibility-Feld), RLS-Schema
- `database/rls_policies.sql` — RLS-Patterns; neue Policies ggf. für E-Mail-Abfragen
- `config.php` — `BASE_URL` (für Link-Generierung in E-Mails), `APP_ENV` (Testmodus)
- `public/index.php` — Router (neue Routen für notify-Flow, member profile, admin notify)
- `src/auth/session.php` — `require_coordinator()`, `require_member()`, `require_admin()`
- `src/db/connection.php` — `set_team_context()`, `get_db()`
- `src/templates/coordinator/layout.php` — `render_coordinator_page()` (ggf. kein neuer Nav-Eintrag nötig)
- `src/templates/member/layout.php` — `render_member_page()` (Nav-Erweiterung um "Profil")
- `src/templates/admin/layout.php` — `render_admin_page()` (Nav-Erweiterung um "Koordinatoren benachrichtigen")
- `src/coordinator/list_detail_handler.php` — Ausgangspunkt für Notify-Button auf Liste
- `src/coordinator/file_detail_handler.php` — Ausgangspunkt für Notify-Button auf Datei
- `src/templates/coordinator/list_detail.php` — Template für Notify-Button
- `src/templates/coordinator/file_detail.php` — Template für Notify-Button
- `src/utils/helpers.php` — `e()`, `redirect()`, `require_coordinator()` etc.

### Sicherheits-Patterns (aus Phase 1)
- `.planning/phases/01-foundation/01-CONTEXT.md` — CSRF-Pattern, PRG-Pattern, Session-Handling

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_coordinator_page()` — Wrapper für alle Koordinator-Seiten; kein neuer Nav-Eintrag für Notify erforderlich (Button sitzt direkt auf bestehenden Seiten)
- `render_member_page()` — Nav-Erweiterung um "Profil" für Member-Profil-Seite
- `render_admin_page()` — Nav-Erweiterung um "Benachrichtigung" für Admin-Notify-Bereich
- `csrf_field()` / `require_csrf()` — Pflicht auf dem Review-Senden-POST-Formular
- `e()` — Pflicht für alle User-Daten-Ausgaben (E-Mail-Adressen, Vorschau-Text)
- Bootstrap `alert alert-success` — für Erfolgs-Banner nach dem Senden (konsistent mit bestehendem Pattern)

### Established Patterns
- **PRG-Pattern:** POST → Redirect → GET. Senden = POST auf Review-Seite → Redirect zurück zur Ursprungsseite mit `?success=1`
- **Zwei-Schritt-Confirm ohne JS:** Review-Seite = "Schritt 1: Überprüfen" → POST = "Schritt 2: Senden" (analog zu Gefahrenzone-Pattern bei Löschaktionen)
- **CSRF auf allen POST-Formularen:** Review-Seite hat ein `<form method="POST">` mit `csrf_field()`
- **Mobile-first:** Review-Seite und Profil-Seite sind primär für Smartphone gestaltet

### Integration Points
- `database/schema.sql` — `ALTER TABLE users ADD COLUMN email VARCHAR(255) NULL;` (oder idempotentes `IF NOT EXISTS`-Pattern beibehalten)
- `public/index.php` — neue Routen:
  - `GET /coordinator/lists/{id}/notify` — Review-Seite für Liste
  - `POST /coordinator/lists/{id}/notify` — Senden-Action für Liste
  - `GET /coordinator/files/{id}/notify` — Review-Seite für Datei
  - `POST /coordinator/files/{id}/notify` — Senden-Action für Datei
  - `GET /member/profile` — Mitglieder-Profil (E-Mail hinterlegen)
  - `POST /member/profile` — E-Mail speichern
  - `GET /admin/notify` — Admin Koordinatoren benachrichtigen (Form)
  - `POST /admin/notify` — Admin Koordinatoren benachrichtigen (Senden)
- `src/templates/coordinator/list_detail.php` — Notify-Button hinzufügen
- `src/templates/coordinator/file_detail.php` — Notify-Button hinzufügen (template muss erstellt/vorhanden sein)
- `src/templates/admin/coach_form.php` — E-Mail-Feld hinzufügen (Koordinator-Verwaltung)

</code_context>

<specifics>
## Specific Ideas

- Review-Seite zeigt die Empfängergruppe (Mitglieder oder Koordinatoren) basierend auf Sichtbarkeit, plus namentlich wer fehlt (keine E-Mail)
- Sichtbarkeits-Logik: private → nur Koordinatoren, public/protected → alle Mitglieder — kein manuelles Eingreifen nötig
- E-Mails sind **plain text**, keine HTML-Formatierung
- Koordinator-E-Mail nur im Admin-Bereich editierbar — Koordinator selbst sieht sie nicht im eigenen Bereich

</specifics>

<deferred>
## Deferred Ideas

- Keine Deferred Ideas — Diskussion blieb im Phase-5-Scope

</deferred>

---

*Phase: 05-email-notifications*
*Context gathered: 2026-07-12*
