<?php
/**
 * WP MCP Plugin — Server Instructions
 *
 * Returned as the MCP `instructions` field on initialize. Concise cross-tool
 * workflow + resource pointers. Deep documentation lives in MCP resources
 * (see class-docs.php) and the shipped SKILL.md.
 *
 * @package WpMcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read and return the concise server instructions text.
 *
 * @return string
 */
function wp_mcp_get_instructions(): string {
	static $instructions = null;

	if ( null === $instructions ) {
		$instructions = file_get_contents( __DIR__ . '/instructions.md' );
	}

	return $instructions;
}

/**
 * MCP documentation resource URIs and one-line descriptions.
 *
 * @return array<string, string> URI => description.
 */
function wp_mcp_get_resource_index(): array {
	return array(
		'wp-mcp://docs/workflow'                 => 'Build workflow, draft-only visual comparison loop, regenerate CSS before preview.',
		'wp-mcp://docs/elementor-data-structure' => '_elementor_data JSON shape, element IDs, responsive suffixes, __globals__.',
		'wp-mcp://docs/container-system'         => 'Flex vs grid containers, nesting rules, two-container full-bleed pattern.',
		'wp-mcp://docs/global-settings'          => 'Reading and updating global colors and typography.',
		'wp-mcp://docs/widget-types'             => 'Common widget reference table and repeater-field caveat.',
		'wp-mcp://docs/common-patterns'          => 'Hero, feature grid, CTA, and landing page flow patterns.',
		'wp-mcp://docs/figma-conversion'         => 'Figma to Elementor conversion workflow across four phases.',
		'wp-mcp://docs/best-practices'           => 'Dos and don\'ts for building Elementor pages.',
	);
}

/**
 * Read and return a documentation file from the docs/ subdirectory.
 *
 * @param string $relative_path Relative path under includes/docs/.
 * @return string
 */
function wp_mcp_read_doc_file( string $relative_path ): string {
	static $cache = array();

	if ( ! isset( $cache[ $relative_path ] ) ) {
		$path = __DIR__ . '/' . $relative_path;

		if ( file_exists( $path ) ) {
			$cache[ $relative_path ] = file_get_contents( $path );
		} else {
			$cache[ $relative_path ] = '';
		}
	}

	return $cache[ $relative_path ];
}
