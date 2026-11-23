# UX Contract

## Product context

- Audience: internal LaundryCard administrators.
- Primary jobs: monitor card discovery and balance eligibility, inspect contact sync, verify store routing, and review recent integration activity.
- Active interface language: English.
- Timezone policy: integration event, store scan, and run timestamps are displayed in UTC and labeled accordingly.
- Accessibility target: WCAG 2.2 AA.

## Business-context sources

No maintained PRD, ADR, or API contract was found in the repository. The Overview's read-only dashboard behavior is evidenced by `app/Http/Controllers/Admin/DashboardController.php`, its Blade fragments under `resources/views/admin/overview/`, and `public/js/overview.js`. Customer details remain masked as in `resources/views/admin/records.blade.php`.

## Visual contract

- Design context: `DESIGN.md`.
- Runtime owner: `public/css/admin.css` owns shared palette, typography, and shell tokens; `public/css/overview.css` adapts those tokens for the Overview.
- Token mapping: `DESIGN.md` mirrors shared CSS values. Overview-specific layout, banner, and dialog recipes stay in `overview.css`.
- Supported theme: light porcelain surfaces with LaundryCard graphite and red-pink brand accents.

## Canonical UI map

| Capability | Canonical owner | Source of truth | Allowed variants | Verification |
|---|---|---|---|---|
| Table selection | Not used on Overview | Overview Blade tables | Read-only rows | Keyboard and narrow viewport review |
| Sign-in form | Laravel auth route/controller, login Blade, and `login.js` | Authentication behavior below | Responsive brand panel, inline field errors, password reveal | Keyboard and narrow viewport review |
| OAuth installations | `admin.installations` Blade view and `HighLevelOAuthService` | OAuth connection lifecycle and encrypted `GhlConnection` fields | Responsive location cards with token, mapping, scope, and error details | Preserve connection actions; confirmation dialog for refresh-all |
| Scrollbar | Shared admin palette plus Overview geometry | `admin.css`, `overview.css` | Rounded brand-colored thumbs on page, sidebar, and table regions; keyboard-accessible contained table scrolling | Computed style and keyboard scrolling |
| Record details | One Overview read-only dialog | `overview.blade.php`, `overview.js` | Service card or table record content | Keyboard open, close, Escape, and focus return |
| CRUD | Not used on Overview | Admin routes and sibling pages | Out of Overview scope | Sibling workflow evidence |

## Overview behavior

- Navigation links point to page sections and remain sticky while scrolling. The pinned banner displays “Integration overview” alongside the section links, or above them when space is limited. On mobile it sits beneath the app menu and centers the wrapped links.
- System health is always visible as four service cards. Each card keeps a transparent horizontal ellipsis action pinned at its top right; the status pill sits below the service name. Additional diagnostics open in the shared read-only detail dialog.
- Store, run, customer-card, and event rows each provide a read-only detail dialog through an icon-only ellipsis control; the button has a full accessible label. Customer identifiers and contact information remain masked.
- The dialog uses semantic `<dialog>` behavior: it contains focus while open, closes with Escape or its Close button, and returns focus to its trigger or the containing table context if live updates replace the row.
- On desktop, Store routing and Recent runs share a grid row and align at both edges. Recent runs shows the eight newest jobs grouped by UTC date in two columns, with rows using the available panel height and no nested scrolling. At tablet and phone widths, the panels stack and Recent runs switches to one column; records become labeled cards so table data is readable without horizontal scrolling.
- Every Details action uses a labeled three-dot icon button and opens the shared dialog. The mobile navigation toggle remains separate from page content, reports its expanded state, and closes on Escape.
- Recent-card filtering is client-side, clears through the labeled Clear button, and is not written to the URL. Section navigation may update the hash.
- Empty table states remain visible. A filter with no matches announces its result count and displays a no-match row.

## Live updates and recovery

- Poll the authenticated Overview every four seconds. Keep the current snapshot readable while refreshing.
- Pause stops automatic updates without clearing the snapshot. Refresh performs a one-shot request; requests time out after ten seconds.
- Stop pending work when the page becomes hidden or is left. Retry transient failures on the next interval.
- A 401 pauses polling and offers Sign in. A 403 pauses polling and announces Access denied. Do not show raw credentials or unmasked customer data.
- Preserve focused row actions through updates; after a removed row, return focus to its panel heading when possible.

## Authentication

- The `/login` screen is available to guests and uses the LaundryCard mascot and palette tokens from `admin.css`. Use the mascot inside the ticket illustration without a separate page header. On narrow screens, the illustration moves below the form.
- Keep the primary action labeled `Sign in` and use the logo's own transfer arrows as the only transfer-arrow illustration on the page.
- Only administrators can sign in. Keep the password masked by default, allow a labeled show/hide control, and never repopulate a password after a failed request.
- Show validation and credential errors beside the affected field, associate them with the input, preserve the entered email, and focus the first invalid field. Prevent duplicate submits while a request is in progress.
- Logout is an authenticated POST action. Login attempts are throttled by the server.

## Navigation and responsive behavior

- Route titles use `{Page} · LaundryCard ⇄ GHL` through the shared app layout.
- Desktop keeps a fixed-width, always-expanded admin sidebar. Narrow layouts use the shared sticky mobile topbar and drawer.
- At widths up to 1180px, Overview tables retain native table semantics but visually reflow each record into a labeled card. The ellipsis action sits in a reserved top-right slot and never occupies a narrow data column. At wider widths, aligned table columns remain visible with their existing scroll regions.
- Section scroll offsets account for the sticky section rail and mobile topbar so headings remain visible after anchor navigation.

## OAuth installations

- Show each GoHighLevel connection as a responsive location card. Preserve its status, token expiry, refresh timestamps, API success, installed date, granted scopes, mapped store, recent errors, and the existing refresh, test, and reconnect actions.
- Access and refresh tokens remain encrypted at rest and are never rendered. The connection cards show operational metadata only.
- Refresh All Tokens opens an app-owned confirmation dialog before submitting the existing batch refresh action. Copy auth URL reports success or recovery guidance in an inline status region and never displays the raw state value.
- A newly authorized location still requires an administrator to assign a LaundryCard branch before synchronization is enabled.

## Verification

- Compared with the Customer Records screen for masked customer fields and the existing UTC timestamp convention.
- Verified with `node --check public/js/overview.js`, `php artisan view:cache`, `git diff --check`, and a browser preview at laptop width. The dashboard feature suite passed with 15 tests and 152 assertions.
