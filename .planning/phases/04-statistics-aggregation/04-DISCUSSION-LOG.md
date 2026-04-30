# Phase 4: Statistics & Aggregation — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-04-30
**Phase:** 04-statistics-aggregation
**Areas discussed:** Statistik-Darstellung

---

## Statistik-Darstellung

| Option | Description | Selected |
|--------|-------------|----------|
| Tabelle: Spieler als Zeilen, Spalten als Spalten | Konsistent mit Phase-3-Listenansicht | ✓ |
| Karten: Eine pro Spieler mit Metriken | Besser für Mobile, schwerer zu vergleichen | |
| Kombination: Tabelle + Detailseite | Übersichtstabelle + Klick öffnet Spieler-Detailseite | |

**User's choice:** Tabelle (Spieler als Zeilen, globale Spalten als Spalten)

| Option | Description | Selected |
|--------|-------------|----------|
| Ja, /player/stats mit Tabelle (eine Zeile) | Spieler sieht eigene Werte in Tabellenform | ✓ |
| Nein, Statistik nur für Coach | Spieler hat keine Statistikseite | |

**User's choice:** Ja — Spieler hat /player/stats

| Option | Description | Selected |
|--------|-------------|----------|
| Tabelle mit einer Zeile | Gleiche Struktur wie Coach, nur eigene Zeile | ✓ |
| Karte mit Metriken-Liste | Einzige Karte mit Label-Wert-Paaren | |

**User's choice:** Tabelle mit einer Zeile (eigene Werte)

| Option | Description | Selected |
|--------|-------------|----------|
| Auf der Statistikseite (zwei Bereiche) | Rangliste unter Statistiktabelle, ein Nav-Eintrag | ✓ |
| Eigener Nav-Eintrag /coach/leaderboard | Rangliste hat eigene Seite | |

**User's choice:** Auf der Statistikseite (kein eigener Nav-Eintrag)

**Zusätzliche Entscheidung (freie Antwort):**
Spieler: nur Werte aus public und protected Listen in der Statistikberechnung berücksichtigen.
Coach: alle Listentypen (inklusive private) fließen in die Statistik ein.

---

## Claude's Discretion

- STAT-02 Filterung: Implementierungsdetails (Dropdown für Listen, Zeitraum-Definition)
- Rangliste: Spaltenauswahl-Mechanismus (GET-Parameter)
- Empty State: Spieler ohne Einträge
- Formatierung von Zahlen in der Tabelle

## Deferred Ideas

- Keine neuen Ideen aufgekommen — Diskussion blieb im Phase-Scope
