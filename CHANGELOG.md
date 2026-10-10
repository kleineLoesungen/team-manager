# Änderungen

## 2026.10.10.3
- [Koordinator, Mitglied] Erinnerung per Push am Tag des Anmeldeschlusses (automatische Umstellung)
- [Koordinator, Mitglied] Kurzfristige Änderungen an Listen und Terminen per Push melden
- [Koordinator, Mitglied] Einmaliger Hinweis „Push einschalten“ auf der Startseite
- [Koordinator, Mitglied] Geteilte Ticker-Links mit Vorschau
- [System] Einheitliche Begriffe in Code und Datenbank
- Migration: 20261010_push_reminders.sql
- Migration: 20261010_rename_member_columns.sql

## 2026.10.10.2
- [Koordinator] Benachrichtigung zu Listen und Dokumenten wahlweise per Push statt E-Mail, mit eigenem kurzem Text
- [Koordinator, Mitglied] Push-Benachrichtigungen im Profil ein- und ausschalten (je Gerät)
- [Mitglied] Kalender-Abo auch im Profil

## 2026.10.10
- [Koordinator, Mitglied] Persönlicher Kalender für Mitglieder über alle ihre Teams; Listen auf Wunsch nur bei „Ja“ in einer gewählten Spalte
- [Koordinator, Mitglied] Bestehende Listen mit genau einer Ja/Nein-Spalte erscheinen im persönlichen Kalender nur noch bei „Ja“
- [Koordinator] Koordinator-Kalender mit Teamnamen und Zusagen, z. B. „U13 - Training (13/14)“
- [Mitglied] Der bisherige Team-Kalender der Mitglieder entfällt – bitte den persönlichen Link neu abonnieren
- [Koordinator, Mitglied] Kalender-Abos zeigen Vergangenes nur noch 3 Monate zurück, ältere Einträge in der App
- [Admin] Abteilungen mit optionalem Symbol, das in den Kalender-Abos der Teams vor jedem Eintrag steht
- [Admin] Version: Migrationen in Reihenfolge mit Anleitung; Hinweis, wenn der Datenbank eine fehlt
- [Koordinator, Mitglied] Ticker teilen: Der Link kommt jetzt auch in WhatsApp an
- [System] Migrationen bleiben dauerhaft im Repository, die Datenbank merkt sich ihren Stand
- Migration: 20261010_personal_calendar.sql

## 2026.10.09.2
- [Admin] Version: Änderungen der installierten und der drei vorherigen Versionen

## 2026.10.09
- [Admin, Koordinator, Mitglied] Dark Mode: Kennzeichen, Kopfzeile, Spaltentyp-Kennzeichen, Hinweise und Dokumentvorschau besser lesbar

## 2026.10.07
- [Admin] Versionsangabe und Hinweis „Update verfügbar“ (Einstellungen → Version)
- [Koordinator, Mitglied] Ressourcen-Auslastung gruppiert: nächste 7 Tage, nächste 4 Wochen, weitere Einträge nachladen
- [Mitglied] Koordinatorenübersicht in der Team-Reihenfolge aus dem Admin
