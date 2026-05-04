<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_GitHub_OAuth {

	const STATE_TRANSIENT = 'wpjs_oauth_state_';

	public function __construct() {
		// Handle OAuth callback via admin-post.php (works for GET redirects from GitHub).
		add_action( 'admin_post_wpjs_oauth_callback', array( $this, 'handle_callback' ) );
		add_action( 'admin_post_wpjs_disconnect', array( $this, 'disconnect' ) );
	}

	public static function is_connected() {
		return (bool) WPJS_Settings::get( 'github_token' );
	}

	public static function connected_user() {
		return array(
			'login'  => WPJS_Settings::get( 'github_login' ),
			'avatar' => WPJS_Settings::get( 'github_avatar' ),
		);
	}

	public static function get_authorize_url() {
		$client_id = WPJS_Settings::get( 'client_id' );
		if ( ! $client_id ) {
			return '';
		}

		$user_id  = get_current_user_id();
		$existing = get_transient( self::STATE_TRANSIENT . $user_id );
		if ( $existing ) {
			$state = $existing;
		} else {
			$state = wp_generate_password( 32, false );
			set_transient( self::STATE_TRANSIENT . $user_id, $state, 600 );
		}

		return add_query_arg( array(
			'client_id'    => $client_id,
			'redirect_uri' => self::callback_url(),
			'scope'        => 'repo',
			'state'        => $state,
		), 'https://github.com/login/oauth/authorize' );
	}

	public static function callback_url() {
		return admin_url( 'admin-post.php?action=wpjs_oauth_callback' );
	}

	public function handle_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		// GitHub error (user denied, etc.). Nonce verification via OAuth state parameter below.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback, verified via state param.
		if ( ! empty( $_GET['error'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$desc = sanitize_text_field( wp_unslash( $_GET['error_description'] ?? $_GET['error'] ?? 'Unknown error' ) );
			self::set_notice( 'error', 'GitHub authorization failed: ' . $desc );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
			exit;
		}

		// Must have code and state. State acts as CSRF/nonce token for OAuth.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback, verified via state param.
		$code  = sanitize_text_field( wp_unslash( $_GET['code'] ?? '' ) );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback, verified via state param.
		$state = sanitize_text_field( wp_unslash( $_GET['state'] ?? '' ) );

		if ( ! $code || ! $state ) {
			self::set_notice( 'error', 'Missing code or state from GitHub. Please try again.' );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
			exit;
		}

		// Verify state (CSRF protection).
		$user_id  = get_current_user_id();
		$expected = get_transient( self::STATE_TRANSIENT . $user_id );
		delete_transient( self::STATE_TRANSIENT . $user_id );

		if ( ! $expected || ! hash_equals( $expected, $state ) ) {
			self::set_notice( 'error', 'OAuth state mismatch — please try logging in again.' );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
			exit;
		}

		// Exchange code for access token.
		$client_id     = WPJS_Settings::get( 'client_id' );
		$client_secret = WPJS_Settings::get( 'client_secret' );

		$response = wp_remote_post( 'https://github.com/login/oauth/access_token', array(
			'headers' => array( 'Accept' => 'application/json' ),
			'body'    => array(
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'code'          => $code,
				'redirect_uri'  => self::callback_url(),
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			self::set_notice( 'error', 'Token exchange failed: ' . $response->get_error_message() );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
			exit;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			$err = $data['error_description'] ?? $data['error'] ?? 'Unknown error (HTTP ' . wp_remote_retrieve_response_code( $response ) . ')';
			self::set_notice( 'error', 'GitHub denied the token: ' . $err );
			wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
			exit;
		}

		$token = sanitize_text_field( $data['access_token'] );

		// Fetch the authenticated user.
		$user_resp = wp_remote_get( 'https://api.github.com/user', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/vnd.github+json',
				'User-Agent'    => 'WP-Jekyll-Sync',
			),
			'timeout' => 15,
		) );

		$login  = '';
		$avatar = '';
		if ( ! is_wp_error( $user_resp ) && wp_remote_retrieve_response_code( $user_resp ) === 200 ) {
			$user_data = json_decode( wp_remote_retrieve_body( $user_resp ), true );
			$login     = $user_data['login'] ?? '';
			$avatar    = $user_data['avatar_url'] ?? '';
		}

		// Persist token and user info (merge with existing settings).
		$opts                  = get_option( WPJS_Settings::OPTION, array() );
		$opts['github_token']  = $token;
		$opts['github_login']  = $login;
		$opts['github_avatar'] = $avatar;
		update_option( WPJS_Settings::OPTION, $opts );

		self::set_notice( 'success', 'Connected to GitHub as <strong>' . esc_html( $login ) . '</strong>. Now select your repository and branch below.' );
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
		exit;
	}

	public function disconnect() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Permission denied.' ); }
		check_admin_referer( 'wpjs_disconnect' );

		$opts = get_option( WPJS_Settings::OPTION, array() );
		unset( $opts['github_token'], $opts['github_login'], $opts['github_avatar'] );
		update_option( WPJS_Settings::OPTION, $opts );

		self::set_notice( 'success', 'Disconnected from GitHub.' );
		wp_safe_redirect( admin_url( 'admin.php?page=wpjs-settings' ) );
		exit;
	}

	private static function set_notice( $type, $message ) {
		set_transient( 'wpjs_notice_' . get_current_user_id(), array(
			'type'    => $type,
			'message' => $message,
		), 60 );
	}
}
