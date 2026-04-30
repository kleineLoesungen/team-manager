# Team Manager

## What This Is

Eine mobile-first Webanwendung in deutscher Sprache zur Verwaltung von Sportteams. Trainer können Spielerlisten mit frei definierbaren Spalten anlegen, Spieler tragen ihre eigenen Daten ein, und eine Statistikseite fasst globale Metriken pro Spieler über alle Listen zusammen. Ein einziger Admin verwaltet Teams und Trainer — alles andere regeln die Trainer selbst.

## Core Value

Trainer können den Spielereinsatz und beliebige Kennzahlen über alle Listen hinweg erfassen und in einer Statistik pro Spieler auf einen Blick auswerten.

## Requirements

### Validated

*Validated in Phase 1: Foundation (2026-04-29)*

- [x] Admin (einmalig in PHP-Config hinterlegt) kann Teams anlegen und Trainer zuweisen
- [x] Trainer erhalten automatisch generierte Zugangsdaten (Benutzername + Passwort, einmalig angezeigt)
- [x] Admin kann das Passwort von Trainern zurücksetzen (neues Passwort wird auf dem Bildschirm angezeigt)
- [x] Jeder Benutzer gehört genau einem Team an (Schema-Isolation via DB_SCHEMA, RLS)

*Validated in Phase 2: Team & Player Management (2026-04-29)*

- [x] Spieler erhalten automatisch generierte Zugangsdaten (Benutzername + Passwort)
- [x] Trainer können Spieler ihres Teams verwalten (anlegen, deaktivieren)
- [x] Trainer können das Passwort von Spielern zurücksetzen (neues Passwort wird auf dem Bildschirm angezeigt)

### Active (Phase 3 + 4 — Validated 2026-04-30)

*Validated in Phase 3: Lists, Columns & Cells (2026-04-30)*

- [x] Trainer können Listen anlegen (flexibler Zweck: Spiel, Training, etc.)
- [x] Jede Liste hat Zeilen (eine pro Spieler) und Spalten (global oder lokal)
- [x] Globale Spalten: auf Teamebene definiert, erscheinen in jeder Liste, Typ boolean oder Zahl
- [x] Lokale Spalten: pro Liste definiert, Typ boolean, Zahl oder Text, nicht statistisch ausgewertet
- [x] Listen-Status: public (Spieler lesen + eigene Zeile bearbeiten, Trainer alles), protected (Spieler nur lesen, Trainer bearbeiten), private (nur Trainer sichtbar + bearbeitbar)
- [x] Spieler können nur ihre eigene Zeile bearbeiten

*Validated in Phase 4: Statistics & Aggregation (2026-04-30)*

- [x] Statistikseite: pro Spieler Summe/Zählung aller globalen Spalten über alle Listen
- [x] Boolean-Globalspalten: Anzahl der true-Werte (z. B. 12 Spiele absolviert)
- [x] Zahlen-Globalspalten: Gesamtsumme (z. B. 15 Tore gesamt)

### Remaining

- [ ] Zugangsdaten sind nicht vom Benutzer selbst editierbar

### Out of Scope

- E-Mail-Infrastruktur — Passwortreset läuft offline (Anzeige auf dem Bildschirm)
- Mehrere Teams pro Benutzer — jeder gehört genau einem Team
- Mehrere Admins — ein einziger Admin in der PHP-Konfiguration
- Mobile App (iOS/Android) — Web-First, responsive
- Echtzeit-Kollaboration / WebSockets — klassisches Request/Response reicht

## Context

- Sprache der gesamten UI: Deutsch
- Stack: PHP (serverseitig), PostgreSQL (Datenbank), kein JS-Framework — modern einfaches CSS/HTML
- Mobile-first: die meisten Nutzer greifen per Smartphone zu
- Keine E-Mail-Adressen der Benutzer notwendig — Benutzername/Passwort genügt
- Admin-Credentials werden in einer PHP-Konfigurationsdatei hinterlegt (nicht in der DB)
- Spieler und Trainer wissen nichts voneinander außerhalb ihres eigenen Teams

## Constraints

- **Stack**: PHP + PostgreSQL — kein Framework-Wechsel; JS-Framework nur wenn unvermeidbar
- **Sprache**: Vollständig Deutsch in der UI
- **Mobile-first**: Alle Views primär für Smartphone-Bildschirme gestaltet
- **Keine E-Mail**: Kein SMTP-Setup, kein Mailversand
- **Einfachheit**: Modernes, schlichtes Design — keine Überladung mit Features

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| PHP + PostgreSQL | Explizite Technologievorgabe des Nutzers | — Pending |
| Einzelner Admin in Config | Kein eigenes Auth-System für Admin nötig | — Pending |
| Globale Spalten auf Teamebene | Wiederverwendung über alle Listen, konsistente Statistik | — Pending |
| Passwort-Reset nur on-screen | Keine E-Mail-Infrastruktur gewünscht | — Pending |
| Ein Team pro Benutzer | Vereinfacht Rechte- und Datenverwaltung erheblich | — Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition** (via `/gsd:transition`):
1. Requirements invalidated? → Move to Out of Scope with reason
2. Requirements validated? → Move to Validated with phase reference
3. New requirements emerged? → Add to Active
4. Decisions to log? → Add to Key Decisions
5. "What This Is" still accurate? → Update if drifted

**After each milestone** (via `/gsd:complete-milestone`):
1. Full review of all sections
2. Core Value check — still the right priority?
3. Audit Out of Scope — reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-04-29 after Phase 2: Team & Player Management complete*
