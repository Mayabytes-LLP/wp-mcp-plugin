<?php

namespace WpMcp;

use WP\MCP\Infrastructure\ErrorHandling\McpErrorFactory;
use WP\MCP\Transport\HttpTransport;
use WP\MCP\Transport\Infrastructure\HttpRequestContext;
use WP\MCP\Transport\Infrastructure\HttpSessionValidator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTTP transport with SSE (GET) support for streamable-http MCP clients (Cursor, etc.).
 *
 * The vendored mcp-adapter HttpTransport returns 405 on GET because SSE is not
 * implemented upstream yet. Cursor's streamable-http transport opens GET after
 * initialize and treats 405 as connect_failure.
 */
class HttpTransportSse extends HttpTransport {

	/**
	 * Preserve WP_Error status codes from the transport permission callback.
	 *
	 * The vendored HttpTransport logs WP_Error and returns false, which WordPress
	 * maps to a generic 401 rest_forbidden and drops 429 rate-limit responses.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return bool|\WP_Error
	 */
	public function check_permission( \WP_REST_Request $request ) {
		$context           = new HttpRequestContext( $request );
		$transport_context = $this->request_handler->get_transport_context();

		if ( null === $transport_context->transport_permission_callback ) {
			return parent::check_permission( $request );
		}

		try {
			$result = call_user_func( $transport_context->transport_permission_callback, $context->request );

			if ( is_wp_error( $result ) ) {
				$transport_context->error_handler->log(
					'Permission callback returned WP_Error: ' . $result->get_error_message(),
					array( 'HttpTransportSse::check_permission' )
				);

				return $result;
			}

			return (bool) $result;
		} catch ( \Throwable $e ) {
			$transport_context->error_handler->log(
				'Error in transport permission callback: ' . $e->getMessage(),
				array( 'HttpTransportSse::check_permission' )
			);

			return false;
		}
	}

	/**
	 * @param \WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return \WP_REST_Response
	 */
	public function handle_request( \WP_REST_Request $request ): \WP_REST_Response {
		if ( 'GET' === $request->get_method() ) {
			return $this->handle_sse_stream( $request );
		}

		return parent::handle_request( $request );
	}

	/**
	 * Open an SSE stream for server-to-client messages (streamable HTTP).
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request The request object.
	 * @return \WP_REST_Response
	 */
	private function handle_sse_stream( \WP_REST_Request $request ): \WP_REST_Response {
		$context    = new HttpRequestContext( $request );
		$validation = HttpSessionValidator::validate_session( $context );

		if ( true !== $validation ) {
			$http_status = McpErrorFactory::get_http_status_for_error( $validation );

			return new \WP_REST_Response( $validation, $http_status );
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		status_header( 200 );
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Connection: close' );
		header( 'X-Accel-Buffering: no' );

		// Handshake only: satisfy streamable-http GET, then release the PHP-FPM worker.
		// Local ships pm.max_children=2; blocking sleep loops exhaust the pool when
		// Agents + Editor each hold an SSE connection.
		echo ": connected\n\n";
		if ( function_exists( 'flush' ) ) {
			flush();
		}

		exit;
	}
}
