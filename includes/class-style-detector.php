<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Style_Detector {

	private $client;

	public function __construct( WPJS_GitHub_Client $client ) {
		$this->client = $client;
	}

	public function detect() {
		$profile = array(
			'detected_at'  => gmdate( 'c' ),
			'source_files' => array(),
			'config'       => array(),
			'front_matter' => array(
				'field_order' => array(),
				'fields'      => array(),
				'array_style' => 'block',
			),
			'markdown' => array(
				'heading_style'   => 'atx',
				'ul_marker'       => '-',
				'emphasis_marker' => '*',
				'strong_marker'   => '**',
				'code_fence'      => '```',
				'hr_style'        => '---',
			),
		);

		// Phase A: Read _config.yml.
		$config_content = $this->client->get_file_content( '_config.yml' );
		if ( ! is_wp_error( $config_content ) ) {
			$profile['source_files'][] = '_config.yml';
			$profile['config'] = $this->parse_config( $config_content );
		}

		// Phase B: Sample posts.
		$posts_path = WPJS_Settings::get( 'posts_path', '_posts' );
		$dir        = $this->client->list_directory( $posts_path );
		if ( is_wp_error( $dir ) ) {
			// If no posts found, return profile with config only.
			if ( ! empty( $profile['config'] ) ) {
				return $profile;
			}
			return $dir;
		}

		// Filter to .md/.markdown files, sort descending (newest first), take 5.
		$md_files = array_filter( $dir, function ( $item ) {
			return $item['type'] === 'file' && preg_match( '/\.(md|markdown)$/i', $item['name'] );
		} );
		usort( $md_files, function ( $a, $b ) {
			return strcmp( $b['name'], $a['name'] );
		} );
		$md_files = array_slice( $md_files, 0, 5 );

		if ( empty( $md_files ) ) {
			return $profile;
		}

		$fm_analyses = array();
		$md_analyses = array();

		foreach ( $md_files as $file ) {
			$content = $this->client->get_file_content( $file['path'] );
			if ( is_wp_error( $content ) ) { continue; }

			$profile['source_files'][] = $file['path'];
			$parts = $this->split_front_matter( $content );
			if ( ! $parts ) { continue; }

			$fm_analyses[] = $this->parse_front_matter( $parts['front_matter'] );
			$md_analyses[] = $this->analyze_markdown( $parts['body'] );
		}

		if ( $fm_analyses ) {
			$profile['front_matter'] = $this->merge_front_matter( $fm_analyses );
		}
		if ( $md_analyses ) {
			$profile['markdown'] = $this->merge_markdown( $md_analyses );
		}

		// Detect images path from featured_image values in sampled posts.
		$image_paths = array();
		foreach ( $fm_analyses as $a ) {
			foreach ( $a['fields'] as $f ) {
				if ( in_array( $f['key'], array( 'featured_image', 'image' ), true ) && ! empty( $f['value'] ) ) {
					$dir = ltrim( dirname( $f['value'] ), '/' );
					if ( $dir && $dir !== '.' ) {
						$image_paths[] = $dir;
					}
				}
			}
		}
		if ( $image_paths ) {
			$counts = array_count_values( $image_paths );
			arsort( $counts );
			$profile['config']['images_path'] = array_key_first( $counts );
		} else {
			// Fallback: check if assets/images exists in repo.
			$check = $this->client->list_directory( 'assets/images' );
			if ( ! is_wp_error( $check ) ) {
				$profile['config']['images_path'] = 'assets/images';
			}
		}

		return $profile;
	}

	private function parse_config( $yaml ) {
		$config = array(
			'permalink' => '',
			'markdown'  => '',
			'url'       => '',
			'baseurl'   => '',
		);
		// Track indentation to only capture top-level keys.
		foreach ( explode( "\n", $yaml ) as $line ) {
			// Skip indented lines (nested under collections, defaults, etc.).
			if ( preg_match( '/^\s/', $line ) ) { continue; }
			if ( preg_match( '/^permalink:\s*(.+)$/i', $line, $m ) ) {
				$config['permalink'] = trim( $m[1], " \t\"'" );
			}
			if ( preg_match( '/^markdown:\s*(.+)$/i', $line, $m ) ) {
				$config['markdown'] = trim( $m[1], " \t\"'" );
			}
			if ( preg_match( '/^url:\s*(.+)$/i', $line, $m ) ) {
				$config['url'] = trim( $m[1], " \t\"'" );
			}
			if ( preg_match( '/^baseurl:\s*(.+)$/i', $line, $m ) ) {
				$val = trim( $m[1], " \t\"'" );
				if ( $val !== '' ) {
					$config['baseurl'] = $val;
				}
			}
		}

		// Also extract posts permalink from collections if present.
		if ( preg_match( '/collections:\s*\n.*?posts:\s*\n.*?permalink:\s*(.+)/s', $yaml, $m ) ) {
			$config['posts_permalink'] = trim( $m[1], " \t\"'" );
		}

		// Combine url + baseurl.
		if ( $config['url'] && $config['baseurl'] ) {
			$config['url'] = rtrim( $config['url'], '/' ) . '/' . ltrim( $config['baseurl'], '/' );
		}
		return $config;
	}

	private function split_front_matter( $content ) {
		if ( ! preg_match( '/\A---\s*\n(.*?)\n---\s*\n(.*)\z/s', $content, $m ) ) {
			return null;
		}
		return array(
			'front_matter' => $m[1],
			'body'         => $m[2],
		);
	}

	private function parse_front_matter( $yaml ) {
		$fields = array();
		$order  = array();
		$array_inline = 0;
		$array_block  = 0;

		$current_key = null;
		foreach ( explode( "\n", $yaml ) as $line ) {
			// Top-level key: value.
			if ( preg_match( '/^([a-zA-Z_][a-zA-Z0-9_-]*):\s*(.*)$/', $line, $m ) ) {
				$key   = $m[1];
				$value = trim( $m[2] );
				$current_key = $key;
				$order[] = $key;

				if ( $value === '' ) {
					$fields[ $key ] = array( 'key' => $key, 'type' => 'string', 'required' => false, 'value' => '' );
				} elseif ( preg_match( '/^\[.*\]$/', $value ) ) {
					$fields[ $key ] = array( 'key' => $key, 'type' => 'array', 'required' => false, 'value' => $value );
					$array_inline++;
				} else {
					$fields[ $key ] = array( 'key' => $key, 'type' => 'string', 'required' => false, 'value' => trim( $value, "\"'" ) );
				}
			} elseif ( preg_match( '/^\s+-\s+/', $line ) && $current_key ) {
				// Block array item under current key.
				if ( isset( $fields[ $current_key ] ) ) {
					$fields[ $current_key ]['type'] = 'array';
				}
				$array_block++;
			}
		}

		return array(
			'fields'      => array_values( $fields ),
			'field_order' => $order,
			'array_style' => $array_inline > $array_block ? 'inline' : 'block',
		);
	}

	private function analyze_markdown( $body ) {
		$result = array(
			'heading_style'   => null,
			'ul_marker'       => null,
			'emphasis_marker' => null,
			'strong_marker'   => null,
			'code_fence'      => null,
			'hr_style'        => null,
		);

		$lines      = explode( "\n", $body );
		$prev_line  = '';
		foreach ( $lines as $i => $line ) {
			// ATX headings.
			if ( preg_match( '/^#{1,6}\s/', $line ) ) {
				$result['heading_style'] = 'atx';
			}
			// Setext headings.
			if ( preg_match( '/^[=]{3,}$/', $line ) && trim( $prev_line ) !== '' ) {
				$result['heading_style'] = 'setext';
			}
			if ( preg_match( '/^[-]{3,}$/', $line ) && trim( $prev_line ) !== '' && $i > 0 ) {
				$result['heading_style'] = 'setext';
			}

			// Unordered list marker.
			if ( preg_match( '/^(\s*)([-*+])\s/', $line, $m ) && $result['ul_marker'] === null ) {
				$result['ul_marker'] = $m[2];
			}

			// Code fences.
			if ( preg_match( '/^(`{3,})/', $line ) && $result['code_fence'] === null ) {
				$result['code_fence'] = '```';
			}
			if ( preg_match( '/^(~{3,})/', $line ) && $result['code_fence'] === null ) {
				$result['code_fence'] = '~~~';
			}

			// Horizontal rules (standalone, not front matter).
			if ( preg_match( '/^\*{3,}\s*$/', $line ) ) { $result['hr_style'] = '***'; }
			if ( preg_match( '/^_{3,}\s*$/', $line ) )  { $result['hr_style'] = '___'; }
			if ( preg_match( '/^-{3,}\s*$/', $line ) && trim( $prev_line ) === '' ) { $result['hr_style'] = '---'; }

			$prev_line = $line;
		}

		// Emphasis and strong — scan inline.
		if ( preg_match( '/(?<!\*)\*(?!\*)[^*]+\*(?!\*)/', $body ) ) {
			$result['emphasis_marker'] = '*';
		} elseif ( preg_match( '/(?<!_)_(?!_)[^_]+_(?!_)/', $body ) ) {
			$result['emphasis_marker'] = '_';
		}

		if ( preg_match( '/\*\*[^*]+\*\*/', $body ) ) {
			$result['strong_marker'] = '**';
		} elseif ( preg_match( '/__[^_]+__/', $body ) ) {
			$result['strong_marker'] = '__';
		}

		return $result;
	}

	private function merge_front_matter( array $analyses ) {
		$all_keys     = array();
		$field_map    = array();
		$order_votes  = array();
		$array_styles = array();
		$total        = count( $analyses );

		foreach ( $analyses as $a ) {
			$array_styles[] = $a['array_style'];
			foreach ( $a['field_order'] as $pos => $key ) {
				if ( ! isset( $order_votes[ $key ] ) ) { $order_votes[ $key ] = array(); }
				$order_votes[ $key ][] = $pos;
			}
			foreach ( $a['fields'] as $f ) {
				$key = $f['key'];
				if ( ! isset( $field_map[ $key ] ) ) {
					$field_map[ $key ] = array( 'key' => $key, 'type' => $f['type'], 'count' => 0 );
				}
				$field_map[ $key ]['count']++;
				if ( $f['type'] === 'array' ) { $field_map[ $key ]['type'] = 'array'; }
			}
		}

		// Sort fields by average position.
		uasort( $order_votes, function ( $a, $b ) {
			return ( array_sum( $a ) / count( $a ) ) <=> ( array_sum( $b ) / count( $b ) );
		} );

		$field_order = array_keys( $order_votes );
		$fields      = array();
		foreach ( $field_order as $key ) {
			$f = $field_map[ $key ];
			$fields[] = array(
				'key'      => $key,
				'type'     => $f['type'],
				'required' => $f['count'] === $total,
			);
		}

		$style_counts = array_count_values( $array_styles );
		$array_style  = array_keys( $style_counts );
		arsort( $style_counts );
		$array_style = array_key_first( $style_counts ) ?? 'block';

		return array(
			'field_order' => $field_order,
			'fields'      => $fields,
			'array_style' => $array_style,
		);
	}

	private function merge_markdown( array $analyses ) {
		$defaults = array(
			'heading_style'   => 'atx',
			'ul_marker'       => '-',
			'emphasis_marker' => '*',
			'strong_marker'   => '**',
			'code_fence'      => '```',
			'hr_style'        => '---',
		);
		$votes = array();
		foreach ( array_keys( $defaults ) as $key ) {
			$votes[ $key ] = array();
		}
		foreach ( $analyses as $a ) {
			foreach ( $defaults as $key => $def ) {
				if ( $a[ $key ] !== null ) {
					$votes[ $key ][] = $a[ $key ];
				}
			}
		}
		$result = array();
		foreach ( $defaults as $key => $def ) {
			if ( empty( $votes[ $key ] ) ) {
				$result[ $key ] = $def;
			} else {
				$counts = array_count_values( $votes[ $key ] );
				arsort( $counts );
				$result[ $key ] = array_key_first( $counts );
			}
		}
		return $result;
	}
}
