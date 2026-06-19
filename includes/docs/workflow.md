## Typical Workflow: Building a Page

1. `get-elementor-global-settings` — check site's design tokens (colors, typography, breakpoints)
2. `create-page` — make the page with title, slug, and optionally `template: "elementor_canvas"` for landing pages
3. `list-elementor-widgets` — see available widgets (installed plugins may add more)
4. `get-elementor-widget-schema` — check settings for each widget type you plan to use
5. For each section:
   a. `add-container` — create the outer section container (use `parent_id: "root"` or omit for top-level)
   b. `add-container` — create inner layout containers if needed
   c. `add-widget` — add widgets into containers (requires `parent_id` of a container)
6. `batch-update` — apply multiple styling tweaks at once for performance
7. `get-page` — verify the final result

### When to Use `update-page-elementor-data`

Use `add-container` and `add-widget` for building pages incrementally. Use `update-page-elementor-data` only when you have a complete, pre-built Elementor JSON tree to apply in one operation (e.g., from a template or programmatic generation). **This is a full replacement — all existing content is overwritten.**

### When to Use `create-page` with `initial_elementor_data`

`create-page` accepts an `initial_elementor_data` array — a complete Elementor JSON tree that seeds the page at creation time. This is useful when you have the full page structure ready and want to avoid many individual `add-container`/`add-widget` calls.

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

Returns `{ "success": true, "post_id": 42, "updated": 3, "failed": [] }`. Check the `failed` array for any operations that couldn't be applied.
