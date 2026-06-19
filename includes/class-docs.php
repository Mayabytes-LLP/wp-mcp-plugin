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

	/** @var array<string,string> Map of slug => docs file path (relative to includes/). */
	private const DOC_FILES = array(
		'wp-mcp/docs-elementor-data-structure' => 'docs/elementor-data-structure.md',
		'wp-mcp/docs-container-system'         => 'docs/container-system.md',
		'wp-mcp/docs-workflow'                 => 'docs/workflow.md',
		'wp-mcp/docs-global-settings'          => 'docs/global-settings.md',
		'wp-mcp/docs-widget-types'              => 'docs/widget-types.md',
		'wp-mcp/docs-common-patterns'           => 'docs/common-patterns.md',
		'wp-mcp/docs-figma-conversion'          => 'docs/figma-conversion.md',
		'wp-mcp/docs-best-practices'            => 'docs/best-practices.md',
	);

	/** @var array<string,string> Map of slug => MCP URI. */
	private const DOC_URIS = array(
		'wp-mcp/docs-elementor-data-structure' => 'wp-mcp://docs/elementor-data-structure',
		'wp-mcp/docs-container-system'         => 'wp-mcp://docs/container-system',
		'wp-mcp/docs-workflow'                 => 'wp-mcp://docs/workflow',
		'wp-mcp/docs-global-settings'          => 'wp-mcp://docs/global-settings',
		'wp-mcp/docs-widget-types'              => 'wp-mcp://docs/widget-types',
		'wp-mcp/docs-common-patterns'           => 'wp-mcp://docs/common-patterns',
		'wp-mcp/docs-figma-conversion'          => 'wp-mcp://docs/figma-conversion',
		'wp-mcp/docs-best-practices'            => 'wp-mcp://docs/best-practices',
	);

	/** @var array<string,string> Map of slug => human label. */
	private const DOC_LABELS = array(
		'wp-mcp/docs-elementor-data-structure' => 'Elementor Data Structure',
		'wp-mcp/docs-container-system'         => 'Container System',
		'wp-mcp/docs-workflow'                 => 'Build Workflow',
		'wp-mcp/docs-global-settings'          => 'Global Settings',
		'wp-mcp/docs-widget-types'              => 'Widget Types',
		'wp-mcp/docs-common-patterns'           => 'Common Patterns',
		'wp-mcp/docs-figma-conversion'          => 'Figma Conversion',
		'wp-mcp/docs-best-practices'            => 'Best Practices',
	);

	/** @var array<string,string> Map of slug => one-line description. */
	private const DOC_DESCRIPTIONS = array(
		'wp-mcp/docs-elementor-data-structure' => 'The _elementor_data JSON tree shape, element IDs, elType, settings, responsive suffixes, and __globals__ references.',
		'wp-mcp/docs-container-system'         => 'Flex vs grid containers, settings table, nesting rules (3-level max), and the two-container full-bleed pattern.',
		'wp-mcp/docs-workflow'                 => 'Standard build workflow, when to use update-page-elementor-data vs incremental add-container/add-widget, and batch-update.',
		'wp-mcp/docs-global-settings'          => 'Reading and updating global colors and typography; the typography_typography:custom requirement.',
		'wp-mcp/docs-widget-types'              => 'Common widget reference table and the repeater-field caveat.',
		'wp-mcp/docs-common-patterns'           => 'Hero, feature grid, CTA, and landing page flow patterns.',
		'wp-mcp/docs-figma-conversion'          => 'Full Figma to Elementor conversion workflow across 4 phases: discover, map, handle problems, build.',
		'wp-mcp/docs-best-practices'            => 'Dos and don\'ts for building Elementor pages and converting Figma designs.',
	);

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Register doc: elementor-data-structure
		wp_register_ability( 'wp-mcp/docs-elementor-data-structure', array(
			'label'                => __( 'Elementor Data Structure', 'wp-mcp-plugin' ),
			'description'          => __( 'The _elementor_data JSON tree shape, element IDs, elType, settings, responsive suffixes, and __globals__ references.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/elementor-data-structure.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/elementor-data-structure',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-elementor-data-structure';

		// Register doc: container-system
		wp_register_ability( 'wp-mcp/docs-container-system', array(
			'label'                => __( 'Container System', 'wp-mcp-plugin' ),
			'description'          => __( 'Flex vs grid containers, settings table, nesting rules (3-level max), and the two-container full-bleed pattern.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/container-system.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/container-system',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-container-system';

		// Register doc: workflow
		wp_register_ability( 'wp-mcp/docs-workflow', array(
			'label'                => __( 'Build Workflow', 'wp-mcp-plugin' ),
			'description'          => __( 'Standard build workflow, when to use update-page-elementor-data vs incremental add-container/add-widget, and batch-update.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/workflow.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/workflow',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-workflow';

		// Register doc: global-settings
		wp_register_ability( 'wp-mcp/docs-global-settings', array(
			'label'                => __( 'Global Settings', 'wp-mcp-plugin' ),
			'description'          => __( 'Reading and updating global colors and typography; the typography_typography:custom requirement.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/global-settings.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/global-settings',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-global-settings';

		// Register doc: widget-types
		wp_register_ability( 'wp-mcp/docs-widget-types', array(
			'label'                => __( 'Widget Types', 'wp-mcp-plugin' ),
			'description'          => __( 'Common widget reference table and the repeater-field caveat.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/widget-types.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/widget-types',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-widget-types';

		// Register doc: common-patterns
		wp_register_ability( 'wp-mcp/docs-common-patterns', array(
			'label'                => __( 'Common Patterns', 'wp-mcp-plugin' ),
			'description'          => __( 'Hero, feature grid, CTA, and landing page flow patterns.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/common-patterns.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/common-patterns',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-common-patterns';

		// Register doc: figma-conversion
		wp_register_ability( 'wp-mcp/docs-figma-conversion', array(
			'label'                => __( 'Figma Conversion', 'wp-mcp-plugin' ),
			'description'          => __( 'Full Figma to Elementor conversion workflow across 4 phases: discover, map, handle problems, build.', 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/figma-conversion.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/figma-conversion',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-figma-conversion';

		// Register doc: best-practices
		wp_register_ability( 'wp-mcp/docs-best-practices', array(
			'label'                => __( 'Best Practices', 'wp-mcp-plugin' ),
			'description'          => __( "Dos and don'ts for building Elementor pages and converting Figma designs.", 'wp-mcp-plugin' ),
			'category'             => 'wp-mcp-plugin',
			'execute_callback'     => function () {
				return wp_mcp_read_doc_file( 'docs/best-practices.md' );
			},
			'permission_callback'  => '__return_true',
			'meta'                 => array(
				'mcp' => array(
					'type'     => 'resource',
					'public'   => true,
					'uri'      => 'wp-mcp://docs/best-practices',
					'mimeType' => 'text/markdown',
				),
			),
		) );
		$this->resource_names[] = 'wp-mcp/docs-best-practices';
	}

	/**
	 * @return string[] Resource ability slugs.
	 */
	public function get_resource_names(): array {
		return $this->resource_names;
	}
}
