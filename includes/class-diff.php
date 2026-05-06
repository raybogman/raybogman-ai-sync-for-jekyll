<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Diff {

	public static function compute( $old, $new ) {
		$old_lines = explode( "\n", $old );
		$new_lines = explode( "\n", $new );
		$diff      = self::line_diff( $old_lines, $new_lines );
		return $diff;
	}

	public static function render_html( array $diff ) {
		$html = '';
		foreach ( $diff as $entry ) {
			$line = esc_html( $entry['line'] );
			switch ( $entry['type'] ) {
				case 'equal':
					$html .= '<div style="padding:1px 8px;font-family:monospace;font-size:13px;white-space:pre-wrap;">' . $line . '</div>';
					break;
				case 'add':
					$html .= '<div style="padding:1px 8px;font-family:monospace;font-size:13px;white-space:pre-wrap;background:#d4edda;color:#155724;">+ ' . $line . '</div>';
					break;
				case 'remove':
					$html .= '<div style="padding:1px 8px;font-family:monospace;font-size:13px;white-space:pre-wrap;background:#f8d7da;color:#721c24;">- ' . $line . '</div>';
					break;
			}
		}
		return $html;
	}

	private static function line_diff( array $old, array $new ) {
		$m   = count( $old );
		$n   = count( $new );
		$lcs = array();

		// Build LCS table.
		for ( $i = 0; $i <= $m; $i++ ) {
			$lcs[ $i ] = array();
			for ( $j = 0; $j <= $n; $j++ ) {
				if ( $i === 0 || $j === 0 ) {
					$lcs[ $i ][ $j ] = 0;
				} elseif ( $old[ $i - 1 ] === $new[ $j - 1 ] ) {
					$lcs[ $i ][ $j ] = $lcs[ $i - 1 ][ $j - 1 ] + 1;
				} else {
					$lcs[ $i ][ $j ] = max( $lcs[ $i - 1 ][ $j ], $lcs[ $i ][ $j - 1 ] );
				}
			}
		}

		// Backtrack to produce diff.
		$diff = array();
		$i    = $m;
		$j    = $n;
		while ( $i > 0 || $j > 0 ) {
			if ( $i > 0 && $j > 0 && $old[ $i - 1 ] === $new[ $j - 1 ] ) {
				array_unshift( $diff, array( 'type' => 'equal', 'line' => $old[ $i - 1 ] ) );
				$i--;
				$j--;
			} elseif ( $j > 0 && ( $i === 0 || $lcs[ $i ][ $j - 1 ] >= $lcs[ $i - 1 ][ $j ] ) ) {
				array_unshift( $diff, array( 'type' => 'add', 'line' => $new[ $j - 1 ] ) );
				$j--;
			} elseif ( $i > 0 ) {
				array_unshift( $diff, array( 'type' => 'remove', 'line' => $old[ $i - 1 ] ) );
				$i--;
			}
		}

		return $diff;
	}
}
