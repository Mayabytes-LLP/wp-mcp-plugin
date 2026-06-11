<?php

namespace WpMcp\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Element-level manipulation tools: add container, add widget, update element,
 * remove element, and batch update.
 */
class Element {

	/** @var string[] */
	private array $ability_names = array();

	public function register(): void {
		$this->register_add_container();
		$this->register_add_widget();
		$this->register_update_element();
		$this->register_remove_element();
		$this->register_batch_update();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
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

	// ── Element tree helpers ──────────────────────────────────────────

	/**
	 * Generate a unique 7-character hex element ID.
	 */
	private function generate_id(): string {
		return substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	/**
	 * Find an element in the tree by ID and return its index and parent reference.
	 *
	 * @param array $elements The elements array to search (passed by reference).
	 * @param string $element_id The ID to find.
	 * @return array{parent: array|null, index: int|null} Parent array and index,
	 *         or [null, null] if not found.
	 */
	private function find_element( array &$elements, string $element_id ): array {
		foreach ( $elements as $i => &$element ) {
			if ( ( $element['id'] ?? '' ) === $element_id ) {
				return array( 'parent' => &$elements, 'index' => $i );
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$result = $this->find_element( $element['elements'], $element_id );
				if ( null !== $result['parent'] ) {
					return $result;
				}
			}
		}

		return array( 'parent' => null, 'index' => null );
	}

	/**
	 * Get the current page data from post meta.
	 *
	 * @param int $post_id
	 * @return array
	 */
	private function get_page_data( int $post_id ): array {
		$data = get_post_meta( $post_id, '_elementor_data', true );

		if ( empty( $data ) ) {
			return array();
		}

		return ( is_array( $data ) ? $data : json_decode( $data, true ) ) ?: array();
	}

	/**
	 * Save page data and invalidate the Elementor CSS cache.
	 *
	 * @param int $post_id
	 * @param array $data
	 */
	private function save_page_data( int $post_id, array $data ): void {
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		delete_post_meta( $post_id, '_elementor_css' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		self::ensure_elementor_meta( $post_id );
	}

	/**
	 * Ensure all Elementor meta keys required for rendering are present.
	 *
	 * When Elementor's Document::save() runs, it sets _elementor_version,
	 * _elementor_template_type, and _elementor_page_settings. Since we write
	 * _elementor_data directly via update_post_meta (bypassing Document::save),
	 * we must also set these keys or the page renders blank.
	 *
	 * @param int $post_id The post ID.
	 */
	public static function ensure_elementor_meta( int $post_id ): void {
		// Set edit mode if not already set.
		if ( ! get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		// Set template type so Elementor identifies the document correctly.
		if ( ! get_post_meta( $post_id, '_elementor_template_type', true ) ) {
			update_post_meta( $post_id, '_elementor_template_type', 'wp-page' );
		}

		// Set version to current Elementor version so CSS generation works.
		if ( ! get_post_meta( $post_id, '_elementor_version', true ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}

		// Ensure page settings exist (template, etc.) so the document renders.
		$settings = get_post_meta( $post_id, '_elementor_page_settings', true );
		if ( ! $settings || ! is_array( $settings ) ) {
			$template = get_post_meta( $post_id, '_wp_page_template', true );
			update_post_meta( $post_id, '_elementor_page_settings', array(
				'template' => $template ?: 'default',
			) );
		}

		// Invalidate CSS cache so Elementor regenerates styles.
		delete_post_meta( $post_id, '_elementor_css' );
	}

	// ── add-container ─────────────────────────────────────────────────

	private function register_add_container(): void {
		$this->ability_names[] = 'wp-mcp/add-container';

		wp_register_ability( 'wp-mcp/add-container', array(
			'label'             => __( 'Add Container', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Add a new flex or grid container to an Elementor page. Containers are the building blocks of Elementor layouts — they hold widgets and can nest other containers. Specify a parent_id (or omit for top-level), position, and container settings.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_add_container' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'  => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'parent_id' => array(
						'type'        => 'string',
						'description' => 'Parent element ID (7-char hex). Omit for top-level. Use "root" to place the container at the page root level.',
					),
					'position' => array(
						'type'        => 'integer',
						'description' => '0-indexed position among parent\'s children. Use -1 to append at end. Default: -1.',
					),
					'settings' => array(
						'type'        => 'object',
						'description' => 'Container settings (flex_direction, content_width, align_items, justify_content, gap, padding, margin, background_color, etc.).',
					),
				),
				'required'   => array( 'post_id' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
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

	public function execute_add_container( array $input ): array|\WP_Error {
		$post_id   = (int) $input['post_id'];
		$parent_id = sanitize_text_field( $input['parent_id'] ?? 'root' );
		$position  = isset( $input['position'] ) ? (int) $input['position'] : -1;
		$settings  = $input['settings'] ?? array();

		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'invalid_settings', __( 'Settings must be an object.', 'wp-mcp-plugin' ) );
		}

		$container = array(
			'id'         => $this->generate_id(),
			'elType'     => 'container',
			'settings'   => $this->sanitize_settings( $settings ),
			'elements'   => array(),
			'isInner'    => false,
		);

		$page_data = $this->get_page_data( $post_id );

		if ( 'root' === $parent_id ) {
			// Top-level insert
			if ( -1 === $position || $position >= count( $page_data ) ) {
				$page_data[] = $container;
			} else {
				array_splice( $page_data, $position, 0, array( $container ) );
			}
		} else {
			$result = $this->find_element( $page_data, $parent_id );

			if ( null === $result['parent'] ) {
				return new \WP_Error(
					'parent_not_found',
					sprintf(
						/* translators: %s: parent element ID */
						__( 'Parent element "%s" not found.', 'wp-mcp-plugin' ),
						$parent_id
					)
				);
			}

			$container['isInner'] = true;
			$parent_element =& $result['parent'][ $result['index'] ];

			if ( ! isset( $parent_element['elements'] ) || ! is_array( $parent_element['elements'] ) ) {
				$parent_element['elements'] = array();
			}

			if ( -1 === $position || $position >= count( $parent_element['elements'] ) ) {
				$parent_element['elements'][] = $container;
			} else {
				array_splice( $parent_element['elements'], $position, 0, array( $container ) );
			}
		}

		$this->save_page_data( $post_id, $page_data );

		return array(
			'element_id' => $container['id'],
			'post_id'    => $post_id,
		);
	}

	// ── add-widget ────────────────────────────────────────────────────

	private function register_add_widget(): void {
		$this->ability_names[] = 'wp-mcp/add-widget';

		wp_register_ability( 'wp-mcp/add-widget', array(
			'label'             => __( 'Add Widget', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Add a widget (any Elementor type) into a container. Use list-elementor-widgets to see available types and get-elementor-widget-schema to know which settings each widget accepts. Requires a parent container ID.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_add_widget' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'     => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'parent_id'   => array(
						'type'        => 'string',
						'description' => 'Parent container element ID (7-char hex).',
					),
					'widget_type' => array(
						'type'        => 'string',
						'description' => 'Elementor widget type key (e.g. heading, button, image).',
					),
					'position'    => array(
						'type'        => 'integer',
						'description' => '0-indexed position among parent\'s children. Use -1 to append at end. Default: -1.',
					),
					'settings'    => array(
						'type'        => 'object',
						'description' => 'Widget settings. Use get-elementor-widget-schema to see valid properties for this widget_type.',
					),
				),
				'required'   => array( 'post_id', 'parent_id', 'widget_type' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id'  => array( 'type' => 'string' ),
					'post_id'     => array( 'type' => 'integer' ),
					'widget_type' => array( 'type' => 'string' ),
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

	public function execute_add_widget( array $input ): array|\WP_Error {
		$post_id     = (int) $input['post_id'];
		$parent_id   = sanitize_text_field( $input['parent_id'] );
		$widget_type = sanitize_text_field( $input['widget_type'] );
		$position    = isset( $input['position'] ) ? (int) $input['position'] : -1;
		$settings    = $input['settings'] ?? array();

		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'invalid_settings', __( 'Settings must be an object.', 'wp-mcp-plugin' ) );
		}

		// Validate widget type exists
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->widgets_manager ) {
			$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
			if ( ! $widget ) {
				return new \WP_Error(
					'widget_not_found',
					sprintf(
						/* translators: %s: widget type key */
						__( 'Widget type "%s" not found. Use list-elementor-widgets to see available types.', 'wp-mcp-plugin' ),
						$widget_type
					)
				);
			}
		}

		$widget_element = array(
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => $this->sanitize_settings( $settings ),
			'elements'   => array(),
		);

		$page_data = $this->get_page_data( $post_id );
		$result    = $this->find_element( $page_data, $parent_id );

		if ( null === $result['parent'] ) {
			return new \WP_Error(
				'parent_not_found',
				__( 'Parent container not found.', 'wp-mcp-plugin' )
			);
		}

		$parent_element =& $result['parent'][ $result['index'] ];

		if ( $parent_element['elType'] !== 'container' ) {
			return new \WP_Error(
				'invalid_parent',
				__( 'Widgets can only be added to containers.', 'wp-mcp-plugin' )
			);
		}

		if ( ! isset( $parent_element['elements'] ) || ! is_array( $parent_element['elements'] ) ) {
			$parent_element['elements'] = array();
		}

		if ( -1 === $position || $position >= count( $parent_element['elements'] ) ) {
			$parent_element['elements'][] = $widget_element;
		} else {
			array_splice( $parent_element['elements'], $position, 0, array( $widget_element ) );
		}

		$this->save_page_data( $post_id, $page_data );

		return array(
			'element_id'  => $widget_element['id'],
			'post_id'     => $post_id,
			'widget_type' => $widget_type,
		);
	}

	// ── update-element ────────────────────────────────────────────────

	private function register_update_element(): void {
		$this->ability_names[] = 'wp-mcp/update-element';

		wp_register_ability( 'wp-mcp/update-element', array(
			'label'             => __( 'Update Element', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Update settings on any element (container or widget) by its element ID. Settings are merged with existing ones — you only need to provide the properties you want to change.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_update_element' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => 'The element ID (7-char hex) to update.',
					),
					'settings'   => array(
						'type'        => 'object',
						'description' => 'New settings to merge into the element. Only changed properties need to be provided.',
					),
				),
				'required'   => array( 'post_id', 'element_id', 'settings' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
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

	public function execute_update_element( array $input ): array|\WP_Error {
		$post_id    = (int) $input['post_id'];
		$element_id = sanitize_text_field( $input['element_id'] );
		$settings   = $input['settings'] ?? array();

		if ( ! is_array( $settings ) ) {
			return new \WP_Error( 'invalid_settings', __( 'Settings must be an object.', 'wp-mcp-plugin' ) );
		}

		$page_data = $this->get_page_data( $post_id );
		$result    = $this->find_element( $page_data, $element_id );

		if ( null === $result['parent'] ) {
			return new \WP_Error(
				'element_not_found',
				__( 'Element not found.', 'wp-mcp-plugin' )
			);
		}

		$element =& $result['parent'][ $result['index'] ];
		$element['settings'] = array_merge( $element['settings'] ?? array(), $this->sanitize_settings( $settings ) );

		$this->save_page_data( $post_id, $page_data );

		return array(
			'element_id' => $element_id,
			'post_id'    => $post_id,
		);
	}

	// ── remove-element ────────────────────────────────────────────────

	private function register_remove_element(): void {
		$this->ability_names[] = 'wp-mcp/remove-element';

		wp_register_ability( 'wp-mcp/remove-element', array(
			'label'             => __( 'Remove Element', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Remove an element (container or widget) and all its children from a page. This is destructive — removed content cannot be recovered.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_remove_element' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'element_id' => array(
						'type'        => 'string',
						'description' => 'The element ID (7-char hex) to remove.',
					),
				),
				'required'   => array( 'post_id', 'element_id' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
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

	public function execute_remove_element( array $input ): array|\WP_Error {
		$post_id    = (int) $input['post_id'];
		$element_id = sanitize_text_field( $input['element_id'] );

		$page_data = $this->get_page_data( $post_id );
		$result    = $this->find_element( $page_data, $element_id );

		if ( null === $result['parent'] ) {
			return new \WP_Error(
				'element_not_found',
				__( 'Element not found.', 'wp-mcp-plugin' )
			);
		}

		array_splice( $result['parent'], $result['index'], 1 );
		$this->save_page_data( $post_id, $page_data );

		return array(
			'success'    => true,
			'element_id' => $element_id,
			'post_id'    => $post_id,
		);
	}

	// ── batch-update ──────────────────────────────────────────────────

	private function register_batch_update(): void {
		$this->ability_names[] = 'wp-mcp/batch-update';

		wp_register_ability( 'wp-mcp/batch-update', array(
			'label'             => __( 'Batch Update Elements', 'wp-mcp-plugin' ),
			'description'       => __( 'IMPORTANT: Call the usage-guide prompt before using this tool to understand Elementor\'s data model and avoid broken layouts. Apply multiple element updates in a single save operation. Much more efficient than calling update-element repeatedly for large pages. Each operation in the array requires element_id and settings.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_batch_update' ),
			'permission_callback' => array( $this, 'check_edit_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'    => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'operations' => array(
						'type'        => 'array',
						'description' => 'Array of operations. Each operation: { element_id (string), settings (object) }.',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'element_id' => array( 'type' => 'string' ),
								'settings'   => array( 'type' => 'object' ),
							),
							'required' => array( 'element_id', 'settings' ),
						),
					),
				),
				'required'   => array( 'post_id', 'operations' ),
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'post_id'     => array( 'type' => 'integer' ),
					'updated'     => array( 'type' => 'integer' ),
					'failed'      => array( 'type' => 'array' ),
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

	public function execute_batch_update( array $input ): array|\WP_Error {
		$post_id    = (int) $input['post_id'];
		$operations = $input['operations'] ?? array();

		if ( ! is_array( $operations ) || empty( $operations ) ) {
			return new \WP_Error( 'invalid_operations', __( 'Operations must be a non-empty array.', 'wp-mcp-plugin' ) );
		}

		$page_data = $this->get_page_data( $post_id );
		$updated   = 0;
		$failed    = array();

		foreach ( $operations as $op ) {
			$element_id = sanitize_text_field( $op['element_id'] ?? '' );
			$settings   = $op['settings'] ?? null;

			if ( empty( $element_id ) || ! is_array( $settings ) ) {
				$failed[] = array(
					'element_id' => $element_id,
					'reason'     => 'Missing element_id or invalid settings.',
				);
				continue;
			}

			$result = $this->find_element( $page_data, $element_id );

			if ( null === $result['parent'] ) {
				$failed[] = array(
					'element_id' => $element_id,
					'reason'     => 'Element not found.',
				);
				continue;
			}

			$element =& $result['parent'][ $result['index'] ];
			$element['settings'] = array_merge( $element['settings'] ?? array(), $this->sanitize_settings( $settings ) );
			$updated++;
		}

		if ( $updated > 0 ) {
			$this->save_page_data( $post_id, $page_data );
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
			'updated' => $updated,
			'failed'  => $failed,
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────

	/**
	 * Basic settings sanitization: text fields, URLs, and arrays.
	 *
	 * @param array $settings
	 * @return array
	 */
	private function sanitize_settings( array $settings ): array {
		$sanitized = array();

		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) ) {
				// URLs get esc_url_raw
				if ( preg_match( '/^(url|link|href|src)$/i', $key ) ) {
					$sanitized[ $key ] = esc_url_raw( $value );
				} else {
					$sanitized[ $key ] = sanitize_text_field( $value );
				}
			} elseif ( is_array( $value ) ) {
				$sanitized[ $key ] = $this->sanitize_settings( $value );
			} elseif ( is_numeric( $value ) ) {
				$sanitized[ $key ] = $value;
			} elseif ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value;
			} else {
				$sanitized[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		return $sanitized;
	}
}
