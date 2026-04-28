# Phase 1: Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-29
**Phase:** 01-foundation
**Areas discussed:** Nutzer-Datenbankmodell, Session-Timeout, Admin-UI-Struktur, Benutzername-Format

---

## Nutzer-Datenbankmodell

| Option | Description | Selected |
|--------|-------------|----------|
| Eine users-Tabelle | id, team_id, role, username, password_hash — simpler, weniger JOINs | ✓ |
| Separate Tabellen | coaches und players als eigene Tabellen — klarere Trennung, aber UNION nötig | |

**User's choice:** Eine `users`-Tabelle mit Role-Spalte

| Option | Description | Selected |
|--------|-------------|----------|
| Nur in Config, kein DB-Eintrag | Admin lebt in config.php. Keine users-Zeile. | ✓ |
| Config + DB-Eintrag | Admin in config.php und als users-Eintrag mit role=admin | |

**User's choice:** Admin ausschließlich in config.php

| Option | Description | Selected |
|--------|-------------|----------|
| Soft Delete (is_active Flag) | Daten bleiben erhalten, Login blockiert | ✓ |
| Hard Delete | Eintrag wird gelöscht — historische Daten inkonsistent | |

**User's choice:** Soft Delete

| Option | Description | Selected |
|--------|-------------|----------|
| In users-Tabelle | password_hash direkt in users — Standard-Ansatz | ✓ |
| Separate credentials-Tabelle | Entkopplung, aber unnötige Komplexität | |

**User's choice:** password_hash in users-Tabelle

---

## Session-Timeout

| Option | Description | Selected |
|--------|-------------|----------|
| Sliding Window | Jede Anfrage verlängert die Session | ✓ |
| Harte Ablaufzeit | Session läuft nach X Stunden ab — egal ob aktiv | |

**User's choice:** Sliding Window

| Option | Description | Selected |
|--------|-------------|----------|
| 30 Minuten | Sicherheitsorientiert | |
| 2 Stunden | Passt für Spiellänge (60-90 Min) | |
| 8 Stunden | Ein ganzer Tag ohne Re-Login | ✓ |

**User's choice:** 8 Stunden — Trainer sollen den ganzen Tag eingeloggt bleiben können

| Option | Description | Selected |
|--------|-------------|----------|
| Redirect zur Login-Seite | Nächster Request leitet auf /login | ✓ |
| Modal-Warnung vor Ablauf | 60s Vorwarnung per JavaScript | |

**User's choice:** Redirect zur Login-Seite

---

## Admin-UI-Struktur

| Option | Description | Selected |
|--------|-------------|----------|
| Separater /admin-Bereich | Eigenes URL-Präfix, eigenes Layout | ✓ |
| Rollenbasierte Navigation | Gleiche URLs, Admin sieht zusätzliche Elemente | |

**User's choice:** Separater /admin-Bereich

| Option | Description | Selected |
|--------|-------------|----------|
| Dashboard mit Übersicht | Startseite zeigt alle Teams mit schnellen Aktionen | ✓ |
| Separate Seiten je Aktion | Keine Übersichtsseite — direkt Listen | |

**User's choice:** Dashboard mit Übersicht

| Option | Description | Selected |
|--------|-------------|----------|
| Nein, reiner Admin | Admin sieht nur Team/Trainer-Verwaltung | ✓ |
| Admin sieht alles | Super-User mit Zugriff auf alle Daten | |

**User's choice:** Reiner Admin — kein Zugriff auf Listen/Statistiken

---

## Benutzername-Format

| Option | Description | Selected |
|--------|-------------|----------|
| Vorname.Nachname | z.B. max.mueller — menschenlesbar | |
| Initialen + Zufallszahl | z.B. mm4821 — kein Kollisionsproblem | ✓ |
| Komplett zufällig | z.B. xk9p3t — maximal anonym | |

**User's choice:** Initialen + Zufallszahl (z.B. mm4821)

| Option | Description | Selected |
|--------|-------------|----------|
| Vorname + Nachname (getrennte Felder) | Separate Pflichtfelder beim Anlegen | ✓ |
| Nur Anzeigename | Ein Freitextfeld, Benutzername zufällig | |

**User's choice:** Vorname + Nachname als separate Pflichtfelder

| Option | Description | Selected |
|--------|-------------|----------|
| Nein, einmal gesetzt | Benutzername permanent nach Generierung | ✓ |
| Ja, Trainer kann ändern | Manuell überschreibbar | |

**User's choice:** Benutzername einmal gesetzt, nicht änderbar

---

## Claude's Discretion

- Genaues PostgreSQL RLS Policy-Design
- Schema-Details der `teams`-Tabelle
- Routing-Struktur (index.php vs. Router-Klasse)
- CSRF-Token-Implementierungsdetail

## Deferred Ideas

Keine — Diskussion blieb im Phase-1-Scope.
