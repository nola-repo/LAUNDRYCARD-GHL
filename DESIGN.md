---
version: alpha
colors:
  canvas: "#e6ebf5"
  surface: "#f7f9fe"
  ink: "#1e2440"
  body: "#434b6e"
  muted: "#535f7c"
  primary: "#525fe1"
  secondary: "#2596be"
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

Keep the cool clay canvas and indigo action color. The featured record total uses deep indigo with a quiet outline of a stored-value card. Success, warning, and failure badges pair semantic color with explicit text.

## Typography

Plus Jakarta Sans carries headings, labels, and tabular counts. JetBrains Mono distinguishes masked card numbers, store IDs, and event stages. Show record and run timestamps in UTC and name the timezone in the table header.

## Layout

Lead with the current feed state, refresh controls, and four record totals. Pair system health with record flow where space permits. Recent cards and activity get full-width tables. Store routing and recent runs follow as supporting detail. Tables scroll inside their panels, and the mobile layout preserves reading order.

## Elevation & Depth

Use the brighter clay surface, a fine blue-gray border, and one soft shadow per panel. Table rows use rules. Reserve filled indigo for the primary refresh action and the featured count.

## Components

- **Live updates.** Show the last successful refresh time. Pause keeps the current snapshot available for reading. Briefly highlight changed counts and incoming or edited rows.
- **System health.** Each service has a named status. Native disclosures keep diagnostics available to keyboard and pointer users.
- **Recent cards.** The initial page and subsequent snapshots render the same masked row template. Keep the filter, focused control, and open disclosures stable across updates.
- **Motion.** Use brief entry, disclosure, and change feedback. Honor reduced motion and keep server-rendered content visible before scripts run.
