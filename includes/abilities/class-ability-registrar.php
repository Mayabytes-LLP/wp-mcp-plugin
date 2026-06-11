<?php

namespace WpMcp\Abilities;

use WpMcp\SchemaGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates registration of all plugin abilities with the WordPress Abilities API.
 *
 * Each ability group class is instantiated and its register() method called
 * in dependency order. The full list of ability slugs is collected for
 * server registration and tool filtering.
 */
class Registrar {

	private SchemaGenerator $schema_generator;

	/** @var string[] */
	private array $ability_names = array();

	public function __construct( SchemaGenerator $schema_generator ) {
		$this->schema_generator = $schema_generator;
	}

	/**
	 * Register all ability groups.
	 */
	public function register_all(): void {
		$query    = new Query( $this->schema_generator );
		$page     = new Page();
		$element  = new Element();
		$settings = new Settings();

		$query->register();
		$page->register();
		$element->register();
		$settings->register();

		$this->ability_names = array_merge(
			$query->get_ability_names(),
			$page->get_ability_names(),
			$element->get_ability_names(),
			$settings->get_ability_names()
		);

		$this->register_usage_guide();
	}

	/**
	 * @return string[] All registered ability slugs.
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Register the usage guide as an MCP prompt.
	 *
	 * Uses the WordPress Abilities API as a prompt builder.
	 */
	private function register_usage_guide(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$guide_content = require WP_MCP_PLUGIN_DIR . 'includes/usage-guide.php';

		wp_register_ability( 'wp-mcp/usage-guide', array(
			'label'             => __( 'Usage Guide', 'wp-mcp-plugin' ),
			'description'       => __( 'Comprehensive guide on how to use the WP MCP Plugin tools for Elementor page building.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => function () use ( $guide_content ) {
				return array(
					'messages' => array(
						array(
							'role'    => 'user',
							'content' => array(
								array(
									'type' => 'text',
									'text' => $guide_content,
								),
							),
						),
					),
				);
			},
			'permission_callback' => '__return_true',
			'meta'              => array(
				'mcp' => array(
					'type'   => 'prompt',
					'public' => true,
				),
			),
		) );
	}
}
