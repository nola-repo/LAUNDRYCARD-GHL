# UX Contract

## Product context

- Audience: internal LaundryCard administrators.
- Primary jobs: monitor card discovery and balance eligibility, inspect contact sync, verify store routing, and review recent integration activity.
- Active interface language: English.
- Timezone policy: integration event, store scan, and run timestamps are displayed in UTC and labeled accordingly.
- Accessibility target: WCAG 2.2 AA.

## Business-context sources

No maintained PRD, ADR, or API contract was found in the repository. The Overview's read-only dashboard behavior is evidenced by `app/Http/Controllers/Admin/DashboardController.php`, its Blade fragments under `resources/views/admin/overview/`, and `public/js/overview.js`. Overview customer fields remain masked. The authenticated Customer Records directory preserves its existing name/email/phone visibility; this redesign does not change authorization or export policy.

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
| Branch route records | `admin.mappings`, `mappings.js`, `mappings.css` | `BranchMappingService::resolve`, `DashboardController::mappings` | Four-column comparison; labeled records below 1180px | Search, filters, paging, empty state, narrow viewport |
| Bulk action confirmation | `confirm-action` Blade component, `admin-actions.js`, `admin-actions.css` | Existing authenticated POST action and shared modal behavior | Branch auto-map; existing OAuth dialog is a legacy variant | Cancel, Escape, focus return, busy state, duplicate prevention |
| Compact sticky banners | `sticky-banner.js` with page CSS | Measured banner and mobile topbar heights | OAuth action bar and Branch Mapping action/navigation bar | Desktop/mobile scrolling, anchor offsets, dynamic feedback height |
| Customer directory | `admin.records`, `admin.records.row`, `records.js`, `records.css` | `DashboardController::records`, `buildRecordsQuery`, and `exportRecords` | Six-column desktop table; labeled tablet/phone records; comfortable/compact density | Server search/filter/paging, clear, disclosures, feed states, native controls, responsive review |
| CSV export options | `confirm-action` download variant, `admin-actions.js`, `admin.records.export`, `CustomerRecordsCsv` | Existing admin authorization and `buildRecordsQuery` | Native streamed GET download with selected allowlisted columns | Combined filters, all pages, column validation, no-match, keyboard focus, narrow viewport |

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

- Place the four totals in a 2×2 summary card first in the location grid. Desktop cards share equal dimensions; mobile stacks the summary before the locations while preserving the four-stat grouping.
- Show each GoHighLevel connection as a responsive location card. Preserve its status, token expiry, refresh timestamps, API success, installed date, granted scopes, mapped store, recent errors, and the existing refresh, test, and reconnect actions.
- Access and refresh tokens remain encrypted at rest and are never rendered. The connection cards show operational metadata only.
- Refresh All Tokens opens an app-owned confirmation dialog before submitting the existing batch refresh action. Copy auth URL reports success or recovery guidance in an inline status region and never displays the raw state value.
- Header actions become icon-only at viewport widths of 1024px and below. Keep their accessible names, 44px touch targets, keyboard focus, and hover/focus labels; desktop retains visible text. This responsive change preserves the existing actions and their order.
- Keep the compact action banner visible while scrolling, beneath the mobile app bar. Copy feedback appears within the banner; resize-aware offsets account for its additional height. The title links back to the page heading.
- A newly authorized location still requires an administrator to assign a LaundryCard branch before synchronization is enabled.

## Branch mapping

- The page is a monitor of the existing routing decisions from `BranchMappingService::resolve`. It does not offer manual assignment or change the service's configured-pair and connection-usability rules. Counts reflect the complete loaded branch set, including discovery statuses.
- Show branch name/ID, mapped location name/ID, routing reason, mapping origin, and OAuth status. Distinguish no mapping, unverified proposals, conflicting configuration, and unusable connections. Do not label every held route as unmapped.
- Local search matches the loaded route records, with a clear button, immediate filtering, IME-safe input, All/Ready/Held filters, and ten records per page. Persist `mapping_q`, `mapping_status`, and `mapping_page` in the URL. Reset/clamp page after filtering; Back/refresh restore the committed state. Without JavaScript, all records remain readable.
- The compact graphite action/navigation banner sticks beneath the shared mobile topbar. Resize-aware scroll offsets keep section headings and focused controls visible. The route table follows document scrolling; the unassigned and recent-history lists use bounded, keyboard-accessible scroll regions with the global scrollbar theme.
- Run auto-map opens the shared confirmation dialog. Cancel receives initial focus; Escape closes and focus returns to the trigger. Submission uses the existing CSRF-protected POST route, prevents duplicate submits, announces busy status without resizing the button, and reports the server result through the existing page flash system. Browser Back restores an enabled submit action.
- Recent decisions retain the server's latest-15 limit and show absolute UTC times. Empty and no-results states explain what to do next. No tokens or secrets are rendered.
- Unassigned locations and Recent decisions share one divided panel with aligned desktop heights and stacked panes on narrower screens. History rows lead with branch names and label the store/location IDs separately. With no unassigned locations, show a compact, truthful “No locations waiting” state and retain Review installations.

## Customer Records

- Keep the existing server-side card-number search, category presets, advanced filters, CSV export, latest-first ordering, and 15/25/50/100 pagination. Committed state lives in the URL. Category changes preserve unrelated filters, and All records removes the category while Reset all clears every filter.
- Category totals describe the complete directory before filtering and remain a page-load snapshot. Live arrivals announce their count and offer Refresh records; do not replace rows or change the active filters while someone reads the page.
- Search submits explicitly through Search records or Enter. Clear resets immediately and returns focus; a committed query is cleared through server navigation. IME confirmation does not prematurely submit. Invalid date ranges show an inline correction associated with the end date. Duplicate navigation submits show stable busy controls; Back restores them.
- Native select and date inputs retain platform-owned popups and keyboard behavior. Advanced filters open automatically only when a nonempty advanced value is committed. Dates filter first-observed timestamps; activity display uses the source timestamp with first-observed fallback and labels UTC.
- Use the shared sticky-banner measurements and shared admin-action controls. The page owns document scrolling. Tags and full location/contact identifiers remain available in native disclosures. Card numbers and reload totals link to the existing credit-history route with card and store context.
- Comfortable/compact density changes spacing only and persists under `laundrycard.records.density`; storage failure must not block the page. Empty directory and no matching records have different recovery copy.
- Preserve the existing six-second live-feed check, ten-second request timeout, hidden-page cancellation, Back/forward recovery, and 401/403 stop behavior. Inline feed errors leave the current records readable. Polling does not change customer visibility or expose tokens.
- Export CSV opens the shared native modal with the page-load matching count, applied filter/category summary, and grouped column checkboxes. It exports every matching page using the same server query as the directory; page size and page number do not limit downloads. Warn about unapplied filter edits and explain that records are checked again at download time. Zero matches disables Download CSV; zero selected columns receives inline validation and focus.
- The export schema is allowlisted in `CustomerRecordsCsv`; selected columns retain the established CSV order. Existing URLs without column choices keep the complete schema and UTF-8 BOM. Downloads use the browser's native streaming/transfer manager rather than buffering customer data in JavaScript; the inline message says only that a download was requested. Native transfer errors, completion, cancellation, and retries belong to the browser. The options stay editable and the download action remains available for repeat exports. Escape/Cancel restores trigger focus; column choices persist only while this page remains open.

## Operational workspaces

- Credit History, Integration Logs, Failed Activity, API Activity, and OAuth Diagnostics use the shared `admin-workspace` component and stylesheet. Shared width, heading, padding, and control tokens live in `admin.css`; Overview retains its established Poppins typography. The page owns scrolling, and the sticky toolbar uses the measured mobile app-bar offset for anchors and keyboard focus.
- Preserve existing authorization, server queries, latest-first ordering, and pagination: 50 credit transactions, 40 integration events, and 25 failed records per page; API Activity shows the latest five runs and 30 matching requests; OAuth Diagnostics shows the latest 20 OAuth events. Credit-history links retain card and branch context. Failed Activity keeps card numbers masked.
- Search and filters submit explicitly, with committed values in the URL. Clear buttons remove one field and return focus, preserving other filters. IME confirmation cannot prematurely submit. Native selects and date inputs use platform-owned popups. Invalid date order receives an inline error associated with the end date. Submission preserves button geometry and prevents duplicates; Back restores enabled controls. GET and POST forms retain native navigation when JavaScript is unavailable.
- Tables reflow into labeled records on smaller screens. Full messages and identifiers wrap; sanitized payloads, run summaries, and attempt histories use native keyboard-accessible disclosures. Null amounts and timestamps say they were not recorded. Negative amounts use a leading minus before the currency symbol. Empty queues do not claim that the whole integration is healthy.
- API inspection forms retain their existing CSRF-protected routes and do not create or update GHL contacts. Preview submissions are simulated. OAuth verification calls the existing authorization-URL endpoint; successful copy explicitly says it does not test the token endpoint. Use a ten-second timeout, cancellation on page exit, inline success/error/session feedback, safe text rendering, and an enabled retry action after completion.
- The standalone OAuth result preserves the existing outcome, message, and server-gated development details. Both outcomes link to installations; failure also links to OAuth logs. Show a labeled UTC timestamp and keep all credentials hidden.

## Verification

- Operational workspace migration: the full regression suite passed (110 tests, 549 assertions); after the final OAuth-result changes, dashboard/export/OAuth tests passed again (33 tests, 261 assertions). Production build, Blade compilation, JavaScript syntax, PHP formatting for changed files, whitespace checks, strict UI audit (zero findings), and DESIGN.md lint (zero errors/warnings) passed. The full 110-test suite passed again after controller formatting. Browser checks covered desktop and 320px layouts, all five empty states, search/clear and combined filters, date validation, full disclosures, simulated inspection submission and Back recovery, diagnostic success/error/retry, and OAuth result success/failure. OAuth actions remain aligned at 44px on desktop and become 44px icon controls on mobile. Preview data lives only in a guarded in-memory database; external service calls and live inspection actions were not exercised. Native mobile hardware, screen readers, and a full 200% zoom matrix were not tested.
- CSV options: 19 dashboard/export tests passed (207 assertions). A real browser download with a branch filter contained exactly 10 matching sample rows and the three selected columns. Checked empty selection feedback, Escape/Cancel focus return, unapplied-filter notice, and narrow modal scrolling with reachable actions.
- Customer Records: six-column table and labeled records reviewed at desktop, 800px, 390px, and 320px with no page overflow. Verified search and clear/focus return, branch filtering, date-range feedback, category selection, compact density, tag disclosure, pagination/page size, empty/no-match states, and feed connected/new/unavailable/session-expired states using a guarded in-memory sample preview. Existing 52 tests/338 assertions, production build, Blade compilation, and JavaScript syntax checks passed.

- Sticky-banner and support-panel update: verified desktop alignment, mobile stacking, populated/empty lists, and 320px OAuth controls with no page overflow. Both banners pin below the mobile app bar; copy feedback updates the measured offset. Refresh confirmation opens, closes with Escape, and returns focus. The sample preview performs no server actions. Existing suites passed again (52 tests, 338 assertions), along with build, Blade compilation, JavaScript syntax, strict UI audit, and design lint.
- Branch Mapping: existing dashboard, discovery/sync, and OAuth suites passed (52 tests, 338 assertions). Browser review covered desktop, tablet, 390px and 320px reflow, filters/search/clear, no-results and empty states, pagination/URL restoration, sticky section navigation, and confirmation focus/Escape/busy feedback. The preview uses sample records and simulates POST submission; production mappings were not changed. JavaScript syntax, Blade compilation, production build, strict UI audit, and DESIGN.md lint passed.
- Compared with the Customer Records screen for the existing UTC timestamp convention; Overview keeps its separate masked-field presentation.
- Verified with `node --check public/js/overview.js`, `php artisan view:cache`, `git diff --check`, and a browser preview at laptop width. The dashboard feature suite passed with 15 tests and 152 assertions.
