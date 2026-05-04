<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Publisher {

	const META_APPROVED  = '_wpjs_approved';
	const META_LAST_PUSH = '_wpjs_last_push';
	const DEFAULT_IMAGES_PATH = 'assets/images';

	public static function publish( WP_Post $post ) {
		$client = new WPJS_GitHub_Client();

		// Push featured image to Jekyll repo if present.
		$jekyll_image_path = '';
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$image_result = self::push_featured_image( $post, $thumb_id, $client );
			if ( ! is_wp_error( $image_result ) ) {
				$jekyll_image_path = $image_result;
			}
		}

		$content  = WPJS_Converter::post_to_markdown( $post, $jekyll_image_path );
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

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		update_post_meta( $post->ID, self::META_LAST_PUSH, current_time( 'mysql' ) );
		return $path;
	}

	private static function push_featured_image( WP_Post $post, $thumb_id, WPJS_GitHub_Client $client ) {
		$file_path = get_attached_file( $thumb_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'wpjs_no_image_file', 'Featured image file not found on disk.' );
		}

		$ext  = pathinfo( $file_path, PATHINFO_EXTENSION );
		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		// Use detected images path from style profile, or fallback.
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
