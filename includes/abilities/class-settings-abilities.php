<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global settings and plugin diagnostics tools.
 */
class Settings {

	/** @var string[] */
	private array $ability_names = array();

	public function register(): void {
		$this->register_update_global_settings();
		$this->register_get_plugin_status();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function check_settings_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function register_update_global_settings(): void {
		$this->ability_names[] = 'wp-mcp/update-elementor-global-settings';

		wp_register_ability( 'wp-mcp/update-elementor-global-settings', array(
			'label'             => __( 'Update Elementor Global Settings', 'wp-mcp-plugin' ),
			'description'       => __( 'Update the active Elementor kit\'s global design settings: custom colors and typography. Use get-elementor-global-settings first to see the current state. See resource \`wp-mcp://docs/global-settings\` for color/typography format and the \`typography_typography: "custom"\` requirement.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_update_global_settings' ),
			'permission_callback' => array( $this, 'check_settings_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'colors'            => array(
						'type'        => 'array',
						'description' => 'Updated custom colors array. Each item: { _id (string), title (string), color (hex string) }.',
						'items'       => AbilitySchemas::kit_color_item(),
					),
					'typography'        => array(
						'type'        => 'array',
						'description' => 'Updated custom typography array. Each item: { _id (string), title (string), typography_* properties }.',
						'items'       => AbilitySchemas::kit_typography_item(),
					),
				),
				'anyOf' => array(
					array( 'required' => array( 'colors' ) ),
					array( 'required' => array( 'typography' ) ),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
				),
				'required'   => array( 'success' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, true, false ),
			),
		) );
	}

	public function execute_update_global_settings( array $input ): array|\WP_Error {
		if ( ! class_exists( '\Elementor\Plugin' )
			|| ! \Elementor\Plugin::$instance->kits_manager
		) {
			return new \WP_Error( 'elementor_missing', __( 'Elementor kits manager unavailable.', 'wp-mcp-plugin' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit ) {
			return new \WP_Error( 'no_active_kit', __( 'No active Elementor kit found.', 'wp-mcp-plugin' ) );
		}

		$updates = array();

		if ( isset( $input['colors'] ) && is_array( $input['colors'] ) ) {
			$colors = array();
			foreach ( $input['colors'] as $i => $color ) {
			$color_val = sanitize_hex_color( $color['color'] ?? '' );
			if ( null === $color_val ) {
					return new \WP_Error(
						'invalid_color',
						sprintf(
							/* translators: %s: color title or index */
							__( 'Invalid hex color for item "%s".', 'wp-mcp-plugin' ),
							$color['title'] ?? (string) $i
						)
					);
				}
				$colors[] = array(
					'_id'    => sanitize_text_field( $color['_id'] ?? wp_generate_uuid4() ),
					'title'  => sanitize_text_field( $color['title'] ?? '' ),
					'color'  => $color_val,
				);
			}

			$current_settings = $kit->get_settings();
			$key = isset( $current_settings['system_colors'] ) ? 'system_colors' : 'custom_colors';
			$existing = $current_settings[ $key ] ?? array();

			// Merge: update existing by _id, add new ones
			$existing_map = array();
			foreach ( $existing as $item ) {
				$existing_map[ $item['_id'] ] = $item;
			}
			foreach ( $colors as $color ) {
				$existing_map[ $color['_id'] ] = $color;
			}
			$updates[ $key ] = array_values( $existing_map );
		}

		if ( isset( $input['typography'] ) && is_array( $input['typography'] ) ) {
			$typographies = array();
			foreach ( $input['typography'] as $typo ) {
				$item = array(
					'_id'                    => sanitize_text_field( $typo['_id'] ?? wp_generate_uuid4() ),
					'title'                  => sanitize_text_field( $typo['title'] ?? '' ),
					'typography_typography'  => 'custom',
					'typography_font_family' => sanitize_text_field( $typo['typography_font_family'] ?? '' ),
				);

				foreach ( array(
					'typography_font_size',
					'typography_font_weight',
					'typography_text_transform',
					'typography_font_style',
					'typography_text_decoration',
					'typography_line_height',
					'typography_letter_spacing',
					'typography_word_spacing',
				) as $field ) {
					if ( isset( $typo[ $field ] ) ) {
						$item[ $field ] = sanitize_text_field( $typo[ $field ] );
					}
				}

				$typographies[] = $item;
			}

			$current_settings = $kit->get_settings();
			$key = isset( $current_settings['system_typography'] ) ? 'system_typography' : 'custom_typography';
			$existing = $current_settings[ $key ] ?? array();

			$existing_map = array();
			foreach ( $existing as $item ) {
				$existing_map[ $item['_id'] ] = $item;
			}
			foreach ( $typographies as $typo ) {
				$existing_map[ $typo['_id'] ] = $typo;
			}
			$updates[ $key ] = array_values( $existing_map );
		}

		if ( empty( $updates ) ) {
			return new \WP_Error(
				'no_updates',
				__( 'No colors or typography provided for update.', 'wp-mcp-plugin' )
			);
		}

		try {
			$kit->update_settings( $updates );
			$kit->save( array( 'settings' => $kit->get_settings() ) );
		} catch ( \Exception $e ) {
			return new \WP_Error( 'kit_save_failed', $e->getMessage() );
		}

		return array( 'success' => true );
	}

	private function register_get_plugin_status(): void {
		$this->ability_names[] = 'wp-mcp/get-plugin-status';

		wp_register_ability( 'wp-mcp/get-plugin-status', array(
			'label'             => __( 'Get Plugin Status', 'wp-mcp-plugin' ),
			'description'       => __( 'Get diagnostic information about the WP MCP Plugin: Elementor version, MCP adapter availability, API key status, server state, and enabled tools count.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_get_plugin_status' ),
			'permission_callback' => array( $this, 'check_settings_permission' ),
			'input_schema'      => array(
				'type' => 'object',
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'elementor_version'        => array( 'type' => 'string' ),
					'elementor_pro_version'    => array( 'type' => 'string' ),
					'mcp_adapter_active'       => array( 'type' => 'boolean' ),
					'api_key_configured'       => array( 'type' => 'boolean' ),
					'server_enabled'           => array( 'type' => 'boolean' ),
					'enabled_tools_count'      => array( 'type' => 'integer' ),
					'plugin_version'           => array( 'type' => 'string' ),
				),
				'required'   => array(
					'elementor_version',
					'elementor_pro_version',
					'mcp_adapter_active',
					'api_key_configured',
					'server_enabled',
					'enabled_tools_count',
					'plugin_version',
				),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_plugin_status( array $input ): array {
		$api_key   = new \WpMcp\ApiKey();
		$mcp_adapter = class_exists( 'WP\MCP\Core\McpAdapter' );

		return array(
			'elementor_version'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
			'elementor_pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '',
			'mcp_adapter_active'    => $mcp_adapter,
			'api_key_configured'    => $api_key->is_configured(),
			'server_enabled'        => (bool) get_option( 'wp_mcp_server_enabled', true ),
			'enabled_tools_count'   => count( get_option( 'wp_mcp_enabled_tools', array() ) ),
			'plugin_version'        => WP_MCP_PLUGIN_VERSION,
		);
	}
}
