## Widget Types

Always use `list-elementor-widgets` to discover available widget types — installed plugins add widgets beyond Elementor's built-in set. Then use `get-elementor-widget-schema` with the widget type key to see all controllable properties.

> **Note:** Repeater fields (like `icon-list`'s items) are not shown in the schema output. When using widgets with repeaters, inspect the `get-page` output for an existing instance to see the expected structure.

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
