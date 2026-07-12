# Phase 5: Email Notifications — Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-07-12
**Phase:** 05-email-notifications
**Areas discussed:** Sende-Flow

---

## Sende-Flow

### Frage 1: Auslöser der Benachrichtigung

| Option | Beschreibung | Ausgewählt |
|--------|-------------|----------|
| Button auf der Listen-/Datei-Seite | Direkt auf der Detailseite, Kontext automatisch befüllt | ✓ |
| Separater 'Senden'-Bereich im Menü | Eigene Seite mit Listen-/Datei-Auswahl | |

**User's choice:** Button auf der Listen-/Datei-Seite
**Notes:** —

---

### Frage 2: Empfänger-Auswahl

| Option | Beschreibung | Ausgewählt |
|--------|-------------|----------|
| Immer an alle mit E-Mail | Keine Auswahl, Review zeigt nur wen ohne E-Mail | ✓ |
| Koordinator kann einzelne abwählen | Checkboxen auf der Review-Seite | |

**User's choice:** Immer an alle mit E-Mail
**Notes:** —

---

### Frage 3: Review-Seite Inhalt

| Option | Beschreibung | Ausgewählt |
|--------|-------------|----------|
| Empfänger + ohne-E-Mail-Warnung + Nachrichtenvorschau | Vollständige Übersicht | |
| Nur Zusammenfassung: X von Y Mitglieder | Kompakter | |
| Free text (Nutzer) | Review zeigt nur Mitglieder ohne E-Mail + Mail-Vorschau; Sichtbarkeit berücksichtigen | ✓ |

**User's choice:** Nur Mitglieder ohne E-Mail anzeigen (nicht die Empfänger); Vorschau der Mail; Sichtbarkeits-Warnung wenn Inhalt private/coordinator-only
**Notes:** Koordinator soll sehen "wen verpasse ich", nicht die vollständige Empfängerliste. Zugriffslevel beachten: private Liste → Mitglieder können Link nicht öffnen.

---

### Frage 4: Nach dem Senden

| Option | Beschreibung | Ausgewählt |
|--------|-------------|----------|
| Redirect zur Ursprungsseite mit Erfolgsmeldung | PRG-Pattern, grüner Banner | ✓ |
| Eigene Bestätigungsseite | Dedizierte "Gesendet!"-Seite | |

**User's choice:** Redirect zur Ursprungsseite mit Erfolgsmeldung
**Notes:** —

---

### Follow-up (User-initiiert)

**User's addition (free text):** "add a notify all coordinators to admin panel. this not be related to a list or file. just for information of all coordinators. coordinator mail are also optional and will be edited by admin only (not coordinators)"

**Captured as:**
- D-05: Admin-Panel → "Koordinatoren benachrichtigen" — freie Textnachricht, kein Listen-/Datei-Bezug
- D-06: Koordinator-E-Mail optional, nur vom Admin editierbar (Koordinator-Verwaltungsformular im Admin)

---

## Claude's Discretion

- E-Mail-Infrastruktur (PHP mail() vs. PHPMailer)
- Mitglieder-Profil-Seite Design und Platzierung des E-Mail-Feldes
- E-Mail-Format (plain text vs. HTML, Betreff-Schema)
- E-Mail-Feld-Platzierung im Admin-Koordinator-Formular
- Fehlerbehandlung bei fehlgeschlagenem Versand
- Verhalten bei privaten Listen (Block vs. Warnung)

## Deferred Ideas

Keine — Diskussion blieb im Phase-5-Scope.
