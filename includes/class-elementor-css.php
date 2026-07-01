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

		delete_post_meta( $post_id, '_elementor_css' );

		$css_file = \Elementor\Core\Files\CSS\Post::create( $post_id );

		try {
			$css_file->update();
		} catch ( \Exception $e ) {
			delete_transient( $lock_key );
			return new \WP_Error( 'css_generation_failed', $e->getMessage() );
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
}
