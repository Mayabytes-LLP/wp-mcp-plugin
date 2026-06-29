<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared JSON Schema fragments and MCP tool annotations for ability registration.
 */
class AbilitySchemas {

	/**
	 * MCP tool annotations with closed-world hint (site-scoped Elementor operations).
	 *
	 * @param bool $readonly    Maps to readOnlyHint.
	 * @param bool $destructive   Maps to destructiveHint.
	 * @param bool $idempotent    Maps to idempotentHint.
	 * @return array<string, bool>
	 */
	public static function annotations( bool $readonly, bool $destructive, bool $idempotent ): array {
		return array(
			'readonly'      => $readonly,
			'destructive'   => $destructive,
			'idempotent'    => $idempotent,
			'openWorldHint' => false,
		);
	}

	/**
	 * Elementor element tree node (_elementor_data item).
	 *
	 * @return array<string, mixed>
	 */
	public static function elementor_element_node(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'         => array( 'type' => 'string' ),
				'elType'     => array( 'type' => 'string' ),
				'widgetType' => array( 'type' => 'string' ),
				'settings'   => array( 'type' => 'object' ),
				'elements'   => array( 'type' => 'array' ),
				'isInner'    => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function elementor_data_array(): array {
		return array(
			'type'  => 'array',
			'items' => self::elementor_element_node(),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function page_summary_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'               => array( 'type' => 'integer' ),
				'title'            => array( 'type' => 'string' ),
				'slug'             => array( 'type' => 'string' ),
				'url'              => array( 'type' => 'string' ),
				'status'           => array( 'type' => 'string' ),
				'elementor_active' => array( 'type' => 'boolean' ),
			),
			'required'   => array( 'id', 'title', 'slug', 'url', 'status', 'elementor_active' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function widget_info_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'type'       => array( 'type' => 'string' ),
				'title'      => array( 'type' => 'string' ),
				'icon'       => array( 'type' => 'string' ),
				'categories' => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
			),
			'required'   => array( 'type', 'title', 'icon', 'categories' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function template_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'id'     => array( 'type' => 'integer' ),
				'title'  => array( 'type' => 'string' ),
				'type'   => array( 'type' => 'string' ),
				'status' => array( 'type' => 'string' ),
			),
			'required'   => array( 'id', 'title', 'type', 'status' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function kit_color_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'_id'   => array( 'type' => 'string' ),
				'title' => array( 'type' => 'string' ),
				'color' => array( 'type' => 'string' ),
			),
			'required'   => array( '_id', 'title', 'color' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function kit_typography_item(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'_id'                    => array( 'type' => 'string' ),
				'title'                  => array( 'type' => 'string' ),
				'typography_typography'  => array( 'type' => 'string' ),
				'typography_font_family' => array( 'type' => 'string' ),
			),
			'additionalProperties' => true,
			'required'             => array( '_id', 'title' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function preview_element_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'element_id' => array( 'type' => 'string' ),
				'selector'   => array( 'type' => 'string' ),
				'elType'     => array( 'type' => 'string' ),
				'widgetType' => array( 'type' => 'string' ),
			),
			'required'   => array( 'element_id', 'selector', 'elType', 'widgetType' ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function batch_failure_item(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'element_id' => array( 'type' => 'string' ),
				'reason'     => array( 'type' => 'string' ),
			),
			'required'   => array( 'element_id', 'reason' ),
		);
	}
}
