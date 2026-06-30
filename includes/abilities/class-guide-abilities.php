<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;
use WpMcp\ApiKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP guidance tools and user-triggered prompt playbooks.
 */
class Guide {

	/** @var string[] Tool ability slugs. */
	private array $ability_names = array();

	/** @var string[] Prompt ability slugs. */
	private array $prompt_names = array();

	public function register(): void {
		$this->register_get_server_guide();
		$this->register_build_landing_page_prompt();
		$this->register_figma_to_elementor_prompt();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** @return string[] */
	public function get_prompt_names(): array {
		return $this->prompt_names;
	}

	private function register_get_server_guide(): void {
		$this->ability_names[] = 'wp-mcp/get-server-guide';

		wp_register_ability( 'wp-mcp/get-server-guide', array(
			'label'               => __( 'Get Server Guide', 'wp-mcp-plugin' ),
			'description'         => __( 'Returns server workflow and documentation index. Call this first if server instructions were not provided by your MCP client (e.g. Cline).', 'wp-mcp-plugin' ),
			'category'            => 'wp-mcp-plugin',
			'execute_callback'    => array( $this, 'execute_get_server_guide' ),
			'permission_callback' => array( ApiKey::class, 'check_ability_permission' ),
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'instructions' => array(
						'type'        => 'string',
						'description' => 'Concise cross-tool workflow and resource pointers (same as initialize instructions).',
					),
					'resources'    => array(
						'type'                 => 'object',
						'description'          => 'Map of MCP resource URI to one-line description.',
						'additionalProperties' => array( 'type' => 'string' ),
					),
				),
				'required'   => array( 'instructions', 'resources' ),
			),
			'meta'                => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_server_guide(): array {
		return array(
			'instructions' => wp_mcp_get_instructions(),
			'resources'    => wp_mcp_get_resource_index(),
		);
	}

	private function register_build_landing_page_prompt(): void {
		$this->prompt_names[] = 'wp-mcp/build-landing-page';

		wp_register_ability( 'wp-mcp/build-landing-page', array(
			'label'               => __( 'Build Landing Page', 'wp-mcp-plugin' ),
			'description'         => __( 'User-triggered playbook for building an Elementor landing page from scratch.', 'wp-mcp-plugin' ),
			'category'            => 'wp-mcp-plugin',
			'execute_callback'    => array( $this, 'execute_build_landing_page_prompt' ),
			'permission_callback' => array( ApiKey::class, 'check_ability_permission' ),
			'meta'                => array(
				'mcp' => array(
					'type'   => 'prompt',
					'public' => true,
				),
			),
		) );
	}

	public function execute_build_landing_page_prompt(): array {
		$text = <<<'PROMPT'
Build an Elementor landing page using this checklist:

1. Call `get-elementor-global-settings` for design tokens.
2. Read `wp-mcp://docs/workflow`, `wp-mcp://docs/container-system`, and `wp-mcp://docs/common-patterns` via resources/read.
3. Call `create-page` with `template: "elementor_canvas"` and draft status.
4. Discover widgets with `list-elementor-widgets` and `get-elementor-widget-schema` before writing.
5. Build layout with `add-container` (max 3 nesting levels; use two-container pattern for full-bleed backgrounds).
6. Add content with `add-widget`.
7. Apply styling tweaks with `batch-update`.
8. Verify with `get-page`.
9. For visual QA: `regenerate-elementor-css` → `visual-compare-preview` → screenshot and compare.

Deep patterns (hero, feature grid, CTA): read `wp-mcp://docs/common-patterns`.
PROMPT;

		return array( 'text' => $text );
	}

	private function register_figma_to_elementor_prompt(): void {
		$this->prompt_names[] = 'wp-mcp/figma-to-elementor';

		wp_register_ability( 'wp-mcp/figma-to-elementor', array(
			'label'               => __( 'Figma to Elementor', 'wp-mcp-plugin' ),
			'description'         => __( 'User-triggered playbook for converting a Figma design into an Elementor page.', 'wp-mcp-plugin' ),
			'category'            => 'wp-mcp-plugin',
			'execute_callback'    => array( $this, 'execute_figma_to_elementor_prompt' ),
			'permission_callback' => array( ApiKey::class, 'check_ability_permission' ),
			'meta'                => array(
				'mcp' => array(
					'type'   => 'prompt',
					'public' => true,
				),
			),
		) );
	}

	public function execute_figma_to_elementor_prompt(): array {
		$text = <<<'PROMPT'
Convert a Figma design to an Elementor page:

1. Read the Figma design via the Figma MCP (design context, screenshot, tokens).
2. Read `wp-mcp://docs/figma-conversion` via resources/read — it is the single source of truth for the 4-phase workflow and mapping tables.
3. Read `wp-mcp://docs/container-system` and `wp-mcp://docs/elementor-data-structure` before any write.
4. Call `get-elementor-global-settings` and map Figma tokens to global colors/typography (`__globals__` preferred over hardcoded hex).
5. Call `create-page` as draft with `template: "elementor_canvas"`.
6. Build with `add-container` / `add-widget`; use `get-elementor-widget-schema` for each widget type (schema-first).
7. Batch styling with `batch-update`; verify with `get-page`.
8. Visual loop: `regenerate-elementor-css` → `visual-compare-preview` → screenshot vs Figma → fix with `update-element` / `batch-update`.

Full conversion guide: `wp-mcp://docs/figma-conversion`.
PROMPT;

		return array( 'text' => $text );
	}
}
