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

- Read: list-pages, get-page, list-elementor-widgets, get-elementor-widget-schema, list-elementor-templates, get-elementor-global-settings, get-elementor-debug-info
- Write: create-page, update-page-elementor-data, delete-page, add-container, add-widget, update-element, remove-element, batch-update, update-elementor-global-settings
- Preview: regenerate-elementor-css, visual-compare-preview
- Guide: get-server-guide

## When something goes wrong

**All debugging goes through MCP. Do not read files or use shell commands to inspect the WordPress install.** The right tool exists for every case:

- `regenerate-elementor-css` returned `bad_repeater_shape` → call **`get-elementor-debug-info`** with the page's `post_id`. It returns `bad_repeaters[]` with the offending `element_id`, `widget_type`, `field`, and the wrong `shape`. Fix each one with `update-element`, passing the value as a flat array (not wrapped in `{"item":[…]}`). Then re-run regenerate-elementor-css.
- `regenerate-elementor-css` returned `css_generation_failed` with `add_controls_stack_style_rules` in the underlying error → same fix: `get-elementor-debug-info` first.
- A tool returned `element_not_found` or `parent_not_found` → call `get-page` to get the current `element_id`s; an earlier `update-element` / `remove-element` may have invalidated them.
- A Figma read failed with "nothing selected" → the Figma Desktop app must have the target layer selected before you call the tool. Open the file, click the frame, retry.

## Reference resources

Deep documentation is available as MCP resources — read them before building:

- `wp-mcp://docs/workflow` — **read first**: build workflow, draft-only preview loop, regenerate CSS before preview
- `wp-mcp://docs/elementor-data-structure` — `_elementor_data` JSON shape, IDs, responsive suffixes, `__globals__`
- `wp-mcp://docs/container-system` — flex vs grid, settings, nesting rules, two-container full-bleed pattern
- `wp-mcp://docs/global-settings` — reading/updating global colors & typography
- `wp-mcp://docs/widget-types` — common widget reference (incl. correct `icon_list` shape)
- `wp-mcp://docs/common-patterns` — hero, feature grid, CTA, landing page flow
- `wp-mcp://docs/figma-conversion` — Figma → Elementor conversion workflow
- `wp-mcp://docs/best-practices` — dos and don'ts

Read `wp-mcp://docs/container-system` and `wp-mcp://docs/elementor-data-structure` before any write operation to avoid broken layouts.

## Repeater fields (`icon_list`, `social_icon_list`, `tabs`, …)

Several Elementor widgets store lists (icon list rows, accordion tabs, social icon rows, form fields, gallery images, slider slides, menu items). Their values must be **flat arrays of item objects**, not wrapped in `{"item":[…]}` or `{"items":[…]}`.

Correct:
```json
{ "icon_list": [ { "_id": "abc12345", "text": "Item 1", "selected_icon": { "value": "fas fa-check" } } ] }
```

Wrong (this breaks CSS generation):
```json
{ "icon_list": { "item": [ { "text": "Item 1" } ] } }
```

`add-widget`, `update-element`, and `batch-update` will **auto-unwrap** the envelope shape if you pass it, so this is forgiving — but the unwrapped flat array is what gets persisted, so prefer that from the start. `get-elementor-debug-info` flags any page where the wrong shape is already on disk.
