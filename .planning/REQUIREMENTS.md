# Requirements: Team Manager

**Definiert:** 2026-04-28
**Core Value:** Trainer erfassen den Spielereinsatz und Kennzahlen über alle Listen hinweg — Statistik pro Spieler auf einen Blick

## v1 Anforderungen

### Authentifizierung (AUTH)

- [x] **AUTH-01**: Benutzer kann sich mit Benutzername und Passwort anmelden
- [x] **AUTH-02**: Session läuft nach Inaktivität automatisch ab und der Benutzer wird zum Login weitergeleitet
- [x] **AUTH-03**: Trainer kann das Passwort eines Spielers zurücksetzen — neues zufälliges Passwort wird einmalig auf dem Bildschirm angezeigt
- [x] **AUTH-04**: Admin kann das Passwort eines Trainers zurücksetzen — neues zufälliges Passwort wird einmalig auf dem Bildschirm angezeigt

### Team-Verwaltung (TEAM)

- [x] **TEAM-01**: Admin kann ein neues Team mit Name anlegen
- [x] **TEAM-02**: Admin kann einem Team einen oder mehrere Trainer zuweisen
- [x] **TEAM-03**: Admin kann ein Team umbenennen oder deaktivieren
- [x] **TEAM-04**: Trainer kann einen neuen Spieler anlegen — Benutzername und zufälliges Passwort werden automatisch generiert und angezeigt

### Listen & Spalten (LIST)

- [ ] **LIST-01**: Trainer kann eine Liste mit Name anlegen (flexibler Zweck: Spiel, Training, etc.)
- [ ] **LIST-02**: Trainer kann globale Spalten auf Teamebene definieren (Typ: boolean oder Zahl) — können in jeder Liste des Teams verwendet werden
- [ ] **LIST-03**: Trainer kann lokale Spalten pro Liste definieren (Typ: boolean, Zahl oder Text) — nur in dieser Liste sichtbar, nicht statistisch ausgewertet
- [ ] **LIST-04**: Jede Liste hat genau einen von drei Sichtbarkeits-States: public, protected oder private
- [ ] **LIST-05**: Trainer kann den Sichtbarkeits-State einer bestehenden Liste ändern

### Zellen-Editing & Zugriffsregeln (CELL)

- [ ] **CELL-01**: Spieler kann Werte ausschließlich in seiner eigenen Zeile bearbeiten — und nur in public Listen
- [ ] **CELL-02**: Trainer kann alle Zeilen in public und protected Listen lesen und bearbeiten
- [ ] **CELL-03**: Private Listen sind für Spieler vollständig unsichtbar; Trainer haben vollen Lese- und Schreibzugriff
- [ ] **CELL-04**: Benutzer sieht alle Zeilen (alle Spieler) einer für ihn sichtbaren Liste, kann aber nur seine eigene Zeile bearbeiten (Spieler) bzw. alle Zeilen (Trainer)

### Statistik (STAT)

- [ ] **STAT-01**: Pro-Spieler-Statistikseite zeigt für alle globalen Spalten: Summe (Zahl-Spalten) oder Anzahl der true-Werte (boolean-Spalten) — aggregiert über alle Listen, in denen die jeweilige globale Spalte verwendet wird
- [ ] **STAT-02**: Statistik kann auf bestimmte Listen oder einen Zeitraum gefiltert werden
- [ ] **STAT-03**: Teamweite Rangliste sortiert Spieler nach dem Wert einer wählbaren globalen Spalte

## v2 Anforderungen

### Listen & Spalten

- **LIST-V2-01**: Trainer kann den Typ einer globalen Spalte nachträglich ändern (nur solange keine Daten eingetragen)
- **LIST-V2-02**: Pro-Liste-Übersicht: Anzahl der Spieler mit einem bestimmten Wert in einer Liste (z. B. wie viele haben gespielt)

### Erweiterte Funktionen

- **EXT-V2-01**: CSV-Export der Statistikseite
- **EXT-V2-02**: Spieler kann ein optionales Profil-Bild hochladen

## Out of Scope

| Feature | Begründung |
|---------|------------|
| E-Mail-Infrastruktur | Kein SMTP gewünscht; Passwort-Reset läuft offline (Anzeige auf Bildschirm) |
| Mehrere Teams pro Benutzer | Vereinfacht Rechte- und Datenverwaltung erheblich |
| Mehrere Admins | Ein einziger Admin in PHP-Konfigurationsdatei reicht |
| Mobile App (iOS/Android) | Web-First, responsive — kein natives App nötig |
| Echtzeit-Kollaboration | Klassisches Request/Response ausreichend für kleine Teams |
| Benachrichtigungen | Kein E-Mail, keine Push-Notifications in v1 |
| Saisons / Turniere | Erhöht Schema-Komplexität ohne klaren v1-Nutzen |
| Equipment-Tracking | Anderes Domain — nicht Teil des Kern-Produkts |
| Öffentliche Liga-Rankings | Nicht Teil des Anwendungsfalls |

## Traceability

| Anforderung | Phase | Status |
|-------------|-------|--------|
| AUTH-01 | 1 | Complete |
| AUTH-02 | 1 | Complete |
| AUTH-03 | 2 | Complete |
| AUTH-04 | 1 | Complete |
| TEAM-01 | 1 | Complete |
| TEAM-02 | 1 | Complete |
| TEAM-03 | 1 | Complete |
| TEAM-04 | 2 | Complete |
| LIST-01 | 3 | Pending |
| LIST-02 | 3 | Pending |
| LIST-03 | 3 | Pending |
| LIST-04 | 3 | Pending |
| LIST-05 | 3 | Pending |
| CELL-01 | 3 | Pending |
| CELL-02 | 3 | Pending |
| CELL-03 | 3 | Pending |
| CELL-04 | 3 | Pending |
| STAT-01 | 4 | Pending |
| STAT-02 | 4 | Pending |
| STAT-03 | 4 | Pending |

**Coverage:**
- v1 Anforderungen: 20 gesamt
- Auf Phasen gemappt: 20
- Nicht gemappt: 0 ✓

---
*Anforderungen definiert: 2026-04-28*
*Zuletzt aktualisiert: 2026-04-28 nach Roadmap-Erstellung*
