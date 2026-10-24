# Setup: Issue-getriebene Entwicklung mit Claude

## So läuft es

```
Issue ──(Label "claude" / "@claude" vom Owner)──▶ Claude setzt um ──▶ Branch claude/… + PR
                                                                      │
        ┌─────────────────────┬─────────────────────┬────────────────┤
        ▼                     ▼                     ▼                ▼
 review-architektur   review-sicherheit   review-dokumentation  Deploy → staging (+ /migrate)
        └─────────────── alle drei PASS ────────────┘
                                  ▼
                        Du mergst ──▶ Deploy → production (+ /migrate)

Montags: Claude prüft Neuerungen ──▶ Issue „Vorschläge KW xx“ (Label „vorschlag“)
         Umsetzen: „@claude setze Punkt 2 um“
```

| Datei | Aufgabe |
|---|---|
| `.github/workflows/claude.yml` | Setzt Issues um (Label `claude` oder `@claude` vom Owner) |
| `.github/workflows/review.yml` | Drei Review-Agenten je PR, BLOCK sperrt den Merge |
| `.github/workflows/vorschlaege.yml` | Wöchentliches Vorschlags-Issue |
| `.github/workflows/deploy.yml` | PR → staging, `main` → production (FTPS, nur geänderte Dateien), danach `POST /migrate` |
| `.github/ISSUE_TEMPLATE/` | Formulare für Features und Fehler |
| `CLAUDE.md` | Regeln für Claude (Spec zuerst, Rückfragen, Vertrauen, Doku, Konventionen) |
| `SPEC.md` | Spezifikation der App |
| `src/migrate.php` | Spielt fehlende `database/migrations/*.sql` ein (Header `X-Migrate-Token`) |
| `scripts/setup-repo.sh` | Secret, Labels, Environments und Ruleset per `gh` |

## Checkliste: einmalig einrichten

1. **Token für Claude** (auf deinem Rechner): `claude setup-token` → im Passwort-Manager ablegen (gilt ein Jahr).
2. **Claude-GitHub-App** installieren: github.com/apps/claude → dieses Repository (oder alle).
3. **Repo einrichten** — per Skript `CLAUDE_TOKEN=… ./scripts/setup-repo.sh kleineLoesungen/team-manager`
   oder von Hand:
   - Secret `CLAUDE_CODE_OAUTH_TOKEN` (Settings → Secrets and variables → Actions)
   - Labels `claude` und `vorschlag`
   - Environments `staging` und `production`; bei production „Deployment branches“ = nur `main`
   - Ruleset für `main`: Pull Request erforderlich, Status-Checks `review-architektur`,
     `review-sicherheit`, `review-dokumentation`, Force-Push und Löschen blockiert,
     Bypass „Repository admin“ nur für Pull Requests (Notausgang)
   - Settings → Actions → General: „Require approval for all outside collaborators“
4. **Staging im Webhosting** (Hetzner): FTP-Unterordner `public_html/team-manager-staging/`,
   Subdomain `staging.<domain>` zeigt darauf (ein Unterpfad wie `<domain>/staging` geht nicht —
   die App braucht ihre Adresse für sich: Links, Cookie, App-Installation). Datenbank: eigenes
   Schema (z. B. `DB_SCHEMA=team_manager_staging`) oder eigene Datenbank; die App legt das Schema
   beim ersten Aufruf an. Keine echten Nutzerdaten auf Staging.
5. **Environments befüllen** (Settings → Environments), je für staging und production:
   - Variables: `FTP_SERVER`, `FTP_USERNAME`, `FTP_SERVER_DIR` (mit `/` am Ende), `APP_URL` (`https://…`)
   - Secrets: `FTP_PASSWORD` und **`DOT_ENV`** = die komplette `.env` dieser Instanz, Vorlage
     `.env.production.example` (DB-Zugang, `DB_SCHEMA`, `ADMIN_PASSWORD_HASH`, `BASE_URL`,
     `CRON_TOKEN`, `MIGRATE_TOKEN`, ggf. `MIGRATE_DB_USER`/`MIGRATE_DB_PASS`, E-Mail).
     Der Deploy schreibt sie bei jedem Lauf als `.env` auf den Server — neue Einstellungen also
     nur hier ändern.
   - Je Environment einen eigenen FTP-Zugang, der nur auf sein Verzeichnis darf.
6. **Datenbank-Rechte**: `/migrate` braucht Eigentümer-Rechte am Schema — entweder ist `DB_USER`
   der Eigentümer, oder `MIGRATE_DB_USER`/`MIGRATE_DB_PASS` in `DOT_ENV` nennen ihn.
7. **Cronjob** je Instanz (z. B. Cronicle): stündlich `POST <APP_URL>/cron`, Header
   `X-Cron-Token` = `CRON_TOKEN` (README, „Cronjob“).

Ohne `FTP_SERVER` im Environment überspringt der Deploy das Hochladen — PRs laufen dann nur durch die Reviews.

## Im Alltag

- **Eigenes Issue**: anlegen + Label `claude` → Claude legt los.
- **Fremdes Issue**: passiert nichts, bis du das Label `claude` setzt.
- **Rückfrage von Claude**: im Issue antworten mit `@claude …`.
- **Review blockiert**: im PR `@claude bitte die Review-Befunde beheben`.
- **Changelog**: Claude schlägt den Eintrag in der PR-Beschreibung vor; `@claude Changelog übernehmen`
  trägt ihn ein. Ohne Eintrag mergen = kein Update-Hinweis für die Instanzen.
- **Mergen**: erst wenn alle drei Reviews PASS sind → Deploy nach production, Migrationen laufen mit.
- **Vorschläge**: montags Issue „Vorschläge KW xx“ → `@claude setze Punkt X um`.

## Datenbank

- Jede Schema-Änderung ist eine neue Datei `database/migrations/JJJJMMTT_thema.sql` (Vorlage und
  Regeln: README, „Datenbank-Änderungen“). Ausgerollte Dateien bleiben unverändert.
- Nach jedem Upload ruft der Deploy `APP_URL/migrate` auf; die Datenbank merkt sich ihren Stand in
  `settings.db_migration`. Schlägt eine Migration fehl, wird der Deploy rot, spätere laufen nicht.
- Migrationen abwärtskompatibel halten: kurz läuft neuer Code auf altem Schema.

## Sicherheit

- Claude merged nie selbst und bekommt keine Deploy-Secrets.
- Prod-Secrets liegen im Environment `production`, das nur `main` nutzen darf.
- Änderungen unter `.github/` blockiert der Sicherheits-Agent immer: selbst ansehen und über den Bypass mergen.
- PRs aus Forks bekommen keine Secrets — keine Reviews, kein Deploy. Claude im Issue um eine eigene Umsetzung bitten.
- Alle Claude-Läufe zählen auf die Nutzungslimits deiner Subscription.
