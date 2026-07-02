<?php

namespace WpMcp\Abilities;

use WpMcp\AbilitySchemas;
use WpMcp\SchemaGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only query tools: list pages, get page content, list widgets, get schemas,
 * list templates, and get global settings.
 */
class Query {

	private SchemaGenerator $schema_generator;

	/** @var string[] */
	private array $ability_names = array();

	public function __construct( SchemaGenerator $schema_generator ) {
		$this->schema_generator = $schema_generator;
	}

	public function register(): void {
		$this->register_list_pages();
		$this->register_get_page();
		$this->register_list_widgets();
		$this->register_get_widget_schema();
		$this->register_list_templates();
		$this->register_get_global_settings();
		$this->register_get_elementor_debug_info();
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	private function register_list_pages(): void {
		$this->ability_names[] = 'wp-mcp/list-pages';

		wp_register_ability( 'wp-mcp/list-pages', array(
			'label'             => __( 'List Pages', 'wp-mcp-plugin' ),
			'description'       => __( 'List all WordPress pages, filterable by status and search term. Returns page ID, title, slug, URL, status, and whether Elementor is active on each page.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_list_pages' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'status' => array(
						'type'        => 'string',
						'description' => 'Filter by post status. Default: any (returns all statuses).',
						'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash', 'any' ),
					),
					'search' => array(
						'type'        => 'string',
						'description' => 'Search term to filter pages by title.',
					),
					'per_page' => array(
						'type'        => 'integer',
						'description' => 'Number of pages per result. Default: 20, Max: 100.',
						'minimum'     => 1,
						'maximum'     => 100,
					),
					'page' => array(
						'type'        => 'integer',
						'description' => 'Page number for pagination. Default: 1.',
						'minimum'     => 1,
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'pages'       => array(
						'type'  => 'array',
						'items' => AbilitySchemas::page_summary_item(),
					),
					'total'       => array( 'type' => 'integer' ),
					'total_pages' => array( 'type' => 'integer' ),
				),
				'required'   => array( 'pages', 'total', 'total_pages' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_list_pages( array $input ): array {
		$status   = sanitize_text_field( $input['status'] ?? 'any' );
		$search   = sanitize_text_field( $input['search'] ?? '' );
		$per_page = min( max( (int) ( $input['per_page'] ?? 20 ), 1 ), 100 );
		$page     = max( (int) ( $input['page'] ?? 1 ), 1 );

		$args = array(
			'post_type'      => 'page',
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$pages = array();

		foreach ( $query->posts as $post ) {
			$edit_mode = get_post_meta( $post->ID, '_elementor_edit_mode', true );
			$pages[]   = array(
				'id'             => $post->ID,
				'title'          => $post->post_title,
				'slug'           => $post->post_name,
				'url'            => get_permalink( $post ),
				'status'         => $post->post_status,
				'elementor_active' => 'builder' === $edit_mode,
			);
		}

		return array(
			'pages'       => $pages,
			'total'       => $query->found_posts,
			'total_pages' => $query->max_num_pages,
		);
	}

	private function register_get_page(): void {
		$this->ability_names[] = 'wp-mcp/get-page';

		wp_register_ability( 'wp-mcp/get-page', array(
			'label'             => __( 'Get Page', 'wp-mcp-plugin' ),
			'description'       => __( 'Get a single page with its full Elementor data. Returns page metadata, edit mode, template, and the complete _elementor_data JSON tree.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_get_page' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'The page post ID.',
					),
					'slug' => array(
						'type'        => 'string',
						'description' => 'The page slug (alternative to post_id).',
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
					'id'                  => array( 'type' => 'integer' ),
					'title'               => array( 'type' => 'string' ),
					'slug'                => array( 'type' => 'string' ),
					'url'                 => array( 'type' => 'string' ),
					'status'              => array( 'type' => 'string' ),
					'template'            => array( 'type' => 'string' ),
					'elementor_edit_mode' => array( 'type' => 'string' ),
					'elementor_data'      => AbilitySchemas::elementor_data_array(),
				),
				'required'   => array( 'id', 'title', 'slug', 'url', 'status', 'template', 'elementor_edit_mode', 'elementor_data' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_page( array $input ): array|\WP_Error {
		$post = null;

		if ( ! empty( $input['post_id'] ) ) {
			$post = get_post( (int) $input['post_id'] );
		} elseif ( ! empty( $input['slug'] ) ) {
			$post = get_page_by_path( sanitize_text_field( $input['slug'] ), OBJECT, 'page' );
		}

		if ( ! $post || 'page' !== $post->post_type ) {
			return new \WP_Error( 'page_not_found', __( 'Page not found.', 'wp-mcp-plugin' ) );
		}

		$elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
		$elementor_data = is_array( $elementor_data ) ? $elementor_data : json_decode( $elementor_data, true );

		return array(
			'id'                  => $post->ID,
			'title'               => $post->post_title,
			'slug'                => $post->post_name,
			'url'                 => get_permalink( $post ),
			'status'              => $post->post_status,
			'template'            => get_post_meta( $post->ID, '_wp_page_template', true ),
			'elementor_edit_mode' => get_post_meta( $post->ID, '_elementor_edit_mode', true ),
			'elementor_data'      => $elementor_data ?: array(),
		);
	}

	private function register_list_widgets(): void {
		$this->ability_names[] = 'wp-mcp/list-elementor-widgets';

		wp_register_ability( 'wp-mcp/list-elementor-widgets', array(
			'label'             => __( 'List Elementor Widgets', 'wp-mcp-plugin' ),
			'description'       => __( 'List all registered Elementor widget types with their type keys, titles, icons, and categories. Use this to discover available widgets before calling add-widget.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_list_widgets' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'category' => array(
						'type'        => 'string',
						'description' => 'Filter widgets by category slug (e.g. basic, general, pro-elements).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'widgets' => array(
						'type'  => 'array',
						'items' => AbilitySchemas::widget_info_item(),
					),
				),
				'required'   => array( 'widgets' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_list_widgets( array $input ): array|\WP_Error {
		$widgets  = $this->schema_generator->get_widget_list();
		$category = sanitize_text_field( $input['category'] ?? '' );

		if ( ! empty( $category ) ) {
			$widgets = array_values( array_filter( $widgets, function ( $w ) use ( $category ) {
				return in_array( $category, $w['categories'], true );
			} ) );
		}

		return array( 'widgets' => $widgets );
	}

	private function register_get_widget_schema(): void {
		$this->ability_names[] = 'wp-mcp/get-elementor-widget-schema';

		wp_register_ability( 'wp-mcp/get-elementor-widget-schema', array(
			'label'             => __( 'Get Widget Schema', 'wp-mcp-plugin' ),
			'description'       => __( 'Get the full JSON Schema for a specific Elementor widget type. Shows all controllable settings and their types, enumerations, and defaults. Use this before calling add-widget to ensure correct settings.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_get_widget_schema' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'widget_type' => array(
						'type'        => 'string',
						'description' => 'The Elementor widget type key (e.g. heading, button, image).',
					),
				),
				'required'             => array( 'widget_type' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'schema' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
				'required'   => array( 'schema' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_widget_schema( array $input ): array|\WP_Error {
		$widget_type = sanitize_text_field( $input['widget_type'] );
		$schema = $this->schema_generator->get_widget_schema( $widget_type );

		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		return array( 'schema' => $schema );
	}

	private function register_list_templates(): void {
		$this->ability_names[] = 'wp-mcp/list-elementor-templates';

		wp_register_ability( 'wp-mcp/list-elementor-templates', array(
			'label'             => __( 'List Elementor Templates', 'wp-mcp-plugin' ),
			'description'       => __( 'List all saved Elementor templates (sections, pages, headers, footers, etc.). Returns template ID, title, type, and status.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_list_templates' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'type' => array(
						'type'        => 'string',
						'description' => 'Filter by template type (section, page, header, footer, single, archive, etc.).',
					),
				),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'templates' => array(
						'type'  => 'array',
						'items' => AbilitySchemas::template_item(),
					),
				),
				'required'   => array( 'templates' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_list_templates( array $input ): array {
		if ( ! class_exists( '\Elementor\Plugin' )
			|| ! \Elementor\Plugin::$instance->templates_manager
		) {
			return array( 'templates' => array() );
		}

		$templates     = array();
		$source        = \Elementor\Plugin::$instance->templates_manager->get_source( 'local' );

		if ( ! $source ) {
			return array( 'templates' => array() );
		}

		try {
			$templates_raw = $source->get_items();
		} catch ( \Exception $e ) {
			$templates_raw = array();
		}
		$filter_type   = sanitize_text_field( $input['type'] ?? '' );

		foreach ( $templates_raw as $template ) {
			if ( ! empty( $filter_type ) && ( $template['type'] ?? '' ) !== $filter_type ) {
				continue;
			}

			$templates[] = array(
				'id'     => $template['template_id'] ?? 0,
				'title'  => sanitize_text_field( $template['title'] ?? '' ),
				'type'   => $template['type'] ?? '',
				'status' => $template['status'] ?? 'publish',
			);
		}

		return array( 'templates' => $templates );
	}

	private function register_get_global_settings(): void {
		$this->ability_names[] = 'wp-mcp/get-elementor-global-settings';

		wp_register_ability( 'wp-mcp/get-elementor-global-settings', array(
			'label'             => __( 'Get Elementor Global Settings', 'wp-mcp-plugin' ),
			'description'       => __( 'Get the active Elementor kit\'s global design settings: custom colors, typography, container widths, and breakpoints.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_get_global_settings' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type' => 'object',
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'colors'          => array(
						'type'  => 'array',
						'items' => AbilitySchemas::kit_color_item(),
					),
					'typography'      => array(
						'type'  => 'array',
						'items' => AbilitySchemas::kit_typography_item(),
					),
					'container_width' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => 'Kit container width settings (empty object when unset).',
					),
					'breakpoints'     => array(
						'type'                 => 'object',
						'additionalProperties' => true,
						'description'          => 'Breakpoint viewport settings (empty object when unset).',
					),
				),
				'required'   => array( 'colors', 'typography', 'container_width', 'breakpoints' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_global_settings( array $input ): array|\WP_Error {
		$settings = $this->schema_generator->get_global_settings();

		if ( is_wp_error( $settings ) ) {
			return $settings;
		}

		return array(
			'colors'          => $settings['custom_colors'],
			'typography'      => $settings['custom_typography'],
			'container_width' => $settings['container_width'] ?? (object) array(),
			'breakpoints'     => $settings['breakpoints'] ?? (object) array(),
		);
	}

	// ── get-elementor-debug-info ──────────────────────────────────────

	private function register_get_elementor_debug_info(): void {
		$this->ability_names[] = 'wp-mcp/get-elementor-debug-info';

		wp_register_ability( 'wp-mcp/get-elementor-debug-info', array(
			'label'             => __( 'Get Elementor Debug Info', 'wp-mcp-plugin' ),
			'description'       => __( 'Read-only introspection of a page\'s _elementor_data: counts of elements/widgets/containers, a tree of element IDs, and any structural problems (repeater envelope shape, missing _id, unknown widget type). Call this BEFORE regenerate-elementor-css fails, or when a tool call returns a bad_repeater_shape error — it gives a structured path to the offending element so you can fix it with update-element. Never read _elementor_data directly: this tool exists for that.', 'wp-mcp-plugin' ),
			'category'          => 'wp-mcp-plugin',
			'execute_callback'  => array( $this, 'execute_get_elementor_debug_info' ),
			'permission_callback' => array( $this, 'check_read_permission' ),
			'input_schema'      => array(
				'type'       => 'object',
				'properties' => array(
					'post_id' => array(
						'type'        => 'integer',
						'description' => 'The page post ID to inspect.',
					),
				),
				'required'             => array( 'post_id' ),
				'additionalProperties' => false,
			),
			'output_schema'     => array(
				'type'       => 'object',
				'properties' => array(
					'post_id'         => array( 'type' => 'integer' ),
					'element_count'   => array( 'type' => 'integer' ),
					'widget_count'    => array( 'type' => 'integer' ),
					'container_count' => array( 'type' => 'integer' ),
					'tree'            => array(
						'type'        => 'array',
						'description' => 'Flattened element map: id → { widgetType|elType, depth, parent_id, child_count }.',
						'items'       => array( 'type' => 'object' ),
					),
					'bad_repeaters'   => array(
						'type'        => 'array',
						'description' => 'Repeater fields stored in the wrong shape. Each entry: { element_id, widget_type, field, shape }. Fix by calling update-element with the unwrapped array.',
						'items'       => array( 'type' => 'object' ),
					),
					'missing_ids'     => array(
						'type'        => 'array',
						'description' => 'Elements without an `id` field. Elementor usually regenerates these on render but they cause flicker.',
						'items'       => array( 'type' => 'object' ),
					),
					'unknown_widgets' => array(
						'type'        => 'array',
						'description' => 'Widget types that Elementor does not currently have registered (deactivated widgets, Pro-only widgets on Pro-less sites).',
						'items'       => array( 'type' => 'object' ),
					),
					'_recommended_resources' => AbilitySchemas::recommended_resources_property(),
				),
				'required'   => array( 'post_id', 'element_count', 'widget_count', 'container_count' ),
			),
			'meta'              => array(
				'mcp'         => array( 'public' => true ),
				'annotations' => AbilitySchemas::annotations( true, false, true ),
			),
		) );
	}

	public function execute_get_elementor_debug_info( array $input ): array|\WP_Error {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post_id', __( 'A positive post_id is required.', 'wp-mcp-plugin' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Page not found.', 'wp-mcp-plugin' ) );
		}

		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( empty( $raw ) ) {
			return array(
				'post_id'         => $post_id,
				'element_count'   => 0,
				'widget_count'    => 0,
				'container_count' => 0,
				'tree'            => array(),
				'bad_repeaters'   => array(),
				'missing_ids'     => array(),
				'unknown_widgets' => array(),
			);
		}

		$tree = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
		if ( ! is_array( $tree ) ) {
			return new \WP_Error( 'invalid_data', __( '_elementor_data is not a valid JSON array.', 'wp-mcp-plugin' ) );
		}

		$flat          = array();
		$bad_repeaters = array();
		$missing_ids   = array();
		$widgets_seen  = array();
		$widget_count  = 0;
		$container_count = 0;

		$known_widgets = array();
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->widgets_manager ) {
			foreach ( \Elementor\Plugin::$instance->widgets_manager->get_widget_types() as $w ) {
				$known_widgets[ $w->get_name() ] = true;
			}
		}

		$walk = function ( array $nodes, int $depth, ?string $parent_id ) use ( &$walk, &$flat, &$bad_repeaters, &$missing_ids, &$unknown_widgets, &$widgets_seen, &$widget_count, &$container_count, $known_widgets ) {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				$id     = isset( $node['id'] ) ? (string) $node['id'] : '';
				$etype  = isset( $node['elType'] ) ? (string) $node['elType'] : '';
				$wtype  = isset( $node['widgetType'] ) ? (string) $node['widgetType'] : '';
				$child  = isset( $node['elements'] ) && is_array( $node['elements'] ) ? count( $node['elements'] ) : 0;

				if ( '' === $id ) {
					$missing_ids[] = array(
						'elType'     => $etype,
						'widgetType' => $wtype,
						'parent_id'  => $parent_id,
					);
				}

				if ( 'widget' === $etype ) {
					$widget_count++;
					$widgets_seen[ $wtype ] = ( $widgets_seen[ $wtype ] ?? 0 ) + 1;
					if ( $wtype && ! isset( $known_widgets[ $wtype ] ) ) {
						$unknown_widgets[] = array( 'element_id' => $id, 'widgetType' => $wtype );
					}
				} elseif ( 'container' === $etype ) {
					$container_count++;
				}

			$settings = isset( $node['settings'] ) && is_array( $node['settings'] ) ? $node['settings'] : array();
			foreach ( Element::REPEATER_KEYS as $key ) {
				if ( ! isset( $settings[ $key ] ) || ! is_array( $settings[ $key ] ) ) {
					continue;
				}
				if ( Element::is_envelope_shape( $settings[ $key ] ) ) {
					$bad_repeaters[] = array(
						'element_id'  => $id,
						'widget_type' => $wtype ?: $etype,
						'field'       => $key,
						'shape'       => Element::describe_envelope( $settings[ $key ] ),
					);
				}
			}

				if ( '' !== $id ) {
					$flat[ $id ] = array(
						'id'         => $id,
						'elType'     => $etype,
						'widgetType' => $wtype,
						'depth'      => $depth,
						'parent_id'  => $parent_id,
						'child_count' => $child,
					);
				}

				if ( $child > 0 ) {
					$walk( $node['elements'], $depth + 1, '' !== $id ? $id : null );
				}
			}
		};

		$unknown_widgets = array();
		$walk( $tree, 0, null );

		// Build the unknown_widgets list now that the walk has populated it.
		$unknown_list = array();
		if ( ! empty( $unknown_widgets ) ) {
			$unknown_list = $unknown_widgets;
		} elseif ( ! empty( $widgets_seen ) ) {
			foreach ( $widgets_seen as $w => $cnt ) {
				if ( $w && ! isset( $known_widgets[ $w ] ) ) {
					$unknown_list[] = array( 'widgetType' => $w, 'count' => $cnt );
				}
			}
		}

		return AbilitySchemas::with_recommended_resources(
			array(
				'post_id'         => $post_id,
				'element_count'   => count( $flat ),
				'widget_count'    => $widget_count,
				'container_count' => $container_count,
				'tree'            => array_values( $flat ),
				'bad_repeaters'   => $bad_repeaters,
				'missing_ids'     => $missing_ids,
				'unknown_widgets' => $unknown_list,
			),
			array( 'wp-mcp://docs/widget-types', 'wp-mcp://docs/elementor-data-structure' )
		);
	}
}
