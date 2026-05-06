<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Sync_Log {

	const OPTION    = 'wpjs_sync_log';
	const MAX_ITEMS = 500;

	public static function add( $post_id, $action, $path, $result ) {
		$log   = get_option( self::OPTION, array() );
		$entry = array(
			'date'    => current_time( 'mysql' ),
			'user'    => wp_get_current_user()->display_name,
			'user_id' => get_current_user_id(),
			'post_id' => (int) $post_id,
			'title'   => get_the_title( $post_id ),
			'action'  => $action,
			'path'    => $path,
			'result'  => is_wp_error( $result ) ? 'error: ' . $result->get_error_message() : 'success',
		);

		array_unshift( $log, $entry );

		if ( count( $log ) > self::MAX_ITEMS ) {
			$log = array_slice( $log, 0, self::MAX_ITEMS );
		}

		update_option( self::OPTION, $log, false );
	}

	public static function get( $limit = 50 ) {
		$log = get_option( self::OPTION, array() );
		return array_slice( $log, 0, $limit );
	}

	public static function clear() {
		delete_option( self::OPTION );
	}

	public static function count() {
		return count( get_option( self::OPTION, array() ) );
	}
}
