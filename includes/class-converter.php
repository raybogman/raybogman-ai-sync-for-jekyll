<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Converter {

	public static function post_to_markdown( WP_Post $post, $jekyll_image_path = '' ) {
		$mode = WPJS_Settings::get( 'conversion_mode', 'standard' );
		if ( $mode === 'style_aware' ) {
			$profile = json_decode( WPJS_Settings::get( 'style_profile', '{}' ), true );
			if ( ! empty( $profile['front_matter']['fields'] ) ) {
				$output = self::post_to_markdown_styled( $post, $profile, $jekyll_image_path );
				return self::rewrite_urls( $output );
			}
		}
		$output = self::post_to_markdown_standard( $post, $jekyll_image_path );
		return self::rewrite_urls( $output );
	}

	private static function post_to_markdown_standard( WP_Post $post, $jekyll_image_path = '' ) {
		$title   = $post->post_title;
		$date    = get_post_time( 'Y-m-d H:i:s O', true, $post );
		$slug    = $post->post_name ?: sanitize_title( $title );
		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		$author  = self::resolve_author( $post );

		$categories = array();
		$tags       = array();
		if ( $post->post_type === 'post' ) {
			$categories = wp_list_pluck( get_the_category( $post->ID ), 'name' );
			$tag_objs   = get_the_tags( $post->ID );
			if ( $tag_objs ) {
				$tags = wp_list_pluck( $tag_objs, 'name' );
			}
		}

		$layout = $post->post_type === 'page' ? 'page' : 'post';

		$front_matter = array(
			'layout' => $layout,
			'title'  => $title,
			'date'   => $date,
			'slug'   => $slug,
			'author' => $author,
		);
		if ( $excerpt ) { $front_matter['description'] = $excerpt; }
		if ( $jekyll_image_path ) { $front_matter['featured_image'] = $jekyll_image_path; }
		if ( $categories ) { $front_matter['categories'] = $categories; }
		if ( $tags ) { $front_matter['tags'] = $tags; }
		if ( $post->post_type === 'page' ) {
			$front_matter['permalink'] = '/' . $slug . '/';
		}

		$yaml     = self::yaml( $front_matter );
		$html     = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$markdown = self::html_to_markdown( $html );

		return "---\n" . $yaml . "---\n\n" . $markdown . "\n";
	}

	private static function post_to_markdown_styled( WP_Post $post, array $profile, $jekyll_image_path = '' ) {
		$fm_spec = $profile['front_matter'];
		$md_spec = $profile['markdown'] ?? array();
		$config  = $profile['config'] ?? array();

		$title      = $post->post_title;
		$date       = get_post_time( 'Y-m-d H:i:s O', true, $post );
		$slug       = $post->post_name ?: sanitize_title( $title );
		$excerpt    = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
		$author     = self::resolve_author( $post );
		$layout     = $post->post_type === 'page' ? 'page' : 'post';
		$categories = array();
		$tags       = array();
		if ( $post->post_type === 'post' ) {
			$categories = wp_list_pluck( get_the_category( $post->ID ), 'name' );
			$tag_objs   = get_the_tags( $post->ID );
			if ( $tag_objs ) { $tags = wp_list_pluck( $tag_objs, 'name' ); }
		}

		// Build a data map for front matter fields.
		$data_map = array(
			'layout'         => $layout,
			'title'          => $title,
			'date'           => $date,
			'slug'           => $slug,
			'author'         => $author,
			'excerpt'        => $excerpt,
			'description'    => $excerpt,
			'featured_image' => $jekyll_image_path,
			'image'          => $jekyll_image_path,
			'categories'     => $categories,
			'tags'           => $tags,
			'permalink'      => $post->post_type === 'page' ? '/' . $slug . '/' : self::build_permalink( $post, $config ),
		);

		// Emit front matter in the detected field order.
		$front_matter = array();
		foreach ( $fm_spec['field_order'] as $key ) {
			if ( ! isset( $data_map[ $key ] ) ) { continue; }
			$value = $data_map[ $key ];
			// Skip empty optional values.
			if ( is_string( $value ) && $value === '' ) { continue; }
			if ( is_array( $value ) && empty( $value ) ) { continue; }
			$front_matter[ $key ] = $value;
		}

		$array_style = $fm_spec['array_style'] ?? 'block';
		$yaml        = self::yaml_styled( $front_matter, $array_style );
		$html        = apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$markdown    = self::html_to_markdown_styled( $html, $md_spec );

		return "---\n" . $yaml . "---\n\n" . $markdown . "\n";
	}

	private static function resolve_author( WP_Post $post ) {
		$setting = WPJS_Settings::get( 'author', '__post_author__' );
		return ( $setting === '__post_author__' || $setting === '' )
			? get_the_author_meta( 'display_name', $post->post_author )
			: $setting;
	}

	private static function build_permalink( WP_Post $post, array $config ) {
		// Use posts-specific permalink from collections if available, otherwise top-level.
		$pattern = $config['posts_permalink'] ?? ( $config['permalink'] ?? '' );
		if ( ! $pattern ) { return ''; }
		$date = get_post_time( 'Y-m-d', true, $post );
		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		list( $y, $m, $d ) = explode( '-', $date );
		$cats = wp_list_pluck( get_the_category( $post->ID ), 'slug' );
		$cat  = $cats ? $cats[0] : '';
		return str_replace(
			array( ':year', ':month', ':day', ':title', ':slug', ':categories', ':category' ),
			array( $y, $m, $d, $slug, $slug, $cat, $cat ),
			$pattern
		);
	}

	public static function filename( WP_Post $post ) {
		$slug = $post->post_name ?: sanitize_title( $post->post_title );
		if ( $post->post_type === 'page' ) {
			return $slug . '.md';
		}
		$date = get_post_time( 'Y-m-d', true, $post );
		return $date . '-' . $slug . '.md';
	}

	private static function yaml( array $data ) {
		$out = '';
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$out .= $key . ":\n";
				foreach ( $value as $item ) {
					$out .= '  - ' . self::yaml_scalar( $item ) . "\n";
				}
			} else {
				$out .= $key . ': ' . self::yaml_scalar( $value ) . "\n";
			}
		}
		return $out;
	}

	private static function yaml_scalar( $value ) {
		$value = (string) $value;
		if ( preg_match( '/[:#\-\?\{\}\[\],&\*!\|>\'"%@`\n]/', $value ) || trim( $value ) !== $value ) {
			return '"' . str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $value ) . '"';
		}
		return $value;
	}

	public static function html_to_markdown( $html ) {
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = preg_replace( '/\s+/', ' ', $html );

		$patterns = array(
			'/<h1[^>]*>(.*?)<\/h1>/is'       => "\n# $1\n\n",
			'/<h2[^>]*>(.*?)<\/h2>/is'       => "\n## $1\n\n",
			'/<h3[^>]*>(.*?)<\/h3>/is'       => "\n### $1\n\n",
			'/<h4[^>]*>(.*?)<\/h4>/is'       => "\n#### $1\n\n",
			'/<h5[^>]*>(.*?)<\/h5>/is'       => "\n##### $1\n\n",
			'/<h6[^>]*>(.*?)<\/h6>/is'       => "\n###### $1\n\n",
			'/<(strong|b)[^>]*>(.*?)<\/\1>/is' => '**$2**',
			'/<(em|i)[^>]*>(.*?)<\/\1>/is'   => '*$2*',
			'/<code[^>]*>(.*?)<\/code>/is'   => '`$1`',
			'/<br\s*\/?>/i'                  => "\n",
			'/<hr\s*\/?>/i'                  => "\n---\n",
			'/<blockquote[^>]*>(.*?)<\/blockquote>/is' => "\n> $1\n\n",
		);
		foreach ( $patterns as $pat => $rep ) {
			$html = preg_replace( $pat, $rep, $html );
		}

		$html = preg_replace_callback( '/<pre[^>]*>(.*?)<\/pre>/is', function ( $m ) {
			$code = html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES | ENT_HTML5 );
			return "\n```\n" . trim( $code ) . "\n```\n\n";
		}, $html );

		$html = preg_replace_callback( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ( $m ) {
			return '[' . wp_strip_all_tags( $m[2] ) . '](' . $m[1] . ')';
		}, $html );

		$html = preg_replace_callback( '/<img\s[^>]*>/i', function ( $m ) {
			$tag = $m[0];
			preg_match( '/src=["\']([^"\']+)["\']/i', $tag, $src );
			preg_match( '/alt=["\']([^"\']*)["\']/i', $tag, $alt );
			$src_url = $src[1] ?? '';
			$alt_txt = $alt[1] ?? '';
			return '![' . $alt_txt . '](' . $src_url . ')';
		}, $html );

		$html = preg_replace_callback( '/<ul[^>]*>(.*?)<\/ul>/is', function ( $m ) {
			$items = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', "- $1\n", $m[1] );
			return "\n" . trim( wp_kses( $items, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array(), 'code' => array() ) ) ) . "\n\n";
		}, $html );

		$html = preg_replace_callback( '/<ol[^>]*>(.*?)<\/ol>/is', function ( $m ) {
			$i    = 0;
			$list = preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function ( $n ) use ( &$i ) {
				$i++;
				return $i . '. ' . trim( $n[1] ) . "\n";
			}, $m[1] );
			return "\n" . trim( wp_kses( $list, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array(), 'code' => array() ) ) ) . "\n\n";
		}, $html );

		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $html );

		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5 );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/* ─── Style-aware methods ────────────────────────── */

	private static function yaml_styled( array $data, $array_style = 'block' ) {
		$out = '';
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( $array_style === 'inline' ) {
					$items = array_map( function ( $v ) { return self::yaml_scalar( $v ); }, $value );
					$out .= $key . ': [' . implode( ', ', $items ) . "]\n";
				} else {
					$out .= $key . ":\n";
					foreach ( $value as $item ) {
						$out .= '  - ' . self::yaml_scalar( $item ) . "\n";
					}
				}
			} else {
				$out .= $key . ': ' . self::yaml_scalar( $value ) . "\n";
			}
		}
		return $out;
	}

	public static function html_to_markdown_styled( $html, array $style ) {
		$em     = $style['emphasis_marker'] ?? '*';
		$strong = $style['strong_marker'] ?? '**';
		$fence  = $style['code_fence'] ?? '```';
		$hr     = $style['hr_style'] ?? '---';
		$ul     = $style['ul_marker'] ?? '-';
		$h_style = $style['heading_style'] ?? 'atx';

		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = preg_replace( '/\s+/', ' ', $html );

		// Headings.
		if ( $h_style === 'setext' ) {
			$html = preg_replace_callback( '/<h1[^>]*>(.*?)<\/h1>/is', function ( $m ) {
				return "\n" . $m[1] . "\n" . str_repeat( '=', max( 3, strlen( wp_strip_all_tags( $m[1] ) ) ) ) . "\n\n";
			}, $html );
			$html = preg_replace_callback( '/<h2[^>]*>(.*?)<\/h2>/is', function ( $m ) {
				return "\n" . $m[1] . "\n" . str_repeat( '-', max( 3, strlen( wp_strip_all_tags( $m[1] ) ) ) ) . "\n\n";
			}, $html );
			// h3-h6 fallback to ATX even in setext mode (setext only supports h1/h2).
			for ( $i = 3; $i <= 6; $i++ ) {
				$html = preg_replace( '/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/is', "\n" . str_repeat( '#', $i ) . " $1\n\n", $html );
			}
		} else {
			for ( $i = 1; $i <= 6; $i++ ) {
				$html = preg_replace( '/<h' . $i . '[^>]*>(.*?)<\/h' . $i . '>/is', "\n" . str_repeat( '#', $i ) . " $1\n\n", $html );
			}
		}

		// Inline formatting.
		$html = preg_replace( '/<(strong|b)[^>]*>(.*?)<\/\1>/is', $strong . '$2' . $strong, $html );
		$html = preg_replace( '/<(em|i)[^>]*>(.*?)<\/\1>/is', $em . '$2' . $em, $html );
		$html = preg_replace( '/<code[^>]*>(.*?)<\/code>/is', '`$1`', $html );
		$html = preg_replace( '/<br\s*\/?>/i', "\n", $html );
		$html = preg_replace( '/<hr\s*\/?>/i', "\n" . $hr . "\n", $html );
		$html = preg_replace( '/<blockquote[^>]*>(.*?)<\/blockquote>/is', "\n> $1\n\n", $html );

		// Pre/code blocks.
		$html = preg_replace_callback( '/<pre[^>]*>(.*?)<\/pre>/is', function ( $m ) use ( $fence ) {
			$code = html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES | ENT_HTML5 );
			return "\n" . $fence . "\n" . trim( $code ) . "\n" . $fence . "\n\n";
		}, $html );

		// Links and images.
		$html = preg_replace_callback( '/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is', function ( $m ) {
			return '[' . wp_strip_all_tags( $m[2] ) . '](' . $m[1] . ')';
		}, $html );

		$html = preg_replace_callback( '/<img\s[^>]*>/i', function ( $m ) {
			preg_match( '/src=["\']([^"\']+)["\']/i', $m[0], $src );
			preg_match( '/alt=["\']([^"\']*)["\']/i', $m[0], $alt );
			return '![' . ( $alt[1] ?? '' ) . '](' . ( $src[1] ?? '' ) . ')';
		}, $html );

		// Lists.
		$html = preg_replace_callback( '/<ul[^>]*>(.*?)<\/ul>/is', function ( $m ) use ( $ul ) {
			$items = preg_replace( '/<li[^>]*>(.*?)<\/li>/is', $ul . " $1\n", $m[1] );
			return "\n" . trim( wp_kses( $items, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array(), 'code' => array() ) ) ) . "\n\n";
		}, $html );

		$html = preg_replace_callback( '/<ol[^>]*>(.*?)<\/ol>/is', function ( $m ) {
			$i = 0;
			$list = preg_replace_callback( '/<li[^>]*>(.*?)<\/li>/is', function ( $n ) use ( &$i ) {
				$i++;
				return $i . '. ' . trim( $n[1] ) . "\n";
			}, $m[1] );
			return "\n" . trim( wp_kses( $list, array( 'a' => array( 'href' => array() ), 'strong' => array(), 'em' => array(), 'code' => array() ) ) ) . "\n\n";
		}, $html );

		$html = preg_replace( '/<p[^>]*>(.*?)<\/p>/is', "$1\n\n", $html );

		$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5 );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/* ─── URL rewriting ──────────────────────────────── */

	public static function get_jekyll_base_url() {
		// Priority: 1) manual setting, 2) style profile config url.
		$opts = get_option( WPJS_Settings::OPTION, array() );
		$manual = $opts['jekyll_base_url'] ?? '';
		if ( $manual ) {
			return rtrim( $manual, '/' );
		}
		$profile = ! empty( $opts['style_profile'] ) ? json_decode( $opts['style_profile'], true ) : array();
		if ( ! empty( $profile['config']['url'] ) ) {
			return rtrim( $profile['config']['url'], '/' );
		}
		return '';
	}

	public static function get_wp_base_urls() {
		$urls = array();
		$site = rtrim( get_site_url(), '/' );
		$home = rtrim( home_url(), '/' );
		if ( $site ) { $urls[] = $site; }
		if ( $home && $home !== $site ) { $urls[] = $home; }

		// Also check wp_options directly in case the functions return something different.
		$db_site = rtrim( get_option( 'siteurl', '' ), '/' );
		$db_home = rtrim( get_option( 'home', '' ), '/' );
		if ( $db_site && ! in_array( $db_site, $urls, true ) ) { $urls[] = $db_site; }
		if ( $db_home && ! in_array( $db_home, $urls, true ) ) { $urls[] = $db_home; }

		return $urls;
	}

	private static function rewrite_urls( $content ) {
		$jekyll_base = self::get_jekyll_base_url();
		if ( ! $jekyll_base ) { return $content; }

		$wp_urls = self::get_wp_base_urls();
		foreach ( $wp_urls as $wp_base ) {
			if ( $wp_base === $jekyll_base ) { continue; }
			$content = str_replace( $wp_base, $jekyll_base, $content );
		}

		return $content;
	}
}
