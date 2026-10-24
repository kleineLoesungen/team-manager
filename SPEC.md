# Spezifikation: Team Manager

Quelle der Wahrheit für das Verhalten der App. Neue Features zuerst hier beschreiben, dann umsetzen
(CLAUDE.md). Einrichtung und Betrieb stehen in der README, Tabellen in CLAUDE.md („Key DB Tables“).

## Ziel

Mobile-first Webanwendung in deutscher Sprache für Sportvereine: Koordinatoren erfassen in Listen mit
frei definierbaren Spalten den Spielereinsatz und beliebige Kennzahlen, Mitglieder tragen ihre Daten
selbst ein, eine Statistik fasst alles pro Mitglied zusammen. Termine, Ressourcen, Live-Ticker und
Benachrichtigungen ergänzen das — schlicht, ohne App-Store, auf normalem Webhosting.

## Nutzer & Rollen

- **Admin** (genau einer, Zugang in den Einstellungen der Instanz, nicht in der DB): Abteilungen,
  Teams, Koordinatoren, Mitgliederprofile, Organisationen, Ressourcen, Profil-Attribute, Version.
- **Koordinator** (ein Konto, ein oder mehrere Teams): Listen, Spalten, Termine, Dokumente, Ticker,
  Mitglieder des Teams, Benachrichtigungen, Statistik.
- **Mitglied** (ein Konto je Team, alle Konten einer Person teilen ein Profil): eigene Einträge,
  Termine (wenn freigeschaltet), Statistik, Profil, Kalender-Abo, Push.
- **Gast** (kein Konto): öffentlicher Gastbereich mit Ticker, freigegebenen Terminen und Ressourcen.

Anrede in der Oberfläche durchgehend „du“. Anmeldung bleibt bei Koordinatoren und Mitgliedern
30 Tage gleitend bestehen, beim Admin 8 Stunden.

## Funktionen

### F1: Abteilungen, Teams, Organisationen
- Eine **Abteilung** (z. B. Fußball, Tennis) gruppiert Teams und Ressourcen; jedes Team und jede
  Ressource gehört zu genau einer. Optional ein Symbol (Emoji), das in den Kalender-Abos der Teams
  vor jedem Eintrag steht („⚽ U13 - Training“).
- Mitglieder und Organisationen (Vereine) gehören zu keiner Abteilung — dieselbe Person kann in
  Teams verschiedener Abteilungen sein. Teamwechsel ohne neue Anmeldung.
- **Profil-Attribute** in Gruppen; eine Gruppe gilt für alle Abteilungen oder nur für eine (z. B.
  „Fußball“: Position, „Tennis“: Schlagarm). Je Attribut: für Mitglieder sichtbar / bearbeitbar.

### F2: Listen
- **Mitgliederliste** (eine Zeile je Mitglied) oder **freie Liste** (eigene Zeilen).
- Spalten: Ja/Nein, Zahl, Text; global (Team, mit Standardwert je Liste) oder nur für diese Liste,
  optional „nur Koordinatoren“. Summen auf den Kacheln.
- **Sichtbarkeit**: Öffentlich (Mitglieder bearbeiten ihre Zeile), Geschützt (nur lesen),
  Privat (nur Koordinatoren). **Automatische Umstellung** X Std. vor Beginn (z. B. Anmeldeschluss);
  sie greift bei jedem Seitenaufruf sofort.
- **Serien** wöchentlich/monatlich bis zu einem Enddatum: jede Liste danach einzeln bearbeitbar.
- Optional Datum, Uhrzeit, Ort (mit Link in die Navi-App), Ressourcen, „verstecken“.

### F3: Statistik
- Pro Mitglied über alle Listen eines Zeitraums: Summen der Zahl-Spalten, Zählung der Ja-Werte.

### F4: Dokumente
- Markdown mit Vorschau, Sichtbarkeit wie Listen, teilen per Link.

### F5: Termine
- Titel, Datum, optional Uhrzeit, Ort, Symbol, Sichtbarkeit Mitglieder/nur Koordinatoren, Serien,
  Ressourcen.
- **Termine durch Mitglieder**: pro Team freischaltbar; Mitglieder legen einzelne Termine an (für das
  ganze Team sichtbar) und ändern nur ihre eigenen. Koordinatoren sehen, wer angelegt hat.

### F6: Ressourcen
- Vom Admin angelegt (Name, Abteilung). Koordinatoren belegen sie mit Listen und Terminen; Belegung
  ohne Uhrzeit = ganzer Tag, ohne Ende = 1 Stunde.
- Überschneidungen werden schon im Formular gemeldet, sind aber erlaubt.
- Auslastung je Abteilung: nächste 7 Tage, nächste 4 Wochen, dann in 2-Wochen-Schritten. Einträge,
  die das eigene Team nicht sehen darf, heißen „Belegt“.

### F7: Kalender-Abos (ICS)
- **Koordinator-Feed** je Team (Token): alle Listen und Termine inkl. privat; Teamname davor, bei
  Listen mit Kalender-Spalte die Zusagen dahinter („U13 - Training (13/14)“).
- **Persönlicher Feed** je Person (Token) über alle ihre Teams: Listen öffentlich/geschützt, Termine
  geschützt. Hat eine Mitgliederliste eine **Kalender-Spalte** (Ja/Nein, für Mitglieder sichtbar),
  erscheint sie nur bei „Ja“ (eingetragener Wert, sonst Standardwert). Link unter Inhalte → Monat
  und im Profil, selbst erneuerbar.
- **Ressourcen-Feed** je Ressource: Belegungen aller Teams, private als „Belegt“.
- Alle Feeds reichen 3 Monate zurück; ältere Einträge nur in der App.

### F8: Live-Ticker
- Koordinatoren (und freigegebene Mitglieder) posten Einträge mit farbigen Tags; öffentliche Seite
  ohne Anmeldung, Zuschauerzahl, teilen mit Link-Vorschau.
- **Abo je Gerät** (angemeldet oder Gast): Push bei der Startmeldung (erster Eintrag) und jedem
  weiteren Eintrag, außer dem eigenen.

### F9: Benachrichtigungen
- Von Hand durch Koordinatoren zu Listen und Dokumenten, **E-Mail oder Push**, Empfänger abwählbar;
  Push mit eigenem Titel und Text (max. 150 Zeichen), Vorschau, Hinweis auf installierte App.
  Empfänger nach Sichtbarkeit (privat → Koordinatoren). Kein Login und kein Passwort per E-Mail.
- Push geht an alle Geräte der Person, auch wenn sie gerade in einem anderen ihrer Teams angemeldet
  ist; ein Tipp wechselt bei Bedarf ins Team des Inhalts.
- **Automatisch**: Erinnerung an den Anmeldeschluss (Liste mit automatischer Umstellung) am Tag der
  Umstellung ab 8 Uhr, liegt sie davor, am Vortag ab 18 Uhr — an alle Mitglieder des Teams, genau
  einmal. **Kurzfristige Änderung** von Datum, Uhrzeit oder Ort in den nächsten 7 Tagen: beim
  Speichern „Per Push über die Änderung informieren“.
- Push ein/aus je Gerät im Profil; einmaliger Hinweis „Push einschalten“ auf der Startseite. Geräte
  zeigen „zuletzt aktiv“; nach 180 Tagen ohne Lebenszeichen werden sie entfernt.

### F10: Gastbereich
- „Als Gast weiter“ auf der Anmeldeseite; das Gerät merkt sich die Wahl. Reiter Ticker, Termine,
  Ressourcen, Anmelden.
- **Termine**: Listen und Termine mit „Für Gäste sichtbar“ (vom Koordinator, nur wirksam solange nicht
  privat) — nur Titel, Ort, Datum, Zeit. Kalender-Abo für alle, je Abteilung oder je Team.
- **Ressourcen**: nur vom Admin freigegebene; alle nicht-privaten Belegungen, Titel nur bei Einträgen
  „Für Gäste sichtbar“, sonst „Belegt“.
- Gäste sehen nie Personen, Listen- oder Dokumentinhalte.

### F11: App und Version
- Installierbar auf dem Startbildschirm (PWA), Dark Mode, offline nur Hinweisseite (keine Daten im
  Cache des Geräts).
- Admin sieht die installierte Version, „Update verfügbar“ mit Änderungen, fehlende Migrationen und
  den Status des Cronjobs.

## Nicht-Ziele

- Kein Login per E-Mail-Link, keine Selbstregistrierung — Zugänge vergeben Admin und Koordinatoren.
- Keine automatischen E-Mails; automatisch sind nur die Pushes aus F9.
- Keine Chat- oder Bezahlfunktion, keine nativen Apps, kein JS-Framework, kein Build-Schritt.
- Gäste haben kein Konto und sehen keine personenbezogenen Daten.

## Daten

Tabellen und wichtigste Spalten: CLAUDE.md („Key DB Tables“), Schema: `database/schema.sql`.
Listen-Inhalte als EAV (`columns` + `cells`), Zugriff über Row-Level Security in PostgreSQL.

## Technik

- PHP 8.3 ohne Framework, PostgreSQL mit Row-Level Security, Bootstrap 5.3 per CDN.
- Hosting: Hetzner Webhosting (FTP, kein SSH) oder Docker. Einstellungen aus `.env`.
- Zeitgesteuertes über einen stündlichen Cronjob (`POST /cron`), Migrationen nach jedem Deployment
  (`POST /migrate`).
- Externe Dienste: Push-Dienste der Browser (Web Push, VAPID), SMTP für E-Mail, GitHub für den
  Update-Hinweis.

## Offene Fragen

- Keine.
