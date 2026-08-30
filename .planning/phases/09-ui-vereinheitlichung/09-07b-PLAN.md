---
phase: 09-ui-vereinheitlichung
plan: 07b
type: execute
wave: 3
depends_on: [09-02]
files_modified:
  - src/templates/admin/members.php
  - src/templates/admin/member_form.php
  - src/templates/admin/member_edit.php
  - src/templates/admin/clubs.php
  - src/templates/admin/club_form.php
  - src/templates/admin/club_edit.php
  - src/templates/admin/attributes.php
  - src/templates/admin/notify_coordinators.php
autonomous: true
requirements: []

must_haves:
  truths:
    - "All 8 admin templates in this plan have zero inline style= violations"
    - "All 8 admin templates have zero py-5, mb-5 violations"
    - "credential_modal.php is NOT modified (excluded per Pitfall 3)"
    - "Club list uses render_empty and render_collection_group"
    - "All form inputs use form-control without -sm suffix"
  artifacts:
    - path: "src/templates/admin/clubs.php"
      provides: "Club list with render_empty, render_collection_group, render_badge"
    - path: "src/templates/admin/attributes.php"
      provides: "EAV attribute group management, no inline styles, no form-control-sm"
    - path: "src/templates/admin/members.php"
      provides: "Admin member list with render_badge and render_empty"
  key_links:
    - from: "src/templates/admin/clubs.php"
      to: "render_collection_group()"
      via: "Active/inactive club grouping"
      pattern: "render_collection_group"
    - from: "src/templates/admin/member_edit.php"
      to: "render_danger_zone()"
      via: "Deactivation danger zone if present"
      pattern: "render_danger_zone"
---

<objective>
Migrate the remaining 8 admin role templates (member/club/attribute/notification management). credential_modal.php is explicitly excluded.

Purpose: Second of two admin plans (split from the original 09-07 for context budget). Covers member management (members, member_form, member_edit), club management (clubs, club_form, club_edit), attribute EAV management (attributes), and coordinator notifications (notify_coordinators).

Output: 8 admin templates migrated, zero violations, clubs and members use render_empty / render_collection_group partials.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/phases/09-ui-vereinheitlichung/09-UI-SPEC.md
@.planning/phases/09-ui-vereinheitlichung/09-RESEARCH.md
@.planning/UI-BASELINE.md
@.planning/phases/09-ui-vereinheitlichung/09-07-SUMMARY.md
</context>

<interfaces>
<!-- CRITICAL EXCLUSION: Do NOT touch src/templates/admin/credential_modal.php -->
<!-- Reason (Pitfall 3): credential_modal sends Cache-Control: no-store header before HTML. -->
<!-- It is rendered by handlers via include, not via a layout function. Do not migrate. -->

<!-- Partials available everywhere — no require_once needed: -->
render_flash(string $type, string $message): void
render_empty(string $icon, string $heading, string $body_text, ?string $action_html): void
render_badge(string $type, string $label): void
render_collection_group(string $label, callable $items_body): void
render_form_section(string $heading, callable $fields_body, ?string $footer_html): void
render_form_field(string $label, string $input_html, ?string $hint): void
render_danger_zone(string $action_label, string $description, string $form_html): void
render_action_bar(string $label, string $form_id): void
render_page_header(string $title, ?string $back_url, ?string $action_html): void
render_filter_pills(array $pills, string $base_url): void
</interfaces>

<tasks>

<task type="auto">
  <name>Task 1: Migrate members.php, member_form.php, member_edit.php, clubs.php, club_form.php, club_edit.php, attributes.php, notify_coordinators.php</name>
  <files>
    src/templates/admin/members.php,
    src/templates/admin/member_form.php,
    src/templates/admin/member_edit.php,
    src/templates/admin/clubs.php,
    src/templates/admin/club_form.php,
    src/templates/admin/club_edit.php,
    src/templates/admin/attributes.php,
    src/templates/admin/notify_coordinators.php
  </files>

  <read_first>
  - src/templates/admin/members.php — read ENTIRE file
  - src/templates/admin/member_form.php — read ENTIRE file
  - src/templates/admin/member_edit.php — read ENTIRE file
  - src/templates/admin/clubs.php — read ENTIRE file
  - src/templates/admin/club_form.php — read ENTIRE file
  - src/templates/admin/club_edit.php — read ENTIRE file
  - src/templates/admin/attributes.php — read ENTIRE file
  - src/templates/admin/notify_coordinators.php — read ENTIRE file
  - src/templates/components/partials.php — review signatures
  </read_first>

  <action>
  Apply the standard violation checklist to all 8 files:
  - Remove all `style="..."` attributes (except img onerror)
  - Replace py-5 empty states → render_empty()
  - Replace mb-5 → mb-4; remove *-0, *-1 utilities
  - Remove shadow-sm from cards
  - Replace form-control-sm → form-control (remove -sm)
  - Replace badge inline patterns → render_badge()
  - Add ?success=1 flash check at top of body

  **members.php (admin) specific changes:**
  - Admin member list view (may have filter for club/team).
  - Status badges (active/inactive): render_badge('ok'/'dim', ...).
  - Empty state: render_empty('person-vcard', 'Noch keine Mitglieder', '...').
  - If filter bar uses pills, use render_filter_pills(). If it's a form dropdown, use form-select without -sm.

  **member_form.php (admin) specific changes:**
  - Admin form for creating a new member (player). May have club selector, team selector.
  - All inputs: form-control, form-select without -sm.
  - Remove shadow-sm from section cards.

  **member_edit.php (admin) specific changes:**
  - Admin form for editing an existing member.
  - All inputs: form-control without -sm.
  - May have danger zone for deactivation → render_danger_zone().

  **clubs.php (admin) specific changes:**
  - Club list view.
  - Status badges: render_badge('ok', 'Aktiv'), render_badge('dim', 'Inaktiv').
  - Empty state: render_empty('building', 'Noch keine Klubs', 'Erstelle den ersten Klub.').
  - Grouped (active/inactive): render_collection_group().

  **club_form.php and club_edit.php specific changes:**
  - Club creation and edit forms.
  - Name input: form-control without -sm.
  - Standard checklist.

  **attributes.php specific changes:**
  - Admin EAV attribute group management (player_attribute_groups + player_attributes).
  - May be a complex page with multiple sections (groups with their attributes nested).
  - For each attribute group shown in a card, remove shadow-sm. Can use render_form_section().
  - Attribute type badges (text/number/boolean): render_badge('dim', 'Text') etc.
  - Switch inputs for visible_to_player/editable_by_player flags: remove inline style.
  - Empty state if no attribute groups: render_empty('list-task', 'Noch keine Attributgruppen', '...').

  **notify_coordinators.php specific changes:**
  - Admin form to notify all coordinators by email.
  - Subject input: form-control without -sm.
  - Message textarea: form-control without -sm.
  - Standard checklist.
  </action>

  <verify>
    <automated>
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/members.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/member_form.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/member_edit.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/clubs.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/club_form.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/club_edit.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/attributes.php && \
    php -l /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/notify_coordinators.php
    # All must exit 0

    for f in members member_form member_edit clubs club_form club_edit attributes notify_coordinators; do
      count=$(grep -c 'style="' /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/${f}.php 2>/dev/null || echo 0)
      onerror=$(grep -c 'onerror' /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/${f}.php 2>/dev/null || echo 0)
      echo "admin/$f: $((count - onerror)) violations"
    done
    # All must show: 0 violations

    grep -c "render_empty\|render_collection_group" /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/clubs.php
    # Must output: >= 1
    </automated>
  </verify>

  <acceptance_criteria>
  - All 8 files pass `php -l`
  - Zero `style="` violations (excluding onerror) in all 8 files
  - Zero `py-5`, `mb-5`, `shadow-sm` in all 8 files
  - clubs.php contains `render_empty(` and `render_badge(`
  - attributes.php has no `style="` on switch inputs
  - notify_coordinators.php has no `form-control-sm` on subject/message inputs
  - member_edit.php has `render_danger_zone(` if a delete/deactivate zone exists
  - credential_modal.php is NOT modified (verify: `git status src/templates/admin/credential_modal.php` shows no changes)
  </acceptance_criteria>

  <done>8 admin member/club/attribute templates migrated. credential_modal.php untouched. All violations removed.</done>
</task>

</tasks>

<verification>
Final check across all 8 admin templates in this plan:
```bash
for f in members member_form member_edit clubs club_form club_edit attributes notify_coordinators; do
  count=$(grep -rn 'style="' /Users/sebastianwiller/Documents/github/team-manager/src/templates/admin/${f}.php 2>/dev/null | grep -v onerror | wc -l)
  echo "admin/$f: $count violations"
done
```
All must output: 0 violations.

Confirm credential_modal.php untouched:
```bash
git -C /Users/sebastianwiller/Documents/github/team-manager diff --name-only src/templates/admin/credential_modal.php
```
Should produce NO output.
</verification>

<success_criteria>
- All 8 admin templates in this plan pass php -l
- Zero inline style violations across all 8 files
- Zero spacing violations (py-5, mb-5) across all 8 files
- credential_modal.php is untouched
- render_empty, render_collection_group, render_badge used where applicable
</success_criteria>

<output>
After completion, create `.planning/phases/09-ui-vereinheitlichung/09-07b-SUMMARY.md`
</output>
