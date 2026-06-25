<?php

namespace WpMcp;

use WpMcp\Abilities\Registrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin orchestrator singleton.
 *
 * Loads all subsystems in dependency order and wires WordPress hooks.
 */
class Plugin {

	private static ?Plugin $instance = null;

	private AdminPage $admin_page;
	private ApiKey $api_key;
	private SchemaGenerator $schema_generator;

	/** @var string[] Full list of ability slugs registered by this plugin. */
	private array $ability_names = array();

	/** @var string[] Full list of resource ability slugs registered by this plugin. */
	private array $resource_names = array();

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance       = new self();
			self::$instance->init();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function init(): void {
		$this->admin_page       = new AdminPage();
		$this->api_key          = new ApiKey();
		$this->schema_generator = new SchemaGenerator();

		// Admin UI
		if ( is_admin() ) {
			$this->admin_page->register();
		}

		// Admin bar indicator
		add_action( 'admin_bar_menu', array( $this->admin_page, 'add_admin_bar_node' ), 100 );

		// Prevent mcp-adapter from creating its default server (which needs
		// built-in abilities registered at init:20). We create our own server
		// with our own abilities — the default server would fail with
		// "ability does not exist" errors since our hook ordering causes
		// wp_abilities_api_init to fire before those abilities are registered.
		add_filter( 'mcp_adapter_create_default_server', '__return_false' );

		// MCP Adapter hooks
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ), 20 );

		// Filter: allow other code to modify the ability list before server registration
		add_filter( 'wp_mcp_ability_names', array( $this, 'filter_disabled_tools' ), 10, 1 );

		// Strip wp-mcp- prefix from MCP tool names so they appear clean (e.g. "list-pages" not "wp-mcp-list-pages").
		add_filter( 'mcp_adapter_tool_name', array( $this, 'strip_tool_name_prefix' ), 10, 2 );
	}

	public function register_category(): void {
		wp_register_ability_category( 'wp-mcp-plugin', array(
			'label'       => __( 'WP MCP Plugin', 'wp-mcp-plugin' ),
			'description' => __( 'Elementor page building tools exposed via MCP.', 'wp-mcp-plugin' ),
		) );
	}

	public function register_abilities(): void {
		$registrar = new Registrar( $this->schema_generator );
		$registrar->register_all();
		$this->ability_names  = $registrar->get_ability_names();
		$this->resource_names = $registrar->get_resource_names();
	}

	public function register_mcp_server( $adapter ): void {
		// Ensure abilities are registered before we reference them.
		// wp_abilities_api_init is a lazy hook that fires when
		// WP_Abilities_Registry::get_instance() is first called.
		// During REST API requests, mcp_adapter_init fires before
		// wp_abilities_api_init has been triggered, so we force
		// the registry to initialize here. This triggers the hook,
		// which calls our register_abilities() and populates
		// $this->ability_names before we use it below.
		\WP_Abilities_Registry::get_instance();

		$server_enabled = (bool) get_option( 'wp_mcp_server_enabled', true );
		if ( ! $server_enabled ) {
			return;
		}

		$ability_names = apply_filters( 'wp_mcp_ability_names', $this->ability_names );

		$adapter->create_server(
			'wp-mcp',
			'wp-mcp',
			'mcp',
			__( 'WP MCP Plugin - Elementor Builder', 'wp-mcp-plugin' ),
			\wp_mcp_get_instructions(),
			'v' . WP_MCP_PLUGIN_VERSION,
			array( \WP\MCP\Transport\HttpTransport::class ),
			\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler::class,
			\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler::class,
			$ability_names,
			$this->resource_names,
			array(),
			function ( $request ) {
				return $this->api_key->verify_transport_permission( $request );
			}
		);
	}

	/**
	 * Strip the wp-mcp- prefix from MCP tool names so tools appear as
	 * "list-pages" instead of "wp-mcp-list-pages".
	 *
	 * @param string     $tool_name Sanitized MCP tool name (e.g. "wp-mcp-list-pages").
	 * @param \WP_Ability $ability  The WordPress ability being converted.
	 * @return string Clean tool name.
	 */
	public function strip_tool_name_prefix( string $tool_name, \WP_Ability $ability ): string {
		if ( str_starts_with( $tool_name, 'wp-mcp-' ) ) {
			return substr( $tool_name, 7 );
		}
		return $tool_name;
	}

	/**
	 * Remove tools the admin has disabled via the settings page.
	 *
	 * @param string[] $ability_names Full list of ability slugs.
	 * @return string[] Filtered list.
	 */
	public function filter_disabled_tools( array $ability_names ): array {
		$enabled_tools = get_option( 'wp_mcp_enabled_tools', $ability_names );
		if ( ! is_array( $enabled_tools ) ) {
			return $ability_names;
		}

		// Migrate renamed tool slugs (pre-v0.2.0) to current naming.
		$renamed = array(
			'wp-mcp/render-page' => 'wp-mcp/visual-compare-preview',
		);
		$updated = false;
		foreach ( $renamed as $old => $new ) {
			$pos = array_search( $old, $enabled_tools, true );
			if ( false !== $pos ) {
				$enabled_tools[ $pos ] = $new;
				$updated = true;
			}
		}
		if ( $updated ) {
			update_option( 'wp_mcp_enabled_tools', $enabled_tools );
		}

		return array_values( array_intersect( $ability_names, $enabled_tools ) );
	}
}
