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
	 * Reads the X-WP-MCP-Key header and validates it against the stored hash.
	 *
	 * @param \WP_REST_Request|null $request The incoming REST request.
	 * @return bool|\WP_Error True if authenticated, WP_Error on failure.
	 */
	public function verify_transport_permission( $request = null ) {
		if ( ! $this->is_configured() ) {
			return new \WP_Error(
				'wp_mcp_no_key',
				__( 'MCP server has no API key configured.', 'wp-mcp-plugin' )
			);
		}

		$provided_key = '';

		if ( $request instanceof \WP_REST_Request ) {
			$provided_key = $request->get_header( 'X-WP-MCP-Key' );
		}

		if ( empty( $provided_key ) ) {
			return new \WP_Error(
				'wp_mcp_missing_key',
				__( 'Missing X-WP-MCP-Key header.', 'wp-mcp-plugin' )
			);
		}

		if ( ! $this->verify( $provided_key ) ) {
			return new \WP_Error(
				'wp_mcp_invalid_key',
				__( 'Invalid API key.', 'wp-mcp-plugin' )
			);
		}

		return true;
	}
}
