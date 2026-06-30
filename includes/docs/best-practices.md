## Dos and Don'ts

### ✅ DO

- **DO fetch design context and screenshot before building from Figma.** Never implement from memory or assumptions.
- **DO use the two-container pattern for full-bleed backgrounds.** Outer container (full-width, background color) → inner container (boxed, content).
- **DO ask the user about elements that don't map to standard widgets.** Forms, animations, custom interactions, masks — always ask before using custom HTML.
- **DO extract colors from Figma tools.** Never guess hex values.
- **DO set line-height explicitly for all text.** Figma and browsers render line-height differently.
- **DO convert Figma's auto-layout gap to Elementor's container `gap`.** Never use per-child margins for spacing between flex items.
- **DO ask about mobile behavior when Figma only provides desktop designs.** Never assume how a layout should behave on mobile.
- **DO flag absolute positioning and ask the user if it's intentional.** Never silently convert absolute positioning to flexbox without confirmation.
- **DO sync Figma design tokens to Elementor global settings before building sections.** This ensures consistency across the entire page.
- **DO use `__globals__` references for colors and typography instead of hardcoded hex values.
- **DO use containers, not legacy sections/columns.** Containers produce 30–50% fewer DOM elements.
- **DO set `flex_direction` first.** Decide row or column before adding children — it determines the entire layout flow.
- **DO use `gap` on the parent container** instead of margins on individual children.
- **DO use `content_width: boxed`** for centered content sections. Use `full` only for hero banners and full-bleed sections.
- **DO keep nesting to 3 levels maximum.** Outer container → inner container → widget.
- **DO call `get-elementor-widget-schema` before setting properties.** Widget settings vary by type — never guess property names.
- **DO use the correct HTML tag for headings.** One H1 per page, then H2, H3 in hierarchy. Never skip levels.
- **DO keep MCP-built pages in draft** until the user explicitly asks to publish. Use `regenerate-elementor-css` then `visual-compare-preview` for visual evaluation — never publish just to preview.
- **DO set `typography_typography: "custom"`** when applying custom font settings — without it, Elementor may ignore your typography changes.
- **DO remove default container padding** (10px on all sides) when not needed — it compounds when nesting.

### ❌ DON'T

- **DON'T set background colors on `content_width: boxed` containers and expect full-bleed.** Use the two-container pattern instead.
- **DON'T skip the screenshot step when building from Figma.** Structured data alone misses spatial relationships and visual hierarchy.
- **DON'T guess colors.** Always extract from Figma tools or ask the user for exact values.
- **DON'T convert Figma's auto-layout gap to per-child margins.** Use container `gap` — it's consistent and doesn't compound at edges.
- **DON'T use fixed pixel widths from Figma directly.** Convert to percentages or flex-grow for responsiveness.
- **DON'T reference Figma CDN URLs for images.** They expire after 7 days. Use placeholder URLs and have the user upload real images.
- **DON'T assume absolute positioning in Figma is intentional.** Always ask the user.
- **DON'T use custom HTML as a first resort.** It breaks Elementor's responsive system and is hard to maintain.
- **DON'T round spacing values to "nice" numbers.** Use the exact values from Figma's design context. 18px padding should be 18px, not 16px or 20px.
- **DON'T wrap everything in containers.** If a single widget doesn't need layout context, place it directly in the parent container.
- **DON'T use spacer widgets for vertical spacing.** Use container `gap`, `padding`, or `margin` instead.
- **DON'T set margins on individual widgets when `gap` will do.** Gap is consistent, predictable, and doesn't compound at edges.
- **DON'T mix containers and legacy sections on the same page.** For new pages, always use containers.
- **DON'T nest more than 3 levels deep.** Deep nesting is the #1 cause of Elementor performance problems.
- **DON'T hardcode widget type keys.** Always discover available types with `list-elementor-widgets` — installed plugins add widgets.
- **DON'T fake headings by making text-editor text big.** Use the heading widget with the correct `header_size` (H1–H6).
- **DON'T use hardcoded hex colors when a global color exists.** Use `__globals__` references instead.
- **DON'T create pages with 50+ widgets.** Each widget adds DOM nodes, CSS, and potentially JS. Keep pages under 30 widgets for optimal performance.
- **DON'T add parallax, scroll effects, or motion to every section.** These are expensive on mobile and hurt Core Web Vitals. Limit to 3–5 strategic animations per page.
- **DON'T publish pages for MCP visual comparison.** Use draft status and `visual-compare-preview` URLs only.
- **DON'T call `visual-compare-preview` without `regenerate-elementor-css` after writes.** Stale Elementor CSS makes previews look wrong.
