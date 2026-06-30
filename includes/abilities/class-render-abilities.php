<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;
use WpMcp\ElementorCss;
use WpMcp\PreviewToken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visual render and comparison tools for Elementor pages.
 *
 * Provides regenerate-elementor-css and visual-compare-preview for draft-only
 * visual comparison workflows (Figma → Elementor).
 */
class Render {

	/** @var string[] */
	private array $ability_names = array();

	public function register(): void {
		$this->register_regenerate_elementor_css();
		$this->register_render_page();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Permission: requires API key (already verified at transport level)
	 * and read permission on the page.
	 */
	public function check_render_permission(): bool {
		return current_user_can( 'edit_pages' );
	}

	// ──────────────────────────────────────────────
	//  regenerate-elementor-css
	// ──────────────────────────────────────────────

	private function register_regenerate_elementor_css(): void {
		$this->ability_names[] = 'wp-mcp/regenerate-elementor-css';

		wp_register_ability( 'wp-mcp/regenerate-elementor-css', array(
			'label'             => __( 'Regenerate Elementor CSS', 'wp-mcp-plugin' ),
			'description'       => __(
				'Flush stale Elementor CSS cache and regenerate styles for a page after '
				. 'add-container, add-widget, update-element, batch-update, or '
				. 'update-page-elementor-data. Call this BEFORE visual-compare-preview '
				. 'whenever the page layout or styling changed — otherwise the preview '
				. 'may show cached/outdated CSS. Read resource `wp-mcp://docs/workflow` '
				. 'for the full draft-preview visual comparison loop.',
				'wp-mcp-plugin'
			),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_regenerate_elementor_css' ),
			'permission_callback' => array( $this, 'check_render_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => 'The WordPress page post ID to regenerate CSS for.',
					),
					'slug'     => array(
						'type'        => 'string',
						'description' => 'The page slug as an alternative to post_id.',
					),
				),
				'anyOf' => array(
					array( 'required' => array( 'post_id' ) ),
					array( 'required' => array( 'slug' ) ),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'post_id'    => array( 'type' => 'integer' ),
					'css_ready'  => array( 'type' => 'boolean' ),
					'css_status' => array( 'type' => 'string' ),
				),
				'required'   => array( 'success', 'post_id', 'css_ready', 'css_status' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, false, true ),
			),
		) );
	}

	public function execute_regenerate_elementor_css( array $input ): array|\WP_Error {
		$post_id = $this->resolve_page_id( $input );

		if ( ! $post_id ) {
			return new \WP_Error(
				'post_not_found',
				__( 'Page not found. Provide a valid post_id or slug.', 'wp-mcp-plugin' )
			);
		}

		return ElementorCss::regenerate_for_post( $post_id );
	}

	// ──────────────────────────────────────────────
	//  visual-compare-preview
	// ──────────────────────────────────────────────

	private function register_render_page(): void {
		$this->ability_names[] = 'wp-mcp/visual-compare-preview';

		wp_register_ability( 'wp-mcp/visual-compare-preview', array(
			'label'             => __( 'Render Page for Visual Comparison', 'wp-mcp-plugin' ),
			'description'       => __(
				'Generate an authenticated, time-limited draft preview URL for visual comparison '
				. 'with a Figma design. Use ONLY on draft or private pages — never publish pages '
				. 'for MCP visual comparison. Call regenerate-elementor-css first after any '
				. 'content or styling change, then use this tool to get a preview_url for '
				. 'headless-browser screenshots (Playwright, Puppeteer). Returns Elementor '
				. 'element selectors for precise targeting. Read resource `wp-mcp://docs/workflow` '
				. 'for the required build → regenerate CSS → preview → screenshot → evaluate loop.',
				'wp-mcp-plugin'
			),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_render_page' ),
			'permission_callback' => array( $this, 'check_render_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => 'The WordPress page post ID to generate a preview URL for.',
					),
					'slug'     => array(
						'type'        => 'string',
						'description' => 'The page slug as an alternative to post_id.',
					),
					'ttl'      => array(
						'type'        => 'integer',
						'description' => 'Token time-to-live in seconds. Default: 300. Max: 600.',
						'default'     => 300,
					),
				),
				'anyOf' => array(
					array( 'required' => array( 'post_id' ) ),
					array( 'required' => array( 'slug' ) ),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'             => array( 'type' => 'integer' ),
					'page_url'            => array( 'type' => 'string' ),
					'preview_url'         => array( 'type' => 'string' ),
					'page_status'         => array( 'type' => 'string' ),
					'page_title'          => array( 'type' => 'string' ),
					'has_elementor_data'  => array( 'type' => 'boolean' ),
					'element_count'       => array( 'type' => 'integer' ),
					'css_ready'           => array( 'type' => 'boolean' ),
					'elements'            => array(
						'type'  => 'array',
						'items' => AbilitySchemas::preview_element_item(),
					),
					'token_expires_at'    => array( 'type' => 'string', 'format' => 'date-time' ),
					'token_expires_unix'  => array( 'type' => 'integer' ),
				),
				'required'   => array(
					'post_id',
					'page_url',
					'preview_url',
					'page_status',
					'page_title',
					'has_elementor_data',
					'element_count',
					'css_ready',
					'elements',
					'token_expires_at',
					'token_expires_unix',
				),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_render_page( array $input ): array|\WP_Error {
		$post_id = $this->resolve_page_id( $input );

		if ( ! $post_id ) {
			return new \WP_Error(
				'post_not_found',
				__( 'Page not found. Provide a valid post_id or slug.', 'wp-mcp-plugin' )
			);
		}

		$post = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return new \WP_Error(
				'post_not_found',
				__( 'Page not found or not a valid page.', 'wp-mcp-plugin' )
			);
		}

		if ( 'trash' === $post->post_status ) {
			return new \WP_Error(
				'post_trashed',
				__( 'Page is in trash. Restore it or use a different page.', 'wp-mcp-plugin' )
			);
		}

		if ( 'publish' === $post->post_status ) {
			return new \WP_Error(
				'page_published',
				__( 'Page is published. MCP visual comparison must use draft or private pages only — create a new draft page or unpublish this one.', 'wp-mcp-plugin' )
			);
		}

		$elementor_loaded = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );

		if ( ! $elementor_loaded ) {
			return new \WP_Error(
				'elementor_missing',
				__( 'Elementor is not active. Install and activate Elementor to use this tool.', 'wp-mcp-plugin' )
			);
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		$has_data       = ! empty( $elementor_data );

		$ttl   = isset( $input['ttl'] ) ? min( (int) $input['ttl'], 600 ) : 300;
		$url   = PreviewToken::build_url( $post_id, $ttl );

		$elements = array();

		if ( $has_data ) {
			$parsed = json_decode( $elementor_data, true );
			if ( is_array( $parsed ) ) {
				$elements = $this->extract_elements( $parsed );
			}
		}

		$css_meta   = get_post_meta( $post_id, '_elementor_css', true );
		$css_status = is_array( $css_meta ) ? (string) ( $css_meta['status'] ?? '' ) : '';
		$css_ready  = ! empty( $css_meta ) && 'empty' !== $css_status;

		return array(
			'post_id'            => $post_id,
			'page_url'           => get_permalink( $post_id ) ?: home_url( '/?p=' . $post_id ),
			'preview_url'        => $url,
			'page_status'        => $post->post_status,
			'page_title'         => $post->post_title,
			'has_elementor_data' => $has_data,
			'element_count'      => count( $elements ),
			'css_ready'          => $css_ready,
			'elements'           => $elements,
			'token_expires_at'   => gmdate( 'c', time() + $ttl ),
			'token_expires_unix' => time() + $ttl,
		);
	}

	/**
	 * Resolve a page post ID from post_id or slug input.
	 *
	 * @param array<string, mixed> $input Tool input.
	 * @return int|null Post ID or null when not found.
	 */
	private function resolve_page_id( array $input ): ?int {
		if ( ! empty( $input['post_id'] ) ) {
			return (int) $input['post_id'];
		}

		if ( ! empty( $input['slug'] ) ) {
			$page = get_page_by_path( sanitize_text_field( $input['slug'] ), OBJECT, 'page' );
			if ( $page ) {
				return (int) $page->ID;
			}
		}

		return null;
	}

	/**
	 * Recursively extract element IDs and types from Elementor's JSON tree.
	 *
	 * @param array $tree The Elementor element tree (or subtree).
	 * @return array Flat array of {element_id, selector, elType, widgetType} maps.
	 */
	private function extract_elements( array $tree ): array {
		$result = array();

		foreach ( $tree as $node ) {
			if ( ! isset( $node['id'] ) ) {
				continue;
			}

			$entry = array(
				'element_id' => $node['id'],
				'selector'   => '.elementor-element-' . $node['id'],
				'elType'     => $node['elType'] ?? 'unknown',
				'widgetType' => $node['widgetType'] ?? '',
			);

			$result[] = $entry;

			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$child_elements = $this->extract_elements( $node['elements'] );
				$result         = array_merge( $result, $child_elements );
			}
		}

		return $result;
	}
}
