## Global Settings

### Reading Global Settings

`get-elementor-global-settings` returns the site's design tokens:
- `custom_colors` / `system_colors` — brand color palette
- `custom_typography` / `system_typography` — font presets
- `container_width` — max content width (typically 1140–1440px)
- `breakpoints` — mobile and tablet breakpoint values

Always check these before choosing colors — match the site's brand palette.

### Updating Global Settings

`update-elementor-global-settings` accepts `colors` and `typography` arrays. Key behaviors:

- **Colors and typography are merged** with existing items by `_id`, not replaced. To add a new color, provide a new `_id`. To update an existing one, use its existing `_id`.
- **`typography_typography` must be set to `"custom"`** for any custom typography entry — the plugin enforces this. Without it, your font settings may be silently ignored.
- **You cannot update `container_width` or `breakpoints`** through this tool — only colors and typography.

**Color format:**
```json
{ "_id": "primary", "title": "Primary", "color": "#2563EB" }
```

**Typography format:**
```json
{
  "_id": "heading",
  "title": "Heading",
  "typography_typography": "custom",
  "typography_font_family": "Inter",
  "typography_font_size": { "unit": "px", "size": 48 },
  "typography_font_weight": "700",
  "typography_line_height": { "unit": "em", "size": 1.2 }
}
```
