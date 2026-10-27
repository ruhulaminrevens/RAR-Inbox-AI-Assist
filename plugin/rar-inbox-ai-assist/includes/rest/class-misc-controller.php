<?php
/**
 * /me, /dealers, /kb/*, /tokens/self/revoke (TRD §5.1).
 *
 * @package RarIaa
 */

namespace RarIaa\Rest;

use RarIaa\Auth\RestAuth;
use RarIaa\Auth\TokenService;
use RarIaa\Capabilities;
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\KbRepository;
use RarIaa\Limits\Budget;
use RarIaa\Limits\RateLimiter;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class MiscController {

	public static function me() {
		$user  = wp_get_current_user();
		$today = Budget::today();
		return Api::respond(
			array(
				'user'        => array( 'id' => (int) $user->ID, 'name' => $user->display_name ),
				'caps'        => Capabilities::short_list( $user->ID ),
				'service'     => array( 'enabled' => Settings::service_enabled(), 'ai_enabled' => (bool) Settings::get( 'ai_enabled' ) ),
				'limits'      => array( 'per_minute' => (int) Settings::get( 'per_minute' ), 'daily' => (int) Settings::get( 'daily_limit' ), 'today' => (int) $today->requests ),
				'settings'    => array(
					'banglish_reply_script' => Settings::get( 'banglish_reply_script' ),
					'reply_digits'          => Settings::get( 'reply_digits' ),
					'suggestion_count'      => (int) Settings::get( 'suggestion_count' ),
					'live_inbox'            => (bool) Settings::get( 'live_inbox' ),
					'public_prices'         => (bool) Settings::get( 'allow_public_mrp' ),
				),
				'server_time' => gmdate( 'c' ),
				'min_client'  => RAR_IAA_MIN_CLIENT,
				'version'     => RAR_IAA_VERSION,
			)
		);
	}

	private static function limited( $scope ) {
		$wait = RateLimiter::hit( get_current_user_id(), $scope, 30 );
		return $wait ? new \WP_Error( 'RATE_LIMITED', 'Too many requests.', array( 'status' => 429, 'retry_after' => $wait ) ) : null;
	}

	public static function dealers( \WP_REST_Request $r ) {
		$err = self::limited( 'dealers' );
		if ( $err ) {
			return Api::respond( $err );
		}
		$search = trim( sanitize_text_field( (string) $r->get_param( 'search' ) ) );
		if ( Util::strlen( $search ) < 2 || Util::strlen( $search ) > 60 ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'search must be 2–60 characters.', array( 'status' => 422 ) ) );
		}
		$page                 = max( 1, (int) $r->get_param( 'page' ) );
		$per                  = (int) $r->get_param( 'per_page' );
		$per                  = $per ? max( 1, min( 50, $per ) ) : 10;
		list( $rows, $total ) = DealerRepository::search_active( $search, $page, $per );
		$out                  = array();
		foreach ( $rows as $d ) {
			$out[] = array( 'id' => (int) $d->id, 'code' => $d->dealer_code, 'name' => $d->name, 'tier' => $d->tier_code, 'district' => $d->district_code );
		}
		$resp = Api::respond( $out );
		$resp->header( 'X-WP-Total', (string) $total );
		$resp->header( 'X-WP-TotalPages', (string) max( 1, (int) ceil( $total / $per ) ) );
		return $resp;
	}

	public static function kb_search( \WP_REST_Request $r ) {
		$err = self::limited( 'kb' );
		if ( $err ) {
			return Api::respond( $err );
		}
		if ( ! Settings::service_enabled() ) {
			return Api::respond( new \WP_Error( 'SERVICE_DISABLED', 'Service is turned off by the administrator.', array( 'status' => 503 ) ) );
		}
		$q = trim( sanitize_text_field( (string) $r->get_param( 'q' ) ) );
		if ( Util::strlen( $q ) < 2 || Util::strlen( $q ) > 100 ) {
			return Api::respond( new \WP_Error( 'VALIDATION_FAILED', 'q must be 2–100 characters.', array( 'status' => 422 ) ) );
		}
		$out = array();
		foreach ( KbRepository::search_serving( $q, 10 ) as $row ) {
			$out[] = array( 'id' => (int) $row->entry_id, 'title' => $row->title, 'category' => $row->category, 'answer_bn' => $row->answer_bn, 'answer_en' => $row->answer_en );
		}
		return Api::respond( $out );
	}

	public static function kb_used( \WP_REST_Request $r ) {
		$id      = (int) $r['id'];
		$serving = false;
		foreach ( KbRepository::serving() as $row ) {
			if ( (int) $row->entry_id === $id ) {
				$serving = true;
				break;
			}
		}
		if ( ! $serving ) {
			return Api::respond( new \WP_Error( 'NOT_FOUND', 'Entry not found.', array( 'status' => 404 ) ) );
		}
		KbRepository::mark_used( array( $id ) );
		return Api::respond( null, 204 );
	}

	public static function revoke_self() {
		$token = RestAuth::token();
		TokenService::revoke( (int) $token->id, (int) $token->user_id, (int) $token->user_id );
		return Api::respond( null, 204 );
	}
}
