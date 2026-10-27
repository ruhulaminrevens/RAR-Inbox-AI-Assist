<?php
/**
 * Conversation → dealer links (FR-07, BR-07, Schema §4.7, §8).
 *
 * @package RarIaa
 */

namespace RarIaa\Domain;

use RarIaa\Audit;
use RarIaa\Db;

defined( 'ABSPATH' ) || exit;

class ThreadLinkRepository {

	/** Raw header value "asset_id:selected_item_id" → validated key or null. */
	public static function parse_key( $raw ) {
		$raw = trim( (string) $raw );
		if ( ! preg_match( '/^([A-Za-z0-9_.\-]{1,64}):([A-Za-z0-9_.\-]{1,128})$/', $raw ) ) {
			return null;
		}
		return $raw;
	}

	/** HMAC of the conversation key with the site secret. Never leaves the server. */
	public static function hash( $key ) {
		$secret = (string) get_option( 'rar_iaa_thread_secret' );
		return hash_hmac( 'sha256', $key, $secret );
	}

	public static function get( $thread_hash ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT l.*, d.dealer_code, d.name AS dealer_name, d.status AS dealer_status, d.tier_id, t.code AS tier_code, t.status AS tier_status, u.display_name AS linked_by_name
				 FROM ' . Db::t( 'thread_links' ) . ' l
				 JOIN ' . Db::t( 'dealers' ) . ' d ON d.id = l.dealer_id
				 LEFT JOIN ' . Db::t( 'tiers' ) . ' t ON t.id = d.tier_id
				 LEFT JOIN ' . $wpdb->users . ' u ON u.ID = l.linked_by
				 WHERE l.thread_hash = %s',
				$thread_hash
			)
		);
	}

	/**
	 * Link or change. $expected is the dealer id the client believes is linked now (null = none).
	 *
	 * @return object|\WP_Error the new link row
	 */
	public static function link( $thread_hash, $dealer_id, $expected, $user_id ) {
		global $wpdb;
		$dealer = DealerRepository::dealer( $dealer_id );
		if ( ! $dealer || 'active' !== $dealer->status || 'active' !== $dealer->tier_status ) {
			return new \WP_Error( 'NOT_FOUND', 'Active dealer not found.', array( 'status' => 404 ) );
		}
		$t       = Db::t( 'thread_links' );
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE thread_hash = %s", $thread_hash ) );
		if ( null === $expected ) {
			if ( $current ) {
				return self::conflict( $thread_hash );
			}
			$suppress = $wpdb->suppress_errors( true );
			$ok       = $wpdb->insert(
				$t,
				array(
					'thread_hash'  => $thread_hash,
					'dealer_id'    => (int) $dealer_id,
					'linked_by'    => (int) $user_id,
					'linked_at'    => Db::now(),
					'updated_at'   => Db::now(),
					'last_seen_at' => Db::now(),
				)
			);
			$wpdb->suppress_errors( $suppress );
			if ( ! $ok ) {
				return self::conflict( $thread_hash );
			}
		} else {
			$changed = $wpdb->query(
				$wpdb->prepare( "UPDATE {$t} SET dealer_id = %d, linked_by = %d, updated_at = %s WHERE thread_hash = %s AND dealer_id = %d", $dealer_id, $user_id, Db::now(), $thread_hash, $expected )
			);
			if ( 1 !== (int) $changed ) {
				return self::conflict( $thread_hash );
			}
		}
		Audit::record( 'thread.link', 'thread_link', null, $current ? array( 'dealer_id' => (int) $current->dealer_id ) : null, array( 'dealer_id' => (int) $dealer_id ), $user_id );
		return self::get( $thread_hash );
	}

	public static function unlink( $thread_hash, $user_id ) {
		global $wpdb;
		$t       = Db::t( 'thread_links' );
		$current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE thread_hash = %s", $thread_hash ) );
		if ( ! $current ) {
			return new \WP_Error( 'NOT_FOUND', 'No link for this conversation.', array( 'status' => 404 ) );
		}
		$wpdb->delete( $t, array( 'id' => (int) $current->id ) );
		Audit::record( 'thread.unlink', 'thread_link', (int) $current->id, array( 'dealer_id' => (int) $current->dealer_id ), null, $user_id );
		return true;
	}

	public static function touch( $thread_hash ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . Db::t( 'thread_links' ) . ' SET last_seen_at = %s WHERE thread_hash = %s AND last_seen_at < %s', Db::now(), $thread_hash, Db::now( -DAY_IN_SECONDS ) ) );
	}

	private static function conflict( $thread_hash ) {
		$cur = self::get( $thread_hash );
		return new \WP_Error(
			'LINK_CONFLICT',
			'The link was changed by someone else.',
			array(
				'status'  => 409,
				'current' => $cur ? array( 'dealer_id' => (int) $cur->dealer_id, 'code' => $cur->dealer_code, 'name' => $cur->dealer_name ) : null,
			)
		);
	}
}
