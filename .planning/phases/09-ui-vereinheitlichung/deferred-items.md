# Phase 09 Deferred Items

Found during final codebase scan in Plan 09-08 (Task 2).
These violations are pre-existing (introduced in plans 09-03 through 09-07b) and out of scope
for this plan per the scope boundary rule. They should be addressed in a dedicated cleanup plan.

## Pre-existing Spacing Utility Violations (*-0 and *-1)

~55 occurrences of `mb-0`, `mb-1`, `mt-1`, `pt-0`, `pb-0`, `py-1`, `px-0`, `px-1` across:
- src/templates/coordinator/list_form.php (8 occurrences — form-check labels)
- src/templates/coordinator/ticker.php (6 occurrences — list-group-item content)
- src/templates/coordinator/stats.php (7 occurrences — filter form labels)
- src/templates/coordinator/list_notify.php (9 occurrences)
- src/templates/coordinator/member_profile.php (12 occurrences)
- src/templates/coordinator/member_profiles.php (3 occurrences)
- src/templates/coordinator/lists.php (4 occurrences)
- src/templates/member/ticker_list.php (5 occurrences)
- src/templates/member/ticker_detail.php (2 occurrences)
- src/templates/member/profile.php (2 occurrences)
- src/templates/member/member_profile.php (8 occurrences)
- src/templates/member/lists.php (2 occurrences)
- src/templates/admin/attributes.php (9 occurrences)
- src/templates/admin/members.php (5 occurrences)
- src/templates/components/partials.php (3 occurrences — partials infrastructure itself)

## fs-* Class Violations

5 occurrences of `fs-5` used for icon sizing in:
- src/templates/member/profile.php (3 occurrences: bi-person-badge, bi-clock-history, bi-box-arrow-right)
- src/templates/member/confirm_profile.php (2 occurrences: bi-shield-check, bi-info-circle-fill)

## credential_modal.php Inline Style

`src/templates/admin/credential_modal.php:10` — `style="background: rgba(0,0,0,0.5);"` on modal overlay.
This is a security file (60-second auto-close credential display). The plan requires it remain
unchanged, creating a conflict with the inline style scan. Should be resolved by adding a CSS class
to app.css (e.g., `.modal-backdrop-custom`) and removing the inline style.
