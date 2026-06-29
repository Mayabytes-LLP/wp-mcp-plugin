<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;
use WpMcp\PreviewToken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visual render and comparison tools for Elementor pages.
 *
 * Provides visual-compare-preview — generates an authenticated preview URL
 * so headless browser tools (Playwright, Puppeteer) can take screenshots
 * of draft/private Elementor pages for visual comparison with Figma designs.
 */
class Render {

	/** @var string[] */
	private array $ability_names = array();

	public function register(): void {
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
	//  visual-compare-preview
	// ──────────────────────────────────────────────

	private function register_render_page(): void {
		$this->ability_names[] = 'wp-mcp/visual-compare-preview';

		wp_register_ability( 'wp-mcp/visual-compare-preview', array(
			'label'             => __( 'Render Page for Visual Comparison', 'wp-mcp-plugin' ),
			'description'       => __(
				'Generate an authenticated, time-limited preview URL for a specific WordPress page '
				. 'so a headless browser (Playwright, Puppeteer) can take a screenshot for visual '
				. 'comparison with a Figma design. Works with draft, private, and published pages. '
				. 'Returns Elementor element selectors for precise targeting in browser automation tools. '
				. 'The token expires after the specified TTL (default 300s, max 600s). '
				. 'Use this as part of the Figma → Elementor visual comparison workflow: '
				. '1) build page, 2) visual-compare-preview to get URL, 3) Playwright screenshot, '
				. '4) model compares against Figma image, 5) fix differences, repeat.',
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
		// ── Resolve post_id ──────────────────────────────
		$post_id = null;

		if ( ! empty( $input['post_id'] ) ) {
			$post_id = (int) $input['post_id'];
		} elseif ( ! empty( $input['slug'] ) ) {
			$page = get_page_by_path( sanitize_text_field( $input['slug'] ), OBJECT, 'page' );
			if ( $page ) {
				$post_id = $page->ID;
			}
		}

		if ( ! $post_id ) {
			return new \WP_Error(
				'post_not_found',
				__( 'Page not found. Provide a valid post_id or slug.', 'wp-mcp-plugin' )
			);
		}

		// ── Verify post exists and is a page ─────────────
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

		// ── Check Elementor availability ─────────────────
		$elementor_loaded = did_action( 'elementor/loaded' ) || class_exists( '\Elementor\Plugin' );

		if ( ! $elementor_loaded ) {
			return new \WP_Error(
				'elementor_missing',
				__( 'Elementor is not active. Install and activate Elementor to use this tool.', 'wp-mcp-plugin' )
			);
		}

		// ── Check for Elementor data ─────────────────────
		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );
		$has_data       = ! empty( $elementor_data );

		// ── Generate preview token ───────────────────────
		$ttl   = isset( $input['ttl'] ) ? min( (int) $input['ttl'], 600 ) : 300;
		$url   = PreviewToken::build_url( $post_id, $ttl );

		// ── Build elements map ───────────────────────────
		$elements = array();

		if ( $has_data ) {
			$parsed = json_decode( $elementor_data, true );
			if ( is_array( $parsed ) ) {
				$elements = $this->extract_elements( $parsed );
			}
		}

		// ── Check CSS readiness ──────────────────────────
		$has_css   = (bool) get_post_meta( $post_id, '_elementor_css', true );
		$css_ready = $has_css;

		// If page is published, CSS should already be generated.
		// Draft/private pages generate CSS on first frontend load.
		if ( ! $has_css && 'publish' === $post->post_status ) {
			// Published page without CSS — unusual, but can happen
			// if Elementor hasn't been used on this page yet.
			$css_ready = false;
		}

		// ── Build response ───────────────────────────────
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
	 * Recursively extract element IDs and types from Elementor's JSON tree.
	 *
	 * Each element in the tree has an `id` (7-char hex), `elType` (container/widget),
	 * and optionally `widgetType` for widgets. We extract these into a flat list
	 * along with their CSS selectors for browser automation tools.
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

			// Recurse into child elements.
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$child_elements = $this->extract_elements( $node['elements'] );
				$result         = array_merge( $result, $child_elements );
			}
		}

		return $result;
	}
}
