<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Elementor post CSS regeneration for MCP preview workflows.
 */
class ElementorCss {

	/**
	 * Regenerate Elementor CSS for a page after _elementor_data changes.
	 *
	 * @param int $post_id Page post ID.
	 * @return array{success: bool, post_id: int, css_ready: bool, css_status: string}|WP_Error
	 */
	public static function regenerate_for_post( int $post_id ): array|\WP_Error {
		if ( ! did_action( 'elementor/loaded' ) && ! class_exists( '\Elementor\Plugin' ) ) {
			return new \WP_Error(
				'elementor_missing',
				__( 'Elementor is not active. Install and activate Elementor to regenerate CSS.', 'wp-mcp-plugin' )
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

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );

		if ( empty( $elementor_data ) ) {
			return new \WP_Error(
				'no_elementor_data',
				__( 'Page has no Elementor content to generate CSS for.', 'wp-mcp-plugin' )
			);
		}

		// Rate-limit: prevent concurrent/rapid-fire CSS regeneration.
		$lock_key = 'wp_mcp_css_regenerating_' . $post_id;
		if ( get_transient( $lock_key ) ) {
			return new \WP_Error(
				'css_already_regenerating',
				__( 'CSS regeneration already in progress for this page. Retry shortly.', 'wp-mcp-plugin' )
			);
		}
		set_transient( $lock_key, 1, 10 );

		Abilities\Element::ensure_elementor_meta( $post_id );

		$document = \Elementor\Plugin::$instance->documents->get_doc_for_frontend( $post_id );

		if ( ! $document ) {
			return new \WP_Error(
				'elementor_document_missing',
				__( 'Elementor could not load a document for this page.', 'wp-mcp-plugin' )
			);
		}

		// Pre-flight: walk the page tree and flag any repeater field stored
		// in the wrong shape (`{"item":[…]}` envelope) so the AI can fix
		// data before we let Elementor's CSS writer explode on it.
		$shape_errors = self::check_repeater_shapes( $post_id );
		if ( ! empty( $shape_errors ) ) {
			delete_transient( $lock_key );
			return new \WP_Error(
				'bad_repeater_shape',
				sprintf(
					/* translators: %d: number of bad repeater rows */
					_n(
						'Page has %d repeater field stored with the wrong shape. Call get-elementor-debug-info to identify, then fix with update-element.',
						'Page has %d repeater fields stored with the wrong shape. Call get-elementor-debug-info to identify, then fix with update-element.',
						count( $shape_errors ),
						'wp-mcp-plugin'
					),
					count( $shape_errors )
				),
				array( 'bad_repeaters' => $shape_errors )
			);
		}

		delete_post_meta( $post_id, '_elementor_css' );

		$css_file = \Elementor\Core\Files\CSS\Post::create( $post_id );

		try {
			$css_file->update();
		} catch ( \Exception $e ) {
			delete_transient( $lock_key );
			// Best-effort: if the underlying error matches the known
			// `add_controls_stack_style_rules` null-args fatal, point the
			// AI at get-elementor-debug-info instead of dumping the
			// Elementor stack trace.
			$msg = $e->getMessage();
			if ( false !== strpos( $msg, 'add_controls_stack_style_rules' ) || false !== strpos( $msg, 'must be of type array' ) ) {
				return new \WP_Error(
					'bad_repeater_shape',
					__( 'CSS generation failed on a repeater field with null values. Call get-elementor-debug-info to identify the offending elements, then fix with update-element.', 'wp-mcp-plugin' ),
					array( 'underlying_error' => $msg )
				);
			}
			return new \WP_Error( 'css_generation_failed', $msg );
		}

		$css_meta   = get_post_meta( $post_id, '_elementor_css', true );
		$css_status = is_array( $css_meta ) ? (string) ( $css_meta['status'] ?? '' ) : '';
		$css_ready  = ! empty( $css_meta ) && 'empty' !== $css_status;

		return array(
			'success'    => $css_ready,
			'post_id'    => $post_id,
			'css_ready'  => $css_ready,
			'css_status' => $css_status ?: 'unknown',
		);
	}

	/**
	 * Walk a page's _elementor_data and flag any repeater-shaped field
	 * stored with the wrong envelope shape (`{"item":[…]}` instead of
	 * `[…]`). Elementor's repeater control reads `$value[0]` as the first
	 * item, so an envelope makes every item null and breaks
	 * `add_controls_stack_style_rules()`.
	 *
	 * Delegate the per-value check to
	 * `Abilities\Element::is_envelope_shape` / `describe_envelope` and the
	 * key list to `Abilities\Element::REPEATER_KEYS` so all three call
	 * sites stay in sync.
	 *
	 * @param int $post_id
	 * @return array<int,array{element_id:string,widget_type:string,field:string,shape:string}>
	 */
	public static function check_repeater_shapes( int $post_id ): array {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return array();
		}
		$tree = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $tree ) ) {
			return array();
		}

		$bad = array();
		self::walk_for_envelopes( $tree, $bad );
		return $bad;
	}

	/**
	 * Recursive walker for check_repeater_shapes().
	 *
	 * @param array $tree
	 * @param array<int,array{element_id:string,widget_type:string,field:string,shape:string}> $bad
	 */
	private static function walk_for_envelopes( array $tree, array &$bad ): void {
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$id         = isset( $node['id'] ) ? (string) $node['id'] : '';
			$widget     = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : ( isset( $node['elType'] ) ? (string) $node['elType'] : '' );
			$settings   = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();

			foreach ( \WpMcp\Abilities\Element::REPEATER_KEYS as $key ) {
				if ( ! isset( $settings[ $key ] ) ) {
					continue;
				}
				$val = $settings[ $key ];
				if ( ! is_array( $val ) ) {
					continue;
				}
				if ( \WpMcp\Abilities\Element::is_envelope_shape( $val ) ) {
					$bad[] = array(
						'element_id'  => $id,
						'widget_type' => $widget,
						'field'       => $key,
						'shape'       => \WpMcp\Abilities\Element::describe_envelope( $val ),
					);
				}
			}

			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				self::walk_for_envelopes( $node['elements'], $bad );
			}
		}
	}
}
