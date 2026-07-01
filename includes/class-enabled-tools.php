<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps wp_mcp_enabled_tools aligned with registered MCP abilities.
 */
class EnabledTools {

	private const OPTION        = 'wp_mcp_enabled_tools';
	private const ROSTER_OPTION = 'wp_mcp_tool_roster';

	/**
	 * Legacy slug renames (pre-v0.2.0).
	 *
	 * @return array<string, string> Old slug => new slug.
	 */
	private static function renamed_slugs(): array {
		return array(
			'wp-mcp/render-page' => 'wp-mcp/visual-compare-preview',
		);
	}

	/**
	 * Registered MCP tool ability slugs (excludes docs resources and prompts).
	 *
	 * @return string[]
	 */
	public static function get_registered_slugs(): array {
		if ( ! function_exists( 'wp_register_ability' ) || ! class_exists( '\WP_Abilities_Registry' ) ) {
			return ToolRegistry::get_all_slugs();
		}

		\WP_Abilities_Registry::get_instance();
		$registered = \WP_Abilities_Registry::get_instance()->get_all_registered();

		$slugs = array();
		foreach ( $registered as $name => $ability ) {
			if ( ! is_string( $name ) || ! str_starts_with( $name, 'wp-mcp/' ) ) {
				continue;
			}

			if ( str_starts_with( $name, 'wp-mcp/docs-' ) ) {
				continue;
			}

			if ( $ability instanceof \WP_Ability && self::is_non_tool_mcp_ability( $ability ) ) {
				continue;
			}

			$slugs[] = $name;
		}

		sort( $slugs );
		return $slugs;
	}

	/**
	 * Whether an ability is exposed as an MCP resource or prompt (not a tool).
	 *
	 * @param \WP_Ability $ability Registered ability.
	 * @return bool
	 */
	private static function is_non_tool_mcp_ability( \WP_Ability $ability ): bool {
		$meta = $ability->get_meta();
		$mcp  = is_array( $meta['mcp'] ?? null ) ? $meta['mcp'] : array();
		$type = $mcp['type'] ?? 'tool';

		return in_array( $type, array( 'resource', 'prompt' ), true );
	}

	/**
	 * Apply legacy slug renames in the stored enabled list.
	 *
	 * @param string[] $enabled Enabled tool slugs.
	 * @return string[]
	 */
	public static function migrate_renamed( array $enabled ): array {
		foreach ( self::renamed_slugs() as $old => $new ) {
			$pos = array_search( $old, $enabled, true );
			if ( false !== $pos ) {
				$enabled[ $pos ] = $new;
			}
		}
		return $enabled;
	}

	/**
	 * Auto-enable newly registered tools and prune removed slugs.
	 *
	 * Only tools that were not in the last known roster are added — deliberately
	 * disabled tools are left alone.
	 *
	 * @return string[] Slugs that were auto-enabled.
	 */
	public static function sync(): array {
		$registered = self::get_registered_slugs();
		$roster     = get_option( self::ROSTER_OPTION, array() );
		if ( ! is_array( $roster ) ) {
			$roster = array();
		}

		$roster     = self::migrate_renamed( $roster );
		$brand_new  = array_values( array_diff( $registered, $roster ) );
		$stored     = get_option( self::OPTION, false );

		if ( ! is_array( $stored ) ) {
			update_option( self::OPTION, $registered );
			update_option( self::ROSTER_OPTION, $registered );
			return $registered;
		}

		$enabled = self::migrate_renamed( $stored );
		$enabled = array_values( array_intersect( $enabled, $registered ) );
		$dirty   = false;

		if ( ! empty( $brand_new ) ) {
			$enabled = array_values( array_unique( array_merge( $enabled, $brand_new ) ) );
			$dirty   = true;
		}

		if ( $enabled !== array_values( $stored ) ) {
			$dirty = true;
		}

		if ( $dirty ) {
			update_option( self::OPTION, $enabled );
		}

		update_option( self::ROSTER_OPTION, $registered );

		return $brand_new;
	}

	/**
	 * @return string[]
	 */
	public static function get_enabled(): array {
		$registered = self::get_registered_slugs();
		$enabled    = get_option( self::OPTION, $registered );

		if ( ! is_array( $enabled ) ) {
			return $registered;
		}

		$enabled = self::migrate_renamed( $enabled );
		return array_values( array_intersect( $registered, $enabled ) );
	}

	public static function enable_all(): void {
		$registered = self::get_registered_slugs();
		update_option( self::OPTION, $registered );
		update_option( self::ROSTER_OPTION, $registered );
	}

	/**
	 * Compare ToolRegistry metadata against live ability registrations.
	 *
	 * @return array{unlisted: string[], orphaned: string[]}
	 */
	public static function get_registry_drift(): array {
		$registered = self::get_registered_slugs();
		$known      = ToolRegistry::get_all_slugs();

		return array(
			'unlisted' => array_values( array_diff( $registered, $known ) ),
			'orphaned' => array_values( array_diff( $known, $registered ) ),
		);
	}
}
