---
version: alpha
colors:
  canvas: "#e6ebf5"
  surface: "#eef2fa"
  ink: "#1e2440"
  body: "#434b6e"
  muted: "#606b8a"
  primary: "#525fe1"
  secondary: "#2596be"
  success: "#1f9d6e"
typography:
  sans:
    fontFamily: "'Plus Jakarta Sans', 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "14px"
    lineHeight: "1.58"
  mono:
    fontFamily: "'JetBrains Mono', 'DM Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
rounded:
  control: "16px"
  card: "22px"
  pill: "9999px"
spacing:
  gridGap: "16px"
  cardPadding: "23px"
  sectionTop: "42px"
components:
  overview-card:
    backgroundColor: "#eef2fa"
    borderRadius: "22px"
    borderColor: "rgba(110, 125, 175, 0.15)"
  dashboard-heading:
    fontFamily: "'Plus Jakarta Sans', 'DM Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
    fontSize: "31px"
  technical-metadata:
    fontFamily: "'JetBrains Mono', 'DM Mono', ui-monospace, SFMono-Regular, Menlo, Consolas, monospace"
---

## Overview

LaundryCard ⇄ GoHighLevel is an administrative console for monitoring the one-way flow of store balances and customer records. The product register is operational: make service health, current counts, store routing, and recent activity easy to scan. The overview follows the real flow from integration health through metrics and data flow to stores, detected cards, and activity.

Keep the established tactile clay language: cool blue-gray surfaces, soft raised cards, shallow recessed wells, and LaundryCard indigo accents. Treat depth as a quiet grouping cue so status and data remain more prominent than decoration. The two-system health matrix and live telemetry capsule are the distinctive product cues; retain their source/destination meaning and status behavior.

## Colors

The runtime palette lives in `public/css/admin.css`. The root variables are the source for the console palette; the overview locally darkens muted text for its smaller labels while preserving the indigo and cyan brand accents.

- Canvas `#e6ebf5`, clay surface `#eef2fa`, and recessed well `#e3e8f4` separate page, card, and inset surfaces.
- Ink `#1e2440` and body `#434b6e` carry primary and supporting text.
- LaundryCard indigo `#525fe1` is the primary action and navigation accent; GoHighLevel cyan `#2596be` is the secondary system accent.
- Status colors keep their meaning: success `#1f9d6e`, warning `#d48217`, error `#d9485f`, and information `#2d7fd3`. Pair color with status text or a shape.

## Typography

Use Plus Jakarta Sans for headings, labels, interface copy, and dashboard totals. Use JetBrains Mono for identifiers and technical metadata. The overview title is 31px on wider screens and 26px on narrow screens; section titles are 20px; card titles are 16.5px; supporting copy stays near 13–14px. Numeric values use tabular figures. Keep labels readable and secondary text dark enough against the pale clay surface.

## Layout

The dashboard is a monitoring surface, not a marketing page. Keep the sticky desktop side rail, then let the overview breathe across the remaining width. Preserve the scan order: live state and section links, integration health, key metrics, pipeline, store routing, detected cards, and activity. Use a 16px card-grid gap and about 42px above each section heading. At narrow widths, the application shell becomes its existing mobile navigation and card grids reflow without changing the order of information.

## Elevation & Depth

Cards use one soft outer lift, a fine blue-gray edge, and the existing pale clay fill. The overview shadow is defined by `--overview-card-shadow` on `.overview-container`; it maps to overview cards in `public/css/admin.css`. Recessed wells continue to use the existing inset shadow tokens. Avoid stacking strong shadows on nested elements or using depth to imply interactivity on non-controls.

## Shapes

Use the runtime radius family in `public/css/admin.css`: 9px, 13px, 16px, 22px, and 26px. Overview cards use the existing 22px large radius; compact controls use 16px; the telemetry capsule keeps its pill shape; status dots remain circular. Preserve these soft forms as part of the established tactile identity.

## Components

- **Overview cards:** keep health, KPI, pipeline, and store cards on the same clay surface and lighter overview shadow. Use card padding near 23px on desktop and 18px on narrow screens.
- **Telemetry:** retain the live indicator, polling detail, and refresh action together. Motion communicates connection or refresh state and respects the shared reduced-motion rule.
- **Status:** preserve explicit text and semantic color for active, warning, and error states. Never communicate status by color alone.
- **Tables and activity:** keep dense records readable and aligned; use the monospace face only for technical values or compact event metadata.

## Do's and Don'ts

- Do preserve the LaundryCard ⇄ GoHighLevel identity and the current monitoring/navigation behavior.
- Do prioritize status, readable labels, aligned values, and clear section spacing.
- Do use the clay effect sparingly: one raised layer for a card and a shallow inset for grouped details.
- Don't replace the cool indigo palette with a new theme, introduce decorative laundry imagery, or add motion that does not clarify state.
- Don't shrink technical text to make a dense card fit; allow long values to wrap within their row.
