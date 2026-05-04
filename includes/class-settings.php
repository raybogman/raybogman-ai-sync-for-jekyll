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
