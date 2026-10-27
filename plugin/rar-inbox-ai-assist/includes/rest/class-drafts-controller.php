<?php
/**
 * /drafts endpoints (TRD §5.3).
 *
 * @package RarIaa
 */

namespace RarIaa\Rest;

use RarIaa\Draft\DraftService;

defined( 'ABSPATH' ) || exit;

class DraftsController {

	private static function body( \WP_REST_Request $r ) {
		$json = $r->get_json_params();
		return is_array( $json ) ? $json : null;
	}

	public static function create( \WP_REST_Request $r ) {
		$body = self::body( $r );
		if ( null === $body ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'JSON body required.', array( 'status' => 422 ) ) );
		}
		$svc = new DraftService( Api::request_id() );
		return Api::respond( $svc->create( get_current_user_id(), $body, $r->get_header( 'idempotency_key' ), $r->get_header( 'x_iaa_thread' ) ) );
	}

	public static function rewrite( \WP_REST_Request $r ) {
		$body = self::body( $r );
		if ( null === $body ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'JSON body required.', array( 'status' => 422 ) ) );
		}
		$svc = new DraftService( Api::request_id() );
		return Api::respond( $svc->rewrite( get_current_user_id(), (string) $r['id'], $body, $r->get_header( 'idempotency_key' ) ) );
	}

	public static function outcome( \WP_REST_Request $r ) {
		$body = self::body( $r );
		if ( null === $body ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'JSON body required.', array( 'status' => 422 ) ) );
		}
		$res = DraftService::outcome( get_current_user_id(), (string) $r['id'], $body );
		return Api::respond( true === $res ? null : $res, 204 );
	}
}
