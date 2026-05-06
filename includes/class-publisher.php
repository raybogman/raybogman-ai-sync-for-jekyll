<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Publisher {

	const META_APPROVED       = '_wpjs_approved';
	const META_LAST_PUSH      = '_wpjs_last_push';
	const DEFAULT_IMAGES_PATH = 'assets/images';

	public static function init() {
		add_action( 'transition_post_status', array( __CLASS__, 'on_publish' ), 10, 3 );
	}

	public static function on_publish( $new_status, $old_status, $post ) {
		if ( $new_status !== 'publish' ) { return; }
		if ( ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) { return; }
		if ( WPJS_Settings::get( 'auto_push_on_publish', '0' ) !== '1' ) { return; }
		if ( ! WPJS_GitHub_OAuth::is_connected() ) { return; }

		self::publish( $post );
	}

	public static function publish( WP_Post $post ) {
		$client = new WPJS_GitHub_Client();

		// AI: generate missing description before conversion.
		self::maybe_ai_description( $post );

		// Push featured image to Jekyll repo if present.
		$jekyll_image_path = '';
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			// AI: generate missing alt text for featured image.
			self::maybe_ai_alt_text( $thumb_id );
			$image_result = self::push_featured_image( $post, $thumb_id, $client );
			if ( ! is_wp_error( $image_result ) ) {
				$jekyll_image_path = $image_result;
			}
		}

		$content  = WPJS_Converter::post_to_markdown( $post, $jekyll_image_path );

		// Upload inline body images to Jekyll and rewrite their URLs.
		$content  = self::sync_inline_images( $content, $post, $client );

		$filename = WPJS_Converter::filename( $post );
		$base     = $post->post_type === 'page'
			? WPJS_Settings::get( 'pages_path', '_pages' )
			: WPJS_Settings::get( 'posts_path', '_posts' );
		$path     = $base . '/' . $filename;

		$result = $client->put_file(
			$path,
			$content,
			sprintf( 'Sync "%s" from WordPress', $post->post_title )
		);

		WPJS_Sync_Log::add( $post->ID, 'push', $path, $result );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $post->ID, self::META_LAST_PUSH, current_time( 'mysql' ) );

		// Trigger GitHub Actions workflow if configured.
		$workflow = WPJS_Settings::get( 'workflow_file', '' );
		if ( $workflow ) {
			$client->trigger_workflow( $workflow );
		}

		return $path;
	}

	public static function delete( WP_Post $post ) {
		$filename = WPJS_Converter::filename( $post );
		$base     = $post->post_type === 'page'
			? WPJS_Settings::get( 'pages_path', '_pages' )
			: WPJS_Settings::get( 'posts_path', '_posts' );
		$path     = $base . '/' . $filename;

		$client = new WPJS_GitHub_Client();
		$result = $client->delete_file( $path, sprintf( 'Delete "%s" from Jekyll', $post->post_title ) );

		WPJS_Sync_Log::add( $post->ID, 'delete', $path, $result );

		if ( ! is_wp_error( $result ) ) {
			delete_post_meta( $post->ID, self::META_LAST_PUSH );
		}

		return $result;
	}

	private static function sync_inline_images( $content, WP_Post $post, WPJS_GitHub_Client $client ) {
		$profile    = json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true );
		$images_dir = $profile['config']['images_path'] ?? self::DEFAULT_IMAGES_PATH;
		$wp_base    = rtrim( get_site_url(), '/' );

		// Match ANY URL containing /wp-content/uploads/ — catches both WP and rewritten Jekyll domain.
		$pattern = '/(https?:\/\/[^\s)"\']+\/wp-content\/uploads\/[^\s)"\']+\.(?:jpg|jpeg|png|gif|webp|svg))/i';
		if ( ! preg_match_all( $pattern, $content, $matches ) ) {
			return $content;
		}

		$urls = array_unique( $matches[1] );
		foreach ( $urls as $url ) {
			// Convert rewritten URL back to WP URL for media library lookup.
			$wp_url = $url;
			$jekyll_base = WPJS_Converter::get_jekyll_base_url();
			if ( $jekyll_base && strpos( $url, $jekyll_base ) === 0 ) {
				$wp_url = str_replace( $jekyll_base, $wp_base, $url );
			}

			// Try to find the attachment in WP media library.
			$attachment_id = attachment_url_to_postid( $wp_url );
			$file_path     = null;

			if ( $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );
				// AI: generate missing alt text for inline image.
				self::maybe_ai_alt_text( $attachment_id );
			}

			// Also try without size suffix (e.g. image-300x300.png → image.png).
			if ( ( ! $file_path || ! file_exists( $file_path ) ) && ! $attachment_id ) {
				$clean_url = preg_replace( '/-\d+x\d+\./', '.', $wp_url );
				$attachment_id = attachment_url_to_postid( $clean_url );
				if ( $attachment_id ) {
					$file_path = get_attached_file( $attachment_id );
				}
			}

			if ( $file_path && file_exists( $file_path ) ) {
				$image_data = file_get_contents( $file_path );
			} else {
				// Download from WP URL.
				$download_url = $wp_url;
				$response = wp_remote_get( $download_url, array( 'timeout' => 30 ) );
				if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
					continue;
				}
				$image_data = wp_remote_retrieve_body( $response );
			}

			if ( empty( $image_data ) ) { continue; }

			// Generate Jekyll filename from the original URL basename.
			$basename    = sanitize_file_name( basename( wp_parse_url( $url, PHP_URL_PATH ) ) );
			$jekyll_path = $images_dir . '/' . $basename;

			$upload = $client->put_file(
				$jekyll_path,
				$image_data,
				sprintf( 'Upload inline image %s for "%s"', $basename, $post->post_title )
			);

			if ( ! is_wp_error( $upload ) ) {
				$jekyll_url = '/' . $jekyll_path;
				$content    = str_replace( $url, $jekyll_url, $content );
			}
		}

		return $content;
	}

	private static function push_featured_image( WP_Post $post, $thumb_id, WPJS_GitHub_Client $client ) {
		$file_path = get_attached_file( $thumb_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'wpjs_no_image_file', 'Featured image file not found on disk.' );
		}

		$ext  = pathinfo( $file_path, PATHINFO_EXTENSION );
		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		$profile     = json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true );
		$images_dir  = $profile['config']['images_path'] ?? self::DEFAULT_IMAGES_PATH;
		$jekyll_filename = $slug . '.' . $ext;
		$jekyll_path     = $images_dir . '/' . $jekyll_filename;

		$image_data = file_get_contents( $file_path );
		if ( $image_data === false ) {
			return new WP_Error( 'wpjs_image_read_failed', 'Could not read image file.' );
		}

		$result = $client->put_file(
			$jekyll_path,
			$image_data,
			sprintf( 'Upload image for "%s"', $post->post_title )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return '/' . $jekyll_path;
	}

	private static function maybe_ai_description( WP_Post $post ) {
		if ( WPJS_Settings::get( 'ai_generate_descriptions', '0' ) !== '1' ) { return; }
		if ( ! WPJS_AI_Client::is_available() ) { return; }

		$excerpt  = get_the_excerpt( $post );
		$seo_desc = get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true )
				 ?: get_post_meta( $post->ID, 'rank_math_description', true );

		if ( $excerpt || $seo_desc ) { return; }

		$text = wp_strip_all_tags( $post->post_content );
		if ( strlen( $text ) < 50 ) { return; }

		$desc = WPJS_AI_Client::call(
			"Write an SEO meta description for this blog post. Requirements:\n- 1-2 sentences\n- Max 160 characters\n- Engaging and descriptive\n- Return only the description text, nothing else\n\nPost content:\n" . mb_substr( $text, 0, 2000 )
		);

		if ( $desc ) {
			$desc = mb_substr( trim( $desc, '"\'.' ), 0, 160 );
			wp_update_post( array( 'ID' => $post->ID, 'post_excerpt' => $desc ) );
		}
	}

	private static function maybe_ai_alt_text( $attachment_id ) {
		if ( WPJS_Settings::get( 'ai_generate_alt_text', '0' ) !== '1' ) { return; }
		if ( ! WPJS_AI_Client::is_available() ) { return; }

		$existing_alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $existing_alt ) { return; }

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) { return; }

		// Skip large files (>5MB) to avoid API limits.
		if ( filesize( $file_path ) > 5 * 1024 * 1024 ) { return; }

		$alt = WPJS_AI_Client::describe_image( $file_path );
		if ( $alt ) {
			$alt = mb_substr( trim( $alt, '"\'.' ), 0, 125 );
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		}
	}

	public static function is_approved( $post_id ) {
		return (bool) get_post_meta( $post_id, self::META_APPROVED, true );
	}

	public static function set_approved( $post_id, $approved ) {
		if ( $approved ) {
			update_post_meta( $post_id, self::META_APPROVED, 1 );
		} else {
			delete_post_meta( $post_id, self::META_APPROVED );
		}
	}
}
