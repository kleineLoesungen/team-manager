---
id: 260714-wkp
type: quick
status: pending
---

# Quick Task 260714-wkp: Move ICS hint to bottom + add time range in calendar cards

## Objective

In both coordinator and member calendar tab views:
1. Remove ICS info box from top (currently between tab-switcher and add/nav controls)
2. Add time range (`HH:MM – HH:MM` or just `HH:MM` when no end time) in dated-items timeline cards, below the location line
3. Re-add ICS info box at the bottom of the calendar tab, after the undated section

## Tasks

### Task 1: Coordinator lists template

**File:** `src/templates/coordinator/lists.php`

**Changes:**
- Delete the ICS info box block at the top (lines 53-60, the `<!-- ICS info box -->` comment + `<?php if (!empty($ics_url)): ?>` block)
- In the dated-items timeline card, after the `<?php if (!empty($item['location'])): ?>` block (line 144-148), add a time range div:
  ```php
  <?php if (!empty($item['time_start'])): ?>
  <div class="small text-muted mt-1">
      <i class="bi bi-clock me-1"></i><?= e(substr((string)$item['time_start'], 0, 5)) ?><?php if (!empty($item['time_end'])): ?> – <?= e(substr((string)$item['time_end'], 0, 5)) ?><?php endif; ?>
  </div>
  <?php endif; ?>
  ```
- After the undated section (after `<?php endif; ?>` closing the undated block, before `<?php else: ?>`), add the ICS info box back:
  ```php
  <?php if (!empty($ics_url)): ?>
  <div class="alert alert-info py-2 mt-4 small">
      <strong>In Kalender-App abonnieren:</strong>
      Kopiere den Link um die Termine in deiner Kalender-App zu abonnieren.<br>
      <code class="user-select-all"><?= e($ics_url) ?></code>
  </div>
  <?php endif; ?>
  ```

### Task 2: Member lists template

**File:** `src/templates/member/lists.php`

**Same three changes as Task 1**, adapted to member template structure (no add-button row, same ICS/timeline/undated structure).
