<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Key generation, storage, and verification.
 *
 * Generates a single master API key hashed with wp_hash_password(),
 * stored in wp_mcp_api_key_hash option. Plaintext shown once via transient.
 */
class ApiKey {

	/**
	 * Generate a new API key.
	 *
	 * @return string The plaintext key (48 hex characters).
	 */
	public function generate(): string {
		return wp_generate_password( 48, false, false );
	}

	/**
	 * Store a newly generated plaintext key.
	 *
	 * Hashes the key and stores it in wp_mcp_api_key_hash option.
	 * Stores plaintext in a short-lived transient for one-time display.
	 *
	 * @param string $plaintext The plaintext API key.
	 */
	public function store( string $plaintext ): void {
		$hash = wp_hash_password( $plaintext );
		update_option( 'wp_mcp_api_key_hash', $hash );
		set_transient( 'wp_mcp_api_key_once', $plaintext, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Verify an API key value against the stored hash.
	 *
	 * @param string $key The plaintext key to verify.
	 * @return bool True if the key matches the stored hash.
	 */
	public function verify( string $key ): bool {
		$hash = get_option( 'wp_mcp_api_key_hash', '' );
		if ( empty( $hash ) || empty( $key ) ) {
			return false;
		}
		return wp_check_password( $key, $hash );
	}

	/**
	 * Check if an API key has been configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		$hash = get_option( 'wp_mcp_api_key_hash', '' );
		return ! empty( $hash );
	}

	/**
	 * Retrieve the plaintext key for one-time display, then delete the transient.
	 *
	 * @return string|null The plaintext key, or null if expired/absent.
	 */
	public function get_once(): ?string {
		$key = get_transient( 'wp_mcp_api_key_once' );
		if ( false !== $key ) {
			delete_transient( 'wp_mcp_api_key_once' );
			return $key;
		}
		return null;
	}

	/**
	 * Transport permission callback for the MCP server.
	 *
	 * Accepts the API key via X-WP-MCP-Key (primary) or Authorization: Bearer
	 * (fallback for clients that only support standard auth headers). On success,
	 * sets the current WordPress user to a service account so the mcp-adapter
	 * can create sessions (which requires a logged-in user).
	 *
	 * @param \WP_REST_Request|null $request The incoming REST request.
	 * @return bool|\WP_Error True if authenticated, WP_Error on failure.
	 */
	public function verify_transport_permission( $request = null ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'wp_mcp_no_key',
				__( 'MCP server has no API key configured.', 'wp-mcp-plugin' ),
				array( 'status' => 503 )
			);
		}

		$provided_key = $this->extract_key_from_request( $request );

		// Valid key clears any IP lockout so fixing a stale client config works immediately.
		if ( '' !== $provided_key && $this->verify( $provided_key ) ) {
			McpHardening::clear_auth_failures();

			// mcp-adapter session creation requires a logged-in user.
			$service_user_id = $this->get_service_user_id();
			if ( $service_user_id > 0 ) {
				wp_set_current_user( $service_user_id );
			}

			return true;
		}

		if ( McpHardening::is_auth_rate_limited() ) {
			return new \WP_Error(
				'wp_mcp_rate_limited',
				__( 'Too many failed authentication attempts. Try again later.', 'wp-mcp-plugin' ),
				array( 'status' => 429 )
			);
		}

		if ( '' === $provided_key ) {
			return new \WP_Error(
				'wp_mcp_missing_key',
				__( 'Missing API key. Send X-WP-MCP-Key or Authorization: Bearer <key>.', 'wp-mcp-plugin' ),
				array( 'status' => 401 )
			);
		}

		McpHardening::record_auth_failure();

		return new \WP_Error(
			'wp_mcp_invalid_key',
			__( 'Invalid API key.', 'wp-mcp-plugin' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Permission callback for MCP abilities (tools and resources).
	 *
	 * Abilities run only after transport auth has set a service user.
	 *
	 * @return bool
	 */
	public static function check_ability_permission(): bool {
		return get_current_user_id() > 0;
	}

	/**
	 * Extract the API key from supported request headers.
	 *
	 * Supports X-WP-MCP-Key (documented) and Authorization: Bearer (common
	 * across MCP clients that expect OAuth-style transport auth).
	 *
	 * @param \WP_REST_Request|null $request The incoming REST request.
	 * @return string Plaintext key, or empty string when absent.
	 */
	private function extract_key_from_request( $request ): string {
		if ( ! $request instanceof \WP_REST_Request ) {
			return '';
		}

		$header_key = $request->get_header( 'X-WP-MCP-Key' );
		if ( is_string( $header_key ) && '' !== $header_key ) {
			return trim( $header_key );
		}

		$authorization = $request->get_header( 'Authorization' );
		if ( is_string( $authorization ) && preg_match( '/^Bearer\s+(.+)$/i', $authorization, $matches ) ) {
			return trim( $matches[1] );
		}

		return '';
	}

	/**
	 * Resolve the WordPress user ID for MCP tool execution.
	 *
	 * Filterable via `wp_mcp_authenticated_user_id`. Defaults to the first
	 * administrator (required for manage_options tools like global settings).
	 *
	 * @return int User ID, or 0 if no suitable user found.
	 */
	private function get_service_user_id(): int {
		/**
		 * Filter the WordPress user ID used for MCP tool execution.
		 *
		 * The user must have the capabilities required by the enabled tools
		 * (at minimum edit_pages; manage_options for global settings tools).
		 *
		 * @param int $user_id Default 0 — resolved to first administrator below.
		 */
		$user_id = (int) apply_filters( 'wp_mcp_authenticated_user_id', 0 );

		if ( $user_id > 0 && get_userdata( $user_id ) ) {
			return $user_id;
		}

		return $this->get_default_admin_user_id();
	}

	/**
	 * Find the first administrator user ID for MCP authentication.
	 *
	 * @return int User ID, or 0 if no admin found.
	 */
	private function get_default_admin_user_id(): int {
		$users = get_users(
			array(
				'role'    => 'administrator',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'ID',
			)
		);

		if ( ! empty( $users ) ) {
			return (int) $users[0];
		}

		return 0;
	}
}
