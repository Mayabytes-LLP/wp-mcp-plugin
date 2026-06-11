<?php
/**
 * WP MCP Plugin — Usage Guide (MCP Prompt)
 *
 * This prompt is served to MCP clients that invoke the wp-mcp/usage-guide
 * prompt. It teaches AI models how to use the plugin's tools effectively.
 *
 * @package WpMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return <<<'GUIDE'
# WP MCP Plugin — Elementor Builder Usage Guide

You are an AI agent with access to Elementor page building tools.
Use them to create and modify WordPress pages with Elementor.

## Tool Overview

### Read Tools (safe, no side effects)
- `list-pages` — find existing pages by status or search
- `get-page` — get a page's full Elementor data structure
- `list-elementor-widgets` — see all available widget types and their keys
- `get-elementor-widget-schema` — get the controllable properties for any widget
- `list-elementor-templates` — see saved reusable templates
- `get-elementor-global-settings` — read site-wide colors, typography, breakpoints

### Write Tools (mutate the site)
- `create-page` — create a new WordPress page
- `update-page-elementor-data` — replace the entire Elementor content of a page
- `delete-page` — trash a page
- `add-container` — add a flex or grid container to a page
- `add-widget` — add any widget type into a container
- `update-element` — change settings on any element
- `remove-element` — remove an element and all its children
- `batch-update` — apply multiple element updates in one save
- `update-elementor-global-settings` — update site-wide colors and typography

### Diagnostics
- `get-plugin-status` — check if Elementor is active, server status, etc.

## Widget Types and Keys

Always use `list-elementor-widgets` first to see what's available. Common keys:
- `heading`, `text-editor`, `image`, `button`, `video`, `icon`, `spacer`, `divider`
- `icon-box`, `image-box`, `icon-list`, `counter`, `progress`, `testimonial`

Get each widget's acceptable settings with `get-elementor-widget-schema`.

## Elementor Data Structure

Elementor content is a JSON tree stored in `_elementor_data` post meta:

```json
[
  {
    "id": "abc1234",
    "elType": "container",
    "settings": { "flex_direction": "column", "content_width": "boxed" },
    "elements": [
      {
        "id": "def5678",
        "elType": "widget",
        "widgetType": "heading",
        "settings": { "title": "Hello World", "header_size": "h1", "align": "center" },
        "elements": []
      }
    ],
    "isInner": false
  }
]
```

Key rules:
- Top level is always an array of sections/containers
- Each element has a unique `id` (7-char hex string)
- `elType` is "container" or "widget"
- Containers nest via the `elements` array
- Widget types use `widgetType` key
- All design properties go in `settings`

## Container Nesting

Elementor containers can nest:
```
Container (flex, column) → Container (inner, horizontal) → Widget
Container (flex, column) → Widget
```

Use `add-container` with appropriate `flex_direction` and `content_width` settings.
Set `isInner: true` for containers nested inside other containers.

## Typical Workflow: Building a Page

1. `create-page` — make the page with title and slug
2. `get-elementor-global-settings` — check site's design tokens
3. `list-elementor-widgets` — see available widgets
4. For each section:
   a. `add-container` — create the outer section container
   b. `add-widget` — add widgets into the container
5. `update-element` — tweak individual element settings
6. `batch-update` — apply multiple tweaks at once for performance

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

## Common Patterns

### Hero Section
- Outer container: full-width, column direction, background image/color
- Inner container: boxed width, column direction
- Heading widget (h1), text-editor widget, button widget

### Grid Layout
- Container with `flex_direction: row`, `flex_wrap: wrap`
- Child containers or widgets distributed across the row

### Color and Typography Sync
- Read `get-elementor-global-settings` to see existing design tokens
- Match your generated colors/typography to the site's existing palette
- Use `update-elementor-global-settings` to sync new palettes when needed

## Tips

- Always get a widget's schema before setting its properties
- Generate unique 7-char hex IDs for new elements
- Position is 0-indexed; use -1 to append at end
- Save operations: batch when possible, single saves when interactive
- Check global settings before choosing colors — match the site's brand
GUIDE;
