---
version: alpha
colors:
  canvas: "#f1f0f2"
  surface: "#fffefe"
  ink: "#242226"
  body: "#514e54"
  muted: "#77737a"
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

The production mark pairs a near-black foundation and silver wordmark with LaundryCard cherry red-pink. That red-pink owns primary actions, links, focus treatments, and the featured record total. The neutral porcelain canvas keeps dense operational data readable. GoHighLevel orange, blue, and green appear as restrained brand accents; status colors keep their separate meaning and always include text.

## Typography

Plus Jakarta Sans carries headings, labels, and tabular counts. JetBrains Mono distinguishes masked card numbers, store IDs, and event stages. Show record and run timestamps in UTC and name the timezone in the table header.

## Layout

Lead with the current feed state and refresh controls inside a graphite operations banner with a red-pink glow, then show four record totals. Pair system health with record flow; place store routing and recent runs directly below them. Recent cards and activity follow as full-width tables. Keep all overview section links visible in a sticky rail that wraps on narrow screens.

## Elevation & Depth

Use a porcelain surface, fine graphite-gray border, and one soft shadow per panel. Table rows use rules and consistent vertical rhythm. Reserve filled cherry red-pink for primary actions and the featured count.

## Shapes

The Overview banner has a perforated laundry-ticket lower edge and a stitched rule over graphite. This is the page's signature shape; keep surrounding panels quiet and rectangular with the shared panel radius.

## Components

- **Token ownership.** `public/css/admin.css` owns the shared runtime palette and type tokens. `DESIGN.md` mirrors those values; `public/css/overview.css` owns the overview-only banner, layout, and dialog recipes.
- **Buttons.** Use text labels and branded surfaces. Do not add decorative arrow icons to overview actions.
- **Live updates.** Show the last successful refresh time. Pause keeps the current snapshot available for reading. Briefly highlight changed counts and incoming or edited rows while preserving focus.
- **System health and record details.** Keep service health cards expanded in the page. A shared read-only detail dialog provides additional service diagnostics and per-row information for stores, runs, customer cards, and events. Preserve masked customer details.
- **Tables.** Keep related tables in aligned two-column panels where space permits. Tables scroll inside their panels and keep stable header and row geometry.
- **Motion.** Use brief entry and change feedback. Honor reduced motion and keep server-rendered content visible before scripts run.
