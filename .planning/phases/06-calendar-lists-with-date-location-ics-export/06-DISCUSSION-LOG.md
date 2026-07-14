# Phase 6: Calendar — Lists with Date, Location & ICS Export — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-14
**Phase:** 06-calendar-lists-with-date-location-ics-export
**Areas discussed:** Kalenderansicht-Stil, Kalender-Einstiegspunkt, ICS-Zugriff, Ort-Feld

---

## Kalenderansicht-Stil

| Option | Description | Selected |
|--------|-------------|----------|
| Sortierte Listenansicht | Chronologisch nach Monat, reines PHP/HTML, kein JS | ✓ |
| Monats-Grid mit JS-Toggle | Echtes Kalender-Grid, Vanilla JS, aufwändig | |
| Hybrid: Liste Standard, Grid optional | Listenansicht Standard, optionaler PHP-Grid-Toggle | |

**User's choice:** Sortierte Listenansicht (Recommended)
**Notes:** User ergänzte: Woche/Monat-Switcher mit Vor/Zurück-Navigation via GET-Parameter; undatierte Einträge im eigenen Abschnitt darunter. "if weekly sorted, then add a switcher current week to next or before. also for months"

---

| Option | Description | Selected |
|--------|-------------|----------|
| Abschnitt "Ohne Datum" darunter | Datierte oben, undatierte im eigenen Abschnitt | ✓ |
| Nur datierte Listen zeigen | Undatierte komplett ausblenden | |
| Alles zusammen, sortiert | Undatierte am Ende ohne Trenner | |

**User's choice:** "add own section below dated content. but do not list all dated content: if weekly sorted..." (wöchentliche/monatliche Navigation + undatierter Abschnitt)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Woche (aktuelle Woche) | Standard = aktuelle Woche, Pfeile zum Navigieren | ✓ |
| Monat (aktueller Monat) | Standard = aktueller Monat | |

**User's choice:** Woche (aktuelle Woche) (Recommended)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Datum + Name + Sichtbarkeit + Ort | Kompakte Karte mit allen Schlüssel-Infos | ✓ |
| Wie bestehende Karten | Gleiche Karte wie auf /coordinator/lists | |

**User's choice:** Datum + Name + Sichtbarkeit + Ort (Recommended)

---

## Kalender-Einstiegspunkt

| Option | Description | Selected |
|--------|-------------|----------|
| Eigene Seite /coordinator/calendar | Neuer Nav-Eintrag, saubere Trennung | |
| Toggle oben auf der bestehenden Inhalte-Seite | Tab-Switcher auf /coordinator/lists, kein neuer Nav-Eintrag | ✓ |
| Kalender als Standard auf /coordinator/lists | Kalender = Standard-Ansicht, Listen separat | |

**User's choice:** Toggle oben auf der bestehenden Inhalte-Seite

---

| Option | Description | Selected |
|--------|-------------|----------|
| Kalender (Standard) | Landet direkt im Kalender (Wochenansicht) | ✓ |
| Liste (Standard) | Klassische Karten-Übersicht ist Standard | |

**User's choice:** Kalender (Recommended)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Ja, gleich: Kalender-Tab ist Standard | Mitglieder sehen auf /member/lists auch zuerst den Kalender-Tab | ✓ |
| Nein, Mitglieder sehen nur Listenansicht | Kalender-Tab nur im Koordinator-Bereich | |

**User's choice:** Ja, gleich (Recommended)

---

## ICS-Zugriff

| Option | Description | Selected |
|--------|-------------|----------|
| Geheimer Token in der URL | /ics/{token}.ics — kein Login, aber zufälliger Token | |
| Login erforderlich | Nur nach Login, schlecht für Kalender-Apps | |
| Öffentlich ohne Token | Jeder mit Team-ID kann ICS abrufen | ✓ |

**User's choice:** Öffentlich ohne Token
**Notes:** Bewusste Entscheidung für Einfachheit; keine sensiblen Daten (public+protected).

---

| Option | Description | Selected |
|--------|-------------|----------|
| Eine ICS pro Team (alle Rollen) | Nur public Listen einbezogen | ✓ |
| Zwei ICS: Koordinator + Mitglied | Coordinator-ICS enthält auch private Listen | |

**User's choice:** Eine ICS pro Team (Recommended)
**Notes:** ICS enthält letztendlich public + protected (nicht private) — Entscheidung gefallen in nächster Frage.

---

| Option | Description | Selected |
|--------|-------------|----------|
| On-demand generiert | PHP generiert dynamisch, immer aktuell | ✓ |
| Gecachte Datei | Komplexer, Invalidierung an vielen Touchpoints | |

**User's choice:** On-demand (mit Rückfrage ob Caching wirklich nötig — verneint für 10 Teams × 15 Mitglieder)
**Notes:** User sagte: "show ics link above calendar view with hint"

---

| Option | Description | Selected |
|--------|-------------|----------|
| Nur public Listen | Datenschutz-konform für öffentliche URL | |
| Public + protected Listen | Mehr Events im Kalender | ✓ |

**User's choice:** Public + protected Listen mit Datum

---

## Ort-Feld

| Option | Description | Selected |
|--------|-------------|----------|
| Freitext, ein Feld | VARCHAR(255) NULL, optional, einfach | ✓ |
| Zwei Felder: Name + Adresse | Strukturierter, mehr Formular-Overhead | |

**User's choice:** Freitext, ein Feld (Recommended)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Formular + Karte + Kalender + Mitglieder-Ansicht | Ort überall sichtbar | ✓ |
| Nur im Koordinator-Bereich | Mitglieder sehen Ort nicht | |

**User's choice:** Überall (Recommended)

---

| Option | Description | Selected |
|--------|-------------|----------|
| Ja — ICS LOCATION-Feld | Kalender-Apps zeigen Ort direkt am Termin | ✓ |
| Nein, nur für die App | Ort nur in der Web-App | |

**User's choice:** Ja (Recommended)

---

## Claude's Discretion

- Tab-Switcher-Design (Bootstrap `.nav-tabs` vs `.btn-group`)
- Wochentags-Gruppierung HTML-Struktur
- ICS VEVENT-Format (DTSTART als ganztägiges DATE)
- Idempotente Schema-Migration für `location`-Spalte

## Deferred Ideas

Keine — Diskussion blieb im Phase-6-Scope.
