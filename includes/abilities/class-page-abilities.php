<?php

namespace WpMcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page-level CRUD tools: create, update, delete.
 */
class Page {

	/** @var string[] */
	private array $ability_names = array();

	public function register(): void {
		$this->register_create_page();
		$this->register_update_page_data();
		$this->register_delete_page();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function check_create_permission(): bool {
		return current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' );
	}

	public function check_edit_permission( array $input ): bool {
		if ( ! current_user_can( 'edit_pages' ) ) {
			return false;
		}
		if ( ! empty( $input['post_id'] ) ) {
			return current_user_can( 'edit_post', (int) $input['post_id'] );
		}
		return true;
	}

	public function check_delete_permission( array $input ): bool {
		if ( ! current_user_can( 'delete_pages' ) ) {
			return false;
		}
		if ( ! empty( $input['post_id'] ) ) {
			return current_user_can( 'delete_post', (int) $input['post_id'] );
		}
		return true;
	}

	private function register_create_page(): void {
		$this->ability_names[] = 'wp-mcp/create-page';

		wp_register_ability( 'wp-mcp/create-page', array(
			'label'             => __( 'Create Page', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the wp-mcp/usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Create a new WordPress page with Elementor builder enabled. Optionally set an initial title, slug, status, and template. Returns the new page ID and URL.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_create_page' ),
			'permission_callback' => array( $this, 'check_create_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'title'    => array(
						'type'        => 'string',
						'description' => 'Page title. Default: Untitled Page.',
					),
					'slug'     => array(
						'type'        => 'string',
						'description' => 'Page slug/permalink. Auto-generated from title if omitted.',
					),
					'status'   => array(
						'type'        => 'string',
						'description' => 'Post status. Default: draft.',
						'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
					),
					'template' => array(
						'type'        => 'string',
						'description' => 'Page template filename (e.g. elementor_canvas, elementor_header_footer).',
					),
					'initial_elementor_data' => array(
						'type'        => 'array',
						'description' => 'Optional initial Elementor content as a JSON array of elements.',
					),
				),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array( 'type' => 'integer' ),
					'url'     => array( 'type' => 'string' ),
					'edit_url' => array( 'type' => 'string' ),
				),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => false,
					'destructive' => false,
					'idempotent'  => false,
				),
			),
		) );
	}

	public function execute_create_page( array $input ): array|\WP_Error {
		$title    = sanitize_text_field( $input['title'] ?? __( 'Untitled Page', 'wp-mcp-plugin' ) );
		$slug     = ! empty( $input['slug'] ) ? sanitize_title( $input['slug'] ) : '';
		$status   = sanitize_text_field( $input['status'] ?? 'draft' );
		$template = sanitize_text_field( $input['template'] ?? '' );

		$post_arr = array(
			'post_title'  => $title,
			'post_type'   => 'page',
			'post_status' => $status,
		);

		if ( ! empty( $slug ) ) {
			$post_arr['post_name'] = $slug;
		}

		$post_id = wp_insert_post( $post_arr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Enable Elementor
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		// Set template if specified
		if ( ! empty( $template ) ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		// Optionally set initial content
		if ( ! empty( $input['initial_elementor_data'] ) && is_array( $input['initial_elementor_data'] ) ) {
			update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $input['initial_elementor_data'] ) ) );
		}

		return array(
			'post_id'  => $post_id,
			'url'      => get_permalink( $post_id ),
			'edit_url' => \Elementor\Plugin::$instance->documents->get( $post_id )
				? \Elementor\Plugin::$instance->documents->get( $post_id )->get_edit_url()
				: '',
		);
	}

	private function register_update_page_data(): void {
		$this->ability_names[] = 'wp-mcp/update-page-elementor-data';

		wp_register_ability( 'wp-mcp/update-page-elementor-data', array(
			'label'             => __( 'Update Page Elementor Data', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the wp-mcp/usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Replace the entire Elementor content (_elementor_data) of an existing page. The provided data must be a valid JSON array of Elementor elements (containers, widgets, etc.). This is a full replacement — previous content is overwritten.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_update_page_data' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'The page post ID to update.',
					),
					'elementor_data' => array(
						'type'        => 'array',
						'description' => 'Complete Elementor content as a JSON array of elements.',
					),
				),
				'required'   => array( 'post_id', 'elementor_data' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'  => array( 'type' => 'boolean' ),
					'post_id'  => array( 'type' => 'integer' ),
				),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => false,
				),
			),
		) );
	}

	public function execute_update_page_data( array $input ): array|\WP_Error {
		$post_id = (int) $input['post_id'];
		$post    = get_post( $post_id );

		if ( ! $post || 'page' !== $post->post_type ) {
			return new \WP_Error( 'page_not_found', __( 'Page not found.', 'wp-mcp-plugin' ) );
		}

		if ( ! isset( $input['elementor_data'] ) || ! is_array( $input['elementor_data'] ) ) {
			return new \WP_Error( 'invalid_data', __( 'elementor_data must be a JSON array.', 'wp-mcp-plugin' ) );
		}

		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $input['elementor_data'] ) ) );

		// Invalidate Elementor CSS cache
		delete_post_meta( $post_id, '_elementor_css' );

		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}

	private function register_delete_page(): void {
		$this->ability_names[] = 'wp-mcp/delete-page';

		wp_register_ability( 'wp-mcp/delete-page', array(
			'label'             => __( 'Delete Page', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the wp-mcp/usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Trash a WordPress page. The page can be restored from trash within 30 days. Does not permanently delete.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_delete_page' ),
			'permission_callback' => array( $this, 'check_delete_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'The page post ID to trash.',
					),
					'force' => array(
						'type'        => 'boolean',
						'description' => 'If true, permanently delete (skip trash). Default: false.',
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success' => array( 'type' => 'boolean' ),
					'post_id' => array( 'type' => 'integer' ),
				),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		) );
	}

	public function execute_delete_page( array $input ): array|\WP_Error {
		$post_id = (int) $input['post_id'];
		$force   = ! empty( $input['force'] );

		$result = wp_delete_post( $post_id, $force );

		if ( ! $result ) {
			return new \WP_Error( 'delete_failed', __( 'Failed to delete the page.', 'wp-mcp-plugin' ) );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}
}
