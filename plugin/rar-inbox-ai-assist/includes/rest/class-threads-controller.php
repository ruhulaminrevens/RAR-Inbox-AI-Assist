<?php
/**
 * /threads/current/link (TRD §5.3). The conversation key arrives in X-IAA-Thread; the HMAC
 * hash is computed here and never returned.
 *
 * @package RarIaa
 */

namespace RarIaa\Rest;

use RarIaa\Domain\ThreadLinkRepository;

defined( 'ABSPATH' ) || exit;

class ThreadsController {

	private static function hash( \WP_REST_Request $r ) {
		$key = ThreadLinkRepository::parse_key( (string) $r->get_header( 'x_iaa_thread' ) );
		return $key ? ThreadLinkRepository::hash( $key ) : null;
	}

	private static function shape( $link ) {
		return array(
			'linked'    => true,
			'dealer'    => array(
				'id'     => (int) $link->dealer_id,
				'code'   => $link->dealer_code,
				'name'   => $link->dealer_name,
				'tier'   => $link->tier_code,
				'active' => 'active' === $link->dealer_status && 'active' === $link->tier_status,
			),
			'linked_by' => $link->linked_by_name,
			'linked_at' => mysql_to_rfc3339( $link->linked_at ), // phpcs:ignore PHPCompatibility.Extensions.RemovedExtensions -- WordPress core function, not ext/mysql.
		);
	}

	private static function missing() {
		return new \WP_Error( 'VALIDATION_FAILED', 'X-IAA-Thread header is required.', array( 'status' => 422, 'fields' => array( 'X-IAA-Thread' => 'asset_id:selected_item_id' ) ) );
	}

	public static function get( \WP_REST_Request $r ) {
		$hash = self::hash( $r );
		if ( ! $hash ) {
			return Api::respond( self::missing() );
		}
		$link = ThreadLinkRepository::get( $hash );
		return Api::respond( $link ? self::shape( $link ) : array( 'linked' => false ) );
	}

	public static function put( \WP_REST_Request $r ) {
		$hash = self::hash( $r );
		if ( ! $hash ) {
			return Api::respond( self::missing() );
		}
		$b        = $r->get_json_params();
		$dealer   = isset( $b['dealer_id'] ) ? $b['dealer_id'] : null;
		$expected = array_key_exists( 'expected_dealer_id', (array) $b ) ? $b['expected_dealer_id'] : null;
		if ( ! is_int( $dealer ) || $dealer < 1 || ( null !== $expected && ( ! is_int( $expected ) || $expected < 1 ) ) ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'dealer_id (integer) is required; expected_dealer_id integer or null.', array( 'status' => 422 ) ) );
		}
		$res = ThreadLinkRepository::link( $hash, $dealer, $expected, get_current_user_id() );
		return Api::respond( is_wp_error( $res ) ? $res : self::shape( $res ) );
	}

	public static function delete( \WP_REST_Request $r ) {
		$hash = self::hash( $r );
		if ( ! $hash ) {
			return Api::respond( self::missing() );
		}
		$res = ThreadLinkRepository::unlink( $hash, get_current_user_id() );
		return Api::respond( true === $res ? null : $res, 204 );
	}
}
