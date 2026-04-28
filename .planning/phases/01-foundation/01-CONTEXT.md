# Phase 1: Foundation - Context

**Gathered:** 2026-04-29
**Status:** Ready for planning

<domain>
## Phase Boundary

Sichere Authentifizierung für alle drei Rollen (Admin/Trainer/Spieler), Datenbank-Schema mit Team-Isolation und PostgreSQL Row-Level Security, sowie Admin-UI für Team- und Trainer-Verwaltung. Spieler- und Listen-Verwaltung ist Phase 2 bzw. Phase 3.

Requirements: AUTH-01, AUTH-02, AUTH-03, AUTH-04, TEAM-01, TEAM-02, TEAM-03

</domain>

<decisions>
## Implementation Decisions

### Nutzer-Datenbankmodell
- **D-01:** Eine `users`-Tabelle mit Spalten `id`, `team_id`, `role` (ENUM: 'coach'/'player'), `first_name`, `last_name`, `username`, `password_hash`, `is_active`, `created_at`. Kein separater Admin-Eintrag in der DB.
- **D-02:** Admin existiert ausschließlich in `config.php` — keine `users`-Zeile. Admin-Session bekommt eine eigene Session-Variable (`$_SESSION['is_admin'] = true`) mit `role = 'admin'` als Kennzeichner.
- **D-03:** Deaktivierte Benutzer werden per Soft Delete behandelt: `is_active = false`. Login wird blockiert, aber historische Listen-Daten bleiben erhalten.
- **D-04:** `password_hash` liegt direkt in der `users`-Tabelle. Kein separates Credentials-Modell.

### Session-Timeout
- **D-05:** Sliding Window — jede Anfrage verlängert die Session. Maximale Inaktivitätsdauer: 8 Stunden.
- **D-06:** Bei Ablauf: einfacher Redirect zur Login-Seite beim nächsten Request. Kein JavaScript-Modal oder Vorwarnung.
- **D-07:** Session-Konfiguration mit `cookie_secure`, `cookie_httponly`, `cookie_samesite=Strict`, `use_strict_mode=true`.

### Admin-UI-Struktur
- **D-08:** Separater `/admin`-Bereich mit eigenem URL-Präfix (z.B. `/admin/teams`, `/admin/coaches`). Eigenes Layout-Template. Zugriffsschutz: Jede Admin-Seite prüft `$_SESSION['is_admin']`.
- **D-09:** Admin-Startseite ist ein Dashboard — zeigt alle Teams mit zugewiesenen Trainern und Schnellaktionen (Team anlegen, Trainer zuweisen, umbenennen).
- **D-10:** Admin ist ein reiner Verwaltungsbenutzer: kein Zugriff auf Listen, Spieler-Daten oder Statistiken.

### Benutzername-Generierung
- **D-11:** Format: Initialen + 4-stellige Zufallszahl (z.B. `mm4821` für Max Müller). Eindeutigkeit wird bei der Generierung geprüft — bei Kollision neue Zahl würfeln.
- **D-12:** Trainer gibt beim Anlegen eines Spielers/Trainers Vorname und Nachname als separate Pflichtfelder ein. Anzeigename = `Vorname Nachname`.
- **D-13:** Benutzername ist nach der Generierung dauerhaft gesetzt — keine nachträgliche Änderung möglich.

### Claude's Discretion
- Genaues PostgreSQL RLS Policy-Design (application-level team_id-Filter als ergänzende Sicherheitsschicht)
- Genaues Schema für die `teams`-Tabelle (Name, is_active, created_at)
- Exact Routing-Struktur (index.php Dispatcher vs. separate Router-Klasse)
- CSRF-Token-Implementierungsdetail (Session-basiert vs. Double-Submit-Cookie)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Anforderungen
- `.planning/REQUIREMENTS.md` — AUTH-01 bis AUTH-04, TEAM-01 bis TEAM-03 (Phase-1-Anforderungen)
- `.planning/PROJECT.md` — Stack-Constraints, Sprache, Mobile-first-Vorgaben

### Architektur & Sicherheit
- `.planning/research/ARCHITECTURE.md` — EAV-Schema, Komponenten-Grenzen, Build-Order
- `.planning/research/PITFALLS.md` — Kritische Sicherheitsfallen: Session-Leakage, Credential-Display, RLS-Retrofit
- `.planning/research/STACK.md` — PHP 8.3+ session-Konfiguration, password_hash, PDO/PDO_PGSQL

### Kein externes Spec für Admin-Config-Format — Entscheidungen vollständig in diesem CONTEXT.md

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- Kein bestehender Code — greenfield Projekt

### Established Patterns
- Keine bestehenden Patterns — Phase 1 legt die Conventions fest

### Integration Points
- `config.php`: Admin-Credentials, DB-Verbindung, Session-Timeout-Wert
- `index.php` oder `router.php`: Zentraler Entry Point, RBAC-Prüfung vor Route-Dispatch
- `/admin/` Verzeichnis: Separates Layout-Template für Admin-Bereich

</code_context>

<specifics>
## Specific Ideas

- Initialen + Zufallszahl als Benutzername-Format (z.B. `mm4821`) — explizit vom Nutzer gewählt über Vor-/Nachname-basiert
- Admin-Dashboard mit Teamübersicht als Startseite — kein reines Listen-Interface
- 8h Session-Timeout mit Sliding Window — längere Session als üblich, weil Trainer während Spielen (60-90 Min) eingeloggt bleiben sollen

</specifics>

<deferred>
## Deferred Ideas

None — Diskussion blieb vollständig im Phase-1-Scope.

</deferred>

---

*Phase: 01-foundation*
*Context gathered: 2026-04-29*
