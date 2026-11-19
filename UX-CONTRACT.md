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
| Scrollbar | Global admin tokens plus Overview geometry | `admin.css`, `overview.css` | Table regions may scroll within aligned panels | Computed style and keyboard scrolling |
| Record details | One Overview read-only dialog | `overview.blade.php`, `overview.js` | Service card or table record content | Keyboard open, close, Escape, and focus return |
| CRUD | Not used on Overview | Admin routes and sibling pages | Out of Overview scope | Sibling workflow evidence |

## Overview behavior

- Navigation links point to page sections and remain sticky while scrolling. On narrow screens they wrap so every section link stays visible.
- System health is always visible as four service cards. Additional diagnostics open in the shared read-only detail dialog.
- Store, run, customer-card, and event rows each provide a labeled Details action. Customer identifiers and contact information remain masked.
- The dialog uses semantic `<dialog>` behavior: it contains focus while open, closes with Escape or its Close button, and returns focus to its trigger or the containing table context if live updates replace the row.
- Table regions scroll independently with sticky column headers. Related store-routing and recent-run tables share a grid row and stretch to equal panel height.
- Recent-card filtering is client-side, clears through the labeled Clear button, and is not written to the URL. Section navigation may update the hash.
- Empty table states remain visible. A filter with no matches announces its result count and displays a no-match row.

## Live updates and recovery

- Poll the authenticated Overview every four seconds. Keep the current snapshot readable while refreshing.
- Pause stops automatic updates without clearing the snapshot. Refresh performs a one-shot request; requests time out after ten seconds.
- Stop pending work when the page becomes hidden or is left. Retry transient failures on the next interval.
- A 401 pauses polling and offers Sign in. A 403 pauses polling and announces Access denied. Do not show raw credentials or unmasked customer data.
- Preserve focused row actions through updates; after a removed row, return focus to its panel heading when possible.

## Navigation and responsive behavior

- Route titles use `{Page} · LaundryCard ⇄ GHL` through the shared app layout.
- Desktop keeps the admin sidebar. Narrow layouts use the shared sticky mobile topbar and drawer.
- Overview tables keep their columns and scroll horizontally when needed; they do not collapse records into unlabeled cards.
- Section scroll offsets account for the sticky section rail and mobile topbar so headings remain visible after anchor navigation.

## Verification

- Compared with the Customer Records screen for masked customer fields and the existing UTC timestamp convention.
- Verified with `node --check public/js/overview.js`, `php artisan view:cache`, `git diff --check`, and a browser preview at laptop width. The dashboard feature suite passed with 15 tests and 152 assertions.
