---
phase: 1
slug: foundation
status: draft
shadcn_initialized: false
preset: none
created: 2026-04-29
---

# Phase 1 — UI Design Contract

> Visual and interaction contract for Foundation phase: Login, Admin Dashboard, and shared layout/navigation shell. German UI, mobile-first, server-rendered PHP with Bootstrap 5 CDN.

---

## Design System

| Property | Value |
|----------|-------|
| Tool | Bootstrap 5 (CDN) |
| Preset | not applicable |
| Component library | Bootstrap native components (Alert, Form, Card, Button, Modal, Nav) |
| Icon library | Bootstrap Icons (via CDN) or native glyphs |
| Font | system sans-serif stack (fallback to -apple-system, BlinkMacSystemFont) |

**Rationale:** Bootstrap 5 via CDN eliminates build complexity and provides mobile-first components out of the box. No component registry required — all Phase 1 UI uses standard Bootstrap classes.

---

## Spacing Scale

Declared values (must be multiples of 4):

| Token | Value | Usage |
|-------|-------|-------|
| xs | 4px | Icon gaps, inline padding, small input borders |
| sm | 8px | Compact element spacing, form label gaps |
| md | 16px | Default element spacing (card padding, input padding) |
| lg | 24px | Section padding, major component separation |
| xl | 32px | Layout gaps between major sections |
| 2xl | 48px | Major section breaks, page-level vertical rhythm |
| 3xl | 64px | Page header spacing, hero areas |

**Exceptions:** 

- **Touch targets on mobile:** Login button and form inputs shall be minimum 44px height (recommended mobile standard). Form input padding: `md` (16px) top/bottom, `md` (16px) left/right.
- **Bootstrap utility spacing:** Phase 1 UI will use Bootstrap's `p-{1-5}` (padding) and `m-{1-5}` (margin) utility classes, which map to Bootstrap's rem-based scale. Bootstrap's default spacing unit is `1rem = 16px`, so `p-2 = 8px`, `p-3 = 16px`, `p-4 = 24px`, `p-5 = 32px`.

---

## Typography

| Role | Size | Weight | Line Height |
|------|------|--------|-------------|
| Body | 16px (1rem) | 400 (regular) | 1.5 |
| Label | 14px (0.875rem) | 600 (semibold) | 1.5 |
| Heading (h4) | 20px (1.25rem) | 600 (semibold) | 1.2 |
| Heading (h2/h3) | 24px (1.5rem) | 600 (semibold) | 1.2 |

**Sources:**
- Body text: Inferred from Bootstrap 5 default (`$font-size-base: 1rem`)
- Labels: Form labels and field headings use semibold for emphasis
- Headings: Admin dashboard titles and section headings

**Mobile First:** All sizes are base mobile sizes. No responsive scaling required for Phase 1 — login and admin dashboard are stack-based (vertical layout) on all screen sizes.

**Implementation:**
- Use Bootstrap typography utilities: `.fs-{1-6}` for size, `.fw-{bold/semibold/normal}` for weight
- Apply `line-height: 1.5` to body via custom CSS if Bootstrap default differs

---

## Color

| Role | Value | Usage |
|------|-------|-------|
| Dominant (60%) | #ffffff (white) | Page background, card backgrounds |
| Secondary (30%) | #f8f9fa (light gray) | Sidebar background (if used), form backgrounds, dividers |
| Accent (10%) | #0d6efd (Bootstrap blue) | Primary buttons, active navigation items, success states |
| Destructive | #dc3545 (Bootstrap red) | Delete/reset buttons, error messages, warnings |

**Accent Reserved For:**
1. Primary CTA buttons ("Anmelden", "Team erstellen", "Trainer hinzufügen")
2. Active navigation state in admin sidebar/nav
3. Form focus states and validation success
4. Admin action buttons (team edit, coach assign)

**DO NOT use accent for:**
- Form field borders (use gray)
- Secondary action buttons (use gray)
- Body text links (use body color with underline)
- Form placeholders

**Secondary Color Usage (30%):**
- Form input backgrounds in focus state (optional subtle tint)
- Card separators and horizontal rules
- Disabled button backgrounds
- Admin dashboard card backgrounds (if separated from page)

**Destructive (10%):**
- "Reset Password" buttons
- "Deactivate Team" buttons
- "Delete" confirmations
- Error alert backgrounds
- Error text for validation messages

---

## Copywriting Contract

**Language:** German (Deutsch) for all UI copy. User-facing messages, labels, buttons, and confirmations are in German. System logs and code comments may use English.

### Primary Elements

| Element | Copy | Context |
|---------|------|---------|
| Page title (Login) | Anmelden | Login page header |
| Page title (Admin) | Teams verwalten | Admin dashboard header |
| Primary CTA (Login) | Anmelden | Login form submit button |
| Primary CTA (Team Create) | Team erstellen | Admin dashboard action button |
| Primary CTA (Coach Assign) | Trainer hinzufügen | Admin team detail action |
| Secondary CTA (Cancel) | Abbrechen | Modal or form cancel button |
| Destructive CTA (Reset) | Passwort zurücksetzen | Admin coach password reset |
| Destructive CTA (Deactivate) | Team deaktivieren | Admin team action (soft delete) |

### Form Labels and Validation

| Element | Copy | Context |
|---------|------|---------|
| Username label | Benutzername | Login form |
| Password label | Passwort | Login form |
| Email / Username hint | Geben Sie Ihren Benutzernamen ein | Input placeholder or help text |
| First name label | Vorname | Team/coach/player creation form |
| Last name label | Nachname | Team/coach/player creation form |
| Team name label | Teamname | Team creation form |

### Empty States

| Element | Copy | Context |
|---------|------|---------|
| No teams heading | Noch keine Teams | Admin dashboard when no teams exist |
| No teams body | Erstellen Sie ein neues Team, um zu beginnen. | Hint + next step |
| No coaches heading | Keine Trainer zugewiesen | Admin team detail when coaches list is empty |
| No coaches body | Fügen Sie einen oder mehrere Trainer hinzu. | Hint + next step |

### Error States

| Element | Copy | Context |
|---------|------|---------|
| Invalid login | Benutzername oder Passwort falsch. Versuchen Sie es erneut. | Login form submission failure |
| Session expired | Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an. | Redirect to login on session timeout |
| Database error | Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut. | Generic database/server error |
| Duplicate username | Dieser Benutzername existiert bereits. | Username collision during coach creation |
| Required field | Dieses Feld ist erforderlich. | Form validation error |

### Destructive Confirmations

| Action | Confirmation Copy | Context |
|--------|-------------------|---------|
| Reset Coach Password | Das Passwort wird zurückgesetzt und angezeigt. Diese Aktion kann nicht rückgängig gemacht werden. | Modal confirmation before reset |
| Deactivate Team | Das Team wird deaktiviert. Alle Trainer und Spieler bleiben im System, können sich aber nicht anmelden. | Modal confirmation |
| Logout | Abmelden | Sidebar/nav logout action (no confirmation needed) |

### Credential Display

| Element | Copy / Behavior | Context |
|---------|-----------------|---------|
| New credential heading | Neue Anmeldedaten | Modal showing generated username/password |
| Username copy action | Kopieren | Button to copy username to clipboard |
| Password copy action | Kopieren | Button to copy password to clipboard |
| Auto-clear timer | Dieses Fenster schließt sich automatisch in 60 Sekunden. | Helper text in credential modal |
| Close button | Schließen | Manual close of credential modal |

---

## Layouts and Components

### Shared Layout Structure

**Header (all pages):**
- Logo or app name (16px, semibold, left-aligned)
- Current user info (username, role indicator) — right-aligned on desktop, stacked on mobile
- Logout link — right-aligned, styled as secondary action

**Navigation (Admin only):**
- Vertical sidebar (mobile: collapsible or bottom tabs) with "Teams" and "Coaches" nav items
- Active state: accent blue background, white text
- Inactive state: dark gray text, light gray background on hover

**Main content:**
- Padding: `lg` (24px) on mobile, `xl` (32px) on tablet+
- Max-width: full width on mobile, 1200px on desktop (standard Bootstrap container)
- Stack-based layout (vertical flex) on mobile; no multi-column layouts in Phase 1

**Footer (optional):**
- Not required for Phase 1 — dismiss if no legal/copyright text needed

### Login Page (`/login` or root `/`)

**Structure:**
- Centered card on white background
- Card max-width: 400px on desktop, 90vw on mobile (responsive)
- Card padding: `lg` (24px)
- Card shadow: Bootstrap `shadow-sm` or `shadow`

**Form fields:**
- Username input: type=`text`, full width, 44px height
- Password input: type=`password`, full width, 44px height
- Spacing between fields: `lg` (24px)
- Input padding: `md` (16px) left/right, input height includes top/bottom padding

**Button:**
- Primary button: full width, 48px height (touch-friendly), accent blue, white text
- Button text: "Anmelden" (14px, semibold)
- On hover: darker blue shade
- On active/submit: disabled state with loading indicator (optional spinner)

**Error messaging:**
- Alert box above form on validation failure
- Alert type: `.alert-danger` (red background, dark red text)
- Alert content: "Benutzername oder Passwort falsch. Versuchen Sie es erneut."

**Focus states:**
- All inputs: blue outline or border on focus (Bootstrap `.form-control:focus`)
- Outline width: 2px
- Outline color: accent blue

### Admin Dashboard

**Page layout:**
- Sidebar + main content (two-column on desktop)
- Mobile: hamburger menu or bottom tab navigation (TBD in planner)
- Sidebar width: 240px on desktop, hidden/collapsed on mobile
- Sidebar background: secondary gray (#f8f9fa)

**Sidebar navigation:**
- Nav items: "Teams" (link to dashboard)
- Nav items: "Coaches" (link to coaches list) — for Phase 2
- Active item: accent blue background, white text, bold
- Inactive item: dark gray text, light background on hover

**Dashboard card grid:**
- Each team shown as a Bootstrap Card
- Card layout: horizontal (image left, content right) on desktop; stack on mobile
- Card max-width: 100% of column (responsive)
- Cards arranged in single column on mobile, 1-2 columns on tablet+
- Card padding: `md` (16px)
- Card spacing: `lg` (24px) bottom margin between cards

**Card content:**
- Team name (20px, semibold, dark text)
- Coach count badge: secondary gray background, dark text, `sm` (8px) padding
- Actions: "Bearbeiten", "Trainer hinzufügen", "Löschen" as small buttons or links
- Action spacing: horizontal flex, `sm` (8px) gap between actions

**Action buttons in admin:**
- "Team erstellen" button: primary, accent blue, full width on mobile, `lg` (24px) padding
- "Team bearbeiten": secondary gray button, smaller (14px text)
- "Trainer hinzufügen": secondary gray button, smaller
- "Team deaktivieren": destructive red button, smaller

**Modals (team creation, coach assignment, password reset):**
- Full width on mobile (90vw max), 500px max-width on desktop
- Modal padding: `lg` (24px)
- Modal header: 20px semibold heading, close button (×)
- Modal body: form fields with labels, spacing `lg` (24px) between fields
- Modal footer: two buttons — "Abbrechen" (secondary) and "Speichern"/"Erstellen" (primary)
- Modal backdrop: dark semi-transparent overlay

### Credential Display Modal (Password Reset, New Player/Coach)

**Structure:**
- Modal with accent blue header
- Heading: "Neue Anmeldedaten" (20px, semibold)
- Content area: code/monospace block with username and password

**Credential display:**
- Username: monospace font (e.g., `<code>mm4821</code>`)
- Password: monospace font, optionally hidden behind "show/hide" toggle (optional for security)
- Each credential line: copy-to-clipboard button (icon or "Kopieren" text)

**Timing and behavior:**
- Modal displays for 60 seconds before auto-closing (via JS timer or server-side redirect)
- User can manually close earlier via "Schließen" button
- Timer countdown: show remaining seconds (optional: "Schließt in 58s")
- On close/timeout: redirect to previous page (admin dashboard or team detail)

**Styling:**
- Credential block: secondary gray background (#f8f9fa), monospace font, `md` (16px) padding, border-radius 4px
- Copy button: small, secondary gray or accent blue (TBD by executor)

---

## Interactions and Behavior

### Form Submission

- **Validation:** Client-side validation (HTML5 `required`, `type=email`, `minlength`, etc.) for UX
- **Backend validation:** Always re-validate on server (never trust client)
- **CSRF protection:** Hidden `<input type="hidden" name="_csrf" value="...">` token in all forms
- **Loading state:** On form submit, disable button and optionally show loading spinner

### Session Timeout

- **Behavior:** On inactivity (8 hours default per context decision D-05), session expires
- **Detection:** Server-side check on every request
- **User feedback:** Simple redirect to `/login` with optional message: "Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an."
- **No JavaScript alert/modal required** (per decision D-06)

### Navigation

- **Role-based visibility:** Admin sees `/admin` routes; coach/player see only their own routes (Phase 2+)
- **Login redirect:** Any unauthenticated request redirects to `/login`
- **Admin redirect:** Non-admin user accessing `/admin/*` redirects to login or 403 page

### Responsive Breakpoints

**Mobile-first approach:**
- Base styles: mobile (< 576px)
- Tablet: `@media (min-width: 768px)` — sidebar appears, cards may span 2 columns
- Desktop: `@media (min-width: 1200px)` — full-width layouts, multi-column grids

**Phase 1 specific:**
- Login page: same appearance on all sizes (centered card)
- Admin dashboard: single-column layout on mobile, optionally multi-column on tablet+ (layout detail deferred to planner)

---

## Accessibility Considerations

- All form fields have associated `<label>` tags with `for` attribute
- Buttons have descriptive text (not just icons)
- Color is not the only indicator (e.g., error messages are text, not just red background)
- Link contrast ratio: WCAG AA (4.5:1 for normal text, 3:1 for large text)
- Form focus indicators: visible blue outline (never `outline: none`)

---

## Registry Safety

| Registry | Blocks Used | Safety Gate |
|----------|-------------|-------------|
| Bootstrap 5 (CDN) | Alert, Button, Card, Form, Modal, Nav, Container | CDN-based; no local code execution required |
| Bootstrap Icons (CDN) | Optional (logout icon, etc.) | CDN-based; no local code execution required |

**No third-party block vetting required:** Bootstrap and Bootstrap Icons are delivered via public CDN, not installed as npm modules or local code. All components are standard Bootstrap classes and HTML.

---

## Checker Sign-Off

- [ ] Dimension 1 Copywriting: PASS
- [ ] Dimension 2 Visuals: PASS
- [ ] Dimension 3 Color: PASS
- [ ] Dimension 4 Typography: PASS
- [ ] Dimension 5 Spacing: PASS
- [ ] Dimension 6 Registry Safety: PASS

**Approval:** pending

---

## Notes for Executor

1. **German language:** All UI text in this contract is German. Ensure all templates use German labels, messages, and button text.
2. **Mobile-first CSS:** Write CSS media queries with mobile base, then add tablet+ overrides.
3. **Bootstrap CDN:** Link to Bootstrap 5.3+ in `<head>`: `<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">`
4. **Custom CSS:** Keep custom CSS minimal. Prefer Bootstrap utilities (`.p-3`, `.fw-bold`, etc.) over new classes.
5. **Form CSRF tokens:** Generate via `bin2hex(random_bytes(32))` in session, validate on submit.
6. **Session hardening:** Apply `session_start()` options from CLAUDE.md (D-07): `cookie_secure`, `cookie_httponly`, `cookie_samesite=Strict`.
7. **Credential display:** Modal auto-closes after 60 seconds or user close. Do not persist credentials beyond this window.
8. **Admin check:** Every admin-only route must check `$_SESSION['is_admin']` before rendering.

---

**Created:** 2026-04-29  
**Generated by:** gsd-ui-researcher  
**Source artifacts:** CONTEXT.md, REQUIREMENTS.md, ROADMAP.md, CLAUDE.md
