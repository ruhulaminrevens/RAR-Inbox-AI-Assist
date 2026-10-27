<?php
/**
 * Price lists per tier: CSV import, activation, rollback (FR-16, BR-08, BR-10, Schema §4.5–4.6, §8).
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Audit;
use RarIaa\Db;
use RarIaa\Lock;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class PriceRepository {

	const MAX_BYTES = 2097152;
	const MAX_ROWS  = 5000;
	const REQUIRED  = array( 'item_code', 'product_name', 'unit', 'price_bdt' );
	const OPTIONAL  = array( 'aliases', 'pack_size', 'mrp_bdt', 'wc_product_id', 'stock_mode', 'manual_stock_status' );

	public static function get_list( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT l.*, t.code AS tier_code FROM ' . Db::t( 'price_lists' ) . ' l LEFT JOIN ' . Db::t( 'tiers' ) . ' t ON t.id = l.tier_id WHERE l.id = %d', $id ) );
	}

	public static function lists( $args = array() ) {
		global $wpdb;
		$where = array( '1=1' );
		$vals  = array();
		if ( ! empty( $args['tier_id'] ) ) {
			$where[] = 'l.tier_id = %d';
			$vals[]  = (int) $args['tier_id'];
		}
		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'draft', 'active', 'archived' ), true ) ) {
			$where[] = 'l.status = %s';
			$vals[]  = $args['status'];
		}
		$sql = 'SELECT l.*, t.code AS tier_code FROM ' . Db::t( 'price_lists' ) . ' l LEFT JOIN ' . Db::t( 'tiers' ) . ' t ON t.id = l.tier_id WHERE ' . implode( ' AND ', $where ) . ' ORDER BY l.id DESC LIMIT 200';
		return $wpdb->get_results( $vals ? $wpdb->prepare( $sql, $vals ) : $sql ); // phpcs:ignore
	}

	public static function active_for_tier( $tier_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'price_lists' ) . ' WHERE tier_id = %d AND active_flag = 1', $tier_id ) );
	}

	public static function items( $list_id, $search = '' ) {
		global $wpdb;
		$t = Db::t( 'price_items' );
		if ( '' !== $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE price_list_id = %d AND (item_code LIKE %s OR product_name LIKE %s OR aliases LIKE %s) ORDER BY item_code", $list_id, $like, $like, $like ) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE price_list_id = %d ORDER BY item_code", $list_id ) );
	}

	public static function items_by_ids( array $ids ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( ! $ids ) {
			return array();
		}
		return $wpdb->get_results( 'SELECT i.*, l.source AS list_source FROM ' . Db::t( 'price_items' ) . ' i JOIN ' . Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE i.id IN (' . implode( ',', $ids ) . ')' ); // phpcs:ignore
	}

	/** Active items for public facts: the public tier's list when it exists, else every active list. */
	public static function public_items() {
		global $wpdb;
		$pub = DealerRepository::public_tier();
		if ( $pub ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT i.*, l.source AS list_source FROM ' . Db::t( 'price_items' ) . ' i JOIN ' . Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE l.tier_id = %d AND l.active_flag = 1 ORDER BY i.id ASC LIMIT 5000', $pub->id ) );
			if ( $rows ) {
				return $rows;
			}
		}
		return $wpdb->get_results( 'SELECT i.*, l.source AS list_source FROM ' . Db::t( 'price_items' ) . ' i JOIN ' . Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE l.active_flag = 1 ORDER BY i.id ASC LIMIT 5000' ); // phpcs:ignore
	}

	/** Items of the active list of every tier, keyed by WooCommerce product id (for the linked-products view). */
	public static function active_items_by_wc_product() {
		global $wpdb;
		$rows = $wpdb->get_results( 'SELECT i.wc_product_id, i.price_bdt, l.tier_id FROM ' . Db::t( 'price_items' ) . ' i JOIN ' . Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE l.active_flag = 1 AND i.wc_product_id IS NOT NULL' ); // phpcs:ignore
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ (int) $r->wc_product_id ][ (int) $r->tier_id ] = (float) $r->price_bdt;
		}
		return $out;
	}

	/** Delete old archived WooCommerce sync snapshots of a tier, keeping the newest $keep. */
	public static function prune_synced( $tier_id, $keep = 10 ) {
		global $wpdb;
		$t   = Db::t( 'price_lists' );
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$t} WHERE tier_id = %d AND source = 'woocommerce' AND status = 'archived' ORDER BY id DESC LIMIT 1000 OFFSET %d", $tier_id, $keep ) );
		$cur = $wpdb->get_var( $wpdb->prepare( "SELECT previous_list_id FROM {$t} WHERE tier_id = %d AND active_flag = 1", $tier_id ) );
		$ids = array_diff( array_map( 'intval', $ids ), array( (int) $cur ) );
		if ( ! $ids ) {
			return 0;
		}
		$in = implode( ',', $ids );
		$wpdb->query( 'DELETE FROM ' . Db::t( 'price_items' ) . " WHERE price_list_id IN ({$in})" ); // phpcs:ignore
		$wpdb->query( "DELETE FROM {$t} WHERE id IN ({$in})" ); // phpcs:ignore
		return count( $ids );
	}

	/** Items of every active list that also carry an MRP (public price candidates). */
	public static function active_items_with_mrp() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT i.* FROM ' . Db::t( 'price_items' ) . ' i JOIN ' . Db::t( 'price_lists' ) . ' l ON l.id = i.price_list_id WHERE l.active_flag = 1 AND i.mrp_bdt IS NOT NULL' );
	}

	/** Parse a number written with ASCII or Bangla digits, grouping commas, ৳/tk/taka. */
	public static function parse_money( $raw ) {
		$s = Util::ascii_digits( trim( (string) $raw ) );
		$s = preg_replace( '/(৳|tk\.?|taka|bdt|টাকা|\/-)/iu', '', $s );
		$s = str_replace( array( ',', ' ' ), '', $s );
		if ( ! preg_match( '/^\d+(\.\d{1,2})?$/', $s ) ) {
			return null;
		}
		return (float) $s;
	}

	/**
	 * Validate a CSV file. Returns array( 'rows' => [...], 'errors' => [[row, column, message]], 'warnings' => [...], 'sha256' => '' ).
	 */
	public static function parse_csv( $path ) {
		$result = array( 'rows' => array(), 'errors' => array(), 'warnings' => array(), 'sha256' => '' );
		if ( ! is_readable( $path ) ) {
			$result['errors'][] = array( 0, '', 'File could not be read.' );
			return $result;
		}
		if ( filesize( $path ) > self::MAX_BYTES ) {
			$result['errors'][] = array( 0, '', 'File is larger than 2 MB.' );
			return $result;
		}
		$content = (string) file_get_contents( $path ); // phpcs:ignore
		$result['sha256'] = hash( 'sha256', $content );
		if ( 0 === strpos( $content, "\xEF\xBB\xBF" ) ) {
			$content = substr( $content, 3 );
		}
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $content, 'UTF-8' ) ) {
			$result['errors'][] = array( 0, '', 'File is not UTF-8. In Excel use "Save As → CSV UTF-8".' );
			return $result;
		}
		$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore
		fwrite( $fh, $content ); // phpcs:ignore
		rewind( $fh );
		$header = fgetcsv( $fh, 0, ',', '"', '\\' );
		if ( ! $header ) {
			$result['errors'][] = array( 1, '', 'The file is empty.' );
			return $result;
		}
		$cols = array();
		foreach ( $header as $i => $h ) {
			$h = strtolower( trim( (string) $h ) );
			if ( '' === $h ) {
				continue;
			}
			if ( ! in_array( $h, array_merge( self::REQUIRED, self::OPTIONAL ), true ) ) {
				$result['errors'][] = array( 1, $h, 'Unknown column. Allowed: ' . implode( ', ', array_merge( self::REQUIRED, self::OPTIONAL ) ) );
				continue;
			}
			if ( isset( $cols[ $h ] ) ) {
				$result['errors'][] = array( 1, $h, 'Column appears twice.' );
			}
			$cols[ $h ] = $i;
		}
		foreach ( self::REQUIRED as $req ) {
			if ( ! isset( $cols[ $req ] ) ) {
				$result['errors'][] = array( 1, $req, 'Required column is missing.' );
			}
		}
		if ( $result['errors'] ) {
			return $result;
		}
		$seen   = array();
		$line   = 1;
		$wc     = function_exists( 'wc_get_product' );
		while ( false !== ( $data = fgetcsv( $fh, 0, ',', '"', '\\' ) ) ) {
			$line++;
			if ( 1 === count( $data ) && ( null === $data[0] || '' === trim( (string) $data[0] ) ) ) {
				continue;
			}
			if ( count( $result['rows'] ) >= self::MAX_ROWS ) {
				$result['errors'][] = array( $line, '', 'More than 5,000 rows.' );
				break;
			}
			$get = static function ( $c ) use ( $cols, $data ) {
				return isset( $cols[ $c ], $data[ $cols[ $c ] ] ) ? trim( (string) $data[ $cols[ $c ] ] ) : '';
			};
			$row  = array();
			$code = strtoupper( sanitize_text_field( $get( 'item_code' ) ) );
			if ( ! preg_match( '/^[A-Z0-9_\-\/.]{1,40}$/', $code ) ) {
				$result['errors'][] = array( $line, 'item_code', 'Required; 1–40 characters (A–Z, 0–9, - _ / .).' );
			} elseif ( isset( $seen[ $code ] ) ) {
				$result['errors'][] = array( $line, 'item_code', 'Duplicate code (also on row ' . $seen[ $code ] . ').' );
			} else {
				$seen[ $code ] = $line;
			}
			$row['item_code']    = $code;
			$row['product_name'] = sanitize_text_field( $get( 'product_name' ) );
			if ( '' === $row['product_name'] || Util::strlen( $row['product_name'] ) > 200 ) {
				$result['errors'][] = array( $line, 'product_name', 'Required (max 200 characters).' );
			}
			$row['unit'] = sanitize_text_field( $get( 'unit' ) );
			if ( '' === $row['unit'] || Util::strlen( $row['unit'] ) > 30 ) {
				$result['errors'][] = array( $line, 'unit', 'Required (max 30 characters), e.g. carton, dozen, pcs.' );
			}
			$price = self::parse_money( $get( 'price_bdt' ) );
			if ( null === $price || $price <= 0 ) {
				$result['errors'][] = array( $line, 'price_bdt', 'Must be a number above 0 with at most 2 decimals.' );
			}
			$row['price_bdt'] = $price;
			$mrp_raw          = $get( 'mrp_bdt' );
			$row['mrp_bdt']   = null;
			if ( '' !== $mrp_raw ) {
				$mrp = self::parse_money( $mrp_raw );
				if ( null === $mrp || $mrp <= 0 ) {
					$result['errors'][] = array( $line, 'mrp_bdt', 'Must be a number above 0 or empty.' );
				}
				$row['mrp_bdt'] = $mrp;
			}
			$aliases = array();
			foreach ( preg_split( '/[|;]/u', $get( 'aliases' ) ) as $a ) {
				$a = trim( sanitize_text_field( $a ) );
				if ( '' !== $a ) {
					$aliases[] = $a;
				}
			}
			$row['aliases'] = implode( '|', $aliases );
			if ( strlen( $row['aliases'] ) > 1000 ) {
				$result['errors'][] = array( $line, 'aliases', 'Max 1000 characters.' );
			}
			$row['pack_size'] = sanitize_text_field( $get( 'pack_size' ) );
			if ( Util::strlen( $row['pack_size'] ) > 50 ) {
				$result['errors'][] = array( $line, 'pack_size', 'Max 50 characters.' );
			}
			$row['pack_size']     = '' === $row['pack_size'] ? null : $row['pack_size'];
			$wid                  = Util::ascii_digits( $get( 'wc_product_id' ) );
			$row['wc_product_id'] = null;
			if ( '' !== $wid ) {
				if ( ! ctype_digit( $wid ) ) {
					$result['errors'][] = array( $line, 'wc_product_id', 'Must be a WooCommerce product ID number.' );
				} else {
					$row['wc_product_id'] = (int) $wid;
					if ( ! $wc ) {
						$result['warnings'][] = array( $line, 'wc_product_id', 'WooCommerce is not active; stock will show as unknown.' );
					} elseif ( ! wc_get_product( (int) $wid ) ) {
						$result['warnings'][] = array( $line, 'wc_product_id', 'No WooCommerce product with this ID.' );
					}
				}
			}
			$mode = strtolower( $get( 'stock_mode' ) );
			if ( '' === $mode ) {
				$mode = $row['wc_product_id'] ? 'wc' : 'hidden';
			}
			if ( ! in_array( $mode, array( 'wc', 'manual', 'hidden' ), true ) ) {
				$result['errors'][] = array( $line, 'stock_mode', 'Use wc, manual or hidden.' );
			} elseif ( 'wc' === $mode && ! $row['wc_product_id'] ) {
				$result['errors'][] = array( $line, 'stock_mode', 'stock_mode wc needs wc_product_id.' );
			}
			$row['stock_mode']          = $mode;
			$manual                     = strtolower( $get( 'manual_stock_status' ) );
			$row['manual_stock_status'] = '' === $manual ? null : $manual;
			if ( 'manual' === $mode && ! in_array( $manual, array( 'in_stock', 'limited', 'out_of_stock' ), true ) ) {
				$result['errors'][] = array( $line, 'manual_stock_status', 'Required for manual mode: in_stock, limited or out_of_stock.' );
			} elseif ( '' !== $manual && ! in_array( $manual, array( 'in_stock', 'limited', 'out_of_stock' ), true ) ) {
				$result['errors'][] = array( $line, 'manual_stock_status', 'Use in_stock, limited or out_of_stock.' );
			}
			$result['rows'][] = $row;
		}
		fclose( $fh ); // phpcs:ignore
		if ( ! $result['rows'] && ! $result['errors'] ) {
			$result['errors'][] = array( 2, '', 'No data rows.' );
		}
		return $result;
	}

	/** Save a validated parse result as a draft list. Returns list id or WP_Error. */
	public static function save_draft( $tier_id, $name, $effective_from, array $parsed, $user_id, $source = 'csv' ) {
		global $wpdb;
		$tier = DealerRepository::tier( $tier_id );
		$name = trim( sanitize_text_field( $name ) );
		if ( ! $tier || 'active' !== $tier->status ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Choose an active tier.' );
		}
		if ( '' === $name || Util::strlen( $name ) > 150 ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Name is required (max 150 characters).' );
		}
		$dt = \DateTime::createFromFormat( 'Y-m-d', (string) $effective_from );
		if ( ! $dt || $dt->format( 'Y-m-d' ) !== $effective_from ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Effective date must be YYYY-MM-DD.' );
		}
		if ( $parsed['errors'] || ! $parsed['rows'] ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'The file has errors; nothing was saved.' );
		}
		$source = 'woocommerce' === $source ? 'woocommerce' : 'csv';
		return Db::transaction(
			function () use ( $wpdb, $tier_id, $name, $effective_from, $parsed, $user_id, $source ) {
				$wpdb->insert(
					Db::t( 'price_lists' ),
					array(
						'tier_id'        => (int) $tier_id,
						'name'           => $name,
						'source'         => $source,
						'status'         => 'draft',
						'effective_from' => $effective_from,
						'file_sha256'    => $parsed['sha256'],
						'row_count'      => count( $parsed['rows'] ),
						'imported_by'    => (int) $user_id,
						'imported_at'    => Db::now(),
					)
				);
				$list_id = (int) $wpdb->insert_id;
				foreach ( $parsed['rows'] as $r ) {
					$r['price_list_id'] = $list_id;
					$wpdb->insert( Db::t( 'price_items' ), $r );
				}
				Audit::record( 'woocommerce' === $source ? 'price_list.wc_sync' : 'price_list.import', 'price_list', $list_id, null, array( 'tier_id' => (int) $tier_id, 'name' => $name, 'rows' => count( $parsed['rows'] ), 'sha256' => $parsed['sha256'] ), $user_id );
				return $list_id;
			}
		);
	}

	/** Activate a draft (or archived) list for its tier; the previous active list becomes archived. */
	public static function activate( $list_id, $user_id, $reactivate_previous = false ) {
		global $wpdb;
		$list = self::get_list( $list_id );
		if ( ! $list ) {
			return new \WP_Error( 'NOT_FOUND', 'Price list not found.' );
		}
		$lock = 'tier_' . (int) $list->tier_id;
		if ( ! Lock::acquire( $lock, 5, 60 ) ) {
			return new \WP_Error( 'BUSY', 'Another activation is running. Try again.' );
		}
		try {
			return Db::transaction(
				function () use ( $wpdb, $list_id, $user_id, $reactivate_previous ) {
					$t    = Db::t( 'price_lists' );
					$list = self::get_list( $list_id );
					$cur  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE tier_id = %d AND active_flag = 1", $list->tier_id ) );
					if ( $reactivate_previous ) {
						if ( 'archived' !== $list->status || ! $cur || (int) $cur->previous_list_id !== (int) $list->id ) {
							throw new ConflictException( 'Only the list that was active before the current one can be re-activated.' );
						}
					} elseif ( 'draft' !== $list->status ) {
						throw new ConflictException( 'Only a draft list can be activated.' );
					}
					if ( $cur ) {
						$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'archived', active_flag = NULL, archived_at = %s WHERE id = %d", Db::now(), $cur->id ) );
					}
					$set_prev = $reactivate_previous ? '' : ', previous_list_id = ' . ( $cur ? (int) $cur->id : 'NULL' );
					$wpdb->query( $wpdb->prepare( "UPDATE {$t} SET status = 'active', active_flag = 1, activated_by = %d, activated_at = %s, archived_at = NULL{$set_prev} WHERE id = %d", $user_id, Db::now(), $list->id ) ); // phpcs:ignore
					$check = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE tier_id = %d AND active_flag = 1", $list->tier_id ) );
					if ( 1 !== $check ) {
						throw new ConflictException( 'Activation conflict; nothing changed.' );
					}
					Audit::record( $reactivate_previous ? 'price_list.reactivate' : 'price_list.activate', 'price_list', $list->id, array( 'active_list_id' => $cur ? (int) $cur->id : null ), array( 'active_list_id' => (int) $list->id ), $user_id );
					return true;
				}
			);
		} finally {
			Lock::release( $lock );
		}
	}

	public static function archive_draft( $list_id, $user_id ) {
		global $wpdb;
		$list = self::get_list( $list_id );
		if ( ! $list || 'draft' !== $list->status ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Only draft lists can be archived here.' );
		}
		$wpdb->update( Db::t( 'price_lists' ), array( 'status' => 'archived', 'archived_at' => Db::now() ), array( 'id' => (int) $list_id ) );
		Audit::record( 'price_list.archive', 'price_list', $list_id, array( 'status' => 'draft' ), array( 'status' => 'archived' ), $user_id );
		return true;
	}
}
