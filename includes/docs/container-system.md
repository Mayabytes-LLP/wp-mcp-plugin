## Container System

### Flexbox vs Grid

Use **flex containers** for one-dimensional layouts (row OR column):
- Navigation bars, headers, footers
- Centering content
- Side-by-side layouts that wrap on mobile

Use **grid containers** for two-dimensional layouts (rows AND columns):
- Uniform multi-column feature grids (3×3, 4×2, etc.)
- Photo galleries with equal cells
- Magazine layouts with items spanning multiple columns

### Container Settings

These are the most common settings for `add-container`. Call `get-elementor-widget-schema` with widget type `container` for the complete list — Elementor has many more controls (borders, shadows, gradients, CSS filters, animations, responsive visibility, etc.) that aren't listed here.

| Setting | Values | Purpose |
|---------|--------|---------|
| `flex_direction` | `column`, `row` | Layout direction |
| `content_width` | `boxed`, `full` | Boxed centers content; full stretches edge-to-edge |
| `flex_wrap` | `wrap`, `nowrap` | Whether children wrap to new lines |
| `align_items` | `flex-start`, `center`, `flex-end`, `stretch` | Cross-axis alignment |
| `justify_content` | `flex-start`, `center`, `flex-end`, `space-between`, `space-around`, `space-evenly` | Main-axis alignment |
| `gap` | Dimensions object or string | Space between children |
| `padding` | Dimensions object | Inner spacing |
| `margin` | Dimensions object | Outer spacing |
| `background_color` | Hex string | Background fill |
| `background_image` | Media object | Background image |

> **Default container padding is 10px on all sides.** This compounds when nesting. Always check and remove it when not needed by setting `padding` to `{"unit":"px","top":"0","right":"0","bottom":"0","left":"0","isLinked":false}`.

### Nesting Rules

Containers can nest, but **never exceed 3 levels deep**. Three levels is ideal:

```
Container (outer, column) → Container (inner, row) → Widget
Container (outer, column) → Widget
```

`isInner` is set automatically — containers nested inside other containers get `isInner: true`.

### Two-Container Pattern for Full-Bleed Backgrounds

When a background color or image needs to span the full viewport width while content stays centered:

1. **Outer container** — `content_width: full`, `flex_direction: column`, `align_items: center`. **Set the background color here.**
2. **Inner container** — `content_width: boxed`, `flex_direction: column`. Content goes here.

Never set a background color on a `content_width: boxed` container and expect it to bleed — it won't.
