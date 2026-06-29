---
name: elementor-page-builder
description: "Build and edit Elementor pages in WordPress via the wp-mcp-plugin MCP server. Use when the user wants to create a new page, add sections or widgets to an existing page, convert a Figma design into an Elementor layout, update global colors or typography, or troubleshoot a broken Elementor layout. Triggers on requests mentioning Elementor, WordPress page building, landing pages, or Figma-to-WordPress conversion. The server exposes 8 reference resources at wp-mcp://docs/* — read the relevant one before any write operation."
---

# Elementor Page Builder

Model-invoked skill for building WordPress pages with Elementor via the wp-mcp-plugin MCP server. The server's `instructions` field carries the concise workflow; this skill carries the invocation trigger and the ordered build procedure. Deep reference lives in the server's MCP resources — read them on demand, do not hold them in memory.

## When to use

- Creating a new Elementor page (landing page, section, full site)
- Adding containers or widgets to an existing page
- Converting a Figma design (via the Figma MCP) into an Elementor page
- Updating global colors or typography
- Fixing a broken Elementor layout (nesting too deep, boxed background expecting full-bleed, hardcoded colors)

## Build procedure

Each step ends on a checkable completion criterion. Run `schema-first`: discover the available types and their settings before writing, never guess property names.

1. **Read the site's design tokens.** Call `get-elementor-global-settings`. Completion: you have the brand color palette, typography presets, container width, and breakpoints.
2. **Read the layout rules.** Call `mcp_read_resource` for `wp-mcp://docs/container-system` and `wp-mcp://docs/elementor-data-structure`. Completion: you know the `_elementor_data` JSON shape, the 3-level nesting limit (`three-level max`), and the two-container full-bleed pattern.
3. **Create the page.** Call `create-page` with `template: "elementor_canvas"` for landing pages. Completion: you have the `post_id`.
4. **Discover widget types and schemas.** Call `list-elementor-widgets`, then `get-elementor-widget-schema` for each widget type you plan to use (`schema-first`). Completion: every widget type you will use is validated against its schema — no guessed property names.
5. **Build the container tree.** Call `add-container` for the outer section, then inner layout containers. Apply `global-first`: reference global colors/typography via `__globals__` instead of hardcoded hex. Completion: the container tree is no deeper than 3 levels (`three-level max`) and every full-bleed background uses the two-container pattern.
6. **Add widgets.** Call `add-widget` into containers. Completion: every widget's `settings` match the schema from step 4.
7. **Batch styling tweaks.** Call `batch-update` for multiple `update-element` changes in one save (`batch-update`). Completion: `success` is `true` and the `failed` array is empty.
8. **Verify.** Call `get-page` and read the full `_elementor_data`. Completion: the rendered tree matches the intended layout and every nesting depth is ≤ 3.

## Figma conversion branch

When the user is converting a Figma design (the Figma MCP is wired in the harness), read `wp-mcp://docs/figma-conversion` via `mcp_read_resource` before step 1 — it carries the 4-phase Figma workflow, the Auto Layout → flexbox mapping table, the Tailwind → Elementor settings table, and the responsive-gap defaults. Do not duplicate that mapping here; the resource is the single source of truth.

## Leading words

- `schema-first` — discover widget types and settings before writing; never guess property names.
- `three-level max` — container nesting never exceeds 3 levels; deep nesting is the #1 Elementor performance problem.
- `two-container pattern` — full-bleed backgrounds use an outer `content_width: full` container (with the background) wrapping an inner `content_width: boxed` container (with the content). Never set a background on a boxed container expecting full-bleed.
- `global-first` — prefer `__globals__` references over hardcoded hex values to maintain consistency.
- `batch-update` — multiple `update-element` changes go in one `batch-update` call, not many individual ones.

## Installation (for skills-aware clients)

This skill ships inside the wp-mcp-plugin. To activate it in a skills-aware client, copy `skills/elementor-page-builder/SKILL.md` into the client's skills directory:

- OpenCode: `.opencode/skills/elementor-page-builder/SKILL.md` (or `~/.config/opencode/skills/`)
- Claude Code: `.claude/skills/elementor-page-builder/SKILL.md`
- Cursor: `.cursor/skills/elementor-page-builder/SKILL.md`

The skill is client-agnostic; the MCP resources it points at work in any MCP-respecting client via `mcp_read_resource`.
