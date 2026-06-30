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
5. Go to **WP MCP** in the admin sidebar, generate/copy your API key, and ensure the MCP server is enabled. The admin page shows how many tools are exposed (enabled / total); new tools are auto-enabled on plugin updates, and you can use **Enable all tools** to reset visibility.

### From source

Clone this repo into `wp-content/plugins/` (the `vendor/` directory is committed, so no Composer is required). Activate from the WordPress admin.

## Connecting an MCP client

The MCP endpoint is:

```
POST /wp-json/wp-mcp/mcp
Header: X-WP-MCP-Key: <your-api-key>
```

Alternatively, clients that only support standard auth headers may send:

```
Authorization: Bearer <your-api-key>
```

The server negotiates MCP protocol version `2025-11-25` (with fallbacks to `2025-06-18` and `2024-11-05`) and uses session-based Streamable HTTP via the vendored mcp-adapter. After `initialize`, clients must include the `Mcp-Session-Id` header returned by the server on subsequent requests.

For **Cursor**, add `.cursor/mcp.json` (project) or `~/.cursor/mcp.json` (global). Reference an environment variable so the config is safe to commit — Cursor does not support VS Code-style `inputs` / `${input:...}` prompts:

```json
{
  "mcpServers": {
    "wp-mcp": {
      "url": "http://your-site.test/wp-json/wp-mcp/mcp",
      "headers": { "X-WP-MCP-Key": "${env:WP_MCP_API_KEY}" }
    }
  }
}
```

Set the key locally (`export WP_MCP_API_KEY="..."` in your shell profile), then restart Cursor.

For **OpenCode**, add a remote MCP server in `opencode.jsonc`:

```jsonc
{
  "mcp": {
    "wp-mcp": {
      "type": "remote",
      "url": "http://your-site.test/wp-json/wp-mcp/mcp",
      "headers": { "X-WP-MCP-Key": "${env:WP_MCP_API_KEY}" }
    }
  }
}
```

For Claude Desktop / other MCP clients, follow the client's remote-server configuration using the same URL and header.

## Tool inventory

### Read / query

- `list-pages` — list WordPress pages with Elementor status.
- `get-page` — get a page with its full Elementor data tree.
- `list-elementor-widgets` — list registered widget types.
- `get-elementor-widget-schema` — full JSON schema for a widget type.
- `list-elementor-templates` — list saved Elementor templates.
- `get-elementor-global-settings` — active kit colors and typography.
- `get-plugin-status` — adapter version, Elementor status, API-key state.
- `get-server-guide` — server workflow and documentation index (bootstrap for clients that ignore initialize instructions).

### Write / mutate

- `create-page` — create a new page (optionally with initial Elementor data).
- `update-page-elementor-data` — replace a page's entire Elementor content.
- `delete-page` — trash or permanently delete a page.
- `add-container` — add a flex/grid container to a page.
- `add-widget` — add a widget into a container.
- `update-element` — update settings on a single element.
- `remove-element` — remove an element and its children.
- `batch-update` — update multiple elements in one save.
- `update-elementor-global-settings` — update kit colors/typography.

### Render

- `regenerate-elementor-css` — flush stale cache and regenerate Elementor CSS after writes (call before preview).
- `visual-compare-preview` — generate an authenticated draft preview URL for headless-browser screenshotting (rejects published pages).

All mutating tools require WordPress capabilities on the service user (typically `edit_pages` for page tools, `manage_options` for global settings). The API key gates the transport; on success the plugin authenticates as a service WordPress user (first administrator by default, overridable via the `wp_mcp_authenticated_user_id` filter) so the mcp-adapter can manage sessions and enforce ability-level capability checks.

## Figma → Elementor workflow

This plugin is designed to pair with a Figma MCP server. The typical loop:

1. MCP client reads a Figma design via the Figma MCP.
2. MCP client calls `create-page` (draft) and `add-container` / `add-widget` to build the Elementor page.
3. `regenerate-elementor-css` flushes stale CSS after writes.
4. `visual-compare-preview` produces a draft preview URL.
5. A headless browser (Playwright/Puppeteer) screenshots the preview.
6. The screenshot is compared against the Figma design; differences are fixed with further `update-element` / `batch-update` calls, then repeat from step 3.

The plugin ships in-app documentation (container nesting limits, the full-bleed pattern, widget schemas) as MCP resources at `wp-mcp://docs/*`. The server's `instructions` field on initialize carries the concise workflow; read resources on demand via `resources/read`. Write tools may return `_recommended_resources` URIs pointing at relevant docs.

**Client compatibility:** Cursor, Claude Code, and OpenCode inject initialize `instructions` automatically. Clients that do not (e.g. Cline) should call `get-server-guide` on first connect. Optional user-triggered MCP prompts: `build-landing-page`, `figma-to-elementor`.

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
