# Feature Landscape

**Domain:** Sports team management web application (German UI)
**Researched:** 2026-04-28
**Context:** PHP/PostgreSQL stack, mobile-first, simple German UI, coach/player roles, statistics tracking

## Executive Summary

Sports team management apps live in a well-established ecosystem with clear table-stakes expectations. The core value is **list-based tracking + aggregated player statistics**. This project already has the right instincts: dynamic column definitions, multi-state visibility, and per-player stat aggregation are differentiators, not basics.

Key finding: **The constraint to "keep it simple for v1" is the right move.** The typical feature creep in sports apps (real-time notifications, seasonal/tournament structures, equipment tracking, fitness profiles) should be deliberately deferred. Focus v1 on core: lists, stats, and role-based access.

---

## Table Stakes

Features users expect. Missing = product feels incomplete or broken.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **User authentication** | Without login, there's no multi-team isolation or data privacy | Low | Already designed: auto-generated credentials, no email |
| **Role-based access control** | Different users need different permissions (admin, trainer, player) | Low-Medium | PROJECT.md specifies 3 roles clearly |
| **Team membership & isolation** | Users must not see other teams' data | Low | Single team per user simplifies this |
| **Player lists** | Core document for tracking who plays, when they play, what they did | Low | The spreadsheet metaphor is the entire product |
| **Player data editing** | Players must be able to update their own data (at least some fields) | Low | PROJECT.md: players edit own row only |
| **Trainer management tools** | Coaches need to create lists, define columns, manage players | Medium | Covers list creation, column definition, player add/remove |
| **Visibility controls** | Lists need public/protected/private states | Low | Supports different use cases (team info vs internal tracking) |
| **Read-only access** | Some lists should be viewable without editing | Low | Part of visibility model |
| **Basic auth persistence** | Session/token management to stay logged in | Low | PHP sessions standard |
| **Password reset capability** | Forgotten passwords must be recoverable | Low | On-screen reset per PROJECT.md (no email) |
| **Mobile-responsive layout** | Mobile-first: touch-friendly, readable on small screens | Medium | CSS/responsive design needed |

---

## Differentiators

Features that set product apart. Not expected in basic team management, but valuable.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **Global column aggregation** | Stats auto-calculated across all lists per player (sums, counts) | Medium | The secret sauce: "15 goals total across 3 matches" auto-computed |
| **Flexible column types** | Boolean, number, text types per column with different aggregation rules | Medium | Boolean counts true values, numbers sum, text not aggregated |
| **Per-team global columns** | Reusable column definitions at team level (e.g., "Games played", "Goals") | Medium | Reduces duplication, maintains consistency across lists |
| **Local vs global columns** | Distinguish between team-wide stats (goals) and list-specific notes (field position) | Medium | Allows lightweight note-taking without cluttering stats |
| **Flexible list purposes** | Lists for anything: matches, training sessions, squad rotation, injury tracking | Low | Removes artificial constraints — trainer defines meaning |
| **Quick player row editing** | Inline edit UI for lists so players/coaches don't need form pages | Low | UX win, especially on mobile |
| **CSV/batch import** | Populate initial player list or bulk results from external source | Medium-High | Could defer to v2; nice for scaling to larger coaches |

---

## Anti-Features

Features to explicitly NOT build in v1. Good reasons to defer.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Email notifications** | PROJECT.md out of scope; adds infrastructure (SMTP), auth complexity (email verification), and GDPR surface area | Use on-screen alerts and logs; coach manually checks app |
| **Real-time collaboration (WebSockets)** | Overkill for list editing; rarely multiple simultaneous editors; adds complexity/deployment burden | Classical request/response; users reload to see updates |
| **Seasons / tournament structures** | Tempting add (matches played across "Spring 2026 League"), but invites schema complexity and feature creep | Keep lists flat; let trainer organize folders/naming (e.g., "2026_Spring_Match_1") |
| **Equipment/kit management** | Orthogonal to player stats; tempts tracking who has jersey #7, rental status, etc. | Defer entirely; separate app if needed |
| **Fitness profiling / drills** | Looks like "performance tracking" but is a different data model (workouts, exercises, energy levels) | Stay focused on "what happened in matches/training" not "how to train" |
| **Attendance/scheduling integration** | Calendar, recurring events, auto-messaging for practice times | Too much; track attendance in lists (boolean column) instead |
| **Export/reporting** | PDF downloads, charts, season reports look good in pitch but are rarely used in small clubs | Defer; browsers have print-to-PDF if needed |
| **Mobile app (iOS/Android)** | PROJECT.md: web-first. Responsive web + homescreen shortcut covers 95% of use cases. | Keep web-only; PWA installable later if justified |
| **Public league tables / competition ranking** | Multi-team feature; introduces ranking logic, dispute resolution, league rules | Out of scope per PROJECT.md: single admin, isolated teams |
| **Third-party integrations (Google Calendar, Slack, Discord)** | Every coach uses a different tool; maintenance burden is high; usually not worth it for v1 | Manual, simple: coaches manage their own calendars |
| **Player feedback / performance ratings** | Subjective scoring (1-5 stars) invites disputes and adds moderation work | Objective stats (goals, fouls, minutes played) only |
| **Custom roles (e.g., "Assistant Coach", "Medic")** | PROJECT.md defines admin/trainer/player; additional roles add permission matrix complexity | Keep 3 roles fixed |
| **Bulk messaging / announcements** | "Send SMS to all players" or in-app notifications | Email out of scope; push notifs are infrastructure; use shared lists instead |
| **Team financials / budget tracking** | Player fees, sponsorships, equipment costs | Completely separate domain; avoid |

---

## Feature Dependencies

```
Authentication → Team isolation → Player lists
Player lists → Visibility controls
Player lists → Trainer tools (create lists, add players)
Trainer tools → Column definition
Column definition → Global column aggregation
Global column aggregation → Statistics page
Password reset ← User auth (must have auth to reset it)
Mobile-responsive layout ← All features (must work on small screens)
```

### Critical Path (v1 MVP)
1. User auth + team isolation (foundation)
2. Player lists + basic editing
3. Trainer tools (list/column creation, player management)
4. Visibility controls (public/protected/private)
5. Statistics aggregation (the differentiator)
6. Mobile-responsive polish

---

## Feature Complexity Tiers

### Tier 1: Low Complexity (1-2 days per feature)
- User auth, password reset
- Team isolation (WHERE team_id = ?)
- Visibility controls (role + status checks)
- Basic CRUD for players/lists
- Mobile-responsive CSS

**Why:** Standard patterns, no algorithmic complexity, database schema straightforward.

### Tier 2: Medium Complexity (3-5 days per feature)
- Trainer management tools (multi-operation dashboard)
- Column definition system (admin UI for defining columns)
- Row editing UX (inline forms, validation)
- Statistics aggregation (queries that sum/count across lists)
- Mobile touch UI polish

**Why:** Need careful UX design, multiple interrelated operations, query optimization for stats.

### Tier 3: High Complexity (1-2 weeks per feature)
- CSV batch import
- Permission matrix for custom roles
- Real-time notifications

**Why:** Not needed for v1. Avoid in MVP.

---

## MVP Feature Set

**Prioritize in this order:**

### Phase 1: Foundation (Auth + Team Structure)
1. Admin login (PHP config file per PROJECT.md)
2. Admin team management (create teams, assign trainers)
3. Trainer + player user accounts (auto-generated credentials)
4. Password reset (on-screen display)
5. Session management

**Why:** Nothing else works without auth and team isolation.

### Phase 2: Core Tracking (Lists + Columns)
1. Trainer creates lists (matches, training sessions, etc.)
2. Player lists with rows (one row per player)
3. Global columns (team-level, boolean/number, for stats)
4. Local columns (list-level, boolean/number/text, not aggregated)
5. Visibility controls (public/protected/private)
6. Players edit own rows; trainers edit/delete anything

**Why:** This is the product. Everything else supports it.

### Phase 3: Analytics (Statistics)
1. Statistics page per player (aggregated global column stats)
2. Boolean columns: count of true values (e.g., 12 games played)
3. Number columns: sum (e.g., 15 goals total)
4. Link stats to specific lists (drill down: which matches had those goals?)

**Why:** The differentiator. Makes v1 worth using over a spreadsheet.

### Phase 4: Polish (Mobile UX)
1. Responsive grid/table layouts
2. Touch-friendly buttons and inputs
3. Mobile-optimized forms
4. Hamburger menu or simplified nav
5. Print-friendly styles (coaches print rosters)

**Why:** Mobile-first is a constraint. Users access from sidelines on phones.

---

## Defer to v2+ (If Roadmap Extends)

| Feature | Reason | Estimated Effort |
|---------|--------|------------------|
| CSV import | Nice for bulk data, not needed if small squads start manual | 3-5 days |
| Seasonal/tournament views | Tempting, but introduces schema complexity; lists handle it via naming | 1-2 weeks |
| Export/reporting (PDF, charts) | Users rarely use it; browser print works | 3-7 days |
| Real-time collaboration | Not needed for small teams; async is fine | 1-2 weeks |
| Mobile app | Web-first PWA covers it; native app is diminishing returns | 4-8 weeks |
| Player fitness/performance ratings | Shifts from data tracking to subjective evaluation; moderation burden | 1-2 weeks |
| Integration with external calendars/chat | Maintenance burden high; most coaches use separate tools | 1-2 weeks per integration |
| Bulk messaging | Out of scope (no email); in-app push adds infrastructure | 1-2 weeks |
| Public league rankings | Multi-team feature; requires ranking logic, rule arbitration | 2-3 weeks |

---

## Anti-Pattern: What NOT to Build

### Trap 1: The "Fitness Profile" Overreach
**What:** "Let's track workouts, exercises, energy levels, performance ratings"
**Why it's wrong:** Shifts the product from "what happened in matches?" to "how should we train?" — different data model, different user, different expertise needed
**Prevention:** Stick to objective, match-based metrics only

### Trap 2: Multi-Team User Accounts
**What:** One coach managing multiple teams, one account per coach
**Why it's wrong:** PROJECT.md says no. One account = one team. Simplifies auth, data privacy, and permission model
**Prevention:** If a coach needs multiple teams, they get multiple logins (unusual anyway)

### Trap 3: The "Real-Time" Temptation
**What:** "Coaches want live updates; let's add WebSockets"
**Why it's wrong:** Coaches don't need live notifications during a match (they're busy). Async request/response is fine
**Prevention:** Test with actual coaches; request/response beats infrastructure complexity

### Trap 4: Custom Role Explosion
**What:** Admin, Coach, Assistant Coach, Medic, Club President, Treasurer...
**Why it's wrong:** Permission matrix explodes; PROJECT.md fixes 3 roles; one extra role = 3x permission logic
**Prevention:** Define roles once; defer custom roles to v2

---

## Success Criteria for Features

A feature is "done" when:
1. **It solves a real coach problem** — test with actual users in early phases
2. **It's mobile-usable** — works on phone without desktop
3. **It doesn't require email** — per PROJECT.md constraint
4. **Trainers can operate it alone** — no admin involvement after setup
5. **Performance is acceptable** (< 1 second for list with 50 players, < 2 seconds for stats page with 20 players)

---

## Sources & Confidence

**Confidence:** MEDIUM-HIGH

- **Sourced from:** Training data on sports management SaaS (TeamSnap, ACL, Sportlyzer patterns), PROJECT.md requirements, German sports club conventions
- **Not verified with:** Current-year market research (WebSearch unavailable); specific coach interviews
- **HIGH confidence areas:** Table stakes and anti-features (standard patterns across 10+ apps in this category)
- **MEDIUM confidence areas:** Differentiators (flexible columns are strong, but exact UX impact unverified)

**Gaps to address in phase-specific research:**
- Actual coach workflow validation (are lists the right mental model?)
- Mobile UX specifics (exact button sizes, swipe patterns for German coaches)
- Stats aggregation rules (are sums/counts enough? Do coaches need averages, medians?)
- Privacy expectations (do players expect private stats or are team stats public by default?)

