<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP transport hardening: security headers, schema validation, auth hints.
 *
 * Complements the vendored mcp-adapter transport with plugin-specific
 * protections that apply across MCP clients (Cursor, OpenCode, Claude, etc.).
 */
class McpHardening {

	/** MCP REST route prefix (namespace + route). */
	private const MCP_ROUTE_PREFIX = '/wp-mcp/mcp';

	/** Maximum failed API-key attempts per IP before temporary lockout. */
	private const MAX_AUTH_FAILURES = 10;

	/** Lockout window in seconds after too many failed attempts. */
	private const AUTH_LOCKOUT_TTL = 15 * MINUTE_IN_SECONDS;

	public function register(): void {
		add_filter( 'mcp_adapter_validation_enabled', array( $this, 'enable_schema_validation' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_security_headers' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( $this, 'add_auth_challenge_header' ), 10, 3 );
	}

	/**
	 * Enable MCP schema validation for tool/resource DTOs at registration time.
	 *
	 * Catches malformed schemas before they reach clients — important for
	 * agents that strictly validate tools/list responses.
	 *
	 * @param bool                    $enabled   Current value.
	 * @param string                  $server_id Server identifier (omitted on per-tool calls).
	 * @param \WP\MCP\Core\McpServer|null $server    Server instance (omitted on per-tool calls).
	 * @return bool
	 */
	public function enable_schema_validation( bool $enabled, string $server_id = '', $server = null ): bool {
		// McpServer passes server_id; per-tool/resource registration passes only $enabled.
		if ( '' !== $server_id && 'wp-mcp' !== $server_id ) {
			return $enabled;
		}

		return true;
	}

	/**
	 * Add security headers to MCP REST responses.
	 *
	 * @param \WP_REST_Response        $response Response object.
	 * @param \WP_REST_Server          $server   REST server.
	 * @param \WP_REST_Request         $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function add_security_headers( $response, $server, $request ) {
		if ( ! $this->is_mcp_request( $request ) ) {
			return $response;
		}

		if ( ! $response instanceof \WP_REST_Response ) {
			return $response;
		}

		$response->header( 'X-Content-Type-Options', 'nosniff' );
		$response->header( 'X-Frame-Options', 'DENY' );
		$response->header( 'Referrer-Policy', 'no-referrer' );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );

		return $response;
	}

	/**
	 * Add WWW-Authenticate on 401 so OAuth-aware MCP clients can surface auth hints.
	 *
	 * @param \WP_REST_Response $response Response object.
	 * @param \WP_REST_Server   $server   REST server.
	 * @param \WP_REST_Request  $request  Request object.
	 * @return \WP_REST_Response
	 */
	public function add_auth_challenge_header( $response, $server, $request ) {
		if ( ! $this->is_mcp_request( $request ) ) {
			return $response;
		}

		if ( ! $response instanceof \WP_REST_Response || 401 !== $response->get_status() ) {
			return $response;
		}

		$response->header(
			'WWW-Authenticate',
			'Bearer realm="WP MCP", charset="UTF-8", error="invalid_token", error_description="Provide a valid API key via X-WP-MCP-Key or Authorization: Bearer"'
		);

		return $response;
	}

	/**
	 * Check whether a REST request targets the MCP endpoint.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public static function is_mcp_request( $request ): bool {
		if ( ! $request instanceof \WP_REST_Request ) {
			return false;
		}

		$route = $request->get_route();

		return is_string( $route ) && str_starts_with( $route, self::MCP_ROUTE_PREFIX );
	}

	/**
	 * Check whether the client IP is temporarily locked out after failed auth.
	 *
	 * @return bool True when further attempts should be rejected.
	 */
	public static function is_auth_rate_limited(): bool {
		$attempts = (int) get_transient( self::get_rate_limit_transient_key() );

		return $attempts >= self::MAX_AUTH_FAILURES;
	}

	/**
	 * Record a failed API-key authentication attempt.
	 */
	public static function record_auth_failure(): void {
		$key      = self::get_rate_limit_transient_key();
		$attempts = (int) get_transient( $key );
		++$attempts;

		set_transient( $key, $attempts, self::AUTH_LOCKOUT_TTL );
	}

	/**
	 * Clear the rate-limit counter after a successful authentication.
	 */
	public static function clear_auth_failures(): void {
		delete_transient( self::get_rate_limit_transient_key() );
	}

	/**
	 * @return string Transient key scoped to the client IP.
	 */
	private static function get_rate_limit_transient_key(): string {
		return 'wp_mcp_auth_fail_' . md5( self::get_client_ip() );
	}

	/**
	 * Best-effort client IP for rate limiting (respects common proxy headers).
	 *
	 * @return string
	 */
	private static function get_client_ip(): string {
		$candidates = array(
			'HTTP_CF_CONNECTING_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $candidates as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$raw = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

			// X-Forwarded-For may be a comma-separated list; use the first hop.
			if ( str_contains( $raw, ',' ) ) {
				$raw = trim( explode( ',', $raw )[0] );
			}

			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}

		return '0.0.0.0';
	}
}
