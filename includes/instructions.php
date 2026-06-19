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
