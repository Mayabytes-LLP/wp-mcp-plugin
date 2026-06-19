## Working with Figma Designs

When converting a Figma design to an Elementor page, follow this structured workflow.

### Figma MCP Tool Availability

The plugin uses the **remote server** (configured in `opencode.json`), which has different tool availability than the desktop server:

| Tool | Remote | Desktop | Use For |
|------|:---:|:---:|---|
| `get_design_context` | ✅ | ✅ | Layout, spacing, typography, colors |
| `get_screenshot` | ✅ | ✅ | Visual reference |
| `get_metadata` | ✅ | ✅ | Page structure outline |
| `get_variable_defs` | ❌ | ✅ | Named design tokens |
| `search_design_system` | ✅ | ❌ | Find components/variables in libraries |
| `get_libraries` | ✅ | ❌ | List connected design libraries |
| `use_figma` | ✅ | ❌ | Create/edit designs via Plugin API |

**Key limitation:** `get_variable_defs` (design tokens) is **desktop-only**. On the remote server, extract colors from `get_design_context` output or use `search_design_system` to find tokens by name.

### Phase 1: Discover the Design Structure

1. **Get the page outline first.** Use `get_metadata` on the Figma file to see the top-level pages and section structure. This prevents context overload — fetch the outline, then drill into individual sections.
2. **Get design context for each section.** Call `get_design_context` on each section node. The output is always React + Tailwind CSS — you must translate it to Elementor settings (see "Converting Figma Output" below).
3. **Get a screenshot for visual reference.** Call `get_screenshot` on the full page or each section. Structured data alone misses spatial relationships and visual hierarchy.
4. **Get design tokens.** If `get_variable_defs` is available, extract named tokens. Otherwise, extract colors from `get_design_context` output or use `search_design_system`.

**Never implement based on assumptions.** Always fetch design context and screenshot before building.

#### get_design_context Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `fileKey` | string | Yes | The Figma file key from the URL |
| `nodeId` | string | Yes | Target node ID in `123:456` format |
| `clientLanguages` | string | No | Comma-separated languages. **Does NOT change output format** — used only for Code Connect filtering. Output is always React + Tailwind. |
| `clientFrameworks` | string | No | Comma-separated frameworks. **Does NOT change output format** — used only for Code Connect filtering. |
| `forceCode` | boolean | No | Default `false`. When `true`, forces full code output even for large designs. |
| `excludeScreenshot` | boolean | No | Default `false`. When `true`, omits the screenshot from the response. |
| `disableCodeConnect` | boolean | No | When `true`, omits Code Connect component mappings. |

**Important:** The output format is **always React + Tailwind CSS**, regardless of `clientLanguages` or `clientFrameworks`. To get HTML+CSS, you must translate the output yourself.

### Phase 2: Map Figma to Elementor

#### Auto Layout → Flexbox Container Mapping

| Figma Auto Layout | Elementor Container Setting |
|---|---|
| Direction: Horizontal | `flex_direction: row` |
| Direction: Vertical | `flex_direction: column` |
| Gap | `gap` on the container |
| Padding | `padding` on the container |
| Align: Start / Center / End | `align_items: flex-start / center / flex-end` |
| Justify: Start / Center / End / Space Between | `justify_content: flex-start / center / flex-end / space-between` |
| Fill Container | `width: 100%` or flex-grow |
| Hug Contents | Default — no explicit width needed |
| Fixed Size | Explicit `width` / `height` |
| Wrap | `flex_wrap: wrap` |

#### Figma Element → Elementor Widget Mapping

| Figma Element | Elementor Equivalent | Notes |
|---|---|---|
| Frame (auto-layout) | Flexbox Container | Match direction, gap, padding, alignment |
| Text layer (heading) | `heading` widget | Set `header_size` to match semantic level (H1–H6) |
| Text layer (body) | `text-editor` widget | For paragraphs, lists, rich text |
| Rectangle with fill | Container background | NOT an Image widget — use container `background_color` |
| Image fill on frame | Container bg image OR `image` widget | Background for decorative, Image widget for content |
| Button | `button` widget | Map font, padding, border-radius, colors |
| Icon (vector) | `icon` or `icon-box` widget | Check Elementor's built-in icon library first |
| Input field / form | See "Elements Without Standard Widgets" below | Requires Elementor Pro or Contact Form 7 |
| Component instance | Saved template or Global Widget | Use Global Widget if reused 3+ times |

#### Elements Without Standard Widgets

These Figma elements have no direct Elementor widget equivalent. **Always ask the user how to handle them:**

- **Forms** — Free Elementor has a basic form widget. Complex forms need Elementor Pro or Contact Form 7. Ask: "This design includes a form. Should I use a basic Elementor form, embed Contact Form 7 via shortcode, or use custom HTML?"
- **Complex animations** — Figma Smart Animate doesn't map to Elementor. Ask: "Should I use Elementor Motion Effects (limited), or skip animations?"
- **Custom interactions** — Modals, mega menus, conditional displays require custom JS or Elementor Pro. Ask: "Should I use custom HTML/JS, or simplify it?"
- **Masks and clipping paths** — No native Elementor support. Ask: "Should I approximate with border-radius and overflow:hidden, or use custom CSS clip-path?"
- **Blend modes** — Limited support. Ask: "Should I approximate with opacity, or use custom CSS?"

**Always ask before using custom HTML.** Custom HTML breaks Elementor's responsive system and is hard to maintain.

### Phase 3: Handle Common Figma Conversion Problems

#### Designs Without Auto Layout

Figma frames without auto-layout use absolute positioning (fixed X/Y coordinates). This creates layouts that don't flex and break on different screen sizes.

1. **Always ask the user:** "This section uses absolute positioning (no auto-layout). Was this intentional for visual effect, or should I reconstruct it with flexbox for responsiveness?"
2. **If intentional** (overlapping badges, text overlays on hero images): Use `position: absolute` on the child container with explicit offsets. Ensure the parent has `position: relative`.
3. **If not intentional**: Reconstruct using flexbox containers. Group elements by visual proximity and purpose. Infer direction from reading order and alignment.

**Never assume absolute positioning is correct.** Always flag it and ask.

#### Extracting Colors

1. **Use `get_variable_defs`** to get named color tokens (e.g., `color/brand/primary` → `#2563EB`). Most reliable method.
2. **Use `get_design_context`** if `get_variable_defs` is unavailable. Extract from Tailwind classes: `bg-[#hex]`, `text-[#hex]`, `border-[#hex]`, `style={{ backgroundImage: "linear-gradient(...)" }}`.
3. **Use `search_design_system`** to find specific tokens by name.
4. **Map tokens to Elementor Global Colors** via `update-elementor-global-settings`, then reference with `__globals__`.

**Never guess colors.** If you can't extract them from Figma tools, ask the user for exact hex values.

#### Converting Figma Output (Tailwind → Elementor)

`get_design_context` returns React + Tailwind CSS. You must translate it to Elementor settings:

| Tailwind Class | Elementor Setting |
|---|---|
| `flex flex-col` | `flex_direction: column` |
| `flex flex-row` | `flex_direction: row` |
| `items-center` | `align_items: center` |
| `items-start` | `align_items: flex-start` |
| `justify-between` | `justify_content: space-between` |
| `justify-center` | `justify_content: center` |
| `gap-[20px]` | `gap: {"unit":"px","top":"20","right":"20","bottom":"20","left":"20"}` |
| `p-[40px]` | `padding: {"unit":"px","top":"40","right":"40","bottom":"40","left":"40"}` |
| `text-[#e5e5e5]` | `title_color: "#e5e5e5"` or `text_color: "#e5e5e5"` |
| `text-[var(--primary,#3b82f6)]` | Map `#3b82f6` to Elementor global color, then use `__globals__` reference |
| `bg-[#141619]` | `background_color: "#141619"` |
| `bg-[var(--surface,#f5f5f5)]` | Map `#f5f5f5` to Elementor global color, then use `__globals__` reference |
| `font-['Inter:Medium']` | `typography_typography: "custom"`, `typography_font_family: "Inter"`, `typography_font_weight: "500"` |
| `text-[76px]` | `typography_font_size: {"unit":"px","size":76}` |
| `leading-[86px]` | `typography_line_height: {"unit":"px","size":86}` |
| `tracking-[0.53px]` | `typography_letter_spacing: {"unit":"px","size":0.53}` |
| `rounded-[2px]` | `border_radius: {"unit":"px","top":"2","right":"2","bottom":"2","left":"2"}` |
| `uppercase` | `typography_text_transform: "uppercase"` |
| `bg-gradient-to-b from-[#141619]` | `background_color: "#141619"` (use two-container pattern for full-bleed) |
| `style={{ backgroundImage: "linear-gradient(...)" }}` | Button gradient — use `button_background_color` with gradient stops |

**Design tokens in the output:** When Figma variables are defined, colors and spacing appear as CSS custom properties with fallback values: `var(--token-name, fallback-value)`. The fallback value after the comma is the actual resolved value — use it directly in Elementor settings.

**When you see `absolute` positioning in the output**, the Figma design does NOT use auto-layout. Refer to the "Designs Without Auto Layout" section above.

**Handling large designs:** If `get_design_context` returns sparse metadata instead of full code, use `forceCode: true` to force full output, or use `get_metadata` first to identify sub-nodes and fetch them individually.

#### Typography Mapping

| Figma Property | Elementor Setting | Common Pitfall |
|---|---|---|
| Font family + weight | Typography → Font | Fonts may render differently in browser vs Figma canvas |
| Font size (px) | Font Size (px, em, rem) | Figma always uses px; convert to rem for accessibility |
| Line height (% or px) | Line Height (em, px) | **Always set explicitly** — Figma and browsers render line-height differently |
| Letter spacing (px) | Letter Spacing (px, em) | Figma uses px; Elementor defaults to em. Convert: `em = px / font-size-px` |

**Always set `typography_typography: "custom"`** when applying custom font settings — without it, your typography changes may be silently ignored by Elementor.

#### Spacing Pitfalls

- **Figma uses pixels only.** Elementor supports px, em, rem, %. Convert as needed.
- **Gap belongs to the container, not individual children.** Never convert Figma's auto-layout gap to per-child margins.
- **Fixed pixel widths break on mobile.** Use percentages or flex-grow instead.
- **Container width mismatch.** If the Figma frame is 1440px but Elementor's content width is 1140px, everything will be slightly narrower. Check `get-elementor-global-settings` for the site's container width.
- **Default container padding is 10px on all sides.** This compounds when nesting. Always check and remove if not needed.

#### Image Handling

Figma images are not automatically available in WordPress:

1. **Decorative images** (backgrounds, patterns) → Use container `background_image` setting
2. **Content images** (photos, logos) → Use the `image` widget with placeholder URLs
3. **Icons** → Check Elementor's built-in icon library first; upload SVG only if missing
4. **Never reference Figma CDN URLs** — they expire after 7 days
5. **Always set alt text** — use Figma layer names as hints for descriptive alt text
6. **Mark decorative images** with `alt=""` (empty alt) per WCAG guidelines

After building, the user must upload actual images to the WordPress Media Library and update the placeholder URLs.

#### Gradient Backgrounds and Buttons

**Gradient buttons:**
- For simple two-color gradients: set `button_background_color` to the start color and use hover state for the end color
- For multi-stop gradients (3+ colors): use `button_css_id` setting and add custom CSS, or simplify to a two-color gradient

**Gradient section backgrounds:**
- Use the two-container pattern (outer full-width container with gradient, inner boxed container for content)
- For complex gradients, use `background_overlay` with gradient type

#### Decorative Elements (Blobs, Overlays, Shadows)

Modern designs often include decorative background elements — gradient blobs, shadow overlays, decorative shapes. These appear in `get_design_context` as elements with absolute positioning and gradient backgrounds.

**How to handle them:**
1. **Ask the user:** "This design includes decorative background elements. Should I recreate these with CSS gradients and shadows, or simplify?"
2. **CSS gradients** — For simple radial/linear gradients, use container `background_overlay` with gradient type
3. **CSS box-shadow** — For shadow effects, use `box_shadow` setting on containers
4. **Skip decorative overlays** — If the user prefers simplicity, skip `:before`/`:after` pseudo-elements and background blobs
5. **Never use absolute positioning for decorative blobs** unless the user specifically requests pixel-perfect reproduction

#### Responsive Design Gaps

Most Figma designs are desktop-only. **Always ask the user:**

- "Do you have mobile or tablet designs for this page?"
- "If not, how should [specific layout element] behave on mobile? Stack vertically? Hide? Resize?"

**If no mobile designs exist, apply these defaults:**
- Hero sections: stack vertically (image on top, text below)
- Card grids: 3-col → 2-col at tablet → 1-col at mobile
- Font sizes: reduce 15% at tablet, 25% at mobile
- Padding: reduce 20% at tablet, 30% at mobile
- Side-by-side layouts: switch `flex_direction` to `column` on mobile
- Set `flex_wrap: wrap` on all row-direction containers

### Phase 4: Build the Page

Follow this sequence when converting a Figma design:

1. **`get-elementor-global-settings`** — Check existing site colors, typography, breakpoints
2. **`get_metadata`** (Figma) — Get the page outline and section IDs
3. **`get_design_context`** (Figma) — Get layout, spacing, typography, colors for each section
4. **`get_screenshot`** (Figma) — Get visual reference for each section
5. **`get_variable_defs`** (Figma) — Extract named design tokens (if available)
6. **`update-elementor-global-settings`** — Sync Figma design tokens to Elementor (colors, typography)
7. **`create-page`** — Create the page (use `template: "elementor_canvas"` for landing pages)
8. **For each Figma section:**
   a. Map auto-layout direction → `flex_direction`
   b. Map gap → container `gap`
   c. Map padding → container `padding`
   d. Map background fills → outer container `background_color` (use two-container pattern for full-bleed)
   e. Map text layers → `heading` or `text-editor` widgets
   f. Map images → `image` widgets or container backgrounds
   g. Map buttons → `button` widgets
   h. Map icons → `icon` or `icon-box` widgets
   i. **Ask the user** about any elements that don't map to standard widgets
9. **`batch-update`** — Apply responsive overrides and styling tweaks
10. **`get-page`** — Verify the final result

### Handling Multi-Page Figma Files

A single Figma file may contain multiple website designs as top-level frames. Call `get_metadata` without `nodeId` to list all pages, then drill into the specific page you want. Only build one page at a time, and clarify with the user which one they want.
