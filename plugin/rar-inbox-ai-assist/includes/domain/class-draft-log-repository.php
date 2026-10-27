<?php
/**
 * Draft request log (Schema §4.9, §13). Stores IDs and metadata; generated text only when
 * masked logging is enabled, and then only in masked form.
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Db;

defined( 'ABSPATH' ) || exit;

class DraftLogRepository {

	const OUTCOMES       = array( 'inserted', 'edited_inserted', 'copied', 'edited_copied', 'rejected' );
	const REJECT_REASONS = array( 'wrong_fact', 'tone', 'irrelevant', 'other' );

	public static function insert( array $row ) {
		global $wpdb;
		$row['created_at'] = Db::now();
		$wpdb->insert( Db::t( 'draft_log' ), $row );
		return (int) $wpdb->insert_id;
	}

	/** Draft owned by the given user (horizontal-access guard). */
	public static function find_owned( $draft_uuid, $user_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'draft_log' ) . ' WHERE draft_uuid = %s AND user_id = %d', $draft_uuid, $user_id ) );
	}

	/** Once-only outcome. Returns true, or WP_Error OUTCOME_ALREADY_SET / NOT_FOUND. */
	public static function set_outcome( $draft_uuid, $user_id, array $o ) {
		global $wpdb;
		$t       = Db::t( 'draft_log' );
		$changed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$t} SET outcome = %s, selected_variant_id = NULLIF(%s, ''), reject_reason = NULLIF(%s, ''), insert_failed = %d, edit_distance = %d, outcome_at = %s WHERE draft_uuid = %s AND user_id = %d AND outcome = 'pending' AND status = 'ok'",
				$o['outcome'],
				(string) $o['selected_variant_id'],
				(string) $o['reject_reason'],
				$o['insert_failed'] ? 1 : 0,
				(int) $o['edit_distance'],
				Db::now(),
				$draft_uuid,
				$user_id
			)
		);
		if ( 1 === (int) $changed ) {
			return true;
		}
		$row = self::find_owned( $draft_uuid, $user_id );
		if ( ! $row || 'ok' !== $row->status ) {
			return new \WP_Error( 'NOT_FOUND', 'Draft not found.', array( 'status' => 404 ) );
		}
		return new \WP_Error( 'OUTCOME_ALREADY_SET', 'Outcome already recorded.', array( 'status' => 409 ) );
	}

	public static function list_admin( $args ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array();
		$from  = isset( $args['from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['from'] ) ? $args['from'] : Db::dhaka_date();
		$to    = isset( $args['to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['to'] ) ? $args['to'] : Db::dhaka_date();
		// Dhaka dates → UTC range.
		$where[] = 'l.created_at >= %s AND l.created_at < %s';
		$vals[]  = gmdate( 'Y-m-d H:i:s', strtotime( $from . ' 00:00:00 Asia/Dhaka' ) );
		$vals[]  = gmdate( 'Y-m-d H:i:s', strtotime( $to . ' 00:00:00 Asia/Dhaka' ) + DAY_IN_SECONDS );
		foreach ( array( 'user_id' => '%d', 'dealer_id' => '%d' ) as $k => $f ) {
			if ( ! empty( $args[ $k ] ) ) {
				$where[] = "l.{$k} = {$f}";
				$vals[]  = (int) $args[ $k ];
			}
		}
		if ( ! empty( $args['outcome'] ) && in_array( $args['outcome'], array_merge( self::OUTCOMES, array( 'pending' ) ), true ) ) {
			$where[] = 'l.outcome = %s';
			$vals[]  = $args['outcome'];
		}
		if ( ! empty( $args['guard'] ) && in_array( $args['guard'], array( 'pass', 'warned', 'blocked' ), true ) ) {
			$where[] = 'l.guard_result = %s';
			$vals[]  = $args['guard'];
		}
		if ( isset( $args['handoff'] ) && '' !== $args['handoff'] ) {
			$where[] = 'l.handoff_needed = %d';
			$vals[]  = (int) $args['handoff'];
		}
		$page = max( 1, (int) ( isset( $args['paged'] ) ? $args['paged'] : 1 ) );
		$per  = isset( $args['per_page'] ) ? max( 1, min( 5000, (int) $args['per_page'] ) ) : 50;
		$base = 'FROM ' . Db::t( 'draft_log' ) . ' l LEFT JOIN ' . $wpdb->users . ' u ON u.ID = l.user_id LEFT JOIN ' . Db::t( 'dealers' ) . ' d ON d.id = l.dealer_id WHERE ' . implode( ' AND ', $where );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT l.*, u.display_name, d.dealer_code {$base} ORDER BY l.id DESC LIMIT %d OFFSET %d", array_merge( $vals, array( $per, ( $page - 1 ) * $per ) ) ) ); // phpcs:ignore
		$cnt  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$base}", $vals ) ); // phpcs:ignore
		return array( $rows, $cnt );
	}

	/** KPI numbers for the last $days days (A-01). */
	public static function kpis( $days = 7 ) {
		global $wpdb;
		$since = Db::now( -$days * DAY_IN_SECONDS );
		$t     = Db::t( 'draft_log' );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total,
				 SUM(CASE WHEN outcome IN ('inserted','copied') THEN 1 ELSE 0 END) AS accepted_unedited,
				 SUM(CASE WHEN outcome IN ('inserted','copied','edited_inserted','edited_copied') THEN 1 ELSE 0 END) AS used,
				 SUM(CASE WHEN handoff_needed = 1 THEN 1 ELSE 0 END) AS handoffs,
				 SUM(CASE WHEN guard_result = 'blocked' THEN 1 ELSE 0 END) AS blocked
				 FROM {$t} WHERE created_at >= %s AND status = 'ok' AND source <> 'rewrite'",
				$since
			)
		);
		return $row;
	}

	public static function purge_older_than( $days, $batch = 1000 ) {
		global $wpdb;
		$t     = Db::t( 'draft_log' );
		$total = 0;
		do {
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE created_at < %s LIMIT %d", Db::now( -$days * DAY_IN_SECONDS ), $batch ) );
			if ( $ids ) {
				$total += (int) $wpdb->query( "DELETE FROM {$t} WHERE id IN (" . implode( ',', array_map( 'intval', $ids ) ) . ')' ); // phpcs:ignore
			}
		} while ( count( $ids ) === $batch );
		return $total;
	}
}
