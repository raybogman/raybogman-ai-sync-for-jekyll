<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Admin {

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_wpjs_save_settings', array( $this, 'save_settings' ) );
		add_action( 'admin_post_wpjs_toggle_approve', array( $this, 'toggle_approve' ) );
		add_action( 'admin_post_wpjs_publish_one', array( $this, 'publish_one' ) );
		add_action( 'admin_post_wpjs_publish_approved', array( $this, 'publish_approved' ) );
		add_action( 'admin_post_wpjs_delete_one', array( $this, 'delete_one' ) );
		add_action( 'admin_init', array( $this, 'handle_bulk_action' ) );
		add_action( 'wp_ajax_wpjs_preview', array( $this, 'ajax_preview' ) );
		add_action( 'wp_ajax_wpjs_toggle_auto_push', array( $this, 'ajax_toggle_auto_push' ) );
		add_action( 'wp_ajax_wpjs_get_repos', array( $this, 'ajax_get_repos' ) );
		add_action( 'wp_ajax_wpjs_get_branches', array( $this, 'ajax_get_branches' ) );
		add_action( 'wp_ajax_wpjs_validate', array( $this, 'ajax_validate' ) );
		add_action( 'wp_ajax_wpjs_detect_style', array( $this, 'ajax_detect_style' ) );
		add_action( 'wp_ajax_wpjs_save_style_profile', array( $this, 'ajax_save_style_profile' ) );
		add_action( 'wp_ajax_wpjs_diff', array( $this, 'ajax_diff' ) );
		add_action( 'wp_ajax_wpjs_clear_log', array( $this, 'ajax_clear_log' ) );
		add_action( 'wp_ajax_wpjs_list_jekyll_posts', array( $this, 'ajax_list_jekyll_posts' ) );
		add_action( 'wp_ajax_wpjs_pull_post', array( $this, 'ajax_pull_post' ) );
		add_action( 'wp_ajax_wpjs_validate_ai', array( $this, 'ajax_validate_ai' ) );
		add_action( 'wp_ajax_wpjs_generate_ai', array( $this, 'ajax_generate_ai' ) );
		add_action( 'wp_ajax_wpjs_regen_description', array( $this, 'ajax_regen_description' ) );
		add_action( 'wp_ajax_wpjs_save_description', array( $this, 'ajax_save_description' ) );
		add_action( 'wp_ajax_wpjs_regen_alt', array( $this, 'ajax_regen_alt' ) );
		add_action( 'wp_ajax_wpjs_save_alt', array( $this, 'ajax_save_alt' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/* ─── Menu ──────────────────────────────────────── */

	private $settings_hook = '';
	private $articles_hook = '';

	private $dashboard_hook = '';

	public function menu() {
		$this->dashboard_hook = add_menu_page( 'Jekyll Sync', 'Jekyll Sync', 'manage_options', 'wpjs-dashboard', array( $this, 'render_dashboard' ), 'dashicons-share-alt', 30 );
		add_submenu_page( 'wpjs-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'wpjs-dashboard', array( $this, 'render_dashboard' ) );
		$this->articles_hook = add_submenu_page( 'wpjs-dashboard', 'Articles', 'Articles', 'manage_options', 'wpjs-articles', array( $this, 'render_articles' ) );
		$this->settings_hook = add_submenu_page( 'wpjs-dashboard', 'Settings', 'Settings', 'manage_options', 'wpjs-settings', array( $this, 'render_settings' ) );
	}

	public function enqueue( $hook ) {
		// Dashboard page JS.
		if ( $hook === $this->dashboard_hook ) {
			wp_enqueue_script( 'wpjs-articles', WPJS_URL . 'assets/articles.js', array( 'jquery', 'wp-util' ), WPJS_VERSION, true );
			wp_localize_script( 'wpjs-articles', 'wpjs', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpjs_ajax' ),
			) );
			return;
		}

		// Articles page JS.
		if ( $hook === $this->articles_hook ) {
			wp_enqueue_script( 'wpjs-articles', WPJS_URL . 'assets/articles.js', array( 'jquery', 'wp-util' ), WPJS_VERSION, true );
			wp_localize_script( 'wpjs-articles', 'wpjs', array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wpjs_ajax' ),
			) );
			// Add inline styles for column widths and preview modal.
			wp_add_inline_style( 'wp-admin', '
				.wp-list-table .column-cb { width:2.5em; }
				.wp-list-table .column-title { width:40%; }
				.wp-list-table .column-actions { width:auto; }
				.wp-list-table .column-status { width:100px; }
				.wp-list-table .column-last_push { width:90px; }
				#wpjs-preview-overlay { position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:100000;display:none;justify-content:center;align-items:center; }
				#wpjs-preview-modal { background:#fff;width:800px;max-width:90vw;max-height:85vh;border-radius:6px;overflow:hidden;display:flex;flex-direction:column; }
				#wpjs-preview-modal .modal-header { padding:12px 16px;border-bottom:1px solid #dcdcde;display:flex;justify-content:space-between;align-items:center; }
				#wpjs-preview-modal .modal-body { padding:16px;overflow:auto;flex:1; }
				#wpjs-preview-modal pre { background:#f0f0f1;padding:12px;border-radius:4px;white-space:pre-wrap;word-wrap:break-word;font-size:13px;line-height:1.5; }
			' );
			return;
		}

		// Settings page JS.
		if ( $hook !== $this->settings_hook ) { return; }
		wp_enqueue_script( 'wpjs-settings', WPJS_URL . 'assets/settings.js', array( 'jquery' ), WPJS_VERSION, true );
		wp_enqueue_script( 'wpjs-articles', WPJS_URL . 'assets/articles.js', array( 'jquery' ), WPJS_VERSION, true );
		wp_localize_script( 'wpjs-settings', 'wpjs', array(
			'ajax_url'      => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'wpjs_ajax' ),
			'style_profile' => json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true ),
		) );
	}

	/* ─── AJAX: repos & branches ────────────────────── */

	public function ajax_get_repos() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$client = new WPJS_GitHub_Client();
		$repos  = $client->list_repos();
		if ( is_wp_error( $repos ) ) { wp_send_json_error( $repos->get_error_message() ); }
		wp_send_json_success( $repos );
	}

	public function ajax_get_branches() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$full = sanitize_text_field( wp_unslash( $_POST['repo'] ?? '' ) );
		if ( ! $full || strpos( $full, '/' ) === false ) { wp_send_json_error( 'Invalid repo.' ); }
		list( $owner, $repo ) = explode( '/', $full, 2 );
		$client   = new WPJS_GitHub_Client();
		$branches = $client->list_branches( $owner, $repo );
		if ( is_wp_error( $branches ) ) { wp_send_json_error( $branches->get_error_message() ); }
		wp_send_json_success( $branches );
	}

	public function ajax_validate() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }

		$repo   = sanitize_text_field( wp_unslash( $_POST['repo'] ?? '' ) );
		$branch = sanitize_text_field( wp_unslash( $_POST['branch'] ?? '' ) );

		if ( ! $repo || strpos( $repo, '/' ) === false ) {
			wp_send_json_error( 'Select a repository first.' );
		}

		list( $owner, $name ) = explode( '/', $repo, 2 );
		$client = new WPJS_GitHub_Client();
		$result = $client->verify();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		// Also check the specific repo/branch selected in the UI.
		$checks = array(
			'login'       => $result['login'],
			'repo'        => $repo,
			'repo_access' => false,
			'branch'      => $branch,
			'branch_ok'   => false,
		);

		$headers = array(
			'Authorization' => 'Bearer ' . WPJS_Settings::get( 'github_token' ),
			'Accept'        => 'application/vnd.github+json',
			'User-Agent'    => 'WP-Jekyll-Sync',
		);

		$repo_resp = wp_remote_get( sprintf( 'https://api.github.com/repos/%s/%s', $owner, $name ), array(
			'headers' => $headers, 'timeout' => 15,
		) );
		if ( ! is_wp_error( $repo_resp ) && wp_remote_retrieve_response_code( $repo_resp ) === 200 ) {
			$checks['repo_access'] = true;
			$repo_data = json_decode( wp_remote_retrieve_body( $repo_resp ), true );
			$checks['repo_private'] = ! empty( $repo_data['private'] );
			$checks['permissions']  = $repo_data['permissions'] ?? array();
		}

		if ( $branch && $checks['repo_access'] ) {
			$br_resp = wp_remote_get( sprintf(
				'https://api.github.com/repos/%s/%s/branches/%s', $owner, $name, rawurlencode( $branch )
			), array( 'headers' => $headers, 'timeout' => 15 ) );
			$checks['branch_ok'] = ! is_wp_error( $br_resp ) && wp_remote_retrieve_response_code( $br_resp ) === 200;
		}

		wp_send_json_success( $checks );
	}

	public function ajax_detect_style() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }

		$client   = new WPJS_GitHub_Client();
		$detector = new WPJS_Style_Detector( $client );
		$profile  = $detector->detect();

		if ( is_wp_error( $profile ) ) {
			wp_send_json_error( $profile->get_error_message() );
		}

		WPJS_Settings::update_key( 'style_profile', wp_json_encode( $profile ) );
		wp_send_json_success( $profile );
	}

	public function ajax_save_style_profile() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }

		$json = sanitize_text_field( wp_unslash( $_POST['profile'] ?? '' ) );
		$data = json_decode( $json, true );
		if ( ! is_array( $data ) || empty( $data['front_matter'] ) ) {
			wp_send_json_error( 'Invalid profile data.' );
		}

		WPJS_Settings::update_key( 'style_profile', wp_json_encode( $data ) );
		wp_send_json_success( $data );
	}

	/* ─── Settings page ─────────────────────────────── */

	public function render_settings() {
		$action     = admin_url( 'admin-post.php' );
		$connected  = WPJS_GitHub_OAuth::is_connected();
		$login      = WPJS_Settings::get( 'github_login' );
		$avatar     = WPJS_Settings::get( 'github_avatar' );
		$client_id  = WPJS_Settings::get( 'client_id' );
		$has_oauth  = $client_id && WPJS_Settings::get( 'client_secret' );

		$current_owner  = WPJS_Settings::get( 'repo_owner' );
		$current_repo   = WPJS_Settings::get( 'repo_name' );
		$current_full   = $current_owner && $current_repo ? $current_owner . '/' . $current_repo : '';
		$current_branch = WPJS_Settings::get( 'branch', 'main' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation, no data processing.
		$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? 'connection' ) );
		if ( ! $connected && ! in_array( $active_tab, array( 'faq', 'about', 'products' ), true ) ) { $active_tab = 'connection'; }
		$valid_tabs = array( 'connection', 'content', 'style', 'log', 'pull', 'faq', 'about', 'products' );
		if ( ! in_array( $active_tab, $valid_tabs, true ) ) { $active_tab = 'connection'; }
		?>
		<div class="wrap">
			<h1>
				<span class="dashicons dashicons-share-alt" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span>
				Ray Bogman Jekyll Sync — Settings
			</h1>

			<nav class="nav-tab-wrapper" style="margin-bottom:16px;">
			<?php if ( $connected ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=connection' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'connection' ? 'nav-tab-active' : ''; ?>">Connection</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=content' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'content' ? 'nav-tab-active' : ''; ?>">Content</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=style' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'style' ? 'nav-tab-active' : ''; ?>">Formatting</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=log' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'log' ? 'nav-tab-active' : ''; ?>">Log</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=pull' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'pull' ? 'nav-tab-active' : ''; ?>">Pull from Jekyll</a>
			<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=faq' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'faq' ? 'nav-tab-active' : ''; ?>">FAQ</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=about' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'about' ? 'nav-tab-active' : ''; ?>">About</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings&tab=products' ) ); ?>"
				   class="nav-tab <?php echo $active_tab === 'products' ? 'nav-tab-active' : ''; ?>">More by Ray Bogman</a>
			</nav>

			<?php if ( ! $connected ) : ?>

				<?php /* ─── Not connected: OAuth credentials ─── */ ?>
				<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin-top:16px;">
					<input type="hidden" name="action" value="wpjs_save_settings" />
					<?php wp_nonce_field( 'wpjs_save_settings' ); ?>
					<div class="card" style="max-width:720px;padding:20px;">
						<h2 style="margin-top:0;">Step 1: GitHub OAuth App</h2>
						<p>Create an <strong>OAuth App</strong> at
							<a href="https://github.com/settings/developers" target="_blank" rel="noopener">github.com/settings/developers</a>.
						</p>
						<p class="description">Set the <strong>Authorization callback URL</strong> to:<br>
							<code style="display:inline-block;margin:4px 0;padding:4px 8px;background:#f0f0f1;"><?php echo esc_html( WPJS_GitHub_OAuth::callback_url() ); ?></code>
						</p>
						<table class="form-table" role="presentation" style="margin-top:8px;">
							<tr>
								<th><label>Client ID</label></th>
								<td><input type="text" name="client_id" value="<?php echo esc_attr( $client_id ); ?>" class="regular-text" autocomplete="off" /></td>
							</tr>
							<tr>
								<th><label>Client Secret</label></th>
								<td><input type="password" name="client_secret" value="<?php echo esc_attr( WPJS_Settings::get( 'client_secret' ) ); ?>" class="regular-text" autocomplete="off" /></td>
							</tr>
						</table>
						<?php submit_button( 'Save Credentials', 'secondary', 'submit', false ); ?>
					</div>
				</form>

				<?php if ( $has_oauth ) :
					$authorize_url = WPJS_GitHub_OAuth::get_authorize_url();
				?>
					<div class="card" style="max-width:720px;padding:20px;margin-top:16px;">
						<h2 style="margin-top:0;">Step 2: Login with GitHub</h2>
						<p>Click the button below to authorize this plugin with your GitHub account.</p>
						<p>
							<a href="<?php echo esc_url( $authorize_url ); ?>" class="button button-primary button-hero">
								Login with GitHub
							</a>
						</p>
						<p class="description" style="margin-top:12px;">
							If the button does not work, copy this URL and open it in your browser:<br>
							<input type="text" value="<?php echo esc_attr( $authorize_url ); ?>" class="large-text" readonly onclick="this.select();" style="margin-top:4px;" />
						</p>
					</div>
				<?php endif; ?>

			<?php elseif ( $active_tab === 'connection' ) : ?>

				<?php /* ═══ CONNECTION TAB ═══ */ ?>
				<div class="card" style="max-width:720px;padding:20px;">
					<div style="display:flex;align-items:center;justify-content:space-between;">
						<div style="display:flex;align-items:center;gap:12px;">
							<?php if ( $avatar ) : ?>
								<img src="<?php echo esc_url( $avatar ); ?>" width="48" height="48" style="border-radius:50%;" alt="" />
							<?php endif; ?>
							<div>
								<strong style="font-size:14px;">Connected as <?php echo esc_html( $login ); ?></strong><br>
								<span class="description">GitHub account linked.</span>
							</div>
						</div>
						<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin:0;">
							<input type="hidden" name="action" value="wpjs_disconnect" />
							<?php wp_nonce_field( 'wpjs_disconnect' ); ?>
							<button type="submit" class="button" onclick="return confirm('Disconnect from GitHub?');">Disconnect</button>
						</form>
					</div>
				</div>

				<form method="post" action="<?php echo esc_url( $action ); ?>" id="wpjs-repo-form" style="margin-top:16px;">
					<input type="hidden" name="action" value="wpjs_save_settings" />
					<?php wp_nonce_field( 'wpjs_save_settings' ); ?>

					<div class="card" style="max-width:720px;padding:20px;">
						<h2 style="margin-top:0;">Repository &amp; Branch</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wpjs-repo-select">Repository</label></th>
								<td>
									<select name="repo_full" id="wpjs-repo-select" class="regular-text" data-current="<?php echo esc_attr( $current_full ); ?>">
										<option value="">Loading repositories...</option>
									</select>
									<span class="spinner" id="wpjs-repo-spinner" style="float:none;margin-top:4px;"></span>
								</td>
							</tr>
							<tr>
								<th><label for="wpjs-branch-select">Branch</label></th>
								<td>
									<select name="branch" id="wpjs-branch-select" class="regular-text" data-current="<?php echo esc_attr( $current_branch ); ?>" disabled>
										<option value="">Select a repository first</option>
									</select>
									<span class="spinner" id="wpjs-branch-spinner" style="float:none;margin-top:4px;"></span>
								</td>
							</tr>
							<tr>
								<th>Validate</th>
								<td>
									<button type="button" id="wpjs-validate-btn" class="button button-secondary">Validate Connection</button>
									<span class="spinner" id="wpjs-validate-spinner" style="float:none;margin-top:4px;"></span>
									<div id="wpjs-validate-result" style="margin-top:8px;"></div>
								</td>
							</tr>
							<tr>
								<th><label>GitHub Actions Workflow</label></th>
								<td>
									<input type="text" name="workflow_file" value="<?php echo esc_attr( WPJS_Settings::get( 'workflow_file', '' ) ); ?>" class="regular-text" placeholder="jekyll.yml" />
									<p class="description">Optional. Enter the workflow filename to trigger a Jekyll build after each push.</p>
								</td>
							</tr>
							<tr>
								<th>Auto-push on Publish</th>
								<td>
									<label>
										<input type="checkbox" name="auto_push_on_publish" value="1" <?php checked( WPJS_Settings::get( 'auto_push_on_publish', '0' ), '1' ); ?> />
										Automatically push to Jekyll when a post or page is published or updated
									</label>
								</td>
							</tr>
							<tr>
								<th><label>Scheduled Sync</label></th>
								<td>
									<select name="sync_interval_hours" style="width:auto;">
										<option value="0" <?php selected( WPJS_Settings::get( 'sync_interval_hours', '0' ), '0' ); ?>>Disabled</option>
										<option value="1" <?php selected( WPJS_Settings::get( 'sync_interval_hours', '0' ), '1' ); ?>>Every 1 hour</option>
										<option value="6" <?php selected( WPJS_Settings::get( 'sync_interval_hours', '0' ), '6' ); ?>>Every 6 hours</option>
										<option value="12" <?php selected( WPJS_Settings::get( 'sync_interval_hours', '0' ), '12' ); ?>>Every 12 hours</option>
										<option value="24" <?php selected( WPJS_Settings::get( 'sync_interval_hours', '0' ), '24' ); ?>>Every 24 hours</option>
									</select>
									<select name="sync_cron_mode" style="width:auto;">
										<option value="approved" <?php selected( WPJS_Settings::get( 'sync_cron_mode', 'approved' ), 'approved' ); ?>>Approved &amp; outdated only</option>
										<option value="all" <?php selected( WPJS_Settings::get( 'sync_cron_mode', 'approved' ), 'all' ); ?>>All published posts</option>
									</select>
									<?php if ( WPJS_Cron::is_scheduled() ) : ?>
										<p class="description" style="color:#00a32a;">Next sync: <?php echo esc_html( WPJS_Cron::next_run() ); ?> UTC</p>
									<?php else : ?>
										<p class="description">Select an interval and save to enable.</p>
									<?php endif; ?>
								</td>
							</tr>
						</table>
					</div>

					<div class="card" style="max-width:720px;padding:20px;margin-top:16px;">
						<h2 style="margin-top:0;">
							<span class="dashicons dashicons-superhero-alt" style="font-size:20px;width:20px;height:20px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
							AI Features
						</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th>AI Description</th>
								<td>
									<label>
										<input type="checkbox" name="ai_generate_descriptions" value="1" <?php checked( WPJS_Settings::get( 'ai_generate_descriptions', '0' ), '1' ); ?> />
										Auto-generate SEO description when missing (no excerpt, no Yoast/RankMath)
									</label>
								</td>
							</tr>
							<tr>
								<th>AI Alt Text</th>
								<td>
									<label>
										<input type="checkbox" name="ai_generate_alt_text" value="1" <?php checked( WPJS_Settings::get( 'ai_generate_alt_text', '0' ), '1' ); ?> />
										Auto-generate image alt text when missing (uses AI vision)
									</label>
								</td>
							</tr>
						</table>
						<table class="form-table" role="presentation">
							<tr>
								<th>AI Provider</th>
								<td>
									<select name="ai_provider" style="width:auto;">
										<option value="claude" <?php selected( WPJS_Settings::get( 'ai_provider', 'claude' ), 'claude' ); ?>>Claude (Anthropic)</option>
										<option value="openai" <?php selected( WPJS_Settings::get( 'ai_provider', 'claude' ), 'openai' ); ?>>OpenAI (GPT)</option>
									</select>
									<p class="description">Select which AI provider to use for description and alt text generation.</p>
								</td>
							</tr>
						</table>

						<h3 style="margin-top:20px;">Claude (Anthropic)</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th><label>API Key</label></th>
								<td>
									<input type="password" name="ai_claude_api_key" value="<?php echo esc_attr( WPJS_Settings::get( 'ai_claude_api_key', '' ) ); ?>" class="regular-text" placeholder="sk-ant-api03-..." autocomplete="off" />
									<button type="button" class="button wpjs-validate-ai-btn" data-provider="claude" style="margin-left:8px;">Validate</button>
									<span class="wpjs-ai-validate-status" data-provider="claude" style="margin-left:8px;"></span>
									<p class="description">Sign up at <a href="https://console.anthropic.com" target="_blank" rel="noopener">console.anthropic.com</a> to get an API key.</p>
								</td>
							</tr>
							<tr>
								<th><label>Model</label></th>
								<td>
									<select name="ai_claude_model" style="width:auto;">
										<option value="claude-sonnet-4-6" <?php selected( WPJS_Settings::get( 'ai_claude_model', 'claude-sonnet-4-6' ), 'claude-sonnet-4-6' ); ?>>Claude Sonnet 4.6 (recommended)</option>
										<option value="claude-opus-4-6" <?php selected( WPJS_Settings::get( 'ai_claude_model', 'claude-sonnet-4-6' ), 'claude-opus-4-6' ); ?>>Claude Opus 4.6</option>
										<option value="claude-haiku-4-5-20251001" <?php selected( WPJS_Settings::get( 'ai_claude_model', 'claude-sonnet-4-6' ), 'claude-haiku-4-5-20251001' ); ?>>Claude Haiku 4.5</option>
									</select>
								</td>
							</tr>
						</table>

						<h3 style="margin-top:20px;">OpenAI (GPT)</h3>
						<table class="form-table" role="presentation">
							<tr>
								<th><label>API Key</label></th>
								<td>
									<input type="password" name="ai_openai_api_key" value="<?php echo esc_attr( WPJS_Settings::get( 'ai_openai_api_key', '' ) ); ?>" class="regular-text" placeholder="sk-..." autocomplete="off" />
									<button type="button" class="button wpjs-validate-ai-btn" data-provider="openai" style="margin-left:8px;">Validate</button>
									<span class="wpjs-ai-validate-status" data-provider="openai" style="margin-left:8px;"></span>
									<p class="description">Get an API key at <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a></p>
								</td>
							</tr>
							<tr>
								<th><label>Model</label></th>
								<td>
									<select name="ai_openai_model" style="width:auto;">
										<option value="gpt-4o" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4o' ); ?>>GPT-4o (recommended)</option>
										<option value="gpt-4o-mini" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4o-mini' ); ?>>GPT-4o Mini</option>
										<option value="gpt-4-turbo" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4-turbo' ); ?>>GPT-4 Turbo</option>
										<option value="gpt-4.1" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4.1' ); ?>>GPT-4.1</option>
										<option value="gpt-4.1-mini" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4.1-mini' ); ?>>GPT-4.1 Mini</option>
										<option value="gpt-4.1-nano" <?php selected( WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' ), 'gpt-4.1-nano' ); ?>>GPT-4.1 Nano</option>
									</select>
								</td>
							</tr>
						</table>
					</div>

					<?php submit_button( 'Save Settings' ); ?>
				</form>

			<?php elseif ( $active_tab === 'content' ) : ?>

				<?php /* ═══ CONTENT TAB ═══ */ ?>
				<form method="post" action="<?php echo esc_url( $action ); ?>" id="wpjs-content-form">
					<input type="hidden" name="action" value="wpjs_save_settings" />
					<?php wp_nonce_field( 'wpjs_save_settings' ); ?>

					<div class="card" style="max-width:720px;padding:20px;">
						<h2 style="margin-top:0;">Content Mapping</h2>
						<p class="description">Map WordPress content types to Jekyll paths in your repository.</p>
						<?php
						$profile    = json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true );
						$config_url = $profile['config']['url'] ?? '';
						$jekyll_url = WPJS_Settings::get( 'jekyll_base_url', '' );
						?>
						<table class="form-table" role="presentation">
							<tr>
								<th><label>Jekyll Base URL</label></th>
								<td>
									<input type="url" name="jekyll_base_url" value="<?php echo esc_attr( $config_url ?: $jekyll_url ); ?>" class="regular-text" placeholder="https://raybogman.com" />
									<?php
									$active_url = WPJS_Converter::get_jekyll_base_url();
									$wp_urls    = WPJS_Converter::get_wp_base_urls();
									?>
									<?php if ( $active_url ) : ?>
										<div class="notice notice-success inline" style="margin:8px 0;padding:8px 12px;">
											<strong>URL rewriting active:</strong><br>
											<?php foreach ( $wp_urls as $u ) : ?>
												<code><?php echo esc_html( $u ); ?></code> &rarr; <code><?php echo esc_html( $active_url ); ?></code><br>
											<?php endforeach; ?>
										</div>
									<?php else : ?>
										<div class="notice notice-warning inline" style="margin:8px 0;padding:8px 12px;">
											<strong>URL rewriting not active.</strong> Enter your Jekyll site URL above and click Save Settings.
										</div>
									<?php endif; ?>
								</td>
							</tr>
						</table>
						<table class="widefat" style="margin-top:12px;">
							<thead>
								<tr><th>Sync</th><th>WordPress</th><th></th><th>Jekyll Path</th></tr>
							</thead>
							<tbody>
								<tr>
									<td style="width:50px;text-align:center;">
										<input type="checkbox" name="sync_posts" value="1" <?php checked( WPJS_Settings::get( 'sync_posts', '1' ) ); ?> />
									</td>
									<td><strong>Posts</strong></td>
									<td style="text-align:center;">&rarr;</td>
									<td><input type="text" name="posts_path" value="<?php echo esc_attr( WPJS_Settings::get( 'posts_path', '_posts' ) ); ?>" class="regular-text" /></td>
								</tr>
								<tr>
									<td style="text-align:center;">
										<input type="checkbox" name="sync_pages" value="1" <?php checked( WPJS_Settings::get( 'sync_pages', '1' ) ); ?> />
									</td>
									<td><strong>Pages</strong></td>
									<td style="text-align:center;">&rarr;</td>
									<td><input type="text" name="pages_path" value="<?php echo esc_attr( WPJS_Settings::get( 'pages_path', '_pages' ) ); ?>" class="regular-text" /></td>
								</tr>
							</tbody>
						</table>
					</div>

					<div class="card" style="max-width:720px;padding:20px;margin-top:16px;">
						<h2 style="margin-top:0;">Author</h2>
						<?php
						$author_setting = WPJS_Settings::get( 'author', '__post_author__' );
						$wp_users       = get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) );
						?>
						<table class="form-table" role="presentation">
							<tr>
								<th><label for="wpjs-author">Author in Jekyll front matter</label></th>
								<td>
									<select name="author" id="wpjs-author" class="regular-text">
										<option value="__post_author__" <?php selected( $author_setting, '__post_author__' ); ?>>
											Use the WordPress post/page author
										</option>
										<?php foreach ( $wp_users as $u ) : ?>
											<option value="<?php echo esc_attr( $u->display_name ); ?>" <?php selected( $author_setting, $u->display_name ); ?>>
												<?php echo esc_html( $u->display_name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<p class="description">"Use the WordPress post/page author" will use the author of each individual post or page.</p>
								</td>
							</tr>
						</table>
						<?php submit_button( 'Save Settings' ); ?>
					</div>
				</form>

			<?php elseif ( $active_tab === 'style' ) : ?>

				<?php /* ═══ CONTENT STYLE TAB ═══ */
				$conversion_mode = WPJS_Settings::get( 'conversion_mode', 'standard' );
				$style_profile   = json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true );
				$has_profile     = ! empty( $style_profile['front_matter']['fields'] );
				?>
				<form method="post" action="<?php echo esc_url( $action ); ?>" id="wpjs-style-form">
					<input type="hidden" name="action" value="wpjs_save_settings" />
					<?php wp_nonce_field( 'wpjs_save_settings' ); ?>

					<div class="card" style="max-width:720px;padding:20px;">
						<h2 style="margin-top:0;">Conversion Mode</h2>
						<table class="form-table" role="presentation">
							<tr>
								<th>Mode</th>
								<td>
									<fieldset>
										<label style="display:block;margin-bottom:8px;">
											<input type="radio" name="conversion_mode" value="standard" <?php checked( $conversion_mode, 'standard' ); ?> />
											<strong>Standard</strong> — clean Markdown with default front matter
										</label>
										<label style="display:block;">
											<input type="radio" name="conversion_mode" value="style_aware" <?php checked( $conversion_mode, 'style_aware' ); ?> />
											<strong>Style-aware</strong> — match your Jekyll site's conventions
										</label>
									</fieldset>
								</td>
							</tr>
						</table>
						<?php submit_button( 'Save Conversion Mode' ); ?>
					</div>
				</form>

				<div class="card" style="max-width:720px;padding:20px;margin-top:16px;">
					<h2 style="margin-top:0;">Detect Style</h2>
					<p>Reads <code>_config.yml</code> and samples up to 5 posts from your Jekyll repository to detect front matter schema, Markdown conventions, and permalink patterns.</p>
					<p>
						<button type="button" id="wpjs-detect-style" class="button button-primary">
							Detect Style from Jekyll Site
						</button>
						<span class="spinner" id="wpjs-detect-spinner" style="float:none;margin-top:4px;"></span>
						<span id="wpjs-detect-status" style="margin-left:8px;"></span>
					</p>
				</div>

				<div id="wpjs-style-profile" style="<?php echo $has_profile ? '' : 'display:none;'; ?>">
					<div class="card" style="max-width:720px;padding:20px;margin-top:16px;">
						<h2 style="margin-top:0;">Detected Profile</h2>
						<div id="wpjs-profile-meta">
							<?php if ( $has_profile ) : ?>
								<p class="description">
									Detected: <?php echo esc_html( $style_profile['detected_at'] ?? '' ); ?> |
									Sources: <?php echo esc_html( count( $style_profile['source_files'] ?? array() ) ); ?> files
								</p>
							<?php endif; ?>
						</div>
						<div id="wpjs-profile-details">
							<?php if ( $has_profile ) : ?>
								<?php $this->render_style_profile( $style_profile ); ?>
							<?php endif; ?>
						</div>
					</div>
				</div>

			<?php elseif ( $active_tab === 'log' ) : ?>

				<?php /* ═══ LOG TAB ═══ */ ?>
				<?php $this->render_log_tab(); ?>

			<?php elseif ( $active_tab === 'pull' ) : ?>

				<?php /* ═══ PULL TAB ═══ */ ?>
				<?php $this->render_pull_tab(); ?>

			<?php elseif ( $active_tab === 'faq' ) : ?>

				<?php /* ═══ FAQ TAB ═══ */ ?>
				<?php $this->render_faq_tab(); ?>

			<?php elseif ( $active_tab === 'about' ) : ?>

				<?php /* ═══ ABOUT TAB ═══ */ ?>
				<?php $this->render_about_tab(); ?>

			<?php elseif ( $active_tab === 'products' ) : ?>

				<?php /* ═══ PRODUCTS TAB ═══ */ ?>
				<?php $this->render_products_tab(); ?>

			<?php endif; ?>
		</div>
		<?php
	}

	private function render_faq_tab() {
		?>
		<div>
			<div class="card" style="padding:16px 20px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-editor-help" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
					Frequently Asked Questions
				</h2>
				<h3>Table of Contents</h3>
				<ol style="column-count:2;column-gap:24px;line-height:2;">
					<li><a href="#faq-what-does-it-do">What does this plugin do?</a></li>
					<li><a href="#faq-github-oauth">How does the GitHub login work?</a></li>
					<li><a href="#faq-callback-url">What is the OAuth callback URL?</a></li>
					<li><a href="#faq-style-detection">What is Style-aware conversion?</a></li>
					<li><a href="#faq-url-rewriting">How does URL rewriting work?</a></li>
					<li><a href="#faq-featured-images">How are featured images handled?</a></li>
					<li><a href="#faq-approve-workflow">What does the Approve button do?</a></li>
					<li><a href="#faq-auto-push">Can I auto-publish when approving?</a></li>
					<li><a href="#faq-bulk-actions">Can I push or delete multiple posts at once?</a></li>
					<li><a href="#faq-preview">Can I preview the Markdown before pushing?</a></li>
					<li><a href="#faq-outdated">What does "Outdated" status mean?</a></li>
					<li><a href="#faq-delete">Can I delete a post from Jekyll?</a></li>
					<li><a href="#faq-config-yml">What is read from _config.yml?</a></li>
					<li><a href="#faq-permalink">How are permalinks generated?</a></li>
					<li><a href="#faq-cross-tab">Why did my settings disappear?</a></li>
					<li><a href="#faq-supported-content">What content types are supported?</a></li>
					<li><a href="#faq-inline-images">Are inline images uploaded to Jekyll?</a></li>
					<li><a href="#faq-api-limits">Are there GitHub API rate limits?</a></li>
				</ol>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-what-does-it-do">
				<h3 style="margin-top:0;">What does this plugin do?</h3>
				<p>Ray Bogman Jekyll Sync lets you publish WordPress posts and pages to a Jekyll site hosted on GitHub Pages. It converts your HTML content to Markdown with YAML front matter, uploads featured images, rewrites internal links, and commits everything directly to your GitHub repository — all from within the WordPress admin.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-github-oauth">
				<h3 style="margin-top:0;">How does the GitHub login work?</h3>
				<p>The plugin uses the standard GitHub OAuth App flow. You create a free OAuth App on GitHub (under Settings → Developer settings), enter the Client ID and Secret in the plugin, and click "Login with GitHub". GitHub handles authorization and redirects you back. No tokens to copy and paste.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-callback-url">
				<h3 style="margin-top:0;">What is the OAuth callback URL?</h3>
				<p>The callback URL is shown on the Connection tab when you first set up the plugin. It looks like:<br>
				<code><?php echo esc_html( WPJS_GitHub_OAuth::callback_url() ); ?></code><br>
				You must enter this exact URL in your GitHub OAuth App settings as the "Authorization callback URL".</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-style-detection">
				<h3 style="margin-top:0;">What is Style-aware conversion?</h3>
				<p>Style-aware mode reads your existing Jekyll site to detect its conventions — front matter fields, their order, Markdown heading style (ATX vs Setext), list markers, emphasis markers, code fences, and more. It then uses this "style profile" for every sync, ensuring your pushed content matches the rest of your Jekyll site exactly.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-url-rewriting">
				<h3 style="margin-top:0;">How does URL rewriting work?</h3>
				<p>When you push a post, all internal links pointing to your WordPress domain are automatically replaced with your Jekyll site's URL. The Jekyll URL is read from your <code>_config.yml</code> (the <code>url</code> field) or can be set manually on the Content tab. For example, <code>https://wp.example.com/my-post/</code> becomes <code>https://example.com/my-post/</code>.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-featured-images">
				<h3 style="margin-top:0;">How are featured images handled?</h3>
				<p>When you push a post that has a featured image, the plugin uploads the image to your Jekyll repository (default path: <code>assets/images/</code>) and sets the <code>featured_image</code> front matter to the Jekyll-native relative path (e.g. <code>/assets/images/my-post.jpg</code>). The images path is auto-detected from your existing Jekyll posts.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-approve-workflow">
				<h3 style="margin-top:0;">What does the Approve button do?</h3>
				<p>The Approve button marks posts for batch publishing. You can approve multiple posts during the week, then click "Publish all approved to Jekyll" to push them all at once. It's a selection mechanism — approving alone does not push unless auto-push is enabled.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-auto-push">
				<h3 style="margin-top:0;">Can I auto-publish when approving?</h3>
				<p>Yes. Check the "Auto-push when approving" checkbox above the articles list. When enabled, clicking Approve on a published post will immediately push it to Jekyll in one click.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-bulk-actions">
				<h3 style="margin-top:0;">Can I push or delete multiple posts at once?</h3>
				<p>Yes. Use the checkboxes in the articles list to select multiple posts, then choose a bulk action from the dropdown: Approve, Unapprove, Push to Jekyll, or Delete from Jekyll. Click Apply to execute.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-preview">
				<h3 style="margin-top:0;">Can I preview the Markdown before pushing?</h3>
				<p>Yes. Each row in the articles list has a Preview button that opens a modal showing the exact Markdown and YAML front matter that will be committed to your repository. This lets you verify the output before pushing.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-outdated">
				<h3 style="margin-top:0;">What does "Outdated" status mean?</h3>
				<p>A yellow "Outdated" status means the WordPress post was modified after it was last pushed to Jekyll. You should re-push it to sync the latest changes. "Published" (green) means it's up to date, and "Not published" (red) means it has never been pushed.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-delete">
				<h3 style="margin-top:0;">Can I delete a post from Jekyll?</h3>
				<p>Yes. The Delete button (visible for published posts) removes the Markdown file from your GitHub repository via the Contents API. It also resets the local publish status so you can re-push later after making changes.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-config-yml">
				<h3 style="margin-top:0;">What is read from _config.yml?</h3>
				<p>The style detection reads: <code>url</code> (site base URL), <code>baseurl</code>, <code>permalink</code> pattern, <code>markdown</code> processor, and the posts permalink from <code>collections.posts.permalink</code>. It also detects the images directory from existing posts' <code>featured_image</code> paths.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-permalink">
				<h3 style="margin-top:0;">How are permalinks generated?</h3>
				<p>In Style-aware mode, the permalink front matter field is generated using the pattern from your <code>_config.yml</code>. Variables like <code>:year</code>, <code>:month</code>, <code>:day</code>, <code>:title</code>, <code>:slug</code>, and <code>:categories</code> are substituted with the post's actual data.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-cross-tab">
				<h3 style="margin-top:0;">Why did my settings disappear?</h3>
				<p>In earlier versions, saving from one settings tab could overwrite settings from another tab. This was fixed in v2.5.0 — settings now only update fields present in the submitted form. If you experience this, re-enter your settings and save from the correct tab.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-supported-content">
				<h3 style="margin-top:0;">What content types are supported?</h3>
				<p>Posts and pages. You can enable or disable each type independently on the Content tab. Posts are saved to the Jekyll <code>_posts/</code> directory (as <code>YYYY-MM-DD-slug.md</code>), and pages to <code>_pages/</code> (as <code>slug.md</code>).</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-inline-images">
				<h3 style="margin-top:0;">Are inline images uploaded to Jekyll?</h3>
				<p>Currently, only featured images are uploaded to the Jekyll repository. Inline images within the post body retain their WordPress URLs. Inline image upload is planned for a future release.</p>
			</div>

			<div class="card" style="padding:16px 20px;margin-bottom:12px;" id="faq-api-limits">
				<h3 style="margin-top:0;">Are there GitHub API rate limits?</h3>
				<p>GitHub allows 5,000 API requests per hour for authenticated users. Each post push uses 2-3 requests (check existing file + create/update), and style detection uses about 7 requests. Normal usage is well within limits. Bulk-pushing 50 posts at once uses approximately 100-150 requests.</p>
			</div>
		</div>
		<?php
	}

	private function render_about_tab() {
		?>
		<div>

			<div class="card" style="padding:16px 20px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-share-alt" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
					About Ray Bogman Jekyll Sync
				</h2>
				<p style="font-size:14px;line-height:1.6;">
					<strong>Ray Bogman Jekyll Sync</strong> bridges the gap between WordPress content management and Jekyll static site generation. Write and manage your content in WordPress, then publish directly to your Jekyll GitHub Pages site with a single click.
				</p>
				<p style="font-size:14px;line-height:1.6;">
					The plugin handles the entire conversion pipeline: HTML to Markdown, YAML front matter generation, featured image uploads, internal link rewriting, and Git commits — all through the GitHub API, no CLI or server-side Git required.
				</p>
				<p style="font-size:14px;line-height:1.6;">
					With <strong>Style-aware mode</strong>, the plugin reads your existing Jekyll site to detect its conventions and matches them exactly — ensuring consistent front matter fields, heading styles, list markers, and permalink patterns across your entire site.
				</p>

				<h3>Key Features</h3>
				<table class="widefat striped" style="max-width:700px;">
					<thead>
						<tr>
							<th>Feature</th>
							<th style="text-align:center;">Included</th>
						</tr>
					</thead>
					<tbody>
						<tr><td>GitHub OAuth Login</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Repository &amp; Branch Picker</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>HTML to Markdown Conversion</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>YAML Front Matter Generation</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Style Detection from Jekyll Site</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Featured Image Upload to Jekyll</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Internal URL Rewriting</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Approve &amp; Bulk Publish Workflow</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Markdown Preview before Push</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Delete from Jekyll</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Stale/Outdated Detection</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Auto-push on Approve</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Connection Validation</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
						<tr><td>Posts &amp; Pages Support</td><td style="text-align:center;"><span class="dashicons dashicons-yes-alt" style="color:#00a32a;"></span></td></tr>
					</tbody>
				</table>
			</div>

			<div class="card" style="padding:16px 20px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-admin-users" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
					About the Author
				</h2>
				<div style="display:flex;gap:20px;flex-wrap:wrap;">
					<div style="flex:1;min-width:300px;">
						<p style="font-size:14px;line-height:1.6;">
							<strong>Ray Bogman</strong> — Fractional CTO, AI Innovator, and Head of Innovation at Alumio. Based in Amstelveen, Netherlands.
						</p>
						<p style="font-size:14px;line-height:1.6;">
							With over two decades of experience in web development, cloud architecture, and digital commerce, Ray specializes in bridging the gap between cutting-edge AI technology and practical business applications. He has led technology teams across e-commerce, integration platforms, and content management systems.
						</p>
						<p style="font-size:14px;line-height:1.6;">
							Ray is a recognized speaker, open-source contributor, and author with deep expertise in Magento/Adobe Commerce, cloud-native architectures, and AI-driven content workflows. His work focuses on making complex technology accessible and actionable for businesses of all sizes.
						</p>
						<p style="font-size:14px;line-height:1.6;">
							<a href="https://raybogman.com" target="_blank" rel="noopener" class="button button-secondary" style="margin-right:8px;">
								<span class="dashicons dashicons-admin-links" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
								raybogman.com
							</a>
							<a href="https://linkedin.com/in/raybogman" target="_blank" rel="noopener" class="button button-secondary" style="margin-right:8px;">
								<span class="dashicons dashicons-linkedin" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
								LinkedIn
							</a>
							<a href="https://github.com/raybogman" target="_blank" rel="noopener" class="button button-secondary">
								<span class="dashicons dashicons-github" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
								GitHub
							</a>
						</p>

						<h3>Certifications</h3>
						<ul style="font-size:14px;line-height:1.8;">
							<li>Oxford Artificial Intelligence Programme</li>
							<li>AWS Certified AI Practitioner</li>
							<li>AWS Certified Cloud Practitioner</li>
							<li>CTO Academy Certified Fractional CTO</li>
							<li>Certified Ethical Hacker</li>
							<li>Professional Scrum Master I</li>
							<li>Red Hat Certified Engineer</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="card" style="padding:16px 20px;text-align:center;">
				<p style="color:#50575e;font-size:13px;margin:0;">
					Ray Bogman Jekyll Sync v<?php echo esc_html( WPJS_VERSION ); ?> · &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
					<a href="https://raybogman.com" target="_blank" rel="noopener" style="color:#50575e;">Ray Bogman</a>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_products_tab() {
		$co_installed = is_plugin_active( 'rayai-content-orchestrator/rayai-content-orchestrator.php' )
			|| is_plugin_active( 'ai-content-creator/ai-content-creator.php' );
		?>
		<div>

			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-store" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
					More Solutions by Ray Bogman
				</h2>
				<p style="font-size:14px;line-height:1.6;">
					Explore the full suite of WordPress plugins by Ray Bogman, designed to supercharge your content workflow with AI-powered automation.
				</p>
			</div>

			<?php /* ─── Content Orchestrator ─── */ ?>
			<div class="card" style="padding:20px;margin-top:16px;">
				<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
					<div style="flex:1;min-width:400px;">
						<h2 style="margin-top:0;">
							<span class="dashicons dashicons-edit-large" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
							RayAI – Content Orchestrator
						</h2>
						<p style="font-size:14px;line-height:1.6;">
							The complete AI-powered content pipeline for WordPress. From website scanning through SEO optimization, content generation, featured images, internal linking, LinkedIn sharing, and multi-platform repurposing — all in one plugin.
						</p>

						<h3>Key Features</h3>
						<ul style="font-size:14px;line-height:1.8;column-count:2;column-gap:24px;">
							<li>AI Content Generation (Claude + OpenAI)</li>
							<li>13 Blog Post Styles</li>
							<li>Website Scanner + PDF Library</li>
							<li>SEO Metadata Generation</li>
							<li>DALL-E 3 / Ideogram Images</li>
							<li>Custom Font Title Overlays</li>
							<li>Automatic Internal Linking</li>
							<li>LinkedIn OAuth Auto-sharing</li>
							<li>Content Repurposing (Email, X, Instagram, Pinterest)</li>
							<li>Bulk Content Queue + AI Topic Suggestions</li>
							<li>Scheduled Publishing + Approval</li>
							<li>Stale Content Refresh Detection</li>
							<li>Yoast SEO Integration</li>
							<li>Thrive Architect Compatibility</li>
						</ul>

						<h3>Pricing</h3>
						<table class="widefat striped" style="max-width:500px;">
							<thead>
								<tr><th>Plan</th><th style="text-align:center;">Monthly</th><th style="text-align:center;">Annual</th><th style="text-align:center;">Lifetime</th></tr>
							</thead>
							<tbody>
								<tr>
									<td><strong>Free</strong></td>
									<td style="text-align:center;">$0</td>
									<td style="text-align:center;">$0</td>
									<td style="text-align:center;">—</td>
								</tr>
								<tr>
									<td><strong>Enterprise</strong></td>
									<td style="text-align:center;">$24.99</td>
									<td style="text-align:center;">$249.99</td>
									<td style="text-align:center;">$699.99</td>
								</tr>
							</tbody>
						</table>
						<p class="description" style="margin-top:8px;">AI costs paid directly to providers (~$0.02–$0.10 per post, ~$0.04 per image).</p>

						<div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap;">
							<?php if ( $co_installed ) : ?>
								<span class="button button-secondary" disabled style="opacity:0.7;">
									<span class="dashicons dashicons-yes-alt" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;color:#00a32a;"></span>
									Installed
								</span>
							<?php else : ?>
								<a href="https://raybogman.com/products/ai-content-orchestrator/" target="_blank" rel="noopener" class="button button-primary" style="background:#E4405F;border-color:#E4405F;">
									<span class="dashicons dashicons-star-filled" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
									Get Content Orchestrator
								</a>
							<?php endif; ?>
							<a href="https://raybogman.com/products/ai-content-orchestrator/" target="_blank" rel="noopener" class="button button-secondary">
								<span class="dashicons dashicons-external" style="font-size:16px;width:16px;height:16px;vertical-align:middle;margin-right:4px;"></span>
								Learn More
							</a>
						</div>
					</div>
				</div>
			</div>

			<?php /* ─── Jekyll Sync (this plugin) ─── */ ?>
			<div class="card" style="padding:20px;margin-top:16px;">
				<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
					<div style="flex:1;min-width:400px;">
						<h2 style="margin-top:0;">
							<span class="dashicons dashicons-share-alt" style="font-size:24px;width:24px;height:24px;color:#00a32a;margin-right:8px;vertical-align:middle;"></span>
							Ray Bogman Jekyll Sync
							<span style="background:#00a32a;color:#fff;font-size:11px;padding:2px 8px;border-radius:3px;vertical-align:middle;margin-left:8px;">ACTIVE</span>
						</h2>
						<p style="font-size:14px;line-height:1.6;">
							Publish WordPress content to Jekyll GitHub Pages. OAuth login, style detection, Markdown conversion, featured image upload, URL rewriting, and batch publishing — all from your WordPress admin.
						</p>
						<ul style="font-size:14px;line-height:1.8;">
							<li>GitHub OAuth Login + Repo/Branch Picker</li>
							<li>HTML to Markdown with YAML Front Matter</li>
							<li>Style Detection from Jekyll Site</li>
							<li>Featured Image Upload to Jekyll</li>
							<li>Internal URL Rewriting</li>
							<li>Approve, Preview, Push, Delete Workflow</li>
						</ul>
						<p><strong>Price:</strong> Free</p>
					</div>
				</div>
			</div>

			<?php /* ─── Coming Soon / Ecosystem ─── */ ?>
			<div class="card" style="padding:20px;margin-top:16px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-megaphone" style="font-size:24px;width:24px;height:24px;color:#dba617;margin-right:8px;vertical-align:middle;"></span>
					Ray Bogman Ecosystem
				</h2>
				<p style="font-size:14px;line-height:1.6;">
					The Ray Bogman plugin suite is designed to work together. Use <strong>Content Orchestrator</strong> to generate AI-powered blog posts, then use <strong>Jekyll Sync</strong> to publish them to your static Jekyll site — a complete content-to-deployment pipeline.
				</p>
				<div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:16px;">
					<div style="flex:1;min-width:250px;background:#f0f6fc;border:1px solid #c3c4c7;border-radius:4px;padding:16px;text-align:center;">
						<span class="dashicons dashicons-edit-large" style="font-size:32px;width:32px;height:32px;color:#2271b1;"></span>
						<h4 style="margin:8px 0 4px;">Create</h4>
						<p style="font-size:13px;color:#50575e;margin:0;">Content Orchestrator<br>generates AI content</p>
					</div>
					<div style="flex:0 0 40px;display:flex;align-items:center;justify-content:center;">
						<span class="dashicons dashicons-arrow-right-alt" style="font-size:24px;color:#2271b1;"></span>
					</div>
					<div style="flex:1;min-width:250px;background:#f0faf0;border:1px solid #c3c4c7;border-radius:4px;padding:16px;text-align:center;">
						<span class="dashicons dashicons-share-alt" style="font-size:32px;width:32px;height:32px;color:#00a32a;"></span>
						<h4 style="margin:8px 0 4px;">Publish</h4>
						<p style="font-size:13px;color:#50575e;margin:0;">Jekyll Sync pushes<br>to GitHub Pages</p>
					</div>
					<div style="flex:0 0 40px;display:flex;align-items:center;justify-content:center;">
						<span class="dashicons dashicons-arrow-right-alt" style="font-size:24px;color:#2271b1;"></span>
					</div>
					<div style="flex:1;min-width:250px;background:#fef8ee;border:1px solid #c3c4c7;border-radius:4px;padding:16px;text-align:center;">
						<span class="dashicons dashicons-admin-site-alt3" style="font-size:32px;width:32px;height:32px;color:#dba617;"></span>
						<h4 style="margin:8px 0 4px;">Live</h4>
						<p style="font-size:13px;color:#50575e;margin:0;">Jekyll builds &amp; serves<br>your static site</p>
					</div>
				</div>
			</div>

			<div class="card" style="padding:16px 20px;text-align:center;">
				<p style="color:#50575e;font-size:13px;margin:0;">
					<a href="https://raybogman.com" target="_blank" rel="noopener" style="color:#50575e;">raybogman.com</a> ·
					All Ray Bogman plugins are built by <a href="https://linkedin.com/in/raybogman" target="_blank" rel="noopener" style="color:#50575e;">Ray Bogman</a>
				</p>
			</div>
		</div>
		<?php
	}

	private function render_style_profile( $profile ) {
		$fm = $profile['front_matter'] ?? array();
		$md = $profile['markdown'] ?? array();
		$cfg = $profile['config'] ?? array();
		?>
		<h4 style="margin-top:16px;">Front Matter Fields</h4>
		<table class="widefat" style="max-width:500px;">
			<thead><tr><th>Field</th><th>Type</th><th>Required</th></tr></thead>
			<tbody>
			<?php foreach ( ( $fm['fields'] ?? array() ) as $f ) : ?>
				<tr>
					<td><code><?php echo esc_html( $f['key'] ); ?></code></td>
					<td><?php echo esc_html( $f['type'] ); ?></td>
					<td><?php echo $f['required'] ? 'Yes' : 'No'; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p><strong>Array style:</strong> <?php echo esc_html( $fm['array_style'] ?? 'block' ); ?></p>

		<?php if ( ! empty( $cfg['permalink'] ) ) : ?>
			<p><strong>Permalink:</strong> <code><?php echo esc_html( $cfg['permalink'] ); ?></code></p>
		<?php endif; ?>
		<?php if ( ! empty( $cfg['markdown'] ) ) : ?>
			<p><strong>Markdown processor:</strong> <?php echo esc_html( $cfg['markdown'] ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $cfg['images_path'] ) ) : ?>
			<p><strong>Images path:</strong> <code><?php echo esc_html( $cfg['images_path'] ); ?></code></p>
		<?php endif; ?>

		<h4>Markdown Style</h4>
		<table class="widefat" style="max-width:500px;">
			<tbody>
				<tr><td>Headings</td><td><?php echo esc_html( $md['heading_style'] ?? 'atx' ); ?> (<code><?php echo $md['heading_style'] === 'setext' ? '== / --' : '#'; ?></code>)</td></tr>
				<tr><td>List marker</td><td><code><?php echo esc_html( $md['ul_marker'] ?? '-' ); ?></code></td></tr>
				<tr><td>Emphasis</td><td><code><?php echo esc_html( $md['emphasis_marker'] ?? '*' ); ?></code></td></tr>
				<tr><td>Strong</td><td><code><?php echo esc_html( $md['strong_marker'] ?? '**' ); ?></code></td></tr>
				<tr><td>Code fence</td><td><code><?php echo esc_html( $md['code_fence'] ?? '```' ); ?></code></td></tr>
				<tr><td>Horizontal rule</td><td><code><?php echo esc_html( $md['hr_style'] ?? '---' ); ?></code></td></tr>
			</tbody>
		</table>
		<?php
	}

	/* ─── Articles page ─────────────────────────────── */

	public function render_articles() {
		$table = new WPJS_Articles_Table();
		$table->prepare_items();
		$action     = admin_url( 'admin-post.php' );
		$auto_push  = WPJS_Settings::get( 'auto_push_on_approve', '0' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<span class="dashicons dashicons-share-alt" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span>
				Ray Bogman Jekyll Sync — Articles
			</h1>
			<hr class="wp-header-end" />

			<p>Use the checkboxes and <strong>Bulk Actions</strong> dropdown to approve, push, or delete multiple items. Or use the row buttons for individual actions.</p>

			<div style="margin:12px 0;display:flex;gap:12px;align-items:center;">
				<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin:0;">
					<input type="hidden" name="action" value="wpjs_publish_approved" />
					<?php wp_nonce_field( 'wpjs_publish_approved' ); ?>
					<button type="submit" class="button button-primary">Publish all approved to Jekyll</button>
				</form>
				<label style="display:flex;align-items:center;gap:6px;">
					<input type="checkbox" id="wpjs-auto-push" <?php checked( $auto_push, '1' ); ?> />
					Auto-push when approving
				</label>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'wpjs_bulk_action', 'wpjs_bulk_nonce' ); ?>
				<input type="hidden" name="page" value="wpjs-articles" />
				<?php $table->display(); ?>
			</form>
		</div>

		<?php /* Preview modal */ ?>
		<div id="wpjs-preview-overlay">
			<div id="wpjs-preview-modal">
				<div class="modal-header">
					<strong id="wpjs-preview-title">Markdown Preview</strong>
					<button type="button" id="wpjs-preview-close" class="button">&times;</button>
				</div>
				<div class="modal-body">
					<pre id="wpjs-preview-content">Loading...</pre>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─── Actions ────────────────────────────────────── */

	public function save_settings() {
		$this->check_cap_and_nonce( 'wpjs_save_settings' ); // Nonce verified here.
		WPJS_Settings::update( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		WPJS_Cron::schedule();
		$this->notice( 'success', 'Settings saved.' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$referer  = sanitize_text_field( wp_unslash( $_POST['_wp_http_referer'] ?? '' ) );
		$redirect = admin_url( 'admin.php?page=wpjs-settings' );
		if ( strpos( $referer, 'tab=content' ) !== false ) {
			$redirect = admin_url( 'admin.php?page=wpjs-settings&tab=content' );
		} elseif ( strpos( $referer, 'tab=style' ) !== false ) {
			$redirect = admin_url( 'admin.php?page=wpjs-settings&tab=style' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	public function toggle_approve() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		$id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		check_admin_referer( 'wpjs_toggle_approve_' . $id );
		if ( $id ) {
			$new_state = ! WPJS_Publisher::is_approved( $id );
			WPJS_Publisher::set_approved( $id, $new_state );

			// Auto-push if enabled and just approved.
			if ( $new_state && WPJS_Settings::get( 'auto_push_on_approve', '0' ) === '1' ) {
				$post = get_post( $id );
				if ( $post && $post->post_status === 'publish' ) {
					$result = WPJS_Publisher::publish( $post );
					if ( is_wp_error( $result ) ) {
						$this->notice( 'error', 'Auto-push failed: ' . $result->get_error_message() );
					} else {
						$this->notice( 'success', 'Approved and pushed ' . esc_html( $result ) );
					}
					wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
					exit;
				}
			}
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
		exit;
	}

	public function handle_bulk_action() {
		if ( empty( $_POST['wpjs_bulk_nonce'] ) ) { return; }
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpjs_bulk_nonce'] ) ), 'wpjs_bulk_action' ) ) { return; }
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// WP_List_Table sends bulk action as 'action' (top dropdown) or 'action2' (bottom dropdown).
		$bulk = sanitize_text_field( wp_unslash( $_POST['action'] ?? '-1' ) );
		if ( $bulk === '-1' ) {
			$bulk = sanitize_text_field( wp_unslash( $_POST['action2'] ?? '-1' ) );
		}
		$ids = array_map( 'intval', $_POST['post_ids'] ?? array() );

		if ( empty( $ids ) || $bulk === '-1' ) {
			$this->notice( 'warning', 'No items selected.' );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
			exit;
		}

		$ok = 0; $fail = 0;
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( ! $post ) { continue; }

			switch ( $bulk ) {
				case 'bulk_approve':
					WPJS_Publisher::set_approved( $id, true );
					$ok++;
					break;
				case 'bulk_unapprove':
					WPJS_Publisher::set_approved( $id, false );
					$ok++;
					break;
				case 'bulk_push':
					if ( $post->post_status !== 'publish' ) { $fail++; continue 2; }
					$r = WPJS_Publisher::publish( $post );
					if ( is_wp_error( $r ) ) { $fail++; } else { $ok++; }
					break;
				case 'bulk_delete':
					$r = WPJS_Publisher::delete( $post );
					if ( is_wp_error( $r ) ) { $fail++; } else { $ok++; }
					break;
				case 'bulk_ai':
					WPJS_Publisher::generate_ai_metadata( $post );
					$ok++;
					break;
			}
		}

		$labels = array(
			'bulk_approve'   => 'Approved',
			'bulk_unapprove' => 'Unapproved',
			'bulk_push'      => 'Pushed',
			'bulk_delete'    => 'Deleted',
			'bulk_ai'        => 'AI metadata generated',
		);
		$label = $labels[ $bulk ] ?? 'Processed';
		$this->notice(
			$fail ? 'warning' : 'success',
			sprintf( '%s — succeeded: %d, failed: %d.', $label, $ok, $fail )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
		exit;
	}

	public function ajax_toggle_auto_push() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$enabled = sanitize_text_field( wp_unslash( $_POST['enabled'] ?? '0' ) );
		WPJS_Settings::update_key( 'auto_push_on_approve', $enabled === '1' ? '1' : '0' );
		wp_send_json_success();
	}

	public function ajax_preview() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_send_json_error( 'Post not found.' ); }
		$markdown = WPJS_Converter::post_to_markdown( $post );
		$filename = WPJS_Converter::filename( $post );
		wp_send_json_success( array(
			'filename' => $filename,
			'markdown' => $markdown,
		) );
	}

	public function publish_one() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		$id   = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		check_admin_referer( 'wpjs_publish_one_' . $id );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_die( 'Post not found.' ); }
		$result = WPJS_Publisher::publish( $post );
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', 'Push failed: ' . $result->get_error_message() );
		} else {
			$this->notice( 'success', 'Published ' . esc_html( $result ) );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
		exit;
	}

	public function publish_approved() {
		$this->check_cap_and_nonce( 'wpjs_publish_approved' );
		$q = new WP_Query( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'meta_key'       => WPJS_Publisher::META_APPROVED, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'fields'         => 'ids',
		) );
		$ok = 0; $fail = 0;
		foreach ( $q->posts as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) { continue; }
			$r = WPJS_Publisher::publish( $post );
			if ( is_wp_error( $r ) ) { $fail++; } else { $ok++; }
		}
		$this->notice(
			$fail ? 'warning' : 'success',
			sprintf( 'Publish run complete — succeeded: %d, failed: %d.', $ok, $fail )
		);
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
		exit;
	}

	public function delete_one() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		$id = isset( $_GET['post_id'] ) ? (int) $_GET['post_id'] : 0;
		check_admin_referer( 'wpjs_delete_one_' . $id );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_die( 'Post not found.' ); }

		$result = WPJS_Publisher::delete( $post );

		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', 'Delete failed: ' . $result->get_error_message() );
		} else {
			$this->notice( 'success', 'Deleted from Jekyll.' );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-articles' ) );
		exit;
	}

	/* ─── Notices ────────────────────────────────────── */

	public function notices() {
		$key    = 'wpjs_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) { return; }
		delete_transient( $key );
		printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), wp_kses_post( $notice['message'] ) );
	}

	private function notice( $type, $message ) {
		set_transient( 'wpjs_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), 60 );
	}

	/* ─── Dashboard page ────────────────────────────── */

	public function render_dashboard() {
		$all_posts = new WP_Query( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		) );
		$total     = $all_posts->found_posts;
		$published = 0;
		$outdated  = 0;
		$approved  = 0;
		foreach ( $all_posts->posts as $pid ) {
			$lp = get_post_meta( $pid, '_wpjs_last_push', true );
			if ( $lp ) {
				$push_time = strtotime( $lp );
				$mod_time  = strtotime( get_post( $pid )->post_modified_gmt );
				if ( $mod_time > $push_time ) {
					$outdated++;
				} else {
					$published++;
				}
			}
			if ( get_post_meta( $pid, '_wpjs_approved', true ) ) {
				$approved++;
			}
		}
		$not_published = $total - $published - $outdated;

		$log_entries = class_exists( 'WPJS_Sync_Log' ) ? WPJS_Sync_Log::get( 10 ) : array();
		$action      = admin_url( 'admin-post.php' );
		?>
		<div class="wrap">
			<h1>
				<span class="dashicons dashicons-share-alt" style="font-size:28px;width:28px;height:28px;vertical-align:middle;margin-right:8px;"></span>
				Ray Bogman Jekyll Sync — Dashboard
			</h1>

			<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0;">
				<div class="card" style="flex:1;min-width:150px;text-align:center;padding:20px;">
					<div style="font-size:32px;font-weight:700;color:#2271b1;"><?php echo esc_html( $total ); ?></div>
					<div>Total Posts/Pages</div>
				</div>
				<div class="card" style="flex:1;min-width:150px;text-align:center;padding:20px;">
					<div style="font-size:32px;font-weight:700;color:#00a32a;"><?php echo esc_html( $published ); ?></div>
					<div>Published</div>
				</div>
				<div class="card" style="flex:1;min-width:150px;text-align:center;padding:20px;">
					<div style="font-size:32px;font-weight:700;color:#dba617;"><?php echo esc_html( $outdated ); ?></div>
					<div>Outdated</div>
				</div>
				<div class="card" style="flex:1;min-width:150px;text-align:center;padding:20px;">
					<div style="font-size:32px;font-weight:700;color:#d63638;"><?php echo esc_html( $not_published ); ?></div>
					<div>Not Published</div>
				</div>
				<div class="card" style="flex:1;min-width:150px;text-align:center;padding:20px;">
					<div style="font-size:32px;font-weight:700;color:#2271b1;"><?php echo esc_html( $approved ); ?></div>
					<div>Approved</div>
				</div>
			</div>

			<div class="card" style="padding:20px;margin-top:16px;">
				<h2 style="margin-top:0;">Recent Activity</h2>
				<table class="widefat striped">
					<thead>
						<tr><th>Date</th><th>User</th><th>Post</th><th>Action</th><th>Result</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $log_entries as $e ) :
						$color = strpos( $e['result'], 'success' ) === 0 ? '#00a32a' : '#d63638';
					?>
						<tr>
							<td><?php echo esc_html( $e['date'] ); ?></td>
							<td><?php echo esc_html( $e['user'] ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $e['post_id'] ) ); ?>"><?php echo esc_html( $e['title'] ); ?></a></td>
							<td><?php echo esc_html( $e['action'] ); ?></td>
							<td style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;"><?php echo esc_html( $e['result'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $log_entries ) ) : ?>
						<tr><td colspan="5">No sync activity yet.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="card" style="padding:20px;margin-top:16px;">
				<h2 style="margin-top:0;">Quick Actions</h2>
				<div style="display:flex;gap:8px;flex-wrap:wrap;">
					<form method="post" action="<?php echo esc_url( $action ); ?>" style="margin:0;">
						<input type="hidden" name="action" value="wpjs_publish_approved" />
						<?php wp_nonce_field( 'wpjs_publish_approved' ); ?>
						<button type="submit" class="button button-primary">Push All Approved</button>
					</form>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-articles' ) ); ?>" class="button">View Articles</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-settings' ) ); ?>" class="button">Settings</a>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─── Log tab ────────────────────────────────────── */

	private function render_log_tab() {
		$entries = class_exists( 'WPJS_Sync_Log' ) ? WPJS_Sync_Log::get( 100 ) : array();
		?>
		<div>
			<div class="card" style="padding:20px;">
				<div style="display:flex;justify-content:space-between;align-items:center;">
					<h2 style="margin-top:0;">Sync Log</h2>
					<button type="button" id="wpjs-clear-log" class="button" onclick="return confirm('Clear the entire sync log?');">Clear Log</button>
				</div>
				<p class="description"><?php echo count( $entries ); ?> entries (max 500)</p>
				<table class="widefat striped" style="margin-top:12px;">
					<thead>
						<tr><th>Date</th><th>User</th><th>Post</th><th>Action</th><th>Path</th><th>Result</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $entries as $e ) :
						$color = strpos( $e['result'], 'success' ) === 0 ? '#00a32a' : '#d63638';
					?>
						<tr>
							<td><?php echo esc_html( $e['date'] ); ?></td>
							<td><?php echo esc_html( $e['user'] ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_post_link( $e['post_id'] ) ); ?>"><?php echo esc_html( $e['title'] ); ?></a></td>
							<td><?php echo esc_html( $e['action'] ); ?></td>
							<td><code><?php echo esc_html( $e['path'] ); ?></code></td>
							<td style="color:<?php echo esc_attr( $color ); ?>;font-weight:600;"><?php echo esc_html( $e['result'] ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $entries ) ) : ?>
						<tr><td colspan="6">No sync activity yet.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/* ─── AJAX: diff ─────────────────────────────────── */

	public function ajax_diff() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_send_json_error( 'Post not found.' ); }

		// Generate current markdown.
		$new_content = WPJS_Converter::post_to_markdown( $post );
		$filename    = WPJS_Converter::filename( $post );
		$base        = $post->post_type === 'page'
			? WPJS_Settings::get( 'pages_path', '_pages' )
			: WPJS_Settings::get( 'posts_path', '_posts' );
		$path = $base . '/' . $filename;

		// Fetch existing from GitHub.
		$client      = new WPJS_GitHub_Client();
		$old_content = $client->get_file_content( $path );

		if ( is_wp_error( $old_content ) ) {
			// File doesn't exist yet.
			wp_send_json_success( array(
				'filename' => $filename,
				'html'     => '<div class="notice notice-info inline" style="margin:0;padding:12px;"><p>New file — no previous version on Jekyll.</p></div><pre style="background:#d4edda;padding:12px;border-radius:4px;font-size:13px;white-space:pre-wrap;">' . esc_html( $new_content ) . '</pre>',
			) );
		}

		$diff = WPJS_Diff::compute( $old_content, $new_content );
		$html = WPJS_Diff::render_html( $diff );
		wp_send_json_success( array( 'filename' => $filename, 'html' => $html ) );
	}

	/* ─── AJAX: clear log ────────────────────────────── */

	public function ajax_generate_ai() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_send_json_error( 'Post not found.' ); }

		$results = WPJS_Publisher::generate_ai_metadata( $post );
		wp_send_json_success( $results );
	}

	public function ajax_regen_description() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$post = $id ? get_post( $id ) : null;
		if ( ! $post ) { wp_send_json_error( 'Post not found.' ); }
		// Clear existing excerpt to force regeneration.
		wp_update_post( array( 'ID' => $post->ID, 'post_excerpt' => '' ) );
		$desc = WPJS_Publisher::ai_generate_description( $post );
		if ( ! $desc ) { wp_send_json_error( 'AI generation failed.' ); }
		wp_send_json_success( array( 'description' => $desc ) );
	}

	public function ajax_save_description() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$desc = sanitize_text_field( wp_unslash( $_POST['description'] ?? '' ) );
		if ( ! $id ) { wp_send_json_error( 'Invalid post.' ); }
		wp_update_post( array( 'ID' => $id, 'post_excerpt' => $desc ) );
		wp_send_json_success();
	}

	public function ajax_regen_alt() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$att_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		if ( ! $att_id ) { wp_send_json_error( 'Invalid attachment.' ); }
		delete_post_meta( $att_id, '_wp_attachment_image_alt' );
		$alt = WPJS_Publisher::ai_generate_alt( $att_id );
		if ( ! $alt ) { wp_send_json_error( 'AI generation failed.' ); }
		wp_send_json_success( array( 'alt' => $alt ) );
	}

	public function ajax_save_alt() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$att_id = isset( $_POST['attachment_id'] ) ? (int) $_POST['attachment_id'] : 0;
		$alt    = sanitize_text_field( wp_unslash( $_POST['alt'] ?? '' ) );
		if ( ! $att_id ) { wp_send_json_error( 'Invalid attachment.' ); }
		update_post_meta( $att_id, '_wp_attachment_image_alt', $alt );
		wp_send_json_success();
	}

	public function ajax_validate_ai() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$provider = sanitize_text_field( wp_unslash( $_POST['provider'] ?? '' ) );
		$result   = WPJS_AI_Client::validate_key( $provider );
		if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); }
		wp_send_json_success( array( 'provider' => $provider ?: WPJS_AI_Client::get_provider(), 'model' => WPJS_AI_Client::get_model( $provider ) ) );
	}

	public function ajax_clear_log() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		WPJS_Sync_Log::clear();
		wp_send_json_success();
	}

	/* ─── AJAX: pull ─────────────────────────────────── */

	public function ajax_list_jekyll_posts() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$posts = WPJS_Puller::list_jekyll_posts();
		if ( is_wp_error( $posts ) ) { wp_send_json_error( $posts->get_error_message() ); }
		wp_send_json_success( $posts );
	}

	public function ajax_pull_post() {
		check_ajax_referer( 'wpjs_ajax' );
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( 'Permission denied.' ); }
		$path = sanitize_text_field( wp_unslash( $_POST['path'] ?? '' ) );
		if ( ! $path ) { wp_send_json_error( 'No path provided.' ); }
		$result = WPJS_Puller::pull_post( $path );
		if ( is_wp_error( $result ) ) { wp_send_json_error( $result->get_error_message() ); }
		wp_send_json_success( array( 'post_id' => $result, 'edit_url' => get_edit_post_link( $result, 'raw' ) ) );
	}

	/* ─── Pull tab ───────────────────────────────────── */

	private function render_pull_tab() {
		?>
		<div>
			<div class="card" style="padding:20px;">
				<h2 style="margin-top:0;">
					<span class="dashicons dashicons-download" style="font-size:24px;width:24px;height:24px;color:#2271b1;margin-right:8px;vertical-align:middle;"></span>
					Pull from Jekyll
				</h2>
				<p style="font-size:14px;line-height:1.6;">
					Import posts from your Jekyll repository back into WordPress. Select posts to pull — they will be created as drafts (new) or updated (existing).
				</p>
				<p>
					<button type="button" id="wpjs-load-jekyll-posts" class="button button-primary">Load Jekyll Posts</button>
					<span class="spinner" id="wpjs-pull-spinner" style="float:none;margin-top:4px;"></span>
					<span id="wpjs-pull-status" style="margin-left:8px;"></span>
				</p>
			</div>

			<div id="wpjs-pull-summary" style="display:none;margin-top:16px;">
				<div class="notice notice-success" style="padding:12px;">
					<strong id="wpjs-pull-summary-text"></strong>
					<a href="<?php echo esc_url( admin_url( 'edit.php?post_status=draft' ) ); ?>" class="button button-secondary" style="margin-left:12px;">View Draft Posts</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpjs-articles' ) ); ?>" class="button button-secondary" style="margin-left:4px;">View Articles</a>
				</div>
			</div>

			<div id="wpjs-pull-list" style="display:none;margin-top:16px;">
				<div class="card" style="padding:20px;">
					<div style="display:flex;justify-content:space-between;align-items:center;">
						<h3 style="margin-top:0;">Jekyll Posts</h3>
						<button type="button" id="wpjs-pull-all" class="button button-primary">Import All New</button>
					</div>
					<table class="widefat striped" id="wpjs-pull-table">
						<thead>
							<tr>
								<th>File</th>
								<th>Slug</th>
								<th>In WordPress</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
				</div>
			</div>
		</div>
		<?php
	}

	/* ─── Helpers ────────────────────────────────────── */

	private function check_cap_and_nonce( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		check_admin_referer( $action );
	}
}
