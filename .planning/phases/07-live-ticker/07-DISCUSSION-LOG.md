# Phase 7: Live-Ticker — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-26
**Phase:** 07-live-ticker
**Areas discussed:** Auto-Reload-Technik, Nachrichten-Darstellung, Mitglieder-Freigabe

---

## Auto-Reload-Technik

| Option | Description | Selected |
|--------|-------------|----------|
| Vanilla-JS reload | `setTimeout(() => location.reload(), 5000)` — hält Scroll-Position, ermöglicht Hinweis-Text | ✓ |
| Meta-Refresh | `<meta http-equiv='refresh' content='5'>` — kein JS, aber Seite springt nach oben | |

**User's choice:** Vanilla-JS reload

---

| Option | Description | Selected |
|--------|-------------|----------|
| Stiller Hinweis | Statischer Text "Wird automatisch aktualisiert…" in grau | ✓ |
| Rückwärtszähler | Countdown von 5 auf 0, sichtbar aber potentiell ablenkend | |
| Kein Hinweis | Reload passiert still | |

**User's choice:** Stiller Hinweis

---

| Option | Description | Selected |
|--------|-------------|----------|
| Nur bei aktivem Ticker | Abgeschlossene Ticker laden sich nicht neu | ✓ |
| Immer aktiv | Reload läuft auch für abgeschlossene Ticker | |

**User's choice:** Nur bei aktivem Ticker

---

| Option | Description | Selected |
|--------|-------------|----------|
| Nur im Auth-Bereich posten | Öffentliche Ansicht ist reine Lese-Ansicht | ✓ |
| Post-Formular auch in öffentlicher Ansicht | Session-Check, falls eingeloggt zeige Formular | |

**User's choice:** Nur im Auth-Bereich posten

---

## Nachrichten-Darstellung

| Option | Description | Selected |
|--------|-------------|----------|
| Neueste oben | Wie ein Live-Feed — neue Nachrichten erscheinen direkt oben | ✓ |
| Chronologisch (alt oben) | Wie ein Chat/Protokoll — neueste Nachrichten unten | |

**User's choice:** Neueste oben

---

| Feld | Gewählt |
|------|---------|
| Timestamp (Uhrzeit) | ✓ |
| Autor | |
| Kategorie/Tag | ✓ |

**Notes:** Autor wird nicht angezeigt. Tags optional mit farbigem Bootstrap-Badge.

---

| Option | Description | Selected |
|--------|-------------|----------|
| Vordefinierte Liste (fest im Code) | Standard-Tags wie Tor/Pause/Info/Abpfiff | |
| Pro Ticker beim Erstellen | Koordinator legt Tags beim Anlegen fest | |
| Auf Spalten/Einstellungs-Seite | Team-weite Tag-Konfiguration auf bestehender Seite | ✓ |

**User's choice:** Auf Spalten/Einstellungs-Seite
**Notes:** User schlug vor, "Spalten" → "Einstellungen" umzubenennen und Ticker-Tags dort zu konfigurieren.

---

| Option | Description | Selected |
|--------|-------------|----------|
| Farbiges Bootstrap-Badge | Jedes Tag hat eine feste Farbe | ✓ |
| Plain-Text mit eckigen Klammern | [Tor] — kein Badge | |

**User's choice:** Farbiges Bootstrap-Badge

---

| Option | Description | Selected |
|--------|-------------|----------|
| Umbenennen zu "Einstellungen" | Nav-Link, Titel und URL ändern sich zu `/coordinator/settings` | ✓ |
| Name bleibt "Spalten" + neuer Abschnitt | Minimaler Änderungsaufwand | |

**User's choice:** Umbenennen zu "Einstellungen"

---

| Option | Description | Selected |
|--------|-------------|----------|
| Kurz — max. 280 Zeichen | Tweet-Länge, passt gut auf Mobile | ✓ |
| Mittel — max. 500 Zeichen | Etwas mehr Platz | |
| Kein Limit | TEXT-Feld | |

**User's choice:** Max. 280 Zeichen

---

## Mitglieder-Freigabe

| Option | Description | Selected |
|--------|-------------|----------|
| Checkboxen im Ticker-Edit | Freigabe beim Erstellen/Bearbeiten des Tickers | ✓ |
| Separate "Teilnehmer"-Verwaltung | Eigene Unterseite pro Ticker | |
| Alle Mitglieder immer erlaubt | Kein Freigabe-Mechanismus | |

**User's choice:** Checkboxen im Ticker-Edit

---

| Option | Description | Selected |
|--------|-------------|----------|
| Nur eigene Nachrichten bearbeiten/löschen | Row-Level-Ownership | |
| Nur posten, kein Edit | Mitglieder posten nur | |
| Koordinator entscheidet (Toggle) | Berechtigungs-Einstellung pro Ticker | |

**User's choice (free text):** "let it simple: every coordinator and selected member could create/edit/delete a message"
**Notes:** Keine Row-Level-Ownership. Koordinator + freigegebene Mitglieder haben volle CRUD-Rechte auf alle Nachrichten im Ticker.

---

| Option | Description | Selected |
|--------|-------------|----------|
| Im Mitglieder-Bereich (/member/ticker) | Eigene Ticker-Seite im Members-Bereich | ✓ |
| Nur im Coordinator-Bereich | Macht keinen Sinn für Members | |

**User's choice:** Im Mitglieder-Bereich

---

| Option | Description | Selected |
|--------|-------------|----------|
| Ticker-Feed + Post-Formular kombiniert | Mitglied sieht Feed + kann direkt posten | ✓ |
| Nur Post-Formular | Reine Post-Maske | |

**User's choice:** Ticker-Feed + Post-Formular kombiniert

---

## Claude's Discretion

- Coordinator-Einstiegspunkt (nicht besprochen): Neuer Nav-Eintrag "Ticker" in Sidebar + Mobile-Tab
- DB-Schema der Ticker-Tabellen
- URL-Schema für Ticker-Routen
- Öffentliche Übersichtsseite Layout
- Login-Seite-Link Position
- Tag-Farben Bootstrap-Klassen-Mapping

## Deferred Ideas

- Push-Notifications — eigene Phase
- WebSockets für Echtzeit — out of scope
- Reaktionen/Likes — eigene Phase
- Ticker-Suche/Filterung — backlog
