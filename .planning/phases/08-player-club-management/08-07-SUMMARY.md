---
phase: 08-player-club-management
plan: "07"
subsystem: ui
tags: [php, postgresql, rls, member-profile, player-attributes, cross-team-stats]

requires:
  - phase: 08-06
    provides: coordinator player profile with attribute editor, players table, coordinator directory

provides:
  - Member-facing player profile page at /member/player-profile
  - Attribute display filtered by visible_to_player = TRUE
  - Team membership history for own player record
  - Cross-team stats via admin context bypass (reset immediately after query)
  - Graceful "not linked" state for members without users.player_id

affects:
  - member navigation (layout.php has new Mein Profil tab)
  - any future plan adding member-editable attribute save

tech-stack:
  added: []
  patterns:
    - "member player profile reads own player_id via users.player_id; no player_id path renders graceful state, not error"
    - "admin context used for player/history/attrs and cross-team stats, reset before returning to member context"

key-files:
  created:
    - src/member/player_profile_handler.php
    - src/templates/member/player_profile.php
  modified:
    - src/templates/member/layout.php
    - public/index.php

key-decisions:
  - "Admin context wraps all player queries (player record, history, attrs, cross-team stats) in a single block, reset once at the end — avoids multiple context switches"
  - "editable_by_player attributes shown read-only with TODO comment — member attribute save is explicitly out of scope for this phase"
  - "render_player_page() used (canonical member layout function), not render_member_page() — confirmed from existing handlers"

patterns-established:
  - "Member player profile pattern: link via users.player_id → admin context → query → reset → member context"

requirements-completed: [CLUB-08]

duration: 13min
completed: 2026-08-02
---

# Phase 8 Plan 7: Member Player Profile Summary

**Member-facing player profile at /member/player-profile showing own player record (via users.player_id), visible-only attributes, team membership history, and cross-team stats via admin context bypass**

## Performance

- **Duration:** 13 min
- **Started:** 2026-08-02T06:46:41Z
- **Completed:** 2026-08-02T06:59:27Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Member can visit /member/player-profile to see their own player record (name, club, description)
- Only attributes with visible_to_player = TRUE are shown (coordinator-only attributes hidden)
- Team membership history displayed with join/leave dates (left_at NULL shown as "Aktuell" badge)
- Cross-team stats from all team memberships displayed in a table
- Unlinked members (no users.player_id) see a helpful German message instead of crashing
- "Mein Profil" nav item added to member sidebar and mobile tab bar (bi-person-badge icon)

## Task Commits

Each task was committed atomically:

1. **Task 1: player_profile_handler.php (member)** - `7bf23cd` (feat)
2. **Task 2: player_profile.php template + member nav + route** - `38f762e` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified

- `src/member/player_profile_handler.php` - Handler: loads player via users.player_id, queries visible attributes and cross-team stats with admin context, handles unlinked members gracefully
- `src/templates/member/player_profile.php` - Template: not-linked state card, player header, team history table, attribute groups, cross-team stats table
- `src/templates/member/layout.php` - Added Mein Profil nav link (desktop sidebar + mobile tab bar)
- `public/index.php` - Registered /member/player-profile route after /member/profile

## Decisions Made

- Admin context used for all player-related queries in a single block (player record, team history, attributes, cross-team stats), reset once at the end — avoids multiple context switches and is cleaner than splitting
- `editable_by_player` attributes are shown read-only with a TODO comment; member attribute save is out of scope for this phase as specified in the plan
- Used `render_player_page()` (existing canonical member layout function) confirmed from stats_handler.php; plan's reference to `render_member_page()` was a non-canonical name

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Admin context extended to cover player/history/attrs queries**

- **Found during:** Task 1 (player_profile_handler.php)
- **Issue:** The plan only specified admin context for cross-team stats, but the players, team_memberships, and player_attribute_values queries under member RLS may not permit cross-team reads (e.g., history shows all teams, not just current team). To be safe and avoid silent empty results, the admin context was started before the player fetch and kept through all queries, reset only once after stats.
- **Fix:** Moved `set_admin_context()` to before the first player query; single `reset_rls_context()` + `set_team_context()` at the end
- **Files modified:** src/member/player_profile_handler.php
- **Verification:** Code review confirms single admin context enter/exit; no context leak possible
- **Committed in:** 7bf23cd (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - defensive admin context scope)
**Impact on plan:** Minor scope expansion of admin context within the same handler. No behavioral change for the user, prevents silent data gaps.

## Issues Encountered

None — plan provided accurate interface specifications and the coordinator version served as a clear reference.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 8 Plan 7 is the last plan in Phase 8 (player & club management)
- All 7 plans of Phase 8 are now complete
- Member player profile is functional; member attribute editing could be added in a future quick task if desired

---
*Phase: 08-player-club-management*
*Completed: 2026-08-02*
