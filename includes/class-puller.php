<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Puller {

	/** Post meta: path of the Jekyll file this post was pulled from. */
	const META_JEKYLL_PATH = '_wpjs_jekyll_path';

	/**
	 * Slug from a Jekyll filename: strips the date prefix and extension.
	 */
	public static function slug_from_path( $path ) {
		$name = basename( (string) $path );
		$name = preg_replace( '/^\d{4}-\d{2}-\d{2}-/', '', $name );
		return sanitize_title( preg_replace( '/\.(md|markdown)$/i', '', $name ) );
	}

	/**
	 * Timestamp from the front matter date (any strtotime-parsable form,
	 * e.g. "2026-10-01 08:00:00 +0000"), falling back to the filename date.
	 */
	private static function date_from_front_matter( $fm_date, $path ) {
		$ts = ! empty( $fm_date ) ? strtotime( (string) $fm_date ) : false;
		if ( ! $ts && preg_match( '/^(\d{4}-\d{2}-\d{2})-/', basename( (string) $path ), $m ) ) {
			$ts = strtotime( $m[1] . ' 09:00:00 UTC' );
		}
		return $ts ? (int) $ts : 0;
	}

	/**
	 * Find the WP post for a Jekyll file: by stored path, by filename slug,
	 * then by title slug (how versions before 1.0.9 created the post).
	 */
	public static function find_existing( $jekyll_path, $slug, $title_slug = '' ) {
		$by_meta = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => self::META_JEKYLL_PATH, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => $jekyll_path, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		) );
		if ( $by_meta ) {
			return $by_meta[0];
		}
		foreach ( array_unique( array_filter( array( $slug, $title_slug ) ) ) as $candidate ) {
			$post = get_page_by_path( $candidate, OBJECT, array( 'post', 'page' ) );
			if ( $post && 'trash' !== $post->post_status ) {
				return $post;
			}
		}
		return null;
	}

	public static function pull_post( $jekyll_path ) {
		$client  = new WPJS_GitHub_Client();
		$content = $client->get_file_content( $jekyll_path );
		if ( is_wp_error( $content ) ) { return $content; }

		$parsed = self::parse_markdown( $content );
		if ( is_wp_error( $parsed ) ) { return $parsed; }

		// The Jekyll filename is the URL slug on the Jekyll site, so it is the
		// slug WordPress should use too (a title-based slug often differs).
		$slug = self::slug_from_path( $jekyll_path );
		if ( '' === $slug ) {
			$slug = $parsed['slug'];
		}

		$existing = self::find_existing( $jekyll_path, $slug, $parsed['slug'] );

		$post_data = array(
			'post_title'   => $parsed['title'],
			'post_content' => $parsed['html_content'],
			'post_status'  => 'draft',
			'post_type'    => $parsed['layout'] === 'page' ? 'page' : 'post',
		);

		// Publication date: front matter `date`, else the filename date.
		$timestamp = self::date_from_front_matter( $parsed['date'], $jekyll_path );

		if ( $existing ) {
			$post_data['ID']          = $existing->ID;
			$post_data['post_status'] = $existing->post_status;
			// Drafts are safe to correct; a published post keeps its URL and date.
			if ( 'draft' === $existing->post_status ) {
				$post_data['post_name'] = $slug;
				if ( $timestamp ) {
					$post_data['post_date']     = wp_date( 'Y-m-d H:i:s', $timestamp );
					$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
				}
			}
			$result = wp_update_post( $post_data, true );
		} else {
			$post_data['post_name'] = $slug;
			if ( $timestamp ) {
				$post_data['post_date']     = wp_date( 'Y-m-d H:i:s', $timestamp );
				$post_data['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
			$result = wp_insert_post( $post_data, true );
		}

		if ( is_wp_error( $result ) ) { return $result; }

		$post_id = (int) $result;
		update_post_meta( $post_id, self::META_JEKYLL_PATH, $jekyll_path );

		// Save meta.
		if ( ! empty( $parsed['description'] ) ) {
			wp_update_post( array( 'ID' => $post_id, 'post_excerpt' => $parsed['description'] ) );
		}
		if ( ! empty( $parsed['tags'] ) && is_array( $parsed['tags'] ) ) {
			wp_set_post_tags( $post_id, $parsed['tags'] );
		}
		if ( ! empty( $parsed['categories'] ) && is_array( $parsed['categories'] ) ) {
			wp_set_post_categories( $post_id, array_map( function ( $cat ) {
				$term = get_cat_ID( $cat );
				if ( ! $term ) {
					$new = wp_create_category( $cat );
					return is_wp_error( $new ) ? 0 : $new;
				}
				return $term;
			}, $parsed['categories'] ) );
		}

		// Mark as synced.
		update_post_meta( $post_id, WPJS_Publisher::META_LAST_PUSH, current_time( 'mysql' ) );

		WPJS_Sync_Log::add( $post_id, 'pull', $jekyll_path, 'success' );

		return $post_id;
	}

	public static function list_jekyll_posts() {
		$client    = new WPJS_GitHub_Client();
		$posts_dir = WPJS_Settings::get( 'posts_path', '_posts' );
		$files     = $client->list_directory( $posts_dir );
		if ( is_wp_error( $files ) ) { return $files; }

		$md_files = array_filter( $files, function ( $f ) {
			return $f['type'] === 'file' && preg_match( '/\.(md|markdown)$/i', $f['name'] );
		} );

		$result = array();
		foreach ( $md_files as $f ) {
			$slug = preg_replace( '/^\d{4}-\d{2}-\d{2}-/', '', $f['name'] );
			$slug = preg_replace( '/\.(md|markdown)$/i', '', $slug );

			// Check if exists in WP (stored Jekyll path first, then slug).
			$wp_post    = self::find_existing( $f['path'], $slug );
			$last_push  = $wp_post ? get_post_meta( $wp_post->ID, WPJS_Publisher::META_LAST_PUSH, true ) : '';

			$result[] = array(
				'name'      => $f['name'],
				'path'      => $f['path'],
				'slug'      => $slug,
				'wp_exists' => (bool) $wp_post,
				'wp_id'     => $wp_post ? $wp_post->ID : 0,
				'wp_title'  => $wp_post ? $wp_post->post_title : '',
				'synced'    => (bool) $last_push,
			);
		}

		return $result;
	}

	private static function parse_markdown( $content ) {
		// Split front matter and body.
		if ( ! preg_match( '/\A---\s*\n(.*?)\n---\s*\n(.*)\z/s', $content, $m ) ) {
			return new WP_Error( 'wpjs_no_frontmatter', 'No YAML front matter found.' );
		}

		$yaml_str = $m[1];
		$markdown = trim( $m[2] );

		// Parse YAML front matter.
		$fm = array();
		$current_key = null;
		$current_array = array();
		foreach ( explode( "\n", $yaml_str ) as $line ) {
			if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_-]*):\s*(.*)$/', $line, $match ) ) {
				// Save previous array if any.
				if ( $current_key && $current_array ) {
					$fm[ $current_key ] = $current_array;
					$current_array = array();
				}
				$key   = $match[1];
				$value = trim( $match[2], " \t\"'" );
				$current_key = $key;

				if ( $value === '' ) {
					// Might be a block array.
					$fm[ $key ] = '';
				} elseif ( preg_match( '/^\[(.+)\]$/', $value, $arr ) ) {
					// Inline array.
					$fm[ $key ] = array_map( 'trim', explode( ',', $arr[1] ) );
					$current_key = null;
				} else {
					$fm[ $key ] = $value;
					$current_key = $key;
				}
			} elseif ( preg_match( '/^\s+-\s+(.+)$/', $line, $match ) && $current_key ) {
				$current_array[] = trim( $match[1], " \t\"'" );
			}
		}
		if ( $current_key && $current_array ) {
			$fm[ $current_key ] = $current_array;
		}

		// Extract slug from filename-style title or slug field.
		$slug = '';
		if ( ! empty( $fm['slug'] ) ) {
			$slug = sanitize_title( $fm['slug'] );
		} elseif ( ! empty( $fm['title'] ) ) {
			$slug = sanitize_title( $fm['title'] );
		}

		// Convert Markdown back to HTML (basic).
		$html = self::markdown_to_html( $markdown );

		return array(
			'title'        => $fm['title'] ?? '',
			'slug'         => $slug,
			'date'         => $fm['date'] ?? '',
			'layout'       => $fm['layout'] ?? 'post',
			'description'  => $fm['description'] ?? ( $fm['excerpt'] ?? '' ),
			'tags'         => isset( $fm['tags'] ) && is_array( $fm['tags'] ) ? $fm['tags'] : array(),
			'categories'   => isset( $fm['categories'] ) && is_array( $fm['categories'] ) ? $fm['categories'] : array(),
			'html_content' => $html,
		);
	}

	private static function markdown_to_html( $md ) {
		$html = $md;

		// Headings.
		for ( $i = 6; $i >= 1; $i-- ) {
			$html = preg_replace( '/^' . str_repeat( '#', $i ) . '\s+(.+)$/m', '<h' . $i . '>$1</h' . $i . '>', $html );
		}

		// Bold and italic.
		$html = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/__(.+?)__/', '<strong>$1</strong>', $html );
		$html = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $html );
		$html = preg_replace( '/_(.+?)_/', '<em>$1</em>', $html );

		// Inline code.
		$html = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $html );

		// Code blocks.
		$html = preg_replace_callback( '/```[a-z]*\n(.*?)\n```/s', function ( $m ) {
			return '<pre><code>' . esc_html( $m[1] ) . '</code></pre>';
		}, $html );

		// Images.
		$html = preg_replace( '/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" />', $html );

		// Links.
		$html = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $html );

		// Horizontal rules.
		$html = preg_replace( '/^---+$/m', '<hr />', $html );

		// Unordered lists.
		$html = preg_replace_callback( '/((?:^[-*+]\s+.+\n?)+)/m', function ( $m ) {
			$items = preg_replace( '/^[-*+]\s+(.+)$/m', '<li>$1</li>', trim( $m[1] ) );
			return '<ul>' . $items . '</ul>';
		}, $html );

		// Ordered lists.
		$html = preg_replace_callback( '/((?:^\d+\.\s+.+\n?)+)/m', function ( $m ) {
			$items = preg_replace( '/^\d+\.\s+(.+)$/m', '<li>$1</li>', trim( $m[1] ) );
			return '<ol>' . $items . '</ol>';
		}, $html );

		// Blockquotes.
		$html = preg_replace_callback( '/((?:^>\s?.+\n?)+)/m', function ( $m ) {
			$text = preg_replace( '/^>\s?/m', '', trim( $m[1] ) );
			return '<blockquote>' . $text . '</blockquote>';
		}, $html );

		// Paragraphs.
		$html = preg_replace( '/\n{2,}/', "\n\n", $html );
		$blocks = preg_split( '/\n\n/', $html );
		$wrapped = array();
		foreach ( $blocks as $block ) {
			$block = trim( $block );
			if ( ! $block ) { continue; }
			if ( preg_match( '/^<(h[1-6]|ul|ol|pre|blockquote|hr|div|table)/', $block ) ) {
				$wrapped[] = $block;
			} else {
				$wrapped[] = '<p>' . $block . '</p>';
			}
		}

		return implode( "\n\n", $wrapped );
	}
}
