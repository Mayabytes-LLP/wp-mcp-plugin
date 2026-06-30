<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical MCP tool metadata for the admin UI and activation defaults.
 *
 * Ability slugs must stay in sync with includes/abilities/*.php registrations.
 */
class ToolRegistry {

	/**
	 * All tools grouped by category for the settings UI.
	 *
	 * @return array<string, array<string, string>> Group label => slug => description.
	 */
	public static function get_groups(): array {
		return array(
			__( 'Read Tools', 'wp-mcp-plugin' ) => array(
				'wp-mcp/list-pages'                    => __( 'List all pages', 'wp-mcp-plugin' ),
				'wp-mcp/get-page'                      => __( 'Get page details and content', 'wp-mcp-plugin' ),
				'wp-mcp/list-elementor-widgets'        => __( 'List available widgets', 'wp-mcp-plugin' ),
				'wp-mcp/get-elementor-widget-schema'   => __( 'Get widget schema', 'wp-mcp-plugin' ),
				'wp-mcp/list-elementor-templates'      => __( 'List saved templates', 'wp-mcp-plugin' ),
				'wp-mcp/get-elementor-global-settings' => __( 'Get global design settings', 'wp-mcp-plugin' ),
			),
			__( 'Write Tools', 'wp-mcp-plugin' ) => array(
				'wp-mcp/create-page'                    => __( 'Create a new page', 'wp-mcp-plugin' ),
				'wp-mcp/update-page-elementor-data'     => __( 'Replace page Elementor data', 'wp-mcp-plugin' ),
				'wp-mcp/delete-page'                    => __( 'Delete a page', 'wp-mcp-plugin' ),
				'wp-mcp/add-container'                  => __( 'Add a container', 'wp-mcp-plugin' ),
				'wp-mcp/add-widget'                     => __( 'Add a widget', 'wp-mcp-plugin' ),
				'wp-mcp/update-element'                 => __( 'Update element settings', 'wp-mcp-plugin' ),
				'wp-mcp/remove-element'                 => __( 'Remove an element', 'wp-mcp-plugin' ),
				'wp-mcp/batch-update'                   => __( 'Batch update elements', 'wp-mcp-plugin' ),
				'wp-mcp/update-elementor-global-settings' => __( 'Update global design settings', 'wp-mcp-plugin' ),
			),
			__( 'Render Tools', 'wp-mcp-plugin' ) => array(
				'wp-mcp/regenerate-elementor-css' => __( 'Regenerate Elementor CSS after writes', 'wp-mcp-plugin' ),
				'wp-mcp/visual-compare-preview' => __( 'Preview page for visual comparison', 'wp-mcp-plugin' ),
			),
			__( 'Diagnostics', 'wp-mcp-plugin' ) => array(
				'wp-mcp/get-plugin-status' => __( 'Get plugin status and debug info', 'wp-mcp-plugin' ),
				'wp-mcp/get-server-guide'    => __( 'Get server workflow and documentation index', 'wp-mcp-plugin' ),
			),
		);
	}

	/**
	 * Flat slug => label map.
	 *
	 * @return array<string, string>
	 */
	public static function get_all_flat(): array {
		$tools = array();
		foreach ( self::get_groups() as $group ) {
			$tools = array_merge( $tools, $group );
		}
		return $tools;
	}

	/**
	 * @return string[]
	 */
	public static function get_all_slugs(): array {
		return array_keys( self::get_all_flat() );
	}
}
