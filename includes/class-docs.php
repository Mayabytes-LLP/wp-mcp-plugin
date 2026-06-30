<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the plugin's MCP documentation resources.
 *
 * Each resource is a URI-addressable markdown file exposed via resources/list
 * and resources/read. Resources are the client-agnostic auto-discovery path:
 * the LLM invokes mcp_read_resource autonomously to pull deep documentation
 * on demand, keeping the instructions field and tool descriptions lean.
 */
class Docs {

	/** @var string[] Resource ability slugs. */
	private array $resource_names = array();

	/** @var array<string,array{file:string,uri:string,label:string,description:string,priority:float}> */
	private const DOC_DEFINITIONS = array(
		'wp-mcp/docs-workflow' => array(
			'file'        => 'docs/workflow.md',
			'uri'         => 'wp-mcp://docs/workflow',
			'label'       => 'Build Workflow',
			'description' => 'Build workflow, draft-only visual comparison loop, regenerate-elementor-css before visual-compare-preview, and batch-update.',
			'priority'    => 1.0,
		),
		'wp-mcp/docs-container-system' => array(
			'file'        => 'docs/container-system.md',
			'uri'         => 'wp-mcp://docs/container-system',
			'label'       => 'Container System',
			'description' => 'Flex vs grid containers, settings table, nesting rules (3-level max), and the two-container full-bleed pattern.',
			'priority'    => 0.9,
		),
		'wp-mcp/docs-elementor-data-structure' => array(
			'file'        => 'docs/elementor-data-structure.md',
			'uri'         => 'wp-mcp://docs/elementor-data-structure',
			'label'       => 'Elementor Data Structure',
			'description' => 'The _elementor_data JSON tree shape, element IDs, elType, settings, responsive suffixes, and __globals__ references.',
			'priority'    => 0.9,
		),
		'wp-mcp/docs-global-settings' => array(
			'file'        => 'docs/global-settings.md',
			'uri'         => 'wp-mcp://docs/global-settings',
			'label'       => 'Global Settings',
			'description' => 'Reading and updating global colors and typography; the typography_typography:custom requirement.',
			'priority'    => 0.5,
		),
		'wp-mcp/docs-widget-types' => array(
			'file'        => 'docs/widget-types.md',
			'uri'         => 'wp-mcp://docs/widget-types',
			'label'       => 'Widget Types',
			'description' => 'Common widget reference table and the repeater-field caveat.',
			'priority'    => 0.5,
		),
		'wp-mcp/docs-common-patterns' => array(
			'file'        => 'docs/common-patterns.md',
			'uri'         => 'wp-mcp://docs/common-patterns',
			'label'       => 'Common Patterns',
			'description' => 'Hero, feature grid, CTA, and landing page flow patterns.',
			'priority'    => 0.5,
		),
		'wp-mcp/docs-figma-conversion' => array(
			'file'        => 'docs/figma-conversion.md',
			'uri'         => 'wp-mcp://docs/figma-conversion',
			'label'       => 'Figma Conversion',
			'description' => 'Full Figma to Elementor conversion workflow across 4 phases: discover, map, handle problems, build.',
			'priority'    => 0.5,
		),
		'wp-mcp/docs-best-practices' => array(
			'file'        => 'docs/best-practices.md',
			'uri'         => 'wp-mcp://docs/best-practices',
			'label'       => 'Best Practices',
			'description' => "Dos and don'ts for building Elementor pages and converting Figma designs.",
			'priority'    => 0.5,
		),
	);

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		foreach ( self::DOC_DEFINITIONS as $slug => $definition ) {
			$this->register_doc( $slug, $definition );
		}
	}

	/**
	 * @param string $slug Ability slug.
	 * @param array{file:string,uri:string,label:string,description:string,priority:float} $definition Doc metadata.
	 */
	private function register_doc( string $slug, array $definition ): void {
		$file_path = $definition['file'];

		wp_register_ability( $slug, array(
			'label'               => __( $definition['label'], 'wp-mcp-plugin' ),
			'description'         => __( $definition['description'], 'wp-mcp-plugin' ),
			'category'            => 'wp-mcp-plugin',
			'execute_callback'    => function () use ( $file_path ) {
				return wp_mcp_read_doc_file( $file_path );
			},
			'permission_callback' => array( ApiKey::class, 'check_ability_permission' ),
			'meta'                => array(
				'mcp' => array(
					'type'        => 'resource',
					'public'      => true,
					'uri'         => $definition['uri'],
					'mimeType'    => 'text/markdown',
					'annotations' => AbilitySchemas::resource_annotations( $definition['priority'] ),
				),
			),
		) );

		$this->resource_names[] = $slug;
	}

	/**
	 * @return string[] Resource ability slugs.
	 */
	public function get_resource_names(): array {
		return $this->resource_names;
	}
}
