<?php

namespace WpMcp;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level WordPress admin page for plugin settings.
 *
 * Provides:
 * - API key generation / regeneration
 * - Tool visibility toggles
 * - Server enable/disable
 * - Admin bar status indicator
 */
class AdminPage {

	private const PAGE_SLUG   = 'wp-mcp-plugin';
	private const OPTION_GROUP = 'wp_mcp_settings';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_wp_mcp_generate_key', array( $this, 'handle_generate_key' ) );
		add_action( 'admin_post_wp_mcp_toggle_server', array( $this, 'handle_toggle_server' ) );
	}

	public function add_menu(): void {
		add_menu_page(
			__( 'WP MCP Plugin', 'wp-mcp-plugin' ),
			__( 'WP MCP', 'wp-mcp-plugin' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-rest-api',
			30
		);
	}

	public function register_settings(): void {
		register_setting( self::OPTION_GROUP, 'wp_mcp_enabled_tools', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_enabled_tools' ),
			'default'           => array(),
		) );
	}

	public function sanitize_enabled_tools( $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}
		$all_tools = $this->get_all_tool_slugs();
		return array_values( array_intersect( $input, $all_tools ) );
	}

	public function handle_generate_key(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-mcp-plugin' ) );
		}

		check_admin_referer( 'wp_mcp_generate_key', 'wp_mcp_nonce' );

		$api_key = new ApiKey();
		$key     = $api_key->generate();
		$api_key->store( $key );

		wp_safe_redirect( add_query_arg( 'key_generated', '1', $this->get_page_url() ) );
		exit;
	}

	public function handle_toggle_server(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-mcp-plugin' ) );
		}

		check_admin_referer( 'wp_mcp_toggle_server', 'wp_mcp_nonce' );

		$current = (bool) get_option( 'wp_mcp_server_enabled', true );
		update_option( 'wp_mcp_server_enabled', ! $current );

		wp_safe_redirect( $this->get_page_url() );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-mcp-plugin' ) );
		}

		$api_key        = new ApiKey();
		$key_configured = $api_key->is_configured();
		$server_enabled = (bool) get_option( 'wp_mcp_server_enabled', true );
		$enabled_tools  = get_option( 'wp_mcp_enabled_tools', $this->get_all_tool_slugs() );
		$show_key       = isset( $_GET['key_generated'] );
		$new_key        = $api_key->get_once();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $show_key && $new_key ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'API Key generated!', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'Copy this key now — it will not be shown again.', 'wp-mcp-plugin' ); ?>
					</p>
					<p>
						<code style="font-size:14px;word-break:break-all;"><?php echo esc_html( $new_key ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<div class="wp-mcp-cards" style="max-width:800px;">

				<!-- Server Status Card -->
				<div class="card" style="margin-bottom:20px;padding:0 20px 20px;">
					<h2><?php esc_html_e( 'Server Status', 'wp-mcp-plugin' ); ?></h2>
					<p>
						<strong><?php esc_html_e( 'Status:', 'wp-mcp-plugin' ); ?></strong>
						<?php if ( $server_enabled ) : ?>
							<span style="color:#46b450;">&#9679; <?php esc_html_e( 'Enabled', 'wp-mcp-plugin' ); ?></span>
						<?php else : ?>
							<span style="color:#dc3232;">&#9679; <?php esc_html_e( 'Disabled', 'wp-mcp-plugin' ); ?></span>
						<?php endif; ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Endpoint:', 'wp-mcp-plugin' ); ?></strong>
						<code><?php echo esc_html( rest_url( 'wp-mcp/mcp' ) ); ?></code>
					</p>
					<p>
						<strong><?php esc_html_e( 'API Key:', 'wp-mcp-plugin' ); ?></strong>
						<?php if ( $key_configured ) : ?>
							<span style="color:#46b450;">&#9679; <?php esc_html_e( 'Configured', 'wp-mcp-plugin' ); ?></span>
						<?php else : ?>
							<span style="color:#dc3232;">&#9679; <?php esc_html_e( 'Not configured', 'wp-mcp-plugin' ); ?></span>
						<?php endif; ?>
					</p>

					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="wp_mcp_toggle_server">
						<?php wp_nonce_field( 'wp_mcp_toggle_server', 'wp_mcp_nonce' ); ?>
						<?php if ( $server_enabled ) : ?>
							<button type="submit" class="button">
								<?php esc_html_e( 'Disable Server', 'wp-mcp-plugin' ); ?>
							</button>
						<?php else : ?>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Enable Server', 'wp-mcp-plugin' ); ?>
							</button>
						<?php endif; ?>
					</form>
				</div>

				<!-- API Key Card -->
				<div class="card" style="margin-bottom:20px;padding:0 20px 20px;">
					<h2><?php esc_html_e( 'API Key', 'wp-mcp-plugin' ); ?></h2>
					<p>
						<?php esc_html_e( 'Generate an API key for MCP clients to authenticate. The key is shown only once — save it in your MCP client configuration.', 'wp-mcp-plugin' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'How to use:', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'Add the HTTP header', 'wp-mcp-plugin' ); ?>
						<code>X-WP-MCP-Key: YOUR_KEY</code>
						<?php esc_html_e( 'to every MCP request.', 'wp-mcp-plugin' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="wp_mcp_generate_key">
						<?php wp_nonce_field( 'wp_mcp_generate_key', 'wp_mcp_nonce' ); ?>
						<button type="submit" class="button">
							<?php echo $key_configured
								? esc_html__( 'Regenerate API Key', 'wp-mcp-plugin' )
								: esc_html__( 'Generate API Key', 'wp-mcp-plugin' ); ?>
						</button>
					</form>
				</div>

				<!-- Tool Toggles Card -->
				<div class="card" style="margin-bottom:20px;padding:0 20px 20px;">
					<h2><?php esc_html_e( 'Enabled Tools', 'wp-mcp-plugin' ); ?></h2>
					<p>
						<?php esc_html_e( 'Select which MCP tools are exposed to clients. Disable tools you do not need.', 'wp-mcp-plugin' ); ?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
						<?php
						settings_fields( self::OPTION_GROUP );
						$all_tools = $this->get_all_tool_groups();
						?>
						<table class="form-table">
							<tbody>
							<?php foreach ( $all_tools as $group_label => $tools ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $group_label ); ?></th>
									<td>
										<?php foreach ( $tools as $slug => $label ) : ?>
											<label style="display:block;margin-bottom:4px;">
												<input type="checkbox"
													name="wp_mcp_enabled_tools[]"
													value="<?php echo esc_attr( $slug ); ?>"
													<?php checked( in_array( $slug, $enabled_tools, true ) ); ?>
												>
												<code><?php echo esc_html( $slug ); ?></code>
												— <?php echo esc_html( $label ); ?>
											</label>
										<?php endforeach; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<?php submit_button(); ?>
					</form>
				</div>

				<!-- Visual Comparison Workflow Card -->
				<div class="card" style="margin-bottom:20px;padding:0 20px 20px;">
					<h2><?php esc_html_e( 'Visual Comparison Workflow', 'wp-mcp-plugin' ); ?></h2>
					<p>
						<?php esc_html_e( 'Use the', 'wp-mcp-plugin' ); ?>
						<code>wp-mcp/render-page</code>
						<?php esc_html_e( 'tool to generate authenticated preview URLs for draft and private Elementor pages. This enables visual comparison with Figma designs via headless browser screenshots.', 'wp-mcp-plugin' ); ?>
					</p>
					<h3><?php esc_html_e( 'Typical workflow', 'wp-mcp-plugin' ); ?></h3>
					<ol style="margin-left:20px;max-width:700px;">
						<li><?php esc_html_e( 'Build or update an Elementor page using the plugin\'s write tools.', 'wp-mcp-plugin' ); ?></li>
						<li><?php esc_html_e( 'Call', 'wp-mcp-plugin' ); ?> <code>wp-mcp/render-page</code> <?php esc_html_e( 'to get a time-limited preview URL and Elementor element selectors.', 'wp-mcp-plugin' ); ?></li>
						<li><?php esc_html_e( 'Open the preview URL in Playwright or Puppeteer and take a screenshot.', 'wp-mcp-plugin' ); ?></li>
						<li><?php esc_html_e( 'Compare the screenshot against the Figma design using visual diffing or model-based comparison.', 'wp-mcp-plugin' ); ?></li>
						<li><?php esc_html_e( 'Fix any layout differences and repeat up to 3 times for best results.', 'wp-mcp-plugin' ); ?></li>
					</ol>
					<p style="color:#666;font-style:italic;">
						<?php esc_html_e( 'The preview token is short-lived (default 5 minutes) and scoped to a single page. Do NOT share preview URLs — they grant read access to draft/private pages.', 'wp-mcp-plugin' ); ?>
					</p>
				</div>

			</div>
		</div>
		<?php
	}

	/**
	 * Add a node to the WordPress admin bar showing MCP status.
	 *
	 * @param \WP_Admin_Bar $admin_bar
	 */
	public function add_admin_bar_node( $admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$server_enabled = (bool) get_option( 'wp_mcp_server_enabled', true );
		if ( ! $server_enabled ) {
			return;
		}

		$api_key    = new ApiKey();
		$has_key    = $api_key->is_configured();
		$status     = $has_key
			? __( 'Active', 'wp-mcp-plugin' )
			: __( 'No Key', 'wp-mcp-plugin' );
		$color      = $has_key ? '#46b450' : '#f0ad4e';

		$admin_bar->add_node( array(
			'id'    => 'wp-mcp-status',
			'title' => sprintf(
				'<span style="color:%s;">&#9679;</span> MCP: %s',
				esc_attr( $color ),
				esc_html( $status )
			),
			'href'  => $this->get_page_url(),
			'meta'  => array(
				'title' => $has_key
					? __( 'MCP server is active with API key configured.', 'wp-mcp-plugin' )
					: __( 'MCP server is active but no API key is configured.', 'wp-mcp-plugin' ),
			),
		) );
	}

	/**
	 * Get the URL to this plugin's admin page.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * Get all valid tool slugs.
	 *
	 * @return string[]
	 */
	private function get_all_tool_slugs(): array {
		return array_keys( $this->get_all_tools_flat() );
	}

	/**
	 * Get all tools as slug => label pairs (flat).
	 *
	 * @return array<string, string>
	 */
	private function get_all_tools_flat(): array {
		$tools = array();
		foreach ( $this->get_all_tool_groups() as $group ) {
			$tools = array_merge( $tools, $group );
		}
		return $tools;
	}

	/**
	 * Get all tools grouped by category for the settings UI.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_all_tool_groups(): array {
		return array(
			__( 'Read Tools', 'wp-mcp-plugin' )       => array(
				'wp-mcp/list-pages'                => __( 'List all pages', 'wp-mcp-plugin' ),
				'wp-mcp/get-page'                  => __( 'Get page details and content', 'wp-mcp-plugin' ),
				'wp-mcp/list-elementor-widgets'    => __( 'List available widgets', 'wp-mcp-plugin' ),
				'wp-mcp/get-elementor-widget-schema' => __( 'Get widget schema', 'wp-mcp-plugin' ),
				'wp-mcp/list-elementor-templates'  => __( 'List saved templates', 'wp-mcp-plugin' ),
				'wp-mcp/get-elementor-global-settings' => __( 'Get global design settings', 'wp-mcp-plugin' ),
				'wp-mcp/render-page'             => __( 'Preview page for visual comparison', 'wp-mcp-plugin' ),
			),
			__( 'Write Tools', 'wp-mcp-plugin' )      => array(
				'wp-mcp/create-page'                 => __( 'Create a new page', 'wp-mcp-plugin' ),
				'wp-mcp/update-page-elementor-data'  => __( 'Replace page Elementor data', 'wp-mcp-plugin' ),
				'wp-mcp/delete-page'                 => __( 'Delete a page', 'wp-mcp-plugin' ),
				'wp-mcp/add-container'               => __( 'Add a container', 'wp-mcp-plugin' ),
				'wp-mcp/add-widget'                  => __( 'Add a widget', 'wp-mcp-plugin' ),
				'wp-mcp/update-element'              => __( 'Update element settings', 'wp-mcp-plugin' ),
				'wp-mcp/remove-element'              => __( 'Remove an element', 'wp-mcp-plugin' ),
				'wp-mcp/batch-update'               => __( 'Batch update elements', 'wp-mcp-plugin' ),
				'wp-mcp/update-elementor-global-settings' => __( 'Update global design settings', 'wp-mcp-plugin' ),
			),
			__( 'Diagnostics', 'wp-mcp-plugin' )      => array(
				'wp-mcp/get-plugin-status' => __( 'Get plugin status and debug info', 'wp-mcp-plugin' ),
			),
		);
	}
}
