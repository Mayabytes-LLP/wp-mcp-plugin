# WP MCP Plugin — Agent Notes

WordPress plugin that exposes WordPress + Elementor as an MCP server. Drop-in:
no build step, `vendor/` is committed. Developed inside a Local-by-Flywheel
harness (site `wp-mcp-plugin.local`); the harness is not part of the shipped
artifact.

## Toolchain & commands

- **PHP 8.0+** target (developed against 8.4). WordPress coding standards:
  **tabs**, Yoda conditions, `esc_*` output, nonces + capability checks.
- **No test suite, no linter, no typecheck command.** Verification is manual
  (activate in WP, hit `/wp-json/wp-mcp/mcp`). IDE analysis is intelephense
  via `.neoconf.json` + stubs in `vendor/php-stubs` and `vendor/arts`.
- Composer is a **dev convenience only** — `vendor/` ships in the release zip.

  ```bash
  composer install                                          # dev: pulls stubs for intelephense
  composer install --no-dev --classmap-authoritative         # production vendor tree (matches CI)
  ```

- Do **not** add `vendor/` to `.gitignore` (it is intentionally committed).
  `node_modules/` and `opencode.jsonc` ARE gitignored — `opencode.jsonc`
  contains a live API key, never commit it.

## Architecture

- Entry point: `wp-mcp-plugin.php` → `wp_mcp_init()` hooked on `init`
  priority 5. It loads `vendor/autoload_packages.php` (Jetpack Autoloader),
  requires the `includes/*.php` files **in order**, then calls
  `WpMcp\Plugin::instance()`.
- `WpMcp\Plugin` (singleton, `includes/class-plugin.php`) is the orchestrator.
  It suppresses the mcp-adapter default server
  (`mcp_adapter_create_default_server → false`) and creates its own server on
  `mcp_adapter_init` priority 20.
- **Abilities** (MCP tools) live in `includes/abilities/`, grouped by domain
  (Query, Page, Element, Settings, Render, Guide). `Registrar` instantiates
  each group, calls `register()`, and collects the slug lists.
- `ToolRegistry` (`includes/class-tool-registry.php`) is the **canonical static
  metadata** for the admin UI and activation defaults. Ability slugs there
  **must stay in sync** with the registrations in `includes/abilities/*.php` —
  if you add/rename a tool, update both.
- Tool names are registered as `wp-mcp/<slug>` but the
  `mcp_adapter_tool_name` / `mcp_adapter_prompt_name` filters strip the
  `wp-mcp-` prefix so MCP clients see clean names (`list-pages`, etc.). Keep
  that filter when refactoring.

### Hook-ordering gotcha (do not reorder)

`wp_abilities_api_init` is a lazy hook that fires when
`WP_Abilities_Registry::get_instance()` is first called. During REST requests
`mcp_adapter_init` fires **before** `wp_abilities_api_init` has triggered, so
`Plugin::register_mcp_server()` explicitly calls
`\WP_Abilities_Registry::get_instance()` to force the hook and populate
`$this->ability_names` before they are used. Reordering these hooks will
produce "ability does not exist" errors.

## Vendored dependencies

- `wordpress/mcp-adapter` and `wordpress/php-mcp-schema` ship in `vendor/`.
  The Jetpack Autoloader coordinates versions across plugins — the **newer**
  version wins automatically. If a standalone MCP Adapter plugin is active
  with a compatible version, it is used instead of the vendored copy.
- Minimum adapter version is `WP_MCP_MIN_ADAPTER_VERSION` (currently `0.5.0`)
  in `wp-mcp-plugin.php`. Bump it when raising the `composer.json` require.

## Auth & transport

- MCP endpoint: `POST /wp-json/wp-mcp/mcp`. Auth via `X-WP-MCP-Key` header or
  `Authorization: Bearer <key>`. Protocol version `2025-11-25` with fallbacks
  to `2025-06-18` / `2024-11-05`. Streamable HTTP sessions require the
  `Mcp-Session-Id` header after `initialize`.
- The API key gates the **transport** only. On success the plugin
  authenticates as a service WordPress user (first administrator by default;
  override via the `wp_mcp_authenticated_user_id` filter). Mutating tools then
  enforce WP capabilities (`edit_pages` for page tools, `manage_options` for
  global settings).

## Docs & skill

- `includes/instructions.md` → the concise `instructions` field returned on
  MCP `initialize`. `includes/docs/*.md` → MCP resources at `wp-mcp://docs/*`
  (registered by `includes/class-docs.php`). Edit these markdown files to
  change what clients see; the index is in `includes/instructions.php`.
- `skills/elementor-page-builder/SKILL.md` is the model-invoked skill shipped
  with the plugin (client-agnostic; users copy it into their client's skills
  dir).

## Versioning & release

- Bump the version in **two** places in `wp-mcp-plugin.php`: the
  `Version:` header and the `WP_MCP_PLUGIN_VERSION` constant. On version
  change, `wp_mcp_init()` runs `EnabledTools::sync()` to auto-enable any
  newly-registered tools for existing installs.
- Releases are tag-driven: push `v*` → `.github/workflows/release.yml` runs
  `composer install --no-dev --classmap-authoritative`, zips the plugin dir
  (excluding `.git`, `.github`, `composer.json`, `composer.lock` via `zip -x`
  — `vendor/` IS included), and publishes a GitHub Release.
- `.github/workflows/readme.yml` regenerates `readme.txt` from the plugin
  header on pushes to `main` that touch `wp-mcp-plugin.php` or `includes/**`,
  then auto-commits it. Don't hand-edit `readme.txt` — edit the header in
  `wp-mcp-plugin.php`.
