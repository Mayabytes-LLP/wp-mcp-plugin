# WP MCP Plugin

> Expose WordPress + Elementor as an [MCP](https://modelcontextprotocol.io/) server so AI coding agents can read Figma designs and generate Elementor pages through MCP tools.

WP MCP Plugin bridges WordPress/Elementor and AI coding agents via the Model Context Protocol. It registers Elementor pages, widgets, templates, and settings as MCP tools that an MCP client (Claude Desktop, OpenCode, Cursor, etc.) can call to inspect, build, and modify Elementor pages programmatically — including turning a Figma design into a fully structured Elementor page.

## Features

- **MCP server** exposed at `/wp-json/wp-mcp/mcp`, authenticated via an API key.
- **Read tools** — list/inspect pages, Elementor widgets, templates, and global design settings.
- **Write tools** — create/update/delete pages, add containers and widgets, batch-update elements, manage global settings.
- **Schema validation** — every widget/element is validated against the live Elementor widget schema.
- **Render tool** — generate a time-limited, authenticated preview URL for headless-browser screenshotting (Figma → Elementor visual comparison workflows).
- **Drop-in** — no Composer, no build step. The `vendor/` directory ships in the plugin zip.
- **Bundled MCP Adapter** — `wordpress/mcp-adapter` is vendored; if a compatible standalone adapter is already active, the newer version wins automatically.

## Requirements

| Requirement | Version |
| --- | --- |
| WordPress | 6.9+ |
| PHP | 8.0+ |
| Elementor | Free or Pro |

The plugin gracefully detects a missing Elementor install and shows an admin notice instead of fatal-erroring.

## Installation

### From the release zip (recommended)

1. Install and activate [Elementor](https://wordpress.org/plugins/elementor/).
2. Download `wp-mcp-plugin-0.1.0.zip` from the [latest release](https://github.com/Mayabytes-LLP/wp-mcp-plugin/releases).
3. WordPress Admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now**.
4. Activate the plugin.
5. Go to **Settings → WP MCP**, generate/copy your API key, and ensure the MCP server is enabled.

### From source

Clone this repo into `wp-content/plugins/` (the `vendor/` directory is committed, so no Composer is required). Activate from the WordPress admin.

## Connecting an MCP client

The MCP endpoint is:

```
POST /wp-json/wp-mcp/mcp
Header: X-WP-MCP-Key: <your-api-key>
```

For OpenCode, add it as a remote MCP server in `opencode.jsonc`:

```jsonc
{
  "mcp": {
    "wp-mcp": {
      "type": "remote",
      "url": "http://your-site.test/wp-json/wp-mcp/mcp",
      "headers": { "X-WP-MCP-Key": "<your-api-key>" }
    }
  }
}
```

For Claude Desktop / other MCP clients, follow the client's remote-server configuration using the same URL and header.

## Tool inventory

### Read / query

- `wp-mcp/list-pages` — list WordPress pages with Elementor status.
- `wp-mcp/get-page` — get a page with its full Elementor data tree.
- `wp-mcp/list-elementor-widgets` — list registered widget types.
- `wp-mcp/get-elementor-widget-schema` — full JSON schema for a widget type.
- `wp-mcp/list-elementor-templates` — list saved Elementor templates.
- `wp-mcp/get-elementor-global-settings` — active kit colors and typography.
- `wp-mcp/get-plugin-status` — adapter version, Elementor status, API-key state.

### Write / mutate

- `wp-mcp/create-page` — create a new page (optionally with initial Elementor data).
- `wp-mcp/update-page-elementor-data` — replace a page's entire Elementor content.
- `wp-mcp/delete-page` — trash or permanently delete a page.
- `wp-mcp/add-container` — add a flex/grid container to a page.
- `wp-mcp/add-widget` — add a widget into a container.
- `wp-mcp/update-element` — update settings on a single element.
- `wp-mcp/remove-element` — remove an element and its children.
- `wp-mcp/batch-update` — update multiple elements in one save.
- `wp-mcp/update-elementor-global-settings` — update kit colors/typography.

### Render

- `wp-mcp/render-page` — generate an authenticated, time-limited preview URL for headless-browser screenshotting.

All mutating tools require `manage_options` capability. The API key gates the transport; it does not authenticate as a WordPress user.

## Figma → Elementor workflow

This plugin is designed to pair with a Figma MCP server. The typical loop:

1. MCP client reads a Figma design via the Figma MCP.
2. MCP client calls `wp-mcp/create-page` and `wp-mcp/add-container` / `wp-mcp/add-widget` to build the Elementor page.
3. `wp-mcp/render-page` produces a preview URL.
4. A headless browser (Playwright/Puppeteer) screenshots the preview.
5. The screenshot is compared against the Figma design; differences are fixed with further `wp-mcp/update-element` / `wp-mcp/batch-update` calls.

The plugin ships in-app documentation (container nesting limits, the full-bleed pattern, widget schemas) accessible to the MCP client as prompts.

## Development

This repository is the plugin itself. It is developed inside a Local-by-Flywheel WordPress harness; the harness is not part of the shipped artifact.

- PHP 8.0+ target (developed against 8.4).
- Composer is a dev convenience only — `vendor/` is committed for the drop-in release.
- WordPress coding standards: tabs, Yoda conditions, `esc_*` output, nonces + capability checks.

To refresh dependencies (dev only):

```bash
composer install            # with dev stubs for intelephense
composer install --no-dev --classmap-authoritative   # production vendor tree
```

## License

GPL-2.0-or-later. See [License URI](https://www.gnu.org/licenses/gpl-2.0.html).

## Author

[Mayabytes LLP](https://github.com/Mayabytes-LLP)
