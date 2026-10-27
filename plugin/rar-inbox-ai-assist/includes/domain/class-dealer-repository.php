<?php
/**
 * Tiers and dealers (FR-16, Schema §4.3–4.4).
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Audit;
use RarIaa\Db;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class DealerRepository {

	/* ------------------------------------------------------------------ Tiers */

	public static function tiers( $only_active = false ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . Db::t( 'tiers' ) . ( $only_active ? " WHERE status = 'active'" : '' ) . ' ORDER BY code ASC';
		return $wpdb->get_results( $sql ); // phpcs:ignore
	}

	public static function tier( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'tiers' ) . ' WHERE id = %d', $id ) );
	}

	public static function save_tier( array $in, $user_id ) {
		global $wpdb;
		$id     = isset( $in['id'] ) ? (int) $in['id'] : 0;
		$code   = strtoupper( trim( sanitize_text_field( isset( $in['code'] ) ? $in['code'] : '' ) ) );
		$name   = trim( sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ) );
		$status = ( isset( $in['status'] ) && 'inactive' === $in['status'] ) ? 'inactive' : 'active';
		$errors = array();
		if ( ! preg_match( '/^[A-Z0-9_-]{1,20}$/', $code ) ) {
			$errors['code'] = 'Code: 1–20 characters A–Z, 0–9, _ or -.';
		}
		if ( '' === $name || Util::strlen( $name ) > 100 ) {
			$errors['name'] = 'Name is required (max 100 characters).';
		}
		$t        = Db::t( 'tiers' );
		$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE code = %s AND id <> %d", $code, $id ) );
		if ( $existing ) {
			$errors['code'] = 'This code is already used.';
		}
		if ( $id && 'inactive' === $status ) {
			$in_use = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Db::t( 'dealers' ) . " WHERE tier_id = %d AND status = 'active'", $id ) )
				+ (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . Db::t( 'price_lists' ) . ' WHERE tier_id = %d AND active_flag = 1', $id ) );
			if ( $in_use ) {
				$errors['status'] = 'This tier still has active dealers or an active price list.';
			}
		}
		if ( $errors ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Please fix the errors.', array( 'fields' => $errors ) );
		}
		$data = array( 'code' => $code, 'name' => $name, 'status' => $status, 'updated_at' => Db::now() );
		if ( $id ) {
			$before = self::tier( $id );
			if ( ! $before ) {
				return new \WP_Error( 'NOT_FOUND', 'Tier not found.' );
			}
			$rv      = isset( $in['row_version'] ) ? (int) $in['row_version'] : 0;
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE {$t} SET code = %s, name = %s, status = %s, updated_at = %s, row_version = row_version + 1 WHERE id = %d AND row_version = %d", $code, $name, $status, Db::now(), $id, $rv ) );
			if ( 1 !== (int) $changed ) {
				return new \WP_Error( 'CONFLICT', 'Someone else changed this tier. Reload and try again.' );
			}
			Audit::record( 'tier.edit', 'tier', $id, $before, $data, $user_id );
			return $id;
		}
		$data['created_at'] = Db::now();
		$wpdb->insert( $t, $data );
		$id = (int) $wpdb->insert_id;
		Audit::record( 'tier.create', 'tier', $id, null, $data, $user_id );
		return $id;
	}

	/** The tier whose price list gives the public (website) prices, if any. */
	public static function public_tier() {
		global $wpdb;
		return $wpdb->get_row( 'SELECT * FROM ' . Db::t( 'tiers' ) . " WHERE is_public = 1 AND status = 'active' ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
	}

	/**
	 * Create the public "Website price" tier (WooCommerce current price, no adjustment) once.
	 * Returns its id.
	 */
	public static function ensure_public_tier( $user_id = 0 ) {
		global $wpdb;
		$t   = Db::t( 'tiers' );
		$row = $wpdb->get_row( "SELECT * FROM {$t} WHERE is_public = 1 ORDER BY id ASC LIMIT 1" ); // phpcs:ignore
		if ( $row ) {
			return (int) $row->id;
		}
		$code = 'WEB';
		for ( $i = 2; $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE code = %s", $code ) ); $i++ ) {
			$code = 'WEB' . $i;
		}
		$data = array(
			'code'           => $code,
			'name'           => 'Website price (public)',
			'status'         => 'active',
			'price_source'   => 'woocommerce',
			'wc_price_basis' => 'current',
			'wc_adjust_pct'  => 0,
			'wc_rounding'    => 0,
			'is_public'      => 1,
			'created_at'     => Db::now(),
			'updated_at'     => Db::now(),
		);
		$wpdb->insert( $t, $data );
		$id = (int) $wpdb->insert_id;
		Audit::record( 'tier.create', 'tier', $id, null, $data, $user_id );
		return $id;
	}

	/**
	 * Price rule of a tier: source csv|woocommerce; for WooCommerce the basis (current = sale price
	 * when on sale, else regular | regular), adjustment in percent (-90…+200) and rounding (0 = none,
	 * or to the nearest 1/5/10 taka).
	 */
	public static function save_tier_rule( $id, array $in, $user_id ) {
		global $wpdb;
		$tier = self::tier( $id );
		if ( ! $tier ) {
			return new \WP_Error( 'NOT_FOUND', 'Tier not found.' );
		}
		$errors = array();
		$source = isset( $in['price_source'] ) && 'woocommerce' === $in['price_source'] ? 'woocommerce' : 'csv';
		$basis  = isset( $in['wc_price_basis'] ) && 'regular' === $in['wc_price_basis'] ? 'regular' : 'current';
		$adjust = isset( $in['wc_adjust_pct'] ) && '' !== $in['wc_adjust_pct'] ? filter_var( Util::ascii_digits( (string) $in['wc_adjust_pct'] ), FILTER_VALIDATE_FLOAT ) : 0.0;
		$round  = isset( $in['wc_rounding'] ) ? (int) $in['wc_rounding'] : 0;
		if ( false === $adjust || $adjust < -90 || $adjust > 200 ) {
			$errors['wc_adjust_pct'] = 'Adjustment must be a number from -90 to 200 (percent).';
		}
		if ( ! in_array( $round, array( 0, 1, 5, 10 ), true ) ) {
			$errors['wc_rounding'] = 'Rounding must be none, 1, 5 or 10.';
		}
		if ( (int) $tier->is_public && ( 'woocommerce' !== $source || 'current' !== $basis || 0.0 !== round( (float) $adjust, 2 ) ) ) {
			$errors['price_source'] = 'The public website tier always uses the current WooCommerce price with no adjustment.';
		}
		if ( $errors ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Please fix the errors.', array( 'fields' => $errors ) );
		}
		$data = array(
			'price_source'   => $source,
			'wc_price_basis' => $basis,
			'wc_adjust_pct'  => round( (float) $adjust, 2 ),
			'wc_rounding'    => $round,
			'updated_at'     => Db::now(),
		);
		$wpdb->update( Db::t( 'tiers' ), $data, array( 'id' => (int) $id ) );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Db::t( 'tiers' ) . ' SET row_version = row_version + 1 WHERE id = %d', $id ) );
		Audit::record( 'tier.price_rule', 'tier', $id, array( 'price_source' => $tier->price_source, 'wc_price_basis' => $tier->wc_price_basis, 'wc_adjust_pct' => $tier->wc_adjust_pct, 'wc_rounding' => $tier->wc_rounding ), $data, $user_id );
		return (int) $id;
	}

	/* ------------------------------------------------------------------ Dealers */

	public static function dealer( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT d.*, t.code AS tier_code, t.name AS tier_name, t.status AS tier_status FROM ' . Db::t( 'dealers' ) . ' d LEFT JOIN ' . Db::t( 'tiers' ) . ' t ON t.id = d.tier_id WHERE d.id = %d', $id )
		);
	}

	public static function save_dealer( array $in, $user_id ) {
		global $wpdb;
		$id       = isset( $in['id'] ) ? (int) $in['id'] : 0;
		$code     = strtoupper( trim( sanitize_text_field( isset( $in['dealer_code'] ) ? $in['dealer_code'] : '' ) ) );
		$name     = trim( sanitize_text_field( isset( $in['name'] ) ? $in['name'] : '' ) );
		$tier_id  = isset( $in['tier_id'] ) ? (int) $in['tier_id'] : 0;
		$district = isset( $in['district_code'] ) ? strtoupper( trim( sanitize_text_field( $in['district_code'] ) ) ) : '';
		$status   = ( isset( $in['status'] ) && 'inactive' === $in['status'] ) ? 'inactive' : 'active';
		$note     = trim( sanitize_textarea_field( isset( $in['note'] ) ? $in['note'] : '' ) );
		$errors   = array();
		if ( ! preg_match( '/^[A-Z0-9_\-\/.]{1,30}$/', $code ) ) {
			$errors['dealer_code'] = 'Dealer code: 1–30 characters (A–Z, 0–9, - _ / .).';
		}
		if ( '' === $name || Util::strlen( $name ) > 150 ) {
			$errors['name'] = 'Name is required (max 150 characters).';
		}
		$tier = $tier_id ? self::tier( $tier_id ) : null;
		if ( ! $tier || 'active' !== $tier->status ) {
			$errors['tier_id'] = 'Choose an active tier.';
		}
		if ( '' !== $district && ! preg_match( '/^BD-\d{2}$/', $district ) ) {
			$errors['district_code'] = 'Use a district code like BD-13.';
		}
		if ( Util::strlen( $note ) > 500 ) {
			$errors['note'] = 'Max 500 characters.';
		}
		$t = Db::t( 'dealers' );
		if ( empty( $errors['dealer_code'] ) && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE dealer_code = %s AND id <> %d", $code, $id ) ) ) {
			$errors['dealer_code'] = 'This dealer code is already used.';
		}
		if ( $errors ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Please fix the errors.', array( 'fields' => $errors ) );
		}
		$data = array(
			'dealer_code'   => $code,
			'name'          => $name,
			'tier_id'       => $tier_id,
			'district_code' => '' === $district ? null : $district,
			'status'        => $status,
			'note'          => '' === $note ? null : $note,
			'updated_at'    => Db::now(),
		);
		if ( $id ) {
			$before = self::dealer( $id );
			if ( ! $before ) {
				return new \WP_Error( 'NOT_FOUND', 'Dealer not found.' );
			}
			$rv      = isset( $in['row_version'] ) ? (int) $in['row_version'] : 0;
			$changed = $wpdb->query(
				$wpdb->prepare(
					"UPDATE {$t} SET dealer_code = %s, name = %s, tier_id = %d, district_code = %s, status = %s, note = %s, updated_at = %s, row_version = row_version + 1 WHERE id = %d AND row_version = %d",
					$code,
					$name,
					$tier_id,
					(string) $data['district_code'],
					$status,
					(string) $data['note'],
					Db::now(),
					$id,
					$rv
				)
			);
			if ( 1 !== (int) $changed ) {
				return new \WP_Error( 'CONFLICT', 'Someone else changed this dealer. Reload and try again.' );
			}
			if ( null === $data['district_code'] || null === $data['note'] ) {
				$wpdb->update( $t, array( 'district_code' => $data['district_code'], 'note' => $data['note'] ), array( 'id' => $id ) );
			}
			Audit::record( 'dealer.edit', 'dealer', $id, $before, $data, $user_id );
			return $id;
		}
		$data['created_by'] = (int) $user_id;
		$data['created_at'] = Db::now();
		$wpdb->insert( $t, $data );
		$id = (int) $wpdb->insert_id;
		Audit::record( 'dealer.create', 'dealer', $id, null, $data, $user_id );
		return $id;
	}

	/** Admin list with filters. */
	public static function list_admin( $args ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['s'] ) ) {
			$like    = '%' . $wpdb->esc_like( $args['s'] ) . '%';
			$where[] = '(d.dealer_code LIKE %s OR d.name LIKE %s)';
			array_push( $vals, $like, $like );
		}
		if ( ! empty( $args['tier_id'] ) ) {
			$where[] = 'd.tier_id = %d';
			$vals[]  = (int) $args['tier_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$where[] = 'd.status = %s';
			$vals[]  = $args['status'];
		}
		$page = max( 1, (int) ( isset( $args['paged'] ) ? $args['paged'] : 1 ) );
		$from = 'FROM ' . Db::t( 'dealers' ) . ' d LEFT JOIN ' . Db::t( 'tiers' ) . ' t ON t.id = d.tier_id WHERE ' . implode( ' AND ', $where );
		$cnt  = "SELECT COUNT(*) {$from}";
		$sql  = "SELECT d.*, t.code AS tier_code {$from} ORDER BY d.dealer_code ASC LIMIT 20 OFFSET " . ( ( $page - 1 ) * 20 );
		return array(
			$wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql ), // phpcs:ignore
			(int) $wpdb->get_var( $vals ? $wpdb->prepare( $cnt, $vals ) : $cnt ), // phpcs:ignore
		);
	}

	/** Active dealers matching code prefix or name (S-09). */
	public static function search_active( $search, $page = 1, $per_page = 10 ) {
		global $wpdb;
		$per_page = max( 1, min( 50, (int) $per_page ) );
		$page     = max( 1, (int) $page );
		$from     = 'FROM ' . Db::t( 'dealers' ) . ' d JOIN ' . Db::t( 'tiers' ) . " t ON t.id = d.tier_id WHERE d.status = 'active' AND t.status = 'active' AND (d.dealer_code LIKE %s OR d.name LIKE %s)";
		$args     = array( $wpdb->esc_like( strtoupper( $search ) ) . '%', '%' . $wpdb->esc_like( $search ) . '%' );
		$total    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) {$from}", $args ) ); // phpcs:ignore
		$rows     = $wpdb->get_results( $wpdb->prepare( "SELECT d.id, d.dealer_code, d.name, d.district_code, t.code AS tier_code {$from} ORDER BY d.dealer_code ASC LIMIT %d OFFSET %d", array_merge( $args, array( $per_page, ( $page - 1 ) * $per_page ) ) ) ); // phpcs:ignore
		return array( $rows, $total );
	}

	public static function count_active() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Db::t( 'dealers' ) . " WHERE status = 'active'" );
	}

	/** Active dealers per tier id. */
	public static function count_by_tier() {
		global $wpdb;
		$out = array();
		foreach ( $wpdb->get_results( 'SELECT tier_id, COUNT(*) AS n FROM ' . Db::t( 'dealers' ) . " WHERE status = 'active' GROUP BY tier_id" ) as $r ) { // phpcs:ignore
			$out[ (int) $r->tier_id ] = (int) $r->n;
		}
		return $out;
	}
}
