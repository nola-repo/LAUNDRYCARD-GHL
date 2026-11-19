# Overview QA critique

Source inspected before the redesign: commit `6bcd6ce`, admin Overview. The operator needs to answer three questions quickly: did discovery run, can contacts sync, and which records need attention?

| Priority | Finding | Effect on the operator | Correction |
| --- | --- | --- | --- |
| High | The navigation says “Stores (5)”, the routing card says “100% Routed”, and the distribution bar always fills its baseline segment. See the original Overview view at lines 61, 458, and 668. | A store can be unmapped while the dashboard reports full coverage. The chart can contradict its own counts. | Use persisted counts, separate balance eligibility from historical classification, and handle an empty dataset. |
| High | The original live poll clears the customer table and marks every replacement row as new. The search handler only runs on input. See lines 1098 and 1227. | A poll undoes the filter while the query remains visible. Repeated flashes hide the arrival of a real new card. | Keep stable row identities, retain the query, and highlight only changed values or incoming rows. |
| Medium | The page puts four detailed health cards ahead of the first record count. Multiple headings, badges, and captions repeat the same information. | The operator has to scan several screens to compare volume, eligibility, and sync failures. | Lead with counts, group diagnostic details behind a disclosure, and bring recent records closer to the top. |
| Medium | Initial and live table rows use different eligibility badges and timestamp formats. The empty state mentions a fixed 30,305 records. | The meaning and appearance of a row can change after the first poll. | Share one masked row presentation for initial and live results, use a named timezone, and distinguish empty data from an empty search. |
| Medium | The activity badge and footer announce a live connection before a response arrives. Motion also hides entire sections until a scroll observer runs. | Static or disconnected data can look current. Tall sections may stay invisible. | Show actual connection state, last successful refresh, pause/resume, and brief motion tied to changes. Render content visibly before JavaScript starts. |

## Verification after implementation

The dashboard feature suite passed all 15 tests and 152 assertions using in-memory SQLite. The new regression covers guest and non-admin access to the Overview snapshot, the zero-record state, masked row parity between HTML and refresh, and matching totals across the two dashboard feeds.

Headless Chrome opened the local sign-in page and authenticated against a separate SQLite database containing synthetic stores, cards, and events. The page fit at 320, 390, 768, 1024, and 1440 CSS pixels. At 320 pixels the records table scrolled inside its panel. Desktop and phone screenshots were inspected after layout transitions settled.

Browser interactions covered adding a card during an active filter, preserved row identity, opening diagnostics with the keyboard, no-result and clear states, timed polling, pause/resume, manual refresh, simulated connection failure, session expiry and recovery, reduced motion, and loading without JavaScript. External font requests were blocked for these checks, so the screenshots exercise the local fallback font stack.
