<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_AI_Client {

	public static function is_available() {
		return (bool) ( WPJS_Settings::get( 'ai_claude_api_key', '' ) || WPJS_Settings::get( 'ai_openai_api_key', '' ) );
	}

	public static function get_provider() {
		return WPJS_Settings::get( 'ai_provider', 'claude' );
	}

	public static function get_api_key( $provider = '' ) {
		if ( ! $provider ) { $provider = self::get_provider(); }
		if ( $provider === 'openai' ) {
			return WPJS_Settings::get( 'ai_openai_api_key', '' );
		}
		return WPJS_Settings::get( 'ai_claude_api_key', '' );
	}

	public static function get_model( $provider = '' ) {
		if ( ! $provider ) { $provider = self::get_provider(); }
		if ( $provider === 'openai' ) {
			return WPJS_Settings::get( 'ai_openai_model', 'gpt-4o' );
		}
		return WPJS_Settings::get( 'ai_claude_model', 'claude-sonnet-4-6' );
	}

	public static function call( $prompt, $max_tokens = 500 ) {
		$provider = self::get_provider();
		if ( $provider === 'openai' ) {
			return self::call_openai( $prompt, $max_tokens );
		}
		return self::call_claude( $prompt, $max_tokens );
	}

	public static function describe_image( $file_path ) {
		if ( ! file_exists( $file_path ) ) { return ''; }

		$mime = wp_check_filetype( $file_path )['type'] ?? 'image/jpeg';
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		$data = $wp_filesystem->get_contents( $file_path );
		if ( ! $data ) { return ''; }
		$base64 = base64_encode( $data );

		$provider = self::get_provider();
		if ( $provider === 'openai' ) {
			return self::describe_image_openai( $base64, $mime );
		}
		return self::describe_image_claude( $base64, $mime );
	}

	public static function validate_key( $provider = '' ) {
		if ( ! $provider ) { $provider = self::get_provider(); }
		$key   = self::get_api_key( $provider );
		$model = self::get_model( $provider );
		if ( ! $key ) { return new WP_Error( 'no_key', 'No API key configured for ' . $provider . '.' ); }

		if ( $provider === 'openai' ) {
			$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'      => $model,
					'max_tokens' => 10,
					'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
				) ),
				'timeout' => 15,
			) );
		} else {
			$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
				'headers' => array(
					'x-api-key'         => $key,
					'anthropic-version'  => '2023-06-01',
					'Content-Type'       => 'application/json',
				),
				'body'    => wp_json_encode( array(
					'model'      => $model,
					'max_tokens' => 10,
					'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
				) ),
				'timeout' => 15,
			) );
		}

		if ( is_wp_error( $response ) ) { return $response; }
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) { return true; }
		return new WP_Error( 'api_error', 'API returned HTTP ' . $code );
	}

	private static function call_claude( $prompt, $max_tokens ) {
		$key = self::get_api_key();
		if ( ! $key ) { return ''; }

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version'  => '2023-06-01',
				'Content-Type'       => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => self::get_model(),
				'max_tokens' => $max_tokens,
				'messages'   => array(
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) { return ''; }
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) { return ''; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $data['content'][0]['text'] ?? '' );
	}

	private static function call_openai( $prompt, $max_tokens ) {
		$key = self::get_api_key();
		if ( ! $key ) { return ''; }

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => self::get_model(),
				'max_tokens' => $max_tokens,
				'messages'   => array(
					array( 'role' => 'system', 'content' => 'You are a helpful SEO assistant. Be concise.' ),
					array( 'role' => 'user', 'content' => $prompt ),
				),
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) { return ''; }
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) { return ''; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $data['choices'][0]['message']['content'] ?? '' );
	}

	private static function describe_image_claude( $base64, $mime ) {
		$key = self::get_api_key();
		if ( ! $key ) { return ''; }

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
			'headers' => array(
				'x-api-key'         => $key,
				'anthropic-version'  => '2023-06-01',
				'Content-Type'       => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => self::get_model(),
				'max_tokens' => 100,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type'   => 'image',
								'source' => array(
									'type'         => 'base64',
									'media_type'   => $mime,
									'data'         => $base64,
								),
							),
							array(
								'type' => 'text',
								'text' => 'Describe this image in one concise sentence for HTML alt text. Max 125 characters. Return only the description, no quotes.',
							),
						),
					),
				),
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) { return ''; }
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) { return ''; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $data['content'][0]['text'] ?? '' );
	}

	private static function describe_image_openai( $base64, $mime ) {
		$key = self::get_api_key();
		if ( ! $key ) { return ''; }

		$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $key,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( array(
				'model'      => 'gpt-4o',
				'max_tokens' => 100,
				'messages'   => array(
					array(
						'role'    => 'user',
						'content' => array(
							array( 'type' => 'text', 'text' => 'Describe this image in one concise sentence for HTML alt text. Max 125 characters. Return only the description, no quotes.' ),
							array(
								'type'      => 'image_url',
								'image_url' => array(
									'url' => 'data:' . $mime . ';base64,' . $base64,
								),
							),
						),
					),
				),
			) ),
			'timeout' => 60,
		) );

		if ( is_wp_error( $response ) ) { return ''; }
		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) { return ''; }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return trim( $data['choices'][0]['message']['content'] ?? '' );
	}
}
