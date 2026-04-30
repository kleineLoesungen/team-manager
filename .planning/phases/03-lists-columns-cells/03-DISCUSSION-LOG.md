# Phase 3: Lists, Columns & Cells — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-30
**Phase:** 03-lists-columns-cells
**Areas discussed:** Listen-Darstellung, Zellen-Bearbeitung, Spalten-Verwaltung, Spieler-Bereich

---

## Listen-Darstellung

| Option | Description | Selected |
|--------|-------------|----------|
| Karten wie Spieler-Übersicht | Bootstrap-Karten mit Name, Badge, Aktionsbuttons | ✓ |
| Kompakte Tabelle | Tabelle mit allen Listen, weniger visuell | |

**User's choice:** Karten wie Spieler-Übersicht

| Option | Description | Selected |
|--------|-------------|----------|
| Tabelle: Spieler als Zeilen, Spalten als Spalten | Standard-Spreadsheet-Ansicht | ✓ |
| Spieler-Karten mit Feldgruppen | Eine Karte pro Spieler | |

**User's choice:** Tabelle (Spreadsheet-Ansicht)

| Option | Description | Selected |
|--------|-------------|----------|
| Horizontal scrollbar | Bootstrap overflow-x: auto | ✓ |
| Fixierte erste Spalte | Spielername bleibt sichtbar | |

**User's choice:** Horizontal scrollbar

| Option | Description | Selected |
|--------|-------------|----------|
| Leere Tabelle mit allen Spielern | Keine Placeholder-Texte | ✓ |
| Hinweistext + Tabelle | Kleiner Hinweis + leere Tabelle | |

**User's choice:** Leere Tabelle mit allen Spielern

---

## Zellen-Bearbeitung

| Option | Description | Selected |
|--------|-------------|----------|
| Inline-Editing | Klick auf Zelle → Input-Feld | |
| Zeilen-Formular: Eigene Seite/Modal | Klick → separate Seite mit allen Feldern | ✓ |
| Zeilen-Submit | Alle Zellen einer Zeile gleichzeitig | |

**User's choice:** Eigene Bearbeitungsseite
**Notes:** User fragte, ob "immer eine PHP Datei erzeugt werden muss" — klargestellt, dass es nur eine Handler-Datei für alle Zeilen gibt.

| Option | Description | Selected |
|--------|-------------|----------|
| Jede Zeile ist ein Formular | <form> pro Zeile, Submit-Button | |
| Eigene Bearbeitungsseite | 1 Handler für alle Zeilen | ✓ |

**User's choice:** Eigene Bearbeitungsseite (nach Erläuterung)

| Option | Description | Selected |
|--------|-------------|----------|
| Bearbeiten-Button pro Zeile | Button am Ende jeder Zeile | ✓ |
| Klick auf gesamte Zeile | Gesamte Zeile ist klickbar | |

**User's choice:** Bearbeiten-Button pro Zeile

| Option | Description | Selected |
|--------|-------------|----------|
| Wie in Requirements definiert | CELL-01 bis CELL-04 | ✓ |
| Andere Regelung | Abweichung | |

**User's choice:** Wie in Requirements definiert

---

## Spalten-Verwaltung

| Option | Description | Selected |
|--------|-------------|----------|
| Eigene Teameinstellungs-Seite /coach/columns | Separater Nav-Eintrag | ✓ |
| Während Listen-Erstellung definieren | Eingebettet im Erstellungsformular | |

**User's choice:** Eigene Seite /coach/columns

| Option | Description | Selected |
|--------|-------------|----------|
| Auf der Listen-Detail-Seite direkt | Spalte hinzufügen-Button | ✓ |
| Separates Listen-Einstellungs-Formular | Eigene Einstellungsseite pro Liste | |

**User's choice:** Auf der Listen-Detail-Seite direkt

| Option | Description | Selected |
|--------|-------------|----------|
| Name + Sichtbarkeit | Nur Pflichtfelder | |
| Name + Sichtbarkeit + Spalten auswählen | Komplexes Erstellungsformular | ✓ |

**User's choice:** Name + Sichtbarkeit + Spalten direkt auswählen

---

## Spieler-Bereich

| Option | Description | Selected |
|--------|-------------|----------|
| Liste der public Listen | Karten-Übersicht | ✓ |
| Direkt in neueste Liste | Sofort-Weiterleitung | |

**User's choice:** Liste der public Listen als Karten

| Option | Description | Selected |
|--------|-------------|----------|
| Eigenes /player-Layout | Analog zu /coach | ✓ |
| Gleiches Layout wie Coach | Geteiltes layout.php | |

**User's choice:** Eigenes /player-Layout

| Option | Description | Selected |
|--------|-------------|----------|
| Alle Spieler-Zeilen, eigene editierbar | Bearbeiten-Button nur bei eigener Zeile | ✓ |
| Nur eigene Zeile sichtbar | Vereinfachte Ansicht | |

**User's choice:** Alle Zeilen sichtbar, eigene Zeile editierbar (per CELL-04)

---

## Claude's Discretion

- DB-Schema für EAV (`lists`, `columns`, `cells`-Tabellen)
- Validierung der Zellwerte nach `data_type`
- Erfolgs-Feedback nach Speicherung
- HTML-Element für Sichtbarkeits-State-Auswahl

## Deferred Ideas

- Optionale Listen-Metadaten (Datum/Zeit/Notizen) — Backlog 999.2
- Zeilen-Sichtbarkeitskontrolle — Backlog 999.2
- Spalten-Reihenfolge ändern — v2
