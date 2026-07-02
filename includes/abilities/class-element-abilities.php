<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;

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
			update_post_meta( $post_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
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
			'description'       => __( 'Add a new flex or grid container to an Elementor page. Requires post_id; optional parent_id (7-char hex from a prior add-container). Returns element_id for add-widget and update-element. See `wp-mcp://docs/container-system` for flex/grid rules, the 3-level nesting limit, and the two-container full-bleed pattern.', 'wp-mcp-plugin' ),
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
				'required'             => array( 'post_id' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
					'_recommended_resources' => AbilitySchemas::recommended_resources_property(),
				),
				'required'   => array( 'element_id', 'post_id' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, false, false ),
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
			'settings'   => self::sanitize_settings( $settings ),
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

		return AbilitySchemas::with_recommended_resources(
			array(
				'element_id' => $container['id'],
				'post_id'    => $post_id,
			),
			array( 'wp-mcp://docs/container-system', 'wp-mcp://docs/widget-types' )
		);
	}

	// ── add-widget ────────────────────────────────────────────────────

	private function register_add_widget(): void {
		$this->ability_names[] = 'wp-mcp/add-widget';

		wp_register_ability( 'wp-mcp/add-widget', array(
			'label'             => __( 'Add Widget', 'wp-mcp-plugin' ),
			'description'       => __( 'Add a widget into a container. Requires parent_id (7-char hex container), widget_type, and post_id. Use list-elementor-widgets and get-elementor-widget-schema before calling. Returns element_id for update-element. See `wp-mcp://docs/widget-types`.', 'wp-mcp-plugin' ),
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
				'required'             => array( 'post_id', 'parent_id', 'widget_type' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id'  => array( 'type' => 'string' ),
					'post_id'     => array( 'type' => 'integer' ),
					'widget_type' => array( 'type' => 'string' ),
					'_recommended_resources' => AbilitySchemas::recommended_resources_property(),
				),
				'required'   => array( 'element_id', 'post_id', 'widget_type' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, false, false ),
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
		} else {
			return new \WP_Error(
				'elementor_not_ready',
				__( 'Elementor widgets manager is not available. Cannot validate widget type.', 'wp-mcp-plugin' )
			);
		}

		$widget_element = array(
			'id'         => $this->generate_id(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'settings'   => self::sanitize_settings( $settings ),
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

		return AbilitySchemas::with_recommended_resources(
			array(
				'element_id'  => $widget_element['id'],
				'post_id'     => $post_id,
				'widget_type' => $widget_type,
			),
			array( 'wp-mcp://docs/widget-types', 'wp-mcp://docs/workflow' )
		);
	}

	// ── update-element ────────────────────────────────────────────────

	private function register_update_element(): void {
		$this->ability_names[] = 'wp-mcp/update-element';

		wp_register_ability( 'wp-mcp/update-element', array(
			'label'             => __( 'Update Element', 'wp-mcp-plugin' ),
			'description'       => __( 'Update settings on any element (container or widget) by element_id. Settings merge with existing — provide only changed properties. See `wp-mcp://docs/elementor-data-structure` for the settings shape.', 'wp-mcp-plugin' ),
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
				'required'             => array( 'post_id', 'element_id', 'settings' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
				),
				'required'   => array( 'element_id', 'post_id' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, true, false ),
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
		$element['settings'] = array_merge( $element['settings'] ?? array(), self::sanitize_settings( $settings ) );

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
			'description'       => __( 'Remove an element (container or widget) and all its children from a page. This is destructive — removed content cannot be recovered.', 'wp-mcp-plugin' ),
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
				'required'             => array( 'post_id', 'element_id' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'    => array( 'type' => 'boolean' ),
					'element_id' => array( 'type' => 'string' ),
					'post_id'    => array( 'type' => 'integer' ),
				),
				'required'   => array( 'success', 'element_id', 'post_id' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, true, false ),
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
			'description'       => __( 'Apply multiple element updates in a single save operation. Much more efficient than calling update-element repeatedly for large pages. Each operation in the array requires element_id and settings. See resource \`wp-mcp://docs/workflow\` for batch usage.', 'wp-mcp-plugin' ),
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
						'description' => 'Array of operations. Each operation: { element_id (string), settings (object) }. Per-op validation happens in the executor — one bad op is reported in the response `failed[]` array, not the whole batch.',
						'minItems'    => 1,
						'maxItems'    => 100,
						'items'       => array(
							'type'                 => 'object',
							'additionalProperties' => true,
						),
					),
				),
				'required'             => array( 'post_id', 'operations' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'post_id'     => array( 'type' => 'integer' ),
					'updated'     => array( 'type' => 'integer' ),
					'failed'      => array(
						'type'  => 'array',
						'items' => AbilitySchemas::batch_failure_item(),
					),
					'_recommended_resources' => AbilitySchemas::recommended_resources_property(),
				),
				'required'   => array( 'success', 'post_id', 'updated', 'failed' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( false, true, false ),
			),
		) );
	}

	public function execute_batch_update( array $input ): array|\WP_Error {
		$post_id    = (int) $input['post_id'];
		$operations = $input['operations'] ?? array();

		if ( ! is_array( $operations ) || empty( $operations ) ) {
			return new \WP_Error( 'invalid_operations', __( 'Operations must be a non-empty array.', 'wp-mcp-plugin' ) );
		}

		if ( count( $operations ) > 100 ) {
			return new \WP_Error(
				'too_many_operations',
				__( 'Batch update limited to 100 operations per call.', 'wp-mcp-plugin' )
			);
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
			$element['settings'] = array_merge( $element['settings'] ?? array(), self::sanitize_settings( $settings ) );
			$updated++;
		}

		if ( $updated > 0 ) {
			$this->save_page_data( $post_id, $page_data );
		}

		return AbilitySchemas::with_recommended_resources(
			array(
				'success' => $updated > 0,
				'post_id' => $post_id,
				'updated' => $updated,
				'failed'  => $failed,
			),
			array( 'wp-mcp://docs/workflow' )
		);
	}

	// ── Helpers ───────────────────────────────────────────────────────

	/**
	 * Elementor control keys that store REPEATER values. A repeater is a
	 * flat 0-indexed array of item-objects. The MCP client (LLM) sometimes
	 * wraps the array in `{"item":[…]}` (the shape Elementor's own builder
	 * UI uses to render), which is the wrong on-disk shape — Elementor's
	 * repeater control reads `$value[0]` as an item, so the envelope makes
	 * every item `null` and breaks `add_controls_stack_style_rules()` in
	 * `elementor/core/files/css/base.php`. We unwrap the envelope here so
	 * any client mistake becomes a save-as-flat-array.
	 */
	public const REPEATER_KEYS = array(
		'icon_list',          // icon-list widget
		'social_icon_list',   // social-icons widget
		'tabs',               // accordion / tabs / toggle widgets
		'list_items',         // icon-box / star-rating / etc.
		'slides',             // image-carousel / testimonial / slider
		'gallery',            // image-gallery / basic-gallery
		'form_fields',        // form widget
		'menu_items',         // nav-menu widget
	);

	/**
	 * Heuristic: a value is an "envelope shape" if it is an associative
	 * array whose only entry is a single key holding a flat (list-shaped)
	 * array. Elementor repeater values are flat lists — anything else is
	 * a sign the LLM wrapped the array in the wrong shape.
	 *
	 * Recognised envelopes:
	 *   - `{"item":[…]}`  /  `{"items":[…]}`   (LLM convenience)
	 *   - `{"[key]":[…]}` (single-key dict matching the field name)
	 *
	 * @param array $value
	 * @return bool
	 */
	public static function is_envelope_shape( array $value ): bool {
		if ( array_is_list( $value ) ) {
			return false;
		}
		foreach ( array( 'item', 'items' ) as $k ) {
			if ( isset( $value[ $k ] ) && is_array( $value[ $k ] ) && array_is_list( $value[ $k ] ) ) {
				return true;
			}
		}
		if ( 1 === count( $value ) ) {
			$only_val = reset( $value );
			if ( is_array( $only_val ) && array_is_list( $only_val ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Human-readable description of the envelope shape, for debug output.
	 *
	 * @param array $value
	 * @return string
	 */
	public static function describe_envelope( array $value ): string {
		$keys    = array_keys( $value );
		$first   = reset( $value );
		$first_n = is_array( $first ) ? count( $first ) : 0;
		return sprintf( '{"%s":[…]} (n=%d)', $keys[0] ?? '?', $first_n );
	}

	/**
	 * Detect and unwrap a repeater envelope.
	 *
	 * Recognised envelope shapes (all indicate "one key holding the array"):
	 *   - `{"item":[…]}`         (legacy LLM convention; the bug from the session)
	 *   - `{"items":[…]}`        (plural form)
	 *   - `{"[key]":[…]}`        (single-key dict where the only key is the field name)
	 *
	 * Returns the inner array if an envelope is detected, otherwise null.
	 *
	 * @param array $value
	 * @return array|null
	 */
	private function unwrap_repeater_envelope( array $value ): ?array {
		// Already a flat numeric array → not an envelope.
		if ( array_is_list( $value ) ) {
			return null;
		}

		// `{"item":[…]}` or `{"items":[…]}`.
		foreach ( array( 'item', 'items' ) as $k ) {
			if ( isset( $value[ $k ] ) && is_array( $value[ $k ] ) && array_is_list( $value[ $k ] ) ) {
				return $value[ $k ];
			}
		}

		// `{"icon_list":[…]}` — single-key dict matching the field name.
		if ( 1 === count( $value ) ) {
			$only_key = array_key_first( $value );
			$only_val = $value[ $only_key ];
			if ( is_array( $only_val ) && array_is_list( $only_val ) ) {
				return $only_val;
			}
		}

		return null;
	}

	/**
	 * Sanitize an entire Elementor element tree. Used by `create-page`
	 * (initial_elementor_data) and `update-page-elementor-data` — those
	 * tools bypass `add-widget` / `update-element` and so would otherwise
	 * persist repeater-envelope shapes verbatim.
	 *
	 * Walks `settings` on every node, runs `sanitize_settings()` (which
	 * unwraps repeater envelopes), and recurses into `elements`. Also
	 * assigns an `_id` to any node that is missing one.
	 *
	 * Public + static so other ability classes (e.g. Page) can call it.
	 *
	 * @param array $tree
	 * @return array
	 */
	public static function sanitize_elementor_tree( array $tree ): array {
		$out = array();
		foreach ( $tree as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$node['id'] = isset( $node['id'] ) && ! empty( $node['id'] )
				? (string) $node['id']
				: substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
			if ( isset( $node['settings'] ) && is_array( $node['settings'] ) ) {
				$node['settings'] = self::sanitize_settings( $node['settings'] );
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$node['elements'] = self::sanitize_elementor_tree( $node['elements'] );
			}
			$out[] = $node;
		}
		return $out;
	}

	/**
	 * Sanitize a single repeater item, ensuring it has an `_id`. Elementor
	 * requires every repeater row to have a unique `_id` (8-char hex) for
	 * stable DOM keys; absent one, Elementor regenerates it on render, but
	 * the lack causes flicker on CSS regen.
	 *
	 * @param array $item
	 * @return array
	 */
	private static function sanitize_repeater_item( array $item ): array {
		if ( empty( $item['_id'] ) ) {
			$item['_id'] = substr( bin2hex( random_bytes( 4 ) ), 0, 8 );
		}
		return $item;
	}

	/**
	 * Basic settings sanitization: text fields, URLs, and arrays.
	 *
	 * For known repeater keys (`icon_list`, `social_icon_list`, `tabs`, …),
	 * the value is also normalised: any envelope shape
	 * (`{"item":[…]}`, `{"items":[…]}`, `{"[key]":[…]}`) is unwrapped to a
	 * flat array, and each item is ensured an `_id`.
	 *
	 * @param array $settings
	 * @return array
	 */
	private static function sanitize_settings( array $settings ): array {
		$sanitized = array();
		foreach ( $settings as $key => $value ) {
			if ( is_string( $value ) ) {
				if ( preg_match( '/^(url|link|href|src)$/i', $key ) || str_ends_with( $key, '_url' ) || str_ends_with( $key, '_link' ) ) {
					$sanitized[ $key ] = esc_url_raw( $value );
				} else {
					$sanitized[ $key ] = wp_kses_post( $value );
				}
			} elseif ( is_array( $value ) ) {
				if ( in_array( $key, self::REPEATER_KEYS, true ) ) {
					$unwrapped      = self::unwrap_repeater_envelope( $value );
					$repeater_value = $unwrapped ?? $value;
					$items          = array();
					foreach ( $repeater_value as $item ) {
						if ( is_array( $item ) ) {
							$items[] = self::sanitize_repeater_item( self::sanitize_settings( $item ) );
						}
					}
					$sanitized[ $key ] = $items;
				} else {
					$sanitized[ $key ] = self::sanitize_settings( $value );
				}
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
