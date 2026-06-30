## Typical Workflow: Building a Page

**Read this resource before building or previewing.** It defines the required draft-only preview loop and when to regenerate Elementor CSS.

1. `get-elementor-global-settings` — check site's design tokens (colors, typography, breakpoints)
2. `create-page` — make the page with title, slug, and optionally `template: "elementor_canvas"` for landing pages. **Always use `status: "draft"`** (the default) — never publish pages created via MCP for visual comparison workflows.
3. `list-elementor-widgets` — see available widgets (installed plugins may add more)
4. `get-elementor-widget-schema` — check settings for each widget type you plan to use
5. For each section:
   a. `add-container` — create the outer section container (use `parent_id: "root"` or omit for top-level)
   b. `add-container` — create inner layout containers if needed
   c. `add-widget` — add widgets into containers (requires `parent_id` of a container)
6. `batch-update` — apply multiple styling tweaks at once for performance
7. `get-page` — verify the JSON structure (not the visual render)

## Visual Comparison Preview Loop (required after writes)

After creating or changing Elementor content, **always regenerate CSS before previewing**. Elementor caches per-page CSS in `_elementor_css`; MCP write tools invalidate that cache, but the new CSS is not generated until you explicitly regenerate it or load the page in a browser.

### Draft-only rule

- **Never publish** pages built via MCP for Figma → Elementor visual comparison.
- Keep pages in **`draft`** (or `private` if needed). `visual-compare-preview` **rejects published pages**.
- Use the **authenticated preview URL** from `visual-compare-preview` — not the public permalink — for screenshots and design evaluation.

### Preview sequence (call in this order)

1. **After any write** (`add-container`, `add-widget`, `update-element`, `batch-update`, `update-page-elementor-data`): call **`regenerate-elementor-css`** for the page. Confirm `css_ready: true` in the response.
2. Call **`visual-compare-preview`** to get a time-limited `preview_url` and Elementor element selectors.
3. Open `preview_url` in a headless browser (Playwright, Puppeteer) and take screenshots.
4. Compare screenshots against the Figma design (`get_screenshot` from Figma MCP). Evaluate differences.
5. Fix with `update-element` / `batch-update`, then **repeat from step 1** (regenerate CSS → preview → screenshot → evaluate).

If `visual-compare-preview` returns `css_ready: false`, call `regenerate-elementor-css` first and retry.

### When to Use `update-page-elementor-data`

Use `add-container` and `add-widget` for building pages incrementally. Use `update-page-elementor-data` only when you have a complete, pre-built Elementor JSON tree to apply in one operation (e.g., from a template or programmatic generation). **This is a full replacement — all existing content is overwritten.** After calling it, run `regenerate-elementor-css` before preview.

### When to Use `create-page` with `initial_elementor_data`

`create-page` accepts an `initial_elementor_data` array — a complete Elementor JSON tree that seeds the page at creation time. This is useful when you have the full page structure ready and want to avoid many individual `add-container`/`add-widget` calls. Use `status: "draft"` and run `regenerate-elementor-css` before the first preview.

## Batch Updates

Use `batch-update` instead of many individual `update-element` calls.
It accepts an array of `{element_id, settings}` operations applied in one save:

```json
{
  "post_id": 42,
  "operations": [
    { "element_id": "abc1234", "settings": { "background_color": "#f5f5f5" } },
    { "element_id": "def5678", "settings": { "title": "Updated Title" } }
  ]
}
```

Returns `{ "success": true, "post_id": 42, "updated": 3, "failed": [] }` when at least one operation applied. `success` is `false` when every operation failed (`updated` is 0). Check `failed` for operations that could not be applied. **After batch-update, call `regenerate-elementor-css` before preview.**
