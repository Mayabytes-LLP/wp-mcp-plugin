<?php
/**
 * Plugin Name:  WP MCP Plugin
 * Plugin URI:   https://github.com/Mayabytes-LLP/wp-mcp-plugin
 * Description:  Exposes WordPress + Elementor as an MCP server so AI coding agents can
 *               read Figma designs and generate Elementor pages through MCP tools.
 * Version:      0.1.0
 * Requires at least: 6.9
 * Tested up to: 6.9
 * Requires PHP: 8.0
 * Author:       Mayabytes LLP
 * License:      GPL-2.0-or-later
 * License URI:  https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:  wp-mcp-plugin
 * Domain Path:  /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_MCP_PLUGIN_VERSION', '0.1.0' );
define( 'WP_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_MCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check plugin dependencies and show admin notices if missing.
 *
 * @return string[] List of missing dependency slugs.
 */
function wp_mcp_check_dependencies(): array {
	$missing = array();

	if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
		$missing[] = 'elementor';
	}

	if ( ! class_exists( 'WP\MCP\Core\McpAdapter' ) ) {
		$missing[] = 'mcp-adapter';
	}

	return $missing;
}

/**
 * Display an admin notice for a missing dependency.
 */
function wp_mcp_show_dependency_notice( string $slug ): void {
	$names = array(
		'elementor'    => 'Elementor',
		'mcp-adapter'  => 'WordPress MCP Adapter',
	);

	$label = $names[ $slug ] ?? $slug;

	add_action( 'admin_notices', function () use ( $label, $slug ) {
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			sprintf(
				/* translators: 1: plugin name, 2: dependency name */
				esc_html__( '%1$s requires %2$s to be installed and activated. Please install %2$s to enable the MCP server.', 'wp-mcp-plugin' ),
				'<strong>WP MCP Plugin</strong>',
				'<strong>' . esc_html( $label ) . '</strong>'
			)
		);
	} );
}

/**
 * Bootstrap the plugin on plugins_loaded at priority 20 (after Elementor).
 */
function wp_mcp_init(): void {
	$missing = wp_mcp_check_dependencies();

	if ( ! empty( $missing ) ) {
		foreach ( $missing as $slug ) {
			wp_mcp_show_dependency_notice( $slug );
		}
		return;
	}

	require_once WP_MCP_PLUGIN_DIR . 'includes/class-api-key.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/class-schema-generator.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/class-admin-page.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/abilities/class-query-abilities.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/abilities/class-page-abilities.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/abilities/class-element-abilities.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/abilities/class-settings-abilities.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/abilities/class-ability-registrar.php';
	require_once WP_MCP_PLUGIN_DIR . 'includes/class-plugin.php';
	\WpMcp\Plugin::instance();
}

add_action( 'plugins_loaded', 'wp_mcp_init', 20 );

/**
 * Plugin activation: set default options if not already present.
 */
function wp_mcp_activate(): void {
	if ( false === get_option( 'wp_mcp_server_enabled' ) ) {
		update_option( 'wp_mcp_server_enabled', true );
	}

	$default_tools = array(
		'wp-mcp/list-pages',
		'wp-mcp/get-page',
		'wp-mcp/list-elementor-widgets',
		'wp-mcp/get-elementor-widget-schema',
		'wp-mcp/list-elementor-templates',
		'wp-mcp/get-elementor-global-settings',
		'wp-mcp/create-page',
		'wp-mcp/update-page-elementor-data',
		'wp-mcp/delete-page',
		'wp-mcp/add-container',
		'wp-mcp/add-widget',
		'wp-mcp/update-element',
		'wp-mcp/remove-element',
		'wp-mcp/batch-update',
		'wp-mcp/update-elementor-global-settings',
		'wp-mcp/get-plugin-status',
	);

	if ( false === get_option( 'wp_mcp_enabled_tools' ) ) {
		update_option( 'wp_mcp_enabled_tools', $default_tools );
	}
}

/**
 * Plugin deactivation: clear transients, leave options/data intact.
 */
function wp_mcp_deactivate(): void {
	delete_transient( 'wp_mcp_widget_schemas' );
	delete_transient( 'wp_mcp_widget_list' );
	delete_transient( 'wp_mcp_api_key_once' );
}

register_activation_hook( __FILE__, 'wp_mcp_activate' );
register_deactivation_hook( __FILE__, 'wp_mcp_deactivate' );
