<?php
/**
 * Database helpers: table names, engine detection, time helpers.
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Db {

	const TABLES = array(
		'kb_entries',
		'kb_versions',
		'tiers',
		'dealers',
		'price_lists',
		'price_items',
		'thread_links',
		'tokens',
		'draft_log',
		'usage_daily',
		'rate_buckets',
		'audit_log',
		'cost_reservations',
	);

	/** Full table name for a short name. */
	public static function t( $short ) {
		global $wpdb;
		return $wpdb->prefix . 'rar_iaa_' . $short;
	}

	/** True when WordPress runs on the SQLite drop-in (local development only). */
	public static function is_sqlite() {
		return ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ( defined( 'DATABASE_TYPE' ) && 'sqlite' === DATABASE_TYPE );
	}

	/** Current UTC time as MySQL DATETIME. */
	public static function now( $offset_seconds = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + (int) $offset_seconds );
	}

	/** Asia/Dhaka business date (Y-m-d). */
	public static function dhaka_date( $timestamp = null ) {
		$dt = new \DateTime( '@' . ( null === $timestamp ? time() : (int) $timestamp ) );
		$dt->setTimezone( new \DateTimeZone( 'Asia/Dhaka' ) );
		return $dt->format( 'Y-m-d' );
	}

	/** First and last Asia/Dhaka date of the current month. */
	public static function dhaka_month_bounds() {
		$dt = new \DateTime( 'now', new \DateTimeZone( 'Asia/Dhaka' ) );
		return array( $dt->format( 'Y-m-01' ), $dt->format( 'Y-m-t' ) );
	}

	/** Format a UTC DATETIME for display in Asia/Dhaka. */
	public static function display( $utc ) {
		if ( ! $utc ) {
			return '—';
		}
		$dt = new \DateTime( $utc, new \DateTimeZone( 'UTC' ) );
		$dt->setTimezone( new \DateTimeZone( 'Asia/Dhaka' ) );
		return $dt->format( 'd M Y, H:i' );
	}

	/** Run a callback inside a transaction; rolls back on exception. A ConflictException becomes WP_Error CONFLICT. */
	public static function transaction( callable $fn ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		try {
			$result = $fn();
			$wpdb->query( 'COMMIT' );
			return $result;
		} catch ( Domain\ConflictException $e ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'CONFLICT', $e->getMessage(), array( 'status' => 409 ) );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}
}
