<?php
/**
 * Named locks.
 *
 * Production (MySQL / MariaDB): GET_LOCK / RELEASE_LOCK — connection-scoped, freed automatically
 * if PHP dies. SQLite development installs do not support named locks, so a lease row in the
 * rate_buckets table (primary-key insert, expiry for crash recovery) is used instead.
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Lock {

	/** @var array<string,bool> locks held by this request */
	private static $held = array();

	/**
	 * Try to acquire a lock.
	 *
	 * @param string $name    Lock name (prefixed automatically).
	 * @param int    $timeout Seconds to wait (MySQL) / retry window (SQLite).
	 * @param int    $lease   Lease seconds for the SQLite fallback.
	 */
	public static function acquire( $name, $timeout = 0, $lease = 60 ) {
		global $wpdb;
		$key = self::key( $name );
		if ( ! Db::is_sqlite() ) {
			$got = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, (int) $timeout ) );
			if ( '1' === (string) $got ) {
				self::$held[ $key ] = true;
				return true;
			}
			return false;
		}
		$deadline = microtime( true ) + max( 0, (int) $timeout );
		$table    = Db::t( 'rate_buckets' );
		do {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE bucket_key = %s AND expires_at < %s", 'lock:' . $key, Db::now() ) );
			$suppress = $wpdb->suppress_errors( true );
			$ok       = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (bucket_key, count, expires_at) VALUES (%s, 1, %s)", 'lock:' . $key, Db::now( $lease ) ) );
			$wpdb->suppress_errors( $suppress );
			if ( $ok ) {
				self::$held[ $key ] = true;
				return true;
			}
			if ( microtime( true ) >= $deadline ) {
				return false;
			}
			usleep( 100000 );
		} while ( true );
	}

	public static function release( $name ) {
		global $wpdb;
		$key = self::key( $name );
		if ( empty( self::$held[ $key ] ) ) {
			return;
		}
		unset( self::$held[ $key ] );
		if ( ! Db::is_sqlite() ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
			return;
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'rate_buckets' ) . ' WHERE bucket_key = %s', 'lock:' . $key ) );
	}

	/** Release every lock this request still holds (shutdown safety net). */
	public static function release_all() {
		foreach ( array_keys( self::$held ) as $key ) {
			self::release( substr( $key, strlen( self::prefix() ) ) );
		}
	}

	private static function prefix() {
		global $wpdb;
		return substr( $wpdb->prefix . 'rar_iaa_', 0, 20 );
	}

	private static function key( $name ) {
		// MySQL lock names are limited to 64 characters.
		return substr( self::prefix() . $name, 0, 64 );
	}
}
