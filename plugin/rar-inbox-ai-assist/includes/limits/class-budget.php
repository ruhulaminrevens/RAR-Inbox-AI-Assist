<?php
/**
 * Daily usage counters and atomic monthly cost reservations (TRD §10 / v0.2 §19, Schema §13).
 *
 * Before a provider call the worst-case cost is reserved under a lock; the call is refused
 * if spent + reserved + new reservation would exceed the monthly cap. After the call the
 * reservation is settled with the actual cost. Reservations left by crashed requests expire
 * and are released by maintenance (or the next reservation attempt).
 *
 * Money unit: USD micros (1 USD = 1,000,000).
 *
 * @package RarIaa
 */

namespace RarIaa\Limits;

use RarIaa\Db;
use RarIaa\Lock;
use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class Budget {

	const RESERVATION_TTL = 120;

	/** Cost in micros for a token count at a USD-per-million-token price. */
	public static function micros( $tokens, $usd_per_mtok ) {
		return (int) ceil( max( 0, (int) $tokens ) * max( 0.0, (float) $usd_per_mtok ) );
	}

	public static function cap_micros() {
		return (int) round( (float) Settings::get( 'monthly_cap_usd' ) * 1000000 );
	}

	/** Month totals: array( spent, reserved ) in micros. */
	public static function month_totals() {
		global $wpdb;
		list( $from, $to ) = Db::dhaka_month_bounds();
		$row               = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(cost_usd_micros),0) AS spent, COALESCE(SUM(reserved_cost_usd_micros),0) AS reserved FROM ' . Db::t( 'usage_daily' ) . ' WHERE day BETWEEN %s AND %s',
				$from,
				$to
			)
		);
		return array( (int) $row->spent, (int) $row->reserved );
	}

	public static function today() {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'usage_daily' ) . ' WHERE day = %s', Db::dhaka_date() ) );
		return $row ? $row : (object) array( 'requests' => 0, 'errors' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd_micros' => 0, 'reserved_cost_usd_micros' => 0 );
	}

	/** Atomic counter add on a day row. */
	private static function add( $day, array $deltas ) {
		global $wpdb;
		$cols   = array( 'requests', 'errors', 'tokens_in', 'tokens_out', 'cost_usd_micros', 'reserved_cost_usd_micros' );
		$vals   = array();
		$update = array();
		foreach ( $cols as $c ) {
			$d      = isset( $deltas[ $c ] ) ? (int) $deltas[ $c ] : 0;
			$vals[] = max( 0, $d );
			if ( 0 !== $d ) {
				$update[] = $d >= 0 ? "{$c} = {$c} + " . (int) $d : "{$c} = CASE WHEN {$c} >= " . abs( $d ) . " THEN {$c} - " . abs( $d ) . ' ELSE 0 END';
			}
		}
		$update[] = 'updated_at = VALUES(updated_at)';
		$table    = Db::t( 'usage_daily' );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (day, requests, errors, tokens_in, tokens_out, cost_usd_micros, reserved_cost_usd_micros, updated_at) VALUES (%s, %d, %d, %d, %d, %d, %d, %s) ON DUPLICATE KEY UPDATE " . implode( ', ', $update ), // phpcs:ignore
				array_merge( array( $day ), $vals, array( Db::now() ) )
			)
		);
	}

	/**
	 * Check the daily request limit and reserve worst-case cost.
	 *
	 * @return true|\WP_Error
	 */
	public static function reserve( $request_id, $amount_micros ) {
		global $wpdb;
		if ( ! Lock::acquire( 'budget', 5, 30 ) ) {
			return new \WP_Error( 'BUSY', 'Server busy, try again shortly.', array( 'status' => 503, 'retry_after' => 3 ) );
		}
		try {
			self::release_expired_locked();
			$today = self::today();
			if ( (int) $today->requests >= (int) Settings::get( 'daily_limit' ) ) {
				return new \WP_Error( 'DAILY_LIMIT', 'Daily draft limit reached.', array( 'status' => 429 ) );
			}
			list( $spent, $reserved ) = self::month_totals();
			if ( $spent + $reserved + (int) $amount_micros > self::cap_micros() ) {
				return new \WP_Error( 'BUDGET_EXCEEDED', 'Monthly AI budget reached.', array( 'status' => 429 ) );
			}
			$day = Db::dhaka_date();
			$wpdb->insert(
				Db::t( 'cost_reservations' ),
				array(
					'request_id'        => $request_id,
					'day'               => $day,
					'amount_usd_micros' => (int) $amount_micros,
					'status'            => 'reserved',
					'expires_at'        => Db::now( self::RESERVATION_TTL ),
					'created_at'        => Db::now(),
				)
			);
			self::add( $day, array( 'requests' => 1, 'reserved_cost_usd_micros' => (int) $amount_micros ) );
			return true;
		} finally {
			Lock::release( 'budget' );
		}
	}

	/** Settle a reservation with actual usage (or release it when the call failed). */
	public static function settle( $request_id, $tokens_in, $tokens_out, $actual_micros, $failed = false ) {
		global $wpdb;
		Lock::acquire( 'budget', 5, 30 );
		try {
			$table = Db::t( 'cost_reservations' );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE request_id = %s", $request_id ) );
			if ( ! $row || 'reserved' !== $row->status ) {
				// Already released by expiry: still record the real spend.
				self::add( Db::dhaka_date(), array( 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out, 'cost_usd_micros' => $actual_micros, 'errors' => $failed ? 1 : 0 ) );
				return;
			}
			$wpdb->update( $table, array( 'status' => $failed ? 'released' : 'settled', 'settled_at' => Db::now() ), array( 'id' => (int) $row->id ) );
			self::add( $row->day, array( 'reserved_cost_usd_micros' => -1 * (int) $row->amount_usd_micros ) );
			self::add( Db::dhaka_date(), array( 'tokens_in' => $tokens_in, 'tokens_out' => $tokens_out, 'cost_usd_micros' => $actual_micros, 'errors' => $failed ? 1 : 0 ) );
		} finally {
			Lock::release( 'budget' );
		}
	}

	/** Release reservations whose request died. Caller must hold the budget lock. */
	private static function release_expired_locked() {
		global $wpdb;
		$table = Db::t( 'cost_reservations' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'reserved' AND expires_at < %s LIMIT 100", Db::now() ) );
		foreach ( $rows as $row ) {
			$wpdb->update( $table, array( 'status' => 'expired', 'settled_at' => Db::now() ), array( 'id' => (int) $row->id ) );
			self::add( $row->day, array( 'reserved_cost_usd_micros' => -1 * (int) $row->amount_usd_micros ) );
		}
		return count( $rows );
	}

	public static function release_expired() {
		if ( ! Lock::acquire( 'budget', 5, 30 ) ) {
			return 0;
		}
		try {
			return self::release_expired_locked();
		} finally {
			Lock::release( 'budget' );
		}
	}
}
