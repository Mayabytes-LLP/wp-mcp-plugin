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
		add_action( 'admin_post_wp_mcp_enable_all_tools', array( $this, 'handle_enable_all_tools' ) );
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
		$all_tools = EnabledTools::get_registered_slugs();
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

	public function handle_enable_all_tools(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-mcp-plugin' ) );
		}

		check_admin_referer( 'wp_mcp_enable_all_tools', 'wp_mcp_nonce' );

		EnabledTools::enable_all();

		wp_safe_redirect( add_query_arg( 'tools_enabled_all', '1', $this->get_page_url() ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'wp-mcp-plugin' ) );
		}

		$api_key        = new ApiKey();
		$key_configured = $api_key->is_configured();
		$server_enabled = (bool) get_option( 'wp_mcp_server_enabled', true );
		$synced_tools   = EnabledTools::sync();
		$registered     = EnabledTools::get_registered_slugs();
		$enabled_tools  = EnabledTools::get_enabled();
		$total_tools    = count( $registered );
		$enabled_count  = count( $enabled_tools );
		$all_enabled    = ( $enabled_count === $total_tools );
		$drift          = EnabledTools::get_registry_drift();
		$endpoint       = rest_url( 'wp-mcp/mcp' );
		$show_key       = isset( $_GET['key_generated'] );
		$new_key        = $api_key->get_once();
		?>
		<div class="wrap wp-mcp-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( $show_key && $new_key ) : ?>
				<div class="notice notice-success is-dismissible">
					<p>
						<strong><?php esc_html_e( 'API Key generated!', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'Copy this key now — it will not be shown again.', 'wp-mcp-plugin' ); ?>
					</p>
					<p>
						<code class="wp-mcp-api-key-display"><?php echo esc_html( $new_key ); ?></code>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( isset( $_GET['tools_enabled_all'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'All MCP tools are now enabled.', 'wp-mcp-plugin' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $synced_tools ) ) : ?>
				<div class="notice notice-info is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %d: number of tools */
							esc_html( _n(
								'%d new MCP tool was automatically enabled.',
								'%d new MCP tools were automatically enabled.',
								count( $synced_tools ),
								'wp-mcp-plugin'
							) ),
							count( $synced_tools )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! $all_enabled ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php
						printf(
							/* translators: 1: enabled count, 2: total count */
							esc_html__( '%1$d of %2$d MCP tools are enabled. Disabled tools are hidden from MCP clients.', 'wp-mcp-plugin' ),
							$enabled_count,
							$total_tools
						);
						?>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_mcp_enable_all_tools' ), 'wp_mcp_enable_all_tools', 'wp_mcp_nonce' ) ); ?>" class="button button-secondary" style="margin-left:8px;vertical-align:baseline;">
							<?php esc_html_e( 'Enable all tools', 'wp-mcp-plugin' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $drift['unlisted'] ) || ! empty( $drift['orphaned'] ) ) : ?>
				<div class="notice notice-error">
					<p><strong><?php esc_html_e( 'Tool registry mismatch detected.', 'wp-mcp-plugin' ); ?></strong></p>
					<?php if ( ! empty( $drift['unlisted'] ) ) : ?>
						<p><?php esc_html_e( 'Registered but missing from admin UI:', 'wp-mcp-plugin' ); ?> <code><?php echo esc_html( implode( ', ', $drift['unlisted'] ) ); ?></code></p>
					<?php endif; ?>
					<?php if ( ! empty( $drift['orphaned'] ) ) : ?>
						<p><?php esc_html_e( 'Listed in admin UI but not registered:', 'wp-mcp-plugin' ); ?> <code><?php echo esc_html( implode( ', ', $drift['orphaned'] ) ); ?></code></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">

						<div class="postbox">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'MCP Server', 'wp-mcp-plugin' ); ?></h2>
							</div>
							<div class="inside">
								<table class="form-table" role="presentation">
									<tbody>
									<tr>
										<th scope="row"><?php esc_html_e( 'Status', 'wp-mcp-plugin' ); ?></th>
										<td>
											<?php if ( $server_enabled ) : ?>
												<span class="wp-mcp-status wp-mcp-status--on"><?php esc_html_e( 'Enabled', 'wp-mcp-plugin' ); ?></span>
											<?php else : ?>
												<span class="wp-mcp-status wp-mcp-status--off"><?php esc_html_e( 'Disabled', 'wp-mcp-plugin' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Endpoint', 'wp-mcp-plugin' ); ?></th>
										<td><code class="wp-mcp-endpoint"><?php echo esc_html( $endpoint ); ?></code></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Tools exposed', 'wp-mcp-plugin' ); ?></th>
										<td>
											<strong><?php echo esc_html( (string) $enabled_count ); ?></strong>
											<?php esc_html_e( 'of', 'wp-mcp-plugin' ); ?>
											<strong><?php echo esc_html( (string) $total_tools ); ?></strong>
											<?php if ( ! $all_enabled ) : ?>
												<span class="description"> — <?php esc_html_e( 'some tools are disabled', 'wp-mcp-plugin' ); ?></span>
											<?php endif; ?>
										</td>
									</tr>
									</tbody>
								</table>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
									<input type="hidden" name="action" value="wp_mcp_toggle_server">
									<?php wp_nonce_field( 'wp_mcp_toggle_server', 'wp_mcp_nonce' ); ?>
									<?php if ( $server_enabled ) : ?>
										<button type="submit" class="button"><?php esc_html_e( 'Disable Server', 'wp-mcp-plugin' ); ?></button>
									<?php else : ?>
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Enable Server', 'wp-mcp-plugin' ); ?></button>
									<?php endif; ?>
								</form>
							</div>
						</div>

						<div class="postbox">
							<div class="postbox-header">
								<h2 class="hndle">
									<?php
									printf(
										/* translators: 1: enabled count, 2: total count */
										esc_html__( 'Enabled Tools (%1$d / %2$d)', 'wp-mcp-plugin' ),
										$enabled_count,
										$total_tools
									);
									?>
								</h2>
							</div>
							<div class="inside">
								<p class="description">
									<?php esc_html_e( 'Choose which MCP tools are exposed to clients. New tools are enabled automatically when the plugin is updated.', 'wp-mcp-plugin' ); ?>
								</p>
								<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
									<?php
									settings_fields( self::OPTION_GROUP );
									$all_tools = ToolRegistry::get_groups();
									?>
									<table class="widefat striped wp-mcp-tools-table">
										<thead>
										<tr>
											<th scope="col" class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'Enabled', 'wp-mcp-plugin' ); ?></span></th>
											<th scope="col"><?php esc_html_e( 'Tool', 'wp-mcp-plugin' ); ?></th>
											<th scope="col"><?php esc_html_e( 'Description', 'wp-mcp-plugin' ); ?></th>
										</tr>
										</thead>
										<tbody>
										<?php foreach ( $all_tools as $group_label => $tools ) : ?>
											<tr class="wp-mcp-tools-group">
												<td colspan="3"><strong><?php echo esc_html( $group_label ); ?></strong></td>
											</tr>
											<?php foreach ( $tools as $slug => $label ) : ?>
												<?php
												$is_registered = in_array( $slug, $registered, true );
												$is_enabled    = in_array( $slug, $enabled_tools, true );
												?>
												<tr<?php echo $is_registered ? '' : ' class="wp-mcp-tools-row--orphaned"'; ?>>
													<th scope="row" class="check-column">
														<?php if ( $is_registered ) : ?>
															<input type="checkbox"
																id="wp-mcp-tool-<?php echo esc_attr( sanitize_title( $slug ) ); ?>"
																name="wp_mcp_enabled_tools[]"
																value="<?php echo esc_attr( $slug ); ?>"
																<?php checked( $is_enabled ); ?>
															>
														<?php endif; ?>
													</th>
													<td>
														<label for="wp-mcp-tool-<?php echo esc_attr( sanitize_title( $slug ) ); ?>">
															<code><?php echo esc_html( str_replace( 'wp-mcp/', '', $slug ) ); ?></code>
														</label>
													</td>
													<td><?php echo esc_html( $label ); ?></td>
												</tr>
											<?php endforeach; ?>
										<?php endforeach; ?>
										</tbody>
									</table>
									<p class="submit">
										<?php submit_button( __( 'Save Tool Settings', 'wp-mcp-plugin' ), 'primary', 'submit', false ); ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wp_mcp_enable_all_tools' ), 'wp_mcp_enable_all_tools', 'wp_mcp_nonce' ) ); ?>" class="button button-secondary">
											<?php esc_html_e( 'Enable all tools', 'wp-mcp-plugin' ); ?>
										</a>
									</p>
								</form>
							</div>
						</div>

						<?php $this->render_client_installation_card( $endpoint ); ?>

						<div class="postbox">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'Visual Comparison Workflow', 'wp-mcp-plugin' ); ?></h2>
							</div>
							<div class="inside">
								<p>
									<?php esc_html_e( 'Use the', 'wp-mcp-plugin' ); ?>
									<code>visual-compare-preview</code>
									<?php esc_html_e( 'tool to generate authenticated preview URLs for draft and private Elementor pages. This enables visual comparison with Figma designs via headless browser screenshots.', 'wp-mcp-plugin' ); ?>
								</p>
								<h3><?php esc_html_e( 'Typical workflow', 'wp-mcp-plugin' ); ?></h3>
								<ol class="wp-mcp-workflow-list">
									<li><?php esc_html_e( 'Build or update an Elementor page using the plugin\'s write tools.', 'wp-mcp-plugin' ); ?></li>
									<li><?php esc_html_e( 'Call', 'wp-mcp-plugin' ); ?> <code>visual-compare-preview</code> <?php esc_html_e( 'to get a time-limited preview URL and Elementor element selectors.', 'wp-mcp-plugin' ); ?></li>
									<li><?php esc_html_e( 'Open the preview URL in Playwright or Puppeteer and take a screenshot.', 'wp-mcp-plugin' ); ?></li>
									<li><?php esc_html_e( 'Compare the screenshot against the Figma design using visual diffing or model-based comparison.', 'wp-mcp-plugin' ); ?></li>
									<li><?php esc_html_e( 'Fix any layout differences and repeat up to 3 times for best results.', 'wp-mcp-plugin' ); ?></li>
								</ol>
								<p class="description">
									<?php esc_html_e( 'The preview token is short-lived (default 5 minutes) and scoped to a single page. Do NOT share preview URLs — they grant read access to draft/private pages.', 'wp-mcp-plugin' ); ?>
								</p>
							</div>
						</div>

					</div>

					<div id="postbox-container-1" class="postbox-container">
						<div class="postbox">
							<div class="postbox-header">
								<h2 class="hndle"><?php esc_html_e( 'API Key', 'wp-mcp-plugin' ); ?></h2>
							</div>
							<div class="inside">
								<p>
									<strong><?php esc_html_e( 'Status:', 'wp-mcp-plugin' ); ?></strong>
									<?php if ( $key_configured ) : ?>
										<span class="wp-mcp-status wp-mcp-status--on"><?php esc_html_e( 'Configured', 'wp-mcp-plugin' ); ?></span>
									<?php else : ?>
										<span class="wp-mcp-status wp-mcp-status--off"><?php esc_html_e( 'Not configured', 'wp-mcp-plugin' ); ?></span>
									<?php endif; ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Generate an API key for MCP clients. The key is shown only once — save it in your MCP client configuration.', 'wp-mcp-plugin' ); ?>
								</p>
								<p class="description">
									<?php esc_html_e( 'Send on every request:', 'wp-mcp-plugin' ); ?>
									<br><code>X-WP-MCP-Key: YOUR_KEY</code>
									<br><?php esc_html_e( 'or', 'wp-mcp-plugin' ); ?> <code>Authorization: Bearer YOUR_KEY</code>
								</p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<input type="hidden" name="action" value="wp_mcp_generate_key">
									<?php wp_nonce_field( 'wp_mcp_generate_key', 'wp_mcp_nonce' ); ?>
									<p>
										<button type="submit" class="button button-primary button-large" style="width:100%;">
											<?php echo $key_configured
												? esc_html__( 'Regenerate API Key', 'wp-mcp-plugin' )
												: esc_html__( 'Generate API Key', 'wp-mcp-plugin' ); ?>
										</button>
									</p>
								</form>
							</div>
						</div>
					</div>

				</div>
			</div>
		</div>
		<style>
			.wp-mcp-admin .wp-mcp-status { font-weight: 600; }
			.wp-mcp-admin .wp-mcp-status--on { color: #00a32a; }
			.wp-mcp-admin .wp-mcp-status--off { color: #d63638; }
			.wp-mcp-admin .wp-mcp-status--on::before,
			.wp-mcp-admin .wp-mcp-status--off::before { content: "\25cf "; }
			.wp-mcp-admin .wp-mcp-api-key-display,
			.wp-mcp-admin .wp-mcp-endpoint { font-size: 13px; word-break: break-all; }
			.wp-mcp-admin .wp-mcp-tools-table .wp-mcp-tools-group td { background: #f6f7f7; }
			.wp-mcp-admin .wp-mcp-tools-row--orphaned td { color: #a7aaad; }
			.wp-mcp-admin .wp-mcp-workflow-list { margin-left: 1.5em; max-width: 700px; }
		</style>
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
	 * Render setup instructions for popular MCP clients.
	 *
	 * @param string $endpoint Full MCP endpoint URL.
	 */
	private function render_client_installation_card( string $endpoint ): void {
		$key_placeholder        = 'YOUR_API_KEY';
		$cursor_env_placeholder = '${env:WP_MCP_API_KEY}';
		?>
		<div class="postbox wp-mcp-agent-setup">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Connect an MCP Client', 'wp-mcp-plugin' ); ?></h2>
			</div>
			<div class="inside">
				<p>
					<?php esc_html_e( 'Add this server to your AI coding agent using the endpoint and API key from above. Replace YOUR_API_KEY with the key you generated.', 'wp-mcp-plugin' ); ?>
				</p>
				<p class="description">
				<?php esc_html_e( 'This plugin uses Streamable HTTP. After initialize, clients must send the Mcp-Session-Id header returned by the server on subsequent requests (most clients handle this automatically).', 'wp-mcp-plugin' ); ?>
			</p>

			<details open style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'Cursor', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Create', 'wp-mcp-plugin' ); ?>
						<code>.cursor/mcp.json</code>
						<?php esc_html_e( 'in your project (or', 'wp-mcp-plugin' ); ?>
						<code>~/.cursor/mcp.json</code>
						<?php esc_html_e( 'for all projects). Use config interpolation so the file is safe to commit — Cursor does not support VS Code-style', 'wp-mcp-plugin' ); ?>
						<code>inputs</code>
						<?php esc_html_e( 'prompts.', 'wp-mcp-plugin' ); ?>
					</p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'mcpServers' => array(
							'wp-mcp' => array(
								'url'     => $endpoint,
								'headers' => array(
									'X-WP-MCP-Key' => $cursor_env_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
					<p class="description">
						<?php esc_html_e( 'Set the key in your shell profile, then restart Cursor:', 'wp-mcp-plugin' ); ?>
						<code>export WP_MCP_API_KEY="your-key-from-above"</code>
					</p>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'VS Code', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Requires VS Code 1.101+ with MCP enabled (Settings → search "chat.mcp.enabled"). Create', 'wp-mcp-plugin' ); ?>
						<code>.vscode/mcp.json</code>
						<?php esc_html_e( 'in your workspace, or run', 'wp-mcp-plugin' ); ?>
						<strong><?php esc_html_e( 'MCP: Open User Configuration', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'from the Command Palette.', 'wp-mcp-plugin' ); ?>
					</p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'servers' => array(
							'wp-mcp' => array(
								'type'    => 'http',
								'url'     => $endpoint,
								'headers' => array(
									'X-WP-MCP-Key' => $key_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'Claude Code (CLI)', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Add to', 'wp-mcp-plugin' ); ?>
						<code>.mcp.json</code>
						<?php esc_html_e( 'in your project root, or run:', 'wp-mcp-plugin' ); ?>
					</p>
					<pre style="margin:0 0 12px;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php
					echo esc_html(
						'claude mcp add-json wp-mcp \'{"type":"http","url":"' . $endpoint . '","headers":{"X-WP-MCP-Key":"' . $key_placeholder . '"}}\''
					);
					?></code></pre>
					<p><?php esc_html_e( 'Project config file:', 'wp-mcp-plugin' ); ?></p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'mcpServers' => array(
							'wp-mcp' => array(
								'type'    => 'http',
								'url'     => $endpoint,
								'headers' => array(
									'X-WP-MCP-Key' => $key_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'Claude Desktop', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<strong><?php esc_html_e( 'Public HTTPS sites:', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'Settings → Connectors → Add custom connector. Enter the endpoint URL and add the', 'wp-mcp-plugin' ); ?>
						<code>X-WP-MCP-Key</code>
						<?php esc_html_e( 'header with your API key.', 'wp-mcp-plugin' ); ?>
					</p>
					<p>
						<strong><?php esc_html_e( 'Local dev (localhost):', 'wp-mcp-plugin' ); ?></strong>
						<?php esc_html_e( 'Claude Desktop only supports stdio in its config file. Use the mcp-remote bridge in', 'wp-mcp-plugin' ); ?>
						<code>claude_desktop_config.json</code>
						<?php esc_html_e( '(macOS:', 'wp-mcp-plugin' ); ?>
						<code>~/Library/Application Support/Claude/</code><?php esc_html_e( ', Windows:', 'wp-mcp-plugin' ); ?>
						<code>%APPDATA%\Claude\</code>):
					</p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'mcpServers' => array(
							'wp-mcp' => array(
								'command' => 'npx',
								'args'    => array(
									'-y',
									'mcp-remote',
									$endpoint,
									'--transport',
									'http-only',
									'--header',
									'X-WP-MCP-Key:' . $key_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'OpenCode', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Add to', 'wp-mcp-plugin' ); ?>
						<code>opencode.json</code>
						<?php esc_html_e( 'or', 'wp-mcp-plugin' ); ?>
						<code>opencode.jsonc</code>
						<?php esc_html_e( 'in your project (or', 'wp-mcp-plugin' ); ?>
						<code>~/.config/opencode/opencode.json</code>
						<?php esc_html_e( 'globally).', 'wp-mcp-plugin' ); ?>
					</p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'$schema' => 'https://opencode.ai/config.json',
						'mcp'     => array(
							'wp-mcp' => array(
								'type'    => 'remote',
								'url'     => $endpoint,
								'enabled' => true,
								'headers' => array(
									'X-WP-MCP-Key' => $key_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'Windsurf', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Edit', 'wp-mcp-plugin' ); ?>
						<code>~/.codeium/windsurf/mcp_config.json</code>
						<?php esc_html_e( '(Cascade → hammer icon → Configure). Remote servers use', 'wp-mcp-plugin' ); ?>
						<code>serverUrl</code>
						<?php esc_html_e( ', not', 'wp-mcp-plugin' ); ?>
						<code>url</code>.
					</p>
					<pre style="margin:0;padding:12px;background:#1d2327;color:#f0f0f1;overflow-x:auto;font-size:12px;line-height:1.5;border-radius:4px;"><code><?php echo esc_html( $this->format_client_config( array(
						'mcpServers' => array(
							'wp-mcp' => array(
								'serverUrl' => $endpoint,
								'headers'   => array(
									'X-WP-MCP-Key' => $key_placeholder,
								),
							),
						),
					) ) ); ?></code></pre>
				</div>
			</details>

			<details style="margin-bottom:10px;border:1px solid #ccd0d4;border-radius:4px;">
				<summary style="padding:10px 14px;cursor:pointer;background:#f6f7f7;font-weight:600;">
					<?php esc_html_e( 'Other MCP clients', 'wp-mcp-plugin' ); ?>
				</summary>
				<div style="padding:0 14px 14px;">
					<p>
						<?php esc_html_e( 'Any client that supports remote Streamable HTTP MCP can connect with:', 'wp-mcp-plugin' ); ?>
					</p>
					<ul style="margin-left:20px;list-style:disc;">
						<li>
							<strong><?php esc_html_e( 'URL:', 'wp-mcp-plugin' ); ?></strong>
							<code><?php echo esc_html( $endpoint ); ?></code>
						</li>
						<li>
							<strong><?php esc_html_e( 'Auth header:', 'wp-mcp-plugin' ); ?></strong>
							<code>X-WP-MCP-Key: YOUR_API_KEY</code>
							<?php esc_html_e( '(or', 'wp-mcp-plugin' ); ?>
							<code>Authorization: Bearer YOUR_API_KEY</code>)
						</li>
					</ul>
					<p style="color:#666;font-size:13px;">
						<?php esc_html_e( 'Works with JetBrains AI Assistant, Zed, Cline, and other MCP hosts that support HTTP transport. Clients limited to stdio can use the mcp-remote bridge shown in the Claude Desktop section.', 'wp-mcp-plugin' ); ?>
					</p>
				</div>
			</details>
			</div>
		</div>
		<?php
	}

	/**
	 * Pretty-print JSON for admin setup snippets.
	 *
	 * @param array<string,mixed> $config Config array.
	 */
	private function format_client_config( array $config ): string {
		$json = wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		return is_string( $json ) ? $json : '{}';
	}

	/**
	 * Get the URL to this plugin's admin page.
	 */
	private function get_page_url(): string {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}
}
