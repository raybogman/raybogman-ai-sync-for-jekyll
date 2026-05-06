<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Settings {

	const OPTION = 'wpjs_settings';

	public static function get( $key, $default = '' ) {
		$opts = get_option( self::OPTION, array() );
		return isset( $opts[ $key ] ) && $opts[ $key ] !== '' ? $opts[ $key ] : $default;
	}

	public static function update( array $input ) {
		$existing = get_option( self::OPTION, array() );

		// Only update fields that are actually present in the input.
		// This prevents cross-tab overwrites when saving from a tab that doesn't include all fields.

		// OAuth app credentials (Connection tab).
		if ( array_key_exists( 'client_id', $input ) ) {
			$existing['client_id'] = sanitize_text_field( $input['client_id'] );
		}
		if ( array_key_exists( 'client_secret', $input ) ) {
			$existing['client_secret'] = sanitize_text_field( $input['client_secret'] );
		}

		// Repo (Connection tab).
		if ( array_key_exists( 'repo_full', $input ) ) {
			$full = sanitize_text_field( $input['repo_full'] );
			if ( $full && strpos( $full, '/' ) !== false ) {
				list( $owner, $repo ) = array_map( 'trim', explode( '/', $full, 2 ) );
				$existing['repo_owner'] = $owner;
				$existing['repo_name']  = $repo;
			}
		}
		if ( array_key_exists( 'branch', $input ) ) {
			$existing['branch'] = sanitize_text_field( $input['branch'] );
		}

		// Content mapping (Content tab) — checkboxes: only update if the form has the marker.
		if ( array_key_exists( 'posts_path', $input ) ) {
			// Content tab was submitted.
			$existing['sync_posts'] = ! empty( $input['sync_posts'] ) ? '1' : '0';
			$existing['sync_pages'] = ! empty( $input['sync_pages'] ) ? '1' : '0';
			$existing['posts_path'] = trim( sanitize_text_field( $input['posts_path'] ), '/' );
			$existing['pages_path'] = trim( sanitize_text_field( $input['pages_path'] ?? '_pages' ), '/' );
		}
		if ( array_key_exists( 'author', $input ) ) {
			$existing['author'] = sanitize_text_field( $input['author'] );
		}
		if ( array_key_exists( 'jekyll_base_url', $input ) ) {
			$existing['jekyll_base_url'] = rtrim( esc_url_raw( $input['jekyll_base_url'] ), '/' );
		}
		if ( array_key_exists( 'workflow_file', $input ) ) {
			$existing['workflow_file'] = sanitize_text_field( $input['workflow_file'] );
		}
		if ( array_key_exists( 'auto_push_on_publish', $input ) ) {
			$existing['auto_push_on_publish'] = ! empty( $input['auto_push_on_publish'] ) ? '1' : '0';
		}
		if ( array_key_exists( 'sync_interval_hours', $input ) ) {
			$existing['sync_interval_hours'] = absint( $input['sync_interval_hours'] );
		}
		if ( array_key_exists( 'sync_cron_mode', $input ) ) {
			$existing['sync_cron_mode'] = sanitize_text_field( $input['sync_cron_mode'] );
		}
		if ( array_key_exists( 'ai_generate_descriptions', $input ) ) {
			$existing['ai_generate_descriptions'] = ! empty( $input['ai_generate_descriptions'] ) ? '1' : '0';
		}
		if ( array_key_exists( 'ai_generate_alt_text', $input ) ) {
			$existing['ai_generate_alt_text'] = ! empty( $input['ai_generate_alt_text'] ) ? '1' : '0';
		}
		if ( array_key_exists( 'ai_provider', $input ) ) {
			$existing['ai_provider'] = sanitize_text_field( $input['ai_provider'] );
		}
		if ( array_key_exists( 'ai_claude_api_key', $input ) ) {
			$existing['ai_claude_api_key'] = sanitize_text_field( $input['ai_claude_api_key'] );
		}
		if ( array_key_exists( 'ai_claude_model', $input ) ) {
			$existing['ai_claude_model'] = sanitize_text_field( $input['ai_claude_model'] );
		}
		if ( array_key_exists( 'ai_openai_api_key', $input ) ) {
			$existing['ai_openai_api_key'] = sanitize_text_field( $input['ai_openai_api_key'] );
		}
		if ( array_key_exists( 'ai_openai_model', $input ) ) {
			$existing['ai_openai_model'] = sanitize_text_field( $input['ai_openai_model'] );
		}

		// Conversion mode (Content Style tab).
		if ( array_key_exists( 'conversion_mode', $input ) ) {
			$existing['conversion_mode'] = sanitize_text_field( $input['conversion_mode'] );
		}

		update_option( self::OPTION, $existing );
		return $existing;
	}

	public static function update_key( $key, $value ) {
		$opts = get_option( self::OPTION, array() );
		$opts[ $key ] = $value;
		update_option( self::OPTION, $opts );
	}
}
