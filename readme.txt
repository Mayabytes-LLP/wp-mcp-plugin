=== WP MCP Plugin ===
Contributors: mayabytes
Tags: mcp, elementor, ai, figma, wordpress
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Exposes WordPress + Elementor as an MCP server so AI coding agents can

== Description ==

WP MCP Plugin bridges WordPress/Elementor and AI coding agents via the Model Context Protocol (MCP). It exposes Elementor pages, widgets, templates, and settings as MCP tools that AI agents can call to:

* List and inspect Elementor pages
* Create, update, and delete pages
* Add containers and widgets with full schema validation
* Batch-update multiple elements at once
* Manage Elementor global settings

The plugin requires both Elementor and the WordPress MCP Adapter. If the MCP Adapter is not installed as a standalone plugin, the bundled version is used automatically.

== Installation ==

1. Install and activate Elementor (https://wordpress.org/plugins/elementor/) (free or Pro).
2. Upload the wp-mcp-plugin folder to /wp-content/plugins/, or install the zip via WordPress Admin > Plugins > Add New > Upload Plugin.
3. Activate the plugin through the Plugins menu.
4. Go to Settings > WP MCP to configure your API key and enable the MCP server.
5. Connect your MCP client (e.g. Claude Desktop, OpenCode) to the WordPress MCP server endpoint.

== Frequently Asked Questions ==

= Does this require Elementor Pro? =

No, Elementor free is sufficient for basic page and widget operations. Elementor Pro adds templates and theme builder support.

= Do I need to install the MCP Adapter separately? =

No. The plugin bundles the WordPress MCP Adapter via Composer. If you already have it as a standalone plugin, the newer version takes precedence automatically.

= Can I use this without an AI agent? =

The plugin exposes MCP tools -- you need an MCP client to call them. The tools are registered as WordPress REST API endpoints so you can also call them directly with curl or any HTTP client.

== Changelog ==

= 0.1.6 =
* Initial release -- MCP server with Elementor page, widget, element, and settings tools.

== Upgrade Notice ==

= 0.1.6 =
Initial release.
