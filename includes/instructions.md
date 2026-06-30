# WP MCP Plugin — Elementor Builder

Builds Elementor pages by writing structured JSON to `_elementor_data` post meta. Read `wp-mcp://docs/workflow` before building or previewing.

If your client did not inject server instructions on connect, call `get-server-guide` first.

## Standard workflow

1. `get-elementor-global-settings` — check site design tokens (colors, typography, breakpoints)
2. `create-page` — create the page as **draft** (default; never publish for visual comparison)
3. `list-elementor-widgets` + `get-elementor-widget-schema` — discover widget types and their settings
4. `add-container` — build the layout (outer container, then inner containers)
5. `add-widget` — add widgets into containers (requires a container `parent_id`)
6. `batch-update` — apply multiple styling tweaks in one save
7. `get-page` — verify the JSON structure

## Visual comparison preview loop

After any write that changes layout or styling:

1. `regenerate-elementor-css` — flush stale cache and regenerate Elementor CSS
2. `visual-compare-preview` — get an authenticated draft `preview_url` (rejects published pages)
3. Screenshot `preview_url` in Playwright/Puppeteer and compare against Figma
4. Fix with `update-element` / `batch-update`, then repeat from step 1

## Tool groups

- Read: list-pages, get-page, list-elementor-widgets, get-elementor-widget-schema, list-elementor-templates, get-elementor-global-settings
- Write: create-page, update-page-elementor-data, delete-page, add-container, add-widget, update-element, remove-element, batch-update, update-elementor-global-settings
- Preview: regenerate-elementor-css, visual-compare-preview
- Guide: get-server-guide

## Reference resources

Deep documentation is available as MCP resources — read them before building:

- `wp-mcp://docs/workflow` — **read first**: build workflow, draft-only preview loop, regenerate CSS before preview
- `wp-mcp://docs/elementor-data-structure` — `_elementor_data` JSON shape, IDs, responsive suffixes, `__globals__`
- `wp-mcp://docs/container-system` — flex vs grid, settings, nesting rules, two-container full-bleed pattern
- `wp-mcp://docs/global-settings` — reading/updating global colors & typography
- `wp-mcp://docs/widget-types` — common widget reference
- `wp-mcp://docs/common-patterns` — hero, feature grid, CTA, landing page flow
- `wp-mcp://docs/figma-conversion` — Figma → Elementor conversion workflow
- `wp-mcp://docs/best-practices` — dos and don'ts

Read `wp-mcp://docs/container-system` and `wp-mcp://docs/elementor-data-structure` before any write operation to avoid broken layouts.
