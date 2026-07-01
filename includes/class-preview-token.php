<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Preview token generation and validation for draft/private page access.
 *
 * Generates short-lived, HMAC-signed tokens that grant read-only access
 * to a specific page. Designed for headless browser screenshot tools
 * (Playwright, Puppeteer) that need to capture draft/private Elementor
 * pages without custom auth headers.
 *
 * Security model:
 * - Token signed with a dedicated `wp_mcp_preview_key` option (HMAC-SHA256).
 * - Token is scoped to a single post_id (cross-page reuse prevented).
 * - Surgical capability grants via `user_has_cap` — NOT full admin
 *   impersonation. Grants only `read_post`, `read_private_pages`,
 *   and (for drafts) `edit_posts`/`edit_pages`.
 * - Admin bar suppressed via `show_admin_bar` filter.
 * - Cache-control headers + `DONOTCACHEPAGE` prevent caching.
 * - `Referrer-Policy: no-referrer` prevents token leakage via Referer.
 */
class PreviewToken {

	/**
	 * Generate a time-limited preview token for a post.
	 *
	 * @param int $post_id The post ID.
	 * @param int $ttl     Token lifetime in seconds (max 600).
	 * @return string Base64url-encoded token.
	 */
	public static function generate( int $post_id, int $ttl ): string {
		$ttl       = min( $ttl, 600 );
		$expiry    = time() + $ttl;
		$payload   = $post_id . '|' . $expiry;
		$signature = hash_hmac( 'sha256', $payload, self::get_signing_key() );

		return self::base64url_encode( $payload . '|' . $signature );
	}

	/**
	 * Build the full preview URL with token query parameter.
	 *
	 * @param int $post_id The post ID.
	 * @param int $ttl     Token lifetime in seconds.
	 * @return string Full preview URL.
	 */
	public static function build_url( int $post_id, int $ttl ): string {
		$token = self::generate( $post_id, $ttl );
		$url   = get_permalink( $post_id );

		if ( ! $url ) {
			$url = home_url( '/?p=' . $post_id );
		}

		return add_query_arg( 'wp_mcp_preview_token', $token, $url );
	}

	/**
	 * Validate the preview token and grant read access if valid.
	 *
	 * Hooked to `pre_get_posts` at priority 1 so we can modify the query
	 * BEFORE WP_Query filters out draft/private posts. This is critical —
	 * `template_redirect` is too late.
	 *
	 * @param \WP_Query $query The current query.
	 */
	public static function maybe_grant_access( \WP_Query $query ): void {
		if ( ! isset( $_GET['wp_mcp_preview_token'] ) ) {
			return;
		}

		// Bail in non-front-end request contexts.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		if ( ! $query->is_main_query() || ! $query->is_singular() ) {
			return;
		}

		$token   = sanitize_text_field( wp_unslash( $_GET['wp_mcp_preview_token'] ) );
		$decoded = self::base64url_decode( $token );

		if ( false === $decoded ) {
			return;
		}

		$parts = explode( '|', $decoded, 3 );
		if ( 3 !== count( $parts ) ) {
			return;
		}

		list( $post_id, $expiry, $signature ) = $parts;
		$post_id = (int) $post_id;
		$expiry  = (int) $expiry;

		// Verify expiry.
		if ( time() > $expiry ) {
			return;
		}

		// Verify HMAC signature (constant-time).
		$expected_sig = hash_hmac( 'sha256', $post_id . '|' . $expiry, self::get_signing_key() );
		if ( ! hash_equals( $expected_sig, $signature ) ) {
			return;
		}

		// Verify the post exists and is a page.
		$post = get_post( $post_id );
		if ( ! $post || 'page' !== $post->post_type ) {
			return;
		}

		// ── Grant surgical read access ────────────────────────────
		// Do NOT call wp_set_current_user() — that grants full admin
		// for the entire request. Instead, use user_has_cap filter
		// to grant only the capabilities needed to read this page.
		// The filter is removed on shutdown so the cap grants persist
		// through query execution and template rendering, but don't
		// outlive the request.

		$cap_filter = function ( $allcaps ) use ( $post ) {
			$allcaps['read_post']          = true;
			$allcaps['read_private_pages'] = true;

			// Draft and pending posts need edit capabilities
			// for WP_Query to include them in results.
			if ( 'draft' === $post->post_status || 'pending' === $post->post_status ) {
				$allcaps['edit_posts']        = true;
				$allcaps['edit_pages']        = true;
				$allcaps['edit_others_pages'] = true;
			}

			return $allcaps;
		};

		add_filter( 'user_has_cap', $cap_filter );

		// Suppress admin bar (it would render if user somehow had caps).
		add_filter( 'show_admin_bar', '__return_false' );

		// Force the query to fetch exactly the token's post_id.
		// This is critical: at pre_get_posts, get_queried_object_id()
		// returns 0 for draft pages because get_page_by_path() only
		// finds published posts. By setting 'p' directly, we bypass
		// URL-based resolution and let WP_Query find the draft/private
		// post by ID. The token's HMAC signature already scopes access
		// to this single post, so this is safe.
		$query->set( 'p', $post_id );
		$query->set( 'post_type', 'page' );
		$query->set( 'name', '' );
		$query->set( 'pagename', '' );
		$query->set( 'post_status', array( 'publish', 'draft', 'private', 'pending' ) );
		$query->set( 'suppress_filters', false );

		// Remove the cap filter on shutdown so the grants persist
		// through query execution and template rendering, but
		// don't outlive the request.
		add_action(
			'shutdown',
			function () use ( $cap_filter ) {
				remove_filter( 'user_has_cap', $cap_filter );
			}
		);

		// ── Prevent caching of the privileged render ─────────────
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		header( 'Cache-Control: no-store, no-cache, must-revalidate, private' );
		header( 'Referrer-Policy: no-referrer' );

		// Prevent token leakage: remove from $_GET so it doesn't appear
		// in rendered page content, canonical URLs, or resource URLs.
		unset( $_GET['wp_mcp_preview_token'] );
		if ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$_SERVER['REQUEST_URI'] = remove_query_arg( 'wp_mcp_preview_token', $_SERVER['REQUEST_URI'] );
		}
	}

	/**
	 * Get the HMAC signing key from options, generating one if absent.
	 *
	 * This is a dedicated key, separate from the API key, so:
	 * - It survives salt rotation.
	 * - It can be rotated independently to revoke all tokens.
	 * - It's self-documenting in the options table.
	 *
	 * @return string The signing key.
	 */
	private static function get_signing_key(): string {
		$key = get_option( 'wp_mcp_preview_key', '' );

		if ( empty( $key ) ) {
			$key = wp_generate_password( 64, true, true );
			update_option( 'wp_mcp_preview_key', $key );
		}

		return $key;
	}

	/**
	 * Base64url encode (URL-safe, no padding).
	 *
	 * @param string $data Raw data to encode.
	 * @return string Base64url-encoded string.
	 */
	private static function base64url_encode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Base64url decode.
	 *
	 * @param string $data Base64url-encoded string.
	 * @return string|false Decoded string or false on failure.
	 */
	private static function base64url_decode( string $data ): string|false {
		$decoded = base64_decode( strtr( $data, '-_', '+/' ), true );
		return $decoded;
	}
}
