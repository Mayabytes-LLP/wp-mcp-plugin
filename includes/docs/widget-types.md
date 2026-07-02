## Widget Types

Always use `list-elementor-widgets` to discover available widget types — installed plugins add widgets beyond Elementor's built-in set. Then use `get-elementor-widget-schema` with the widget type key to see all controllable properties.

> **Note:** Repeater fields (like `icon-list`'s items) are not shown in the schema output. When using widgets with repeaters, inspect the `get-page` output for an existing instance to see the expected structure.

### Repeater field shape (icon-list, social-icons, accordion, tabs, toggle, form, gallery, etc.)

Repeater fields must be a **flat array of item objects**, each with an `_id` (8-char hex) and the per-item fields. They are NOT wrapped in `{"item":[…]}` — that envelope breaks Elementor's CSS writer (`add_controls_stack_style_rules` → `null` argument fatal in `elementor/core/files/css/base.php:842`).

**Correct — `icon-list`:**
```json
{
  "view": "inline",
  "icon_list": [
    {
      "_id": "a1b2c3d4",
      "text": "support@nexabyte.io",
      "selected_icon": { "value": "fas fa-envelope", "library": "fa-solid" }
    },
    {
      "_id": "e5f6g7h8",
      "text": "+1 212 946 2707",
      "selected_icon": { "value": "fas fa-phone", "library": "fa-solid" }
    }
  ]
}
```

**Correct — `social-icons`:**
```json
{
  "social_icon_list": [
    {
      "_id": "11112222",
      "social_icon": { "value": "fab fa-facebook", "library": "fa-brands" },
      "link": { "url": "https://facebook.com/...", "is_external": true }
    }
  ]
}
```

**Correct — `accordion` / `tabs` / `toggle`:**
```json
{
  "tabs": [
    { "_id": "tab1id01", "tab_title": "What we do", "tab_content": "<p>...</p>" }
  ]
}
```

`add-widget` and `update-element` will auto-unwrap the `{"item":[…]}` shape if you pass it, so the wrong shape is forgiving on write — but the flat shape is what gets persisted. To check existing pages for the wrong shape (left over from a prior session), call `get-elementor-debug-info`; it returns `bad_repeaters[]` with the offending `element_id` and `field`.

### Common Widget Reference

| Widget | Key | Purpose | Key Settings |
|--------|-----|---------|--------------|
| Heading | `heading` | Page titles, section headings | `title`, `header_size` (h1–h6), `align`, `title_color`, `typography_typography` |
| Text Editor | `text-editor` | Body copy, paragraphs, rich text | `editor` (HTML content), `align`, `text_color` |
| Button | `button` | Call-to-action buttons | `text`, `link`, `icon`, `icon_align`, `button_text_color`, `background_color`, `border_radius` |
| Image | `image` | Photos, illustrations, logos | `image` (media object), `image_size`, `alt`, `link_to`, `align` |
| Icon Box | `icon-box` | Feature cards (icon + title + description) | `icon`, `title_text`, `description_text`, `view`, `icon_position` |
| Image Box | `image-box` | Cards with photo + title + description | `image`, `title_text`, `description_text`, `image_position` |
| Icon List | `icon-list` | Bullet lists, contact details, social links | `view` (vertical/inline), `icon_list` (repeater) |
| Spacer | `spacer` | Vertical spacing | Avoid — use container `gap` or `padding` instead |
| Divider | `divider` | Visual separator | `style`, `weight`, `color`, `width` |
