---
phase: quick
plan: 260729-ijq
type: execute
wave: 1
depends_on: []
files_modified:
  - src/templates/admin/credential_modal.php
  - src/templates/coordinator/list_detail.php
  - src/templates/coordinator/file_detail.php
  - src/templates/member/list_detail.php
  - src/templates/member/file_detail.php
autonomous: true
requirements: [QUICK-260729-IJQ]

must_haves:
  truths:
    - "Coordinator/member can copy a share string for a list or markdown doc with one button click"
    - "Share string format is '[TeamName] Titel - https://host/path' (no query params)"
    - "credential_modal has an 'Alles kopieren' button that copies username and password in one action"
    - "All copy actions work on mobile via navigator.clipboard with execCommand fallback"
  artifacts:
    - path: "src/templates/admin/credential_modal.php"
      provides: "Alles kopieren button in credential modal footer"
    - path: "src/templates/coordinator/list_detail.php"
      provides: "Teilen button in top-right button group"
    - path: "src/templates/coordinator/file_detail.php"
      provides: "Teilen button in top button row"
    - path: "src/templates/member/list_detail.php"
      provides: "Teilen button near page header"
    - path: "src/templates/member/file_detail.php"
      provides: "Teilen button near back link"
  key_links:
    - from: "PHP template"
      to: "JS onclick"
      via: "json_encode($share_text) embedded as data-share attribute or JS variable"
      pattern: "json_encode.*share"
---

<objective>
Two small UX improvements: (1) a share button on list/doc detail pages that copies
"[Team] Titel - URL" to clipboard; (2) an "Alles kopieren" button in the credential
modal that copies username + password in one tap.

Purpose: Saves coordinators manual URL construction when sharing lists/docs; saves
extra taps when distributing new member credentials.
Output: 5 modified PHP templates, no new routes, no DB changes.
</objective>

<execution_context>
@~/.claude/get-shit-done/workflows/execute-plan.md
@~/.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md

Relevant facts already gathered:
- $_SESSION['team_name'] is set at login (src/auth/login_handler.php line 99)
- List templates receive $list['name']; file templates receive $file['name']
- credential_modal.php is shared: used by admin/coordinator_create_handler,
  admin/coordinator_action_handler, coordinator/member_create_handler,
  coordinator/member_action_handler — modifying once fixes all four flows
- navigator.clipboard already used in credential_modal.php copyToClipboard() function
- URL pattern: coordinator lists → /coordinator/lists/{id}, files → /coordinator/files/{id}
  member lists → /member/lists/{id}, files → /member/files/{id}
- Bootstrap 5.3 via CDN, German UI, mobile-first
</context>

<tasks>

<task type="auto">
  <name>Task 1: Add "Alles kopieren" to credential modal</name>
  <files>src/templates/admin/credential_modal.php</files>
  <action>
Add a single "Alles kopieren" button to the modal footer, before the existing
"Schließen" button. When clicked it copies the combined credentials as:

  Benutzername: {username}
  Passwort: {password}

Implementation:
1. Add a new copyAll() JS function that builds the combined string from the
   already-rendered credential values in the DOM (read from #cred-username and
   #cred-password textContent). Use navigator.clipboard.writeText() with the
   same fallback pattern as the existing copyToClipboard().
2. On success briefly change button text to "Kopiert!" (2 s), then restore
   "Alles kopieren".
3. Add fallback: if navigator.clipboard is unavailable, use
   document.execCommand('copy') via a temporary textarea.

Button placement in modal-footer:
  <button type="button" class="btn btn-outline-primary min-touch"
          onclick="copyAll(this)">Alles kopieren</button>

Keep existing layout (Schließen on the right, timer text on the left).
The new "Alles kopieren" sits between timer text and Schließen, or replace
timer-row layout with: [timer-text] [Alles kopieren] [Schließen] — use
d-flex gap-2 align-items-center justify-content-between in modal-footer.

Do NOT remove the existing individual Kopieren buttons — they remain useful.
  </action>
  <verify>
Open the app, create or reset a user credential to trigger the modal.
Click "Alles kopieren" — paste into a text editor and confirm both username
and password appear in two lines.
  </verify>
  <done>
Modal footer shows "Alles kopieren" button. Clicking it copies a two-line
string with Benutzername and Passwort. Individual per-field copy buttons
still work.
  </done>
</task>

<task type="auto">
  <name>Task 2: Add share button to list and doc detail pages</name>
  <files>
    src/templates/coordinator/list_detail.php,
    src/templates/coordinator/file_detail.php,
    src/templates/member/list_detail.php,
    src/templates/member/file_detail.php
  </files>
  <action>
Add a "Teilen" button to each of the four templates that copies a share string
to clipboard. No new routes needed — all done client-side from values already
available in the template.

**Share string format:** [TeamName] Titel - https://host/path
- No query string (strip with strtok($url, '?'))
- Team name from $_SESSION['team_name']
- Title from $list['name'] or $file['name']

**PHP snippet to add near top of each template** (before or inside the first
div, where $list/$file variables are in scope):

```php
<?php
$_share_url  = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http')
             . '://' . $_SERVER['HTTP_HOST']
             . strtok($_SERVER['REQUEST_URI'], '?');
$_share_text = '[' . ($_SESSION['team_name'] ?? 'Team') . '] '
             . ($list['name'] ?? $file['name'] ?? '')
             . ' - ' . $_share_url;
?>
```

**JS copy function** — add once per template as a small inline `<script>`:

```js
function shareItem(btn) {
    var text = btn.getAttribute('data-share');
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            btn.textContent = 'Kopiert!';
            setTimeout(function(){ btn.innerHTML = '<i class="bi bi-share me-1"></i>Teilen'; }, 2000);
        }).catch(function(){ shareFallback(text, btn); });
    } else { shareFallback(text, btn); }
}
function shareFallback(text, btn) {
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.cssText = 'position:fixed;opacity:0';
    document.body.appendChild(ta); ta.focus(); ta.select();
    try { document.execCommand('copy'); btn.textContent = 'Kopiert!';
          setTimeout(function(){ btn.innerHTML = '<i class="bi bi-share me-1"></i>Teilen'; }, 2000); }
    catch(e) { prompt('Link kopieren:', text); }
    document.body.removeChild(ta);
}
```

**Button HTML:**
```html
<button type="button"
        class="btn btn-sm btn-outline-secondary min-touch"
        data-share="<?= htmlspecialchars($_share_text, ENT_QUOTES) ?>"
        onclick="shareItem(this)">
    <i class="bi bi-share me-1"></i>Teilen
</button>
```

**Placement per template:**

coordinator/list_detail.php — Add inside the existing `div.d-flex.gap-2`
at the top (line ~32 area), alongside the existing Einstellungen and email
buttons. Place before the Einstellungen button so share comes first.

coordinator/file_detail.php — Add to the existing `div.mb-3.d-flex.gap-2`
(line ~8 area), alongside the back-link, email button. Place after the back
link and before the email button.

member/list_detail.php — The top area has a flex row with back link and date.
Add the Teilen button inside that row. Change the outer div to
`d-flex justify-content-between align-items-center mb-1 gap-2` if needed, or
add a new small row below the back link: `<div class="mb-2 d-flex gap-2">`.
Prefer adding it to the existing flex row as a third element.

member/file_detail.php — Change `div.mb-3` wrapping the back link to
`div.mb-3 d-flex gap-2 flex-wrap`, and add the Teilen button alongside the
back link button.

In all cases the button must be `btn-sm` and use `min-touch` class for mobile
tap target compliance.
  </action>
  <verify>
1. Open a coordinator list detail page — confirm "Teilen" button appears in the
   button group. Click it; paste result should be "[TeamName] Listenname - https://..."
2. Open a coordinator file detail page — same check with file name.
3. Open a member list detail page — button visible, share text correct.
4. Open a member file detail page — button visible, share text correct.
5. Confirm no query params (?success=1 etc.) appear in the copied URL.
  </verify>
  <done>
"Teilen" button visible on all four pages. Clicking copies "[Team] Titel - URL"
to clipboard. URL has no query params. Button shows "Kopiert!" feedback then
reverts to "Teilen" after 2 s.
  </done>
</task>

</tasks>

<verification>
- credential_modal.php has "Alles kopieren" button that copies both credentials in one action
- All 4 list/doc detail templates have a "Teilen" button
- Share string format is correct: [TeamName] Titel - URL (no query params)
- Copy works on mobile (navigator.clipboard + execCommand fallback)
- No new PHP routes, no DB changes, no new files
</verification>

<success_criteria>
1. Tapping "Alles kopieren" in credential modal pastes "Benutzername: X\nPasswort: Y"
2. Tapping "Teilen" on any list/doc page copies "[Team] Name - https://..." 
3. Both flows work on mobile Safari / Chrome without JS errors
</success_criteria>

<output>
After completion, create `.planning/quick/260729-ijq-share-button-for-list-doc-and-one-click-/260729-ijq-SUMMARY.md`
</output>
