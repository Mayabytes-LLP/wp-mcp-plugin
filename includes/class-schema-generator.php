<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runtime introspection of Elementor widget controls to produce JSON Schemas.
 *
 * Walks a widget's get_controls() output and maps control types to JSON Schema
 * fragments. Results are cached in transients for 5 minutes.
 */
class SchemaGenerator {

	/**
	 * Get a JSON Schema describing a specific Elementor widget type's controllable properties.
	 *
	 * @param string $widget_type The Elementor widget type key (e.g. 'heading', 'button').
	 * @return array|\WP_Error JSON Schema array or WP_Error if widget not found.
	 */
	public function get_widget_schema( string $widget_type ) {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new \WP_Error( 'elementor_missing', __( 'Elementor is not active.', 'wp-mcp-plugin' ) );
		}

		if ( ! \Elementor\Plugin::$instance->widgets_manager ) {
			return new \WP_Error( 'widgets_manager_missing', __( 'Elementor widgets manager unavailable.', 'wp-mcp-plugin' ) );
		}

		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );

		if ( ! $widget ) {
			return new \WP_Error(
				'widget_not_found',
				sprintf(
					/* translators: %s: widget type key */
					__( 'Widget type "%s" not found.', 'wp-mcp-plugin' ),
					$widget_type
				)
			);
		}

		$controls = $widget->get_controls();
		$properties = array();

		foreach ( $controls as $control_id => $control ) {
			$fragment = $this->map_control( $control );
			if ( null !== $fragment ) {
				$properties[ $control_id ] = $fragment;
			}
		}

		return array(
			'type'        => 'object',
			'description' => sprintf(
				/* translators: %s: widget title */
				__( 'Settings for the %s widget.', 'wp-mcp-plugin' ),
				$widget->get_title()
			),
			'properties'  => $properties,
		);
	}

	/**
	 * Get a list of all registered Elementor widget types with metadata.
	 *
	 * @return array[] Array of widget info arrays with keys: type, title, icon, categories.
	 */
	public function get_widget_list(): array {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! \Elementor\Plugin::$instance->widgets_manager ) {
			return array();
		}

		$cache_key = 'wp_mcp_widget_list';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$widget_types = \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
		$list = array();

		foreach ( $widget_types as $type => $widget ) {
			$list[] = array(
				'type'       => $type,
				'title'      => $widget->get_title(),
				'icon'       => $widget->get_icon(),
				'categories' => $widget->get_categories(),
			);
		}

		set_transient( $cache_key, $list, 5 * MINUTE_IN_SECONDS );

		return $list;
	}

	/**
	 * Get Elementor global kit settings (colors, typography, breakpoints).
	 *
	 * @return array|\WP_Error
	 */
	public function get_global_settings() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new \WP_Error( 'elementor_missing', __( 'Elementor is not active.', 'wp-mcp-plugin' ) );
		}

		if ( ! \Elementor\Plugin::$instance->kits_manager ) {
			return new \WP_Error( 'kits_manager_missing', __( 'Elementor kits manager unavailable.', 'wp-mcp-plugin' ) );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

		if ( ! $kit ) {
			return new \WP_Error( 'no_active_kit', __( 'No active Elementor kit found.', 'wp-mcp-plugin' ) );
		}

		$kit_settings = $kit->get_settings();

		return array(
			'custom_colors'    => $kit_settings['system_colors'] ?? $kit_settings['custom_colors'] ?? array(),
			'custom_typography' => $kit_settings['system_typography'] ?? $kit_settings['custom_typography'] ?? array(),
			'container_width'   => $kit_settings['container_width'] ?? array(),
			'breakpoints'       => $kit_settings['viewport_md'] ?? null ? array(
				'mobile'  => $kit_settings['viewport_mobile'] ?? 767,
				'tablet'  => $kit_settings['viewport_md'] ?? 1024,
				'desktop' => null,
			) : null,
		);
	}

	/**
	 * Map an Elementor control definition to a JSON Schema fragment.
	 *
	 * @param array $control The control definition from get_controls().
	 * @return array|null Schema fragment or null if this control should be skipped.
	 */
	private function map_control( array $control ): ?array {
		$type = $control['type'] ?? '';

		// Skip hidden, section, and internal controls
		if ( in_array( $type, array( 'hidden', 'section', 'tab', 'popover_toggle', 'repeater' ), true ) ) {
			return null;
		}

		$fragment = array(
			'description' => $control['label'] ?? $control['name'] ?? '',
		);

		switch ( $type ) {
			case 'text':
			case 'url':
			case 'textarea':
			case 'wysiwyg':
			case 'code':
				$fragment['type'] = 'string';
				break;

			case 'number':
				$fragment['type'] = 'number';
				if ( isset( $control['min'] ) ) {
					$fragment['minimum'] = $control['min'];
				}
				if ( isset( $control['max'] ) ) {
					$fragment['maximum'] = $control['max'];
				}
				break;

			case 'switcher':
				$fragment['type'] = 'boolean';
				$fragment['default'] = ! empty( $control['return_value'] )
					? ( $control['return_value'] === ( $control['default'] ?? '' ) )
					: (bool) ( $control['default'] ?? false );
				break;

			case 'select':
			case 'select2':
				$fragment['type'] = 'string';
				if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
					$fragment['enum'] = array_values( $control['options'] );
				}
				break;

			case 'choose':
				$fragment['type'] = 'string';
				if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
					$fragment['enum'] = array_keys( $control['options'] );
				}
				break;

			case 'slider':
			case 'slider-unit':
				$fragment['type'] = 'string';
				break;

			case 'color':
				$fragment['type'] = 'string';
				$fragment['format'] = 'hex-color';
				break;

			case 'media':
			case 'image':
			case 'gallery':
			case 'icon':
				$fragment['type'] = 'object';
				$fragment['properties'] = array(
					'id'  => array( 'type' => 'integer', 'description' => 'Attachment/image ID' ),
					'url' => array( 'type' => 'string', 'description' => 'Image URL' ),
				);
				break;

			case 'dimensions':
				$fragment['type'] = 'object';
				$fragment['properties'] = array(
					'top'    => array( 'type' => 'string' ),
					'right'  => array( 'type' => 'string' ),
					'bottom' => array( 'type' => 'string' ),
					'left'   => array( 'type' => 'string' ),
				);
				break;

			default:
				$fragment['type'] = 'string';
				break;
		}

		if ( isset( $control['default'] ) && ! isset( $fragment['default'] ) ) {
			$fragment['default'] = $control['default'];
		}

		return $fragment;
	}
}
