---
version: alpha
colors:
  canvas: "#f1f0f2"
  surface: "#fffefe"
  ink: "#242226"
  body: "#514e54"
  muted: "#77737a"
  brandWash: "#fff1f4"
  blueWash: "#eff6ff"
  orangeWash: "#fff6e7"
  greenWash: "#edf8f2"
  primary: "#e21b41"
  primaryHover: "#c11028"
  secondary: "#1e88e5"
  ghlOrange: "#f5a623"
  ghlGreen: "#10b981"
typography:
  sans:
    fontFamily: "'Plus Jakarta Sans', 'DM Sans', system-ui, sans-serif"
  mono:
    fontFamily: "'JetBrains Mono', 'DM Mono', ui-monospace, monospace"
rounded:
  panel: "16px"
  button: "11px"
spacing:
  core: "16px"
  wide: "24px"
---

## Overview

LaundryCard ⇄ GoHighLevel helps administrators monitor card discovery, balance eligibility, store routing, and contact sync. The shared console styles in `public/css/admin.css` own the brand palette, typefaces, sidebar, and status badges. The Overview uses a dedicated stylesheet in `public/css/overview.css`.

## Colors

The production mark pairs a near-black foundation and silver wordmark with LaundryCard cherry red-pink. That red-pink owns primary actions, links, focus treatments, the featured record total, and the health/routing surfaces. The neutral porcelain canvas keeps dense operational data readable. GoHighLevel orange, blue, and green appear as restrained brand accents in run, flow, and service surfaces; status colors keep their separate meaning and always include text. Pale section washes deepen the color hierarchy without tinting the whole page.

## Typography

The Overview uses Poppins throughout its content, including controls, tables, identifiers, and detail dialogs. Use regular weight for body text, bold headings, and ExtraBold stat totals. Set the supporting line close beneath each total. Show record and run timestamps in UTC and name the timezone in the table header.

## Layout

Lead with the current feed state and refresh controls inside a graphite operations banner with a restrained red-pink accent, then show four gradient record totals in a four-column desktop grid and two-column tablet grid. At 520px and below, expose an edge of the adjacent card and provide a text button to advance through the stats. Pair system health with record flow and place store routing and recent runs directly below them. Recent cards and activity follow as full-width records. Keep all overview section links visible in a sticky rail that transforms into a flat graphite banner while scrolling.

## Elevation & Depth

Use porcelain surfaces, restrained gradients, fine graphite-gray borders, and subtle shadows. Remove glossy overlays and inset highlights. Table rows use rules and consistent vertical rhythm. Reserve filled cherry red-pink for primary actions and the featured count; use the GHL orange, blue, and green sparingly to identify integration-related measures.

## Shapes

The Overview banner has a perforated laundry-ticket lower edge and a stitched rule over graphite. This is the page's signature shape; keep surrounding panels quiet and rectangular with the shared panel radius.

## Components

- **Token ownership.** `public/css/admin.css` owns the shared runtime palette and type tokens. `DESIGN.md` mirrors those values; `public/css/overview.css` maps its four pale section washes to panel, table-header, and service-card recipes and owns the overview-only banner, layout, and dialog treatments. `public/css/installations.css` adapts the shared LaundryCard and GoHighLevel color tokens for OAuth connection cards and installation controls; it owns only that page's layout and component recipes.
- **Login.** Use a warm paper canvas with soft red, gold, blue, and green washes from the logo palette, plus faint concentric rings at the outer corners. Keep them behind a receipt-like login ticket; its notched seam and card mascot are the surface's identifying details. Keep LaundryCard red on the primary action and let the mascot carry the GoHighLevel colors. Keep supporting copy specific to store discovery, branch mappings, and contact sync.
- **Buttons.** Use text labels and branded surfaces. Do not add decorative arrow icons to overview actions.
- **Branch mapping.** Use a graphite operations header with compact inline totals and a persistent, arrow-free action/section bar. The signature is the paired branch and destination record: names lead, monospace IDs sit beneath them, and routing decisions explain a hold in plain language. Keep the four-column desktop comparison surface; reflow each record into labeled fields on tablet and phone. Use Plus Jakarta Sans for headings/body and JetBrains Mono only for identifiers. Cherry identifies branch/actions, blue identifies destinations, and green/amber retain their ready/held meanings. `mappings.css` owns this page's layout and adapts shared `admin.css` tokens. Motion is limited to brief control feedback, with reduced-motion support.
- **Shared confirmation actions.** `admin-actions.css`, `admin-actions.js`, and the `confirm-action` Blade component own text-only controls and native modal confirmation for new bulk workflows. Use 44px targets, Cancel first, stable busy geometry, and the shared brand/focus palette. Existing OAuth and Overview dialogs remain their documented legacy variants.
- **OAuth header.** Keep the logo in the app shell and the title and description together above the divider. Group all four actions with equal spacing in the lower toolbar, placing Connect location immediately after Copy auth URL, before the quieter encryption note. At viewport widths up to 1024px, show the four actions as one row of 44px icon buttons with accessible names and hover/focus labels. Desktop keeps the visible text labels. Stack the security note according to the header's available width.
- **OAuth summary.** Place the four installation totals in one 2×2 summary card as the first item of the connected-location grid. Match the branch cards' width and height on desktop, with equal quadrants, compact numbers, and subtle brand-colored surfaces. Echo the Overview's large line-art icons at the lower right of each quadrant, fading them away from the text. Add a fine top accent in the quadrant's color and keep labels clearly readable. On phones, keep the summary first and its four quadrants together above the stacked location cards.
- **Overview stat cards.** Use cherry red for the lead metric, berry for new customers, blue for eligible balances, and green for zero failures. Switch the failure card to red when failures appear. Place the heading at the top and group the value and its caption at the lower left, with a faint line-art watermark at the lower right.
- **Live updates.** Show the last successful refresh time. Pause keeps the current snapshot available for reading. Briefly highlight changed counts and incoming or edited rows while preserving focus.
- **System health and record details.** Keep service health cards expanded in the page. Transparent horizontal ellipsis actions sit at each card’s top right and open a shared read-only detail dialog with additional service diagnostics and per-row information for stores, runs, customer cards, and events. Preserve masked customer details.
- **Responsive records.** At tablet and phone widths, convert Overview table rows into labeled record cards so values remain discoverable without horizontal scrolling. At larger widths, preserve aligned tables and stable headers.
- **Scrollbars.** Share a rounded LaundryCard cherry-to-GHL-blue thumb, soft rose track, and clear hover state across the page, sidebar, and Overview table regions. Keep table scrolling keyboard-accessible and contained.
- **Recent runs.** Show the eight newest jobs grouped by UTC date in a two-column grid at desktop widths; use one column on narrow panels. Keep all rows visible and let the panel flow with the page so it balances naturally beside Store routing.
- **Navigation shell.** Keep the desktop sidebar at its full 260px width with labels visible. On narrow screens, reserve a non-shrinking 44px menu control beside the flexible logo lockup so the navigation icon never covers page content. Keep the drawer's expanded state synchronized for assistive technology and close it with Escape.
- **Section navigation.** When pinned, the charcoal banner shows the page title and wraps the section links inside rounded lower corners. Center the links on phones and tablets.
- **Motion.** Use brief entry and change feedback. Honor reduced motion and keep server-rendered content visible before scripts run.
