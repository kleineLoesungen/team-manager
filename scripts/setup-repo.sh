#!/usr/bin/env bash
# Richtet das Repo einmalig für die Issue-getriebene Entwicklung ein (SETUP.md):
# Secret für Claude, Labels, Environments staging/production, Ruleset für main.
# Voraussetzung: gh CLI, angemeldet mit Admin-Rechten auf dem Repo (gh auth login).
# Aufruf:  CLAUDE_TOKEN=sk-ant-oat01-... ./scripts/setup-repo.sh kleineLoesungen/team-manager
set -euo pipefail

REPO="${1:?Repo angeben, z. B. kleineLoesungen/meine-app}"
: "${CLAUDE_TOKEN:?CLAUDE_TOKEN setzen (Ausgabe von: claude setup-token)}"

echo "→ Secret CLAUDE_CODE_OAUTH_TOKEN"
gh secret set CLAUDE_CODE_OAUTH_TOKEN --repo "$REPO" --body "$CLAUDE_TOKEN"

echo "→ Labels"
gh label create claude    --repo "$REPO" --color 7057FF --description "Freigegeben: Claude setzt um" --force
gh label create vorschlag --repo "$REPO" --color 0E8A16 --description "Wöchentlicher Vorschlag von Claude" --force

echo "→ Environments"
gh api -X PUT "repos/$REPO/environments/staging" --silent
gh api -X PUT "repos/$REPO/environments/production" --silent \
  --input - <<'JSON'
{ "deployment_branch_policy": { "protected_branches": false, "custom_branch_policies": true } }
JSON
gh api -X POST "repos/$REPO/environments/production/deployment-branch-policies" \
  -f name=main -f type=branch --silent || true

echo "→ Ruleset für main (PR + alle drei Reviews Pflicht)"
gh api -X POST "repos/$REPO/rulesets" --silent --input - <<'JSON'
{
  "name": "main-schutz",
  "target": "branch",
  "enforcement": "active",
  "conditions": { "ref_name": { "include": ["~DEFAULT_BRANCH"], "exclude": [] } },
  "bypass_actors": [
    { "actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "pull_request" }
  ],
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    { "type": "pull_request", "parameters": {
        "required_approving_review_count": 0,
        "dismiss_stale_reviews_on_push": false,
        "require_code_owner_review": false,
        "require_last_push_approval": false,
        "required_review_thread_resolution": false } },
    { "type": "required_status_checks", "parameters": {
        "strict_required_status_checks_policy": false,
        "required_status_checks": [
          { "context": "review-architektur" },
          { "context": "review-sicherheit" },
          { "context": "review-dokumentation" } ] } }
  ]
}
JSON

echo
echo "Fertig. Noch von Hand (Settings → Environments), je für staging und production:"
echo "  Variables: FTP_SERVER, FTP_USERNAME, FTP_SERVER_DIR (mit / am Ende), APP_URL"
echo "  Secrets:   FTP_PASSWORD, DOT_ENV (komplette .env der Instanz, Vorlage .env.production.example)"
