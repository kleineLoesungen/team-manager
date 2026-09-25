# UI-Baseline — Team Manager

Verbindlich für **alle** Rollen-Layouts (Admin, Koordinator, Mitglied, öffentlich)
und für jede zukünftige Frontend-Phase.

Diese Datei beschreibt, **was** gilt. Die Zahlen dazu stehen ausschließlich in
`public/css/app.css`. Eine Phasen-UI-SPEC darf diese Baseline verfeinern,
aber nicht überschreiben — Abweichungen erfordern eine Änderung dieser Datei.

---

## 1. Technischer Rahmen

- Bootstrap 5.3 per CDN, kein Build-Schritt, kein Sass.
- Genau **eine** eigene Stylesheet-Datei: `public/css/app.css`, geladen nach dem CDN-Link.
- Genau **ein** Layout: `render_page(array $opts, callable $body)`.
  Die Rollen-Layouts sind dünne Wrapper, die nur Navigationspunkte und Rollenkennzeichen liefern.
- Formulare funktionieren ohne JavaScript. Bootstrap-JS ist nur für Offcanvas/Collapse erlaubt,
  nie für Validierung, Bestätigung oder Feedback.
- Icons: Bootstrap Icons. Keine Emoji in der Oberfläche.

## 2. Wo Größen definiert werden

| Was | Wo |
|---|---|
| Schriftgrößen, Radien, Farben | `:root` in `app.css` |
| Innenabstände von Komponenten (Card, Button, List-Group, Alert, Tabelle) | Komponentenklasse in `app.css` (Bootstrap setzt diese Variablen auf der Klasse, nicht auf `:root`) |
| Seitenrand, Navigationshöhe, Touch-Mindestmaß | `--tm-*`-Tokens in `app.css` |
| Abstände **zwischen** Bausteinen | Utility-Klassen in den Partials, nur aus der erlaubten Teilmenge |

**Erlaubte Spacing-Utilities in Templates:**

- `*-2` = 0,5 rem — innerhalb einer Komponente
- `*-3` = 1 rem — zwischen Feldern und Cards
- `*-4` = 1,5 rem — zwischen Abschnitten

Alles andere ist unzulässig: keine `*-1`, `*-5`, keine `style=""`, keine `fs-*`-Klassen,
keine Pixelwerte im Template. Braucht eine Seite eine neue Größe, kommt sie als Token in `app.css`.

**Typo-Skala (mobile):** h1 1,5 rem · h2 1,25 rem · h3 1,125 rem · Fließtext 1 rem ·
`.small` 0,875 rem. Ein `h1` pro Seite, der Seitentitel.

**Formularfelder nie unter 1 rem Schriftgröße** — Safari auf iOS zoomt sonst beim Fokus hinein.
Deshalb kein `form-control-sm`, auch nicht in gruppierten Abschnitts-Cards.

## 3. Layout

- Header: Vereinslogo, Vereinsname, rechts Profil-/Abmelde-Zugang. Höhe aus `--tm-header-h`.
- Navigation: **Bottom-Nav, maximal fünf Punkte.** Identisch aufgebaut für alle Rollen,
  nur die Punkte unterscheiden sich. Der fünfte Punkt ist „Mehr", sobald eine Rolle mehr braucht.
  Die öffentliche Ticker-Seite nutzt dasselbe Layout ohne Navigation.
- Die Nav berücksichtigt `env(safe-area-inset-bottom)`.
- `<title>`: `{Seitentitel} · {Vereinsname}`, Sprache `lang="de"`.

## 4. Seitenarchetypen

Jede Seite gehört zu genau einem Archetyp. Neue Seiten ohne Zuordnung sind nicht zulässig.

1. **Sammlungsübersicht** — Titel, Untertitel mit Kennzahl, gruppierte Liste, Primäraktion sticky unten.
2. **Detailseite** — Titel, Statusanzeige, Inhalt, sekundäre Aktionen, Gefahrenzone am Ende.
3. **Formularseite** — Felder in Abschnitts-Cards, Aktionen vollbreit gestapelt.
4. **Matrixseite** — Statistik und Listendetail mit EAV-Spalten.
5. **Bestätigungsseite** — nur für destruktive Aktionen.
6. **Öffentliche Seite** — Ticker, ohne Navigation, ohne Anmeldung.

## 5. Komponentenregeln

**Seitenkopf.** Titel plus Untertitel mit Kennzahl („8 Listen, 3 kommende Termine").
Die Primäraktion steht nicht neben dem Titel, sondern in der klebenden Aktionsleiste
über der Bottom-Nav. Genau **eine** Primäraktion pro Seite.

**Sammlungen.** Gruppierte `list-group` mit Datums-Überschriften; Einträge ohne Termin
stehen in einer Gruppe „Ohne Termin" am Ende. Eine Zeile ist ein Link auf das Detail
mit Chevron rechts, keine konkurrierenden Aktionen in der Zeile. Cards sind für
Sammlungen nicht zulässig — sie bleiben Formularabschnitten und der Gefahrenzone vorbehalten.

**Matrix und Statistik.** Immer im Wrapper `.tm-matrix` (oder über `render_matrix_table()`),
nie in `.table-responsive`. Vollständige Tabelle, horizontal scrollbar, erste Spalte und
Kopfzeile fixiert, Summenzeile (`tfoot`) unten fixiert. Keine Spalte wird auf kleinen
Displays ausgeblendet — der Quervergleich über Mitglieder ist der Zweck der Seite.
Die Namensspalte ist auf 42vw begrenzt und kürzt mit „…“. Kopfzellen brechen um
(kein `text-nowrap` auf `<th>`), Zahlen stehen rechtsbündig in Tabellenziffern.
Auf dem Smartphone läuft die Tabelle randlos bis an den Bildschirmrand; sobald
horizontal gescrollt ist, zeigt die fixierte Spalte eine Schattenkante.

**Formularfelder.** Gruppiert in Abschnitts-Cards mit Abschnittsüberschrift.
Label immer sichtbar über dem Feld, Pflichtfelder mit `*`, Hilfetext unter dem Feld.
Boolesche Werte als `form-switch`.

**Fehler.** Roter Alert oben im Content, oberhalb des Formulars. Der Text nennt die Ursache
und den nächsten Schritt: „Der Listenname ist schon vergeben. Wähle einen anderen."
Keine Entschuldigungen, keine Fehlercodes.

**Erfolg.** Grüner Alert oben im Content, nicht schließbar, verschwindet mit dem nächsten
Seitenaufruf. Über `?success=` analog zum bestehenden `?error=` im Redirect nach POST.

**Status.** Subtle Badges (`bg-*-subtle` mit `text-*-emphasis` und passendem Rand).
Feste Zuordnung: grün = läuft/aktiv/eingetragen, grau = beendet/abgeschlossen,
gelb = Eintrag fehlt/wartet, rot = inaktiv/abgelehnt.

**Leerer Zustand.** Gestrichelter Rahmen mit Icon, einer Zeile Erklärung und der Primäraktion
als Button. Der Text sagt, warum der Schritt sinnvoll ist, nicht nur dass nichts da ist.

**Löschen.** Gefahrenzone-Card am Seitenende mit `btn-outline-danger`, danach eigene
Bestätigungsseite. Die Bestätigungsseite benennt die Folgen konkret
(„14 Einträge von 11 Mitgliedern gehen verloren") und bietet Abbrechen als Link.
Keine Modals, kein JavaScript-Confirm.

**Filter.** Pills als GET-Links, die aktive Auswahl ist als gefüllte Pill erkennbar.
Nach dem Filtern wird die Scroll-Position wiederhergestellt (bestehendes Muster).

**Buttons.** In Formularen vollbreit gestapelt, Primäraktion oben, Abbrechen darunter als Link.
`btn-sm` nur in Tabellenzeilen und Filterleisten, nie für die Primäraktion.
Mindesthöhe 44 px für alles Antippbare.

## 6. Sprache

- Du-Form durchgehend.
- Buttonbeschriftung: **Verb + Objekt** — „Liste speichern", „Spalte hinzufügen",
  „Mitglied einladen", „Ticker beenden". Nicht „Speichern", nicht „OK", nicht „Submit".
- Eine Aktion behält ihren Namen über den ganzen Ablauf: Der Button „Liste löschen"
  führt zur Seite „Liste löschen?" und meldet danach „Liste gelöscht."
- Datum `TT.MM.JJJJ`, in Listen kurz `Sa 14.09.`, Uhrzeit `HH:MM`.
- Ein Ding heißt in jeder Rolle gleich: Liste, Spalte, Eintrag, Mitglied, Koordinator, Team, Ticker.

## 7. Qualitätsboden

- Alles Antippbare mindestens 44 px hoch.
- Sichtbarer Fokusring, Kontrast mindestens AA.
- Jedes Eingabefeld hat ein verknüpftes `<label>`.
- `prefers-reduced-motion` wird respektiert.
- Erste Zielbreite ist 360 px. Breakpoints nur `md` und darüber.
- Querformat: Die App ist bis `--app-max` (960 px) breit, nicht auf Hochformatbreite gedeckelt.
  Seitenränder berücksichtigen `env(safe-area-inset-left/right)` (Notch). Bei geringer Höhe
  (≤ 500 px) scrollt die Kopfzeile mit und die Tab-Leiste wird einzeilig.

## 8. Was nicht entschieden ist

- Dark Mode: per Schalter in der Kopfzeile (`data-theme`), Farben nur über die Tokens
  `--surface`, `--surface-2`, `--line`, `--t1…3`. Keine festen Farbwerte wie `#fff`.
- Team-Logos färben das Theme nicht ein. Primärfarbe ist immer Anthrazit `#2f3640`.

## 9. Änderungen

Änderungen an dieser Datei sind eigene Commits,
nie beiläufig in einem Feature-Commit. Wer beim Umsetzen merkt, dass eine Regel nicht trägt,
meldet das, statt sie lokal zu umgehen.
