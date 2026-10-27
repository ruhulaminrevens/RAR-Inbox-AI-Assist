<?php
/**
 * Per-user per-minute counters with atomic upsert (TRD §10, Schema §4.11).
 *
 * @package RarIaa
 */

namespace RarIaa\Limits;

use RarIaa\Db;

defined( 'ABSPATH' ) || exit;

class RateLimiter {

	/**
	 * Count a hit and report whether it is within the limit.
	 *
	 * @return int 0 when allowed, otherwise seconds until the window resets.
	 */
	public static function hit( $user_id, $scope, $limit ) {
		global $wpdb;
		$minute = gmdate( 'YmdHi' );
		$key    = substr( 'u' . (int) $user_id . ':' . $scope . ':' . $minute, 0, 80 );
		$table  = Db::t( 'rate_buckets' );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (bucket_key, count, expires_at) VALUES (%s, 1, %s) ON DUPLICATE KEY UPDATE count = count + 1",
				$key,
				Db::now( 180 )
			)
		);
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT count FROM {$table} WHERE bucket_key = %s", $key ) );
		if ( $count > (int) $limit ) {
			return max( 1, 60 - (int) gmdate( 's' ) );
		}
		return 0;
	}

	public static function purge_expired() {
		global $wpdb;
		$table = Db::t( 'rate_buckets' );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE expires_at < %s AND bucket_key NOT LIKE %s", Db::now(), 'lock:%' ) );
	}
}
