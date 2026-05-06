<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class WPJS_Cron {

	const HOOK     = 'wpjs_scheduled_sync';
	const INTERVAL = 'wpjs_sync_interval';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'run_sync' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_interval' ) );
	}

	public static function add_interval( $schedules ) {
		$hours = (int) WPJS_Settings::get( 'sync_interval_hours', 0 );
		if ( $hours > 0 ) {
			$schedules[ self::INTERVAL ] = array(
				'interval' => $hours * HOUR_IN_SECONDS,
				'display'  => sprintf( 'Every %d hour(s)', $hours ),
			);
		}
		return $schedules;
	}

	public static function schedule() {
		$hours = (int) WPJS_Settings::get( 'sync_interval_hours', 0 );
		self::unschedule();
		if ( $hours > 0 ) {
			wp_schedule_event( time(), self::INTERVAL, self::HOOK );
		}
	}

	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
		}
	}

	public static function is_scheduled() {
		return (bool) wp_next_scheduled( self::HOOK );
	}

	public static function next_run() {
		$ts = wp_next_scheduled( self::HOOK );
		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
	}

	public static function run_sync() {
		if ( ! WPJS_GitHub_OAuth::is_connected() ) { return; }

		$mode = WPJS_Settings::get( 'sync_cron_mode', 'approved' );
		$args = array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( $mode === 'approved' ) {
			// Only push approved posts.
			$args['meta_key']   = WPJS_Publisher::META_APPROVED; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$args['meta_value'] = '1'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		}

		$q  = new WP_Query( $args );
		$ok = 0;

		foreach ( $q->posts as $pid ) {
			$post = get_post( $pid );
			if ( ! $post ) { continue; }

			// Skip if already up to date (not outdated).
			$last_push = get_post_meta( $pid, WPJS_Publisher::META_LAST_PUSH, true );
			if ( $last_push && $mode === 'approved' ) {
				$push_time = strtotime( $last_push );
				$mod_time  = strtotime( $post->post_modified_gmt );
				if ( $mod_time <= $push_time ) { continue; } // Already current.
			}

			$result = WPJS_Publisher::publish( $post );
			if ( ! is_wp_error( $result ) ) { $ok++; }
		}

		WPJS_Sync_Log::add( 0, 'cron_sync', 'scheduled', 'Synced ' . $ok . ' posts' );
	}
}
