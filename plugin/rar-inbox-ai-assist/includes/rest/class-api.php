<?php
/**
 * REST namespace rar-iaa/v1: route registration and shared response rules (TRD §5, §7).
 *
 * @package RarIaa
 */

namespace RarIaa\Rest;

use RarIaa\Auth\RestAuth;
use RarIaa\Capabilities;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class Api {

	const NS        = 'rar-iaa/v1';
	const MAX_BODY  = 65536;

	/** @var string|null */
	private static $request_id = null;

	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( __CLASS__, 'post_dispatch' ), 10, 3 );
	}

	public static function request_id() {
		if ( null === self::$request_id ) {
			self::$request_id = Util::uuid4();
		}
		return self::$request_id;
	}

	/** Permission callback factory: capability check against the (token or cookie) user. */
	public static function can( $cap ) {
		return static function () use ( $cap ) {
			if ( ! get_current_user_id() ) {
				return new \WP_Error( 'AUTH_MISSING', 'Sign in with an Inbox AI Assist token.', array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $cap ) ) {
				return new \WP_Error( 'FORBIDDEN', 'You do not have permission for this.', array( 'status' => 403 ) );
			}
			return true;
		};
	}

	public static function routes() {
		$use  = self::can( Capabilities::USE );
		$link = self::can( Capabilities::LINK_DEALER );

		register_rest_route(
			self::NS,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( MiscController::class, 'me' ),
				'permission_callback' => static function () {
					return get_current_user_id() ? true : new \WP_Error( 'AUTH_MISSING', 'Sign in with an Inbox AI Assist token.', array( 'status' => 401 ) );
				},
			)
		);
		register_rest_route( self::NS, '/drafts', array( 'methods' => 'POST', 'callback' => array( DraftsController::class, 'create' ), 'permission_callback' => $use ) );
		register_rest_route(
			self::NS,
			'/drafts/(?P<id>[0-9a-f\-]{36})/rewrite',
			array( 'methods' => 'POST', 'callback' => array( DraftsController::class, 'rewrite' ), 'permission_callback' => $use )
		);
		register_rest_route(
			self::NS,
			'/drafts/(?P<id>[0-9a-f\-]{36})/outcome',
			array( 'methods' => 'POST', 'callback' => array( DraftsController::class, 'outcome' ), 'permission_callback' => $use )
		);
		register_rest_route(
			self::NS,
			'/threads/current/link',
			array(
				array( 'methods' => 'GET', 'callback' => array( ThreadsController::class, 'get' ), 'permission_callback' => $use ),
				array( 'methods' => 'PUT', 'callback' => array( ThreadsController::class, 'put' ), 'permission_callback' => $link ),
				array( 'methods' => 'DELETE', 'callback' => array( ThreadsController::class, 'delete' ), 'permission_callback' => $link ),
			)
		);
		register_rest_route( self::NS, '/dealers', array( 'methods' => 'GET', 'callback' => array( MiscController::class, 'dealers' ), 'permission_callback' => $link ) );
		register_rest_route( self::NS, '/kb/search', array( 'methods' => 'GET', 'callback' => array( MiscController::class, 'kb_search' ), 'permission_callback' => $use ) );
		register_rest_route( self::NS, '/kb/(?P<id>\d+)/used', array( 'methods' => 'POST', 'callback' => array( MiscController::class, 'kb_used' ), 'permission_callback' => $use ) );
		register_rest_route(
			self::NS,
			'/tokens/self/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( MiscController::class, 'revoke_self' ),
				'permission_callback' => static function () {
					return RestAuth::token() ? true : new \WP_Error( 'AUTH_MISSING', 'Token required.', array( 'status' => 401 ) );
				},
			)
		);
	}

	/** Size limit and minimum client version for our namespace. */
	public static function pre_dispatch( $result, $server, $request ) {
		if ( 0 !== strpos( ltrim( $request->get_route(), '/' ), self::NS ) || null !== $result ) {
			return $result;
		}
		if ( strlen( (string) $request->get_body() ) > self::MAX_BODY ) {
			return new \WP_Error( 'PAYLOAD_TOO_LARGE', 'Request body is too large.', array( 'status' => 413 ) );
		}
		$client = (string) $request->get_header( 'x_iaa_client' );
		if ( preg_match( '#^ext/(\d+\.\d+\.\d+)#', $client, $m ) && Util::version_lt( $m[1], RAR_IAA_MIN_CLIENT ) ) {
			return new \WP_Error( 'CLIENT_TOO_OLD', 'Please update the extension.', array( 'status' => 426, 'min_version' => RAR_IAA_MIN_CLIENT ) );
		}
		return $result;
	}

	/** No-store / no-cache headers and request id on every response in our namespace (incl. errors). */
	public static function post_dispatch( $response, $server, $request ) {
		if ( 0 !== strpos( ltrim( $request->get_route(), '/' ), self::NS ) ) {
			return $response;
		}
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache' );
		$response->header( 'X-IAA-Request-Id', self::request_id() );
		if ( $response->is_error() ) {
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
					$data['data'] = array();
				}
				$data['data']['request_id'] = self::request_id();
				$response->set_data( $data );
			}
		}
		return $response;
	}

	/** Uniform error→response conversion for controllers. */
	public static function respond( $result, $status = 200 ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( ! is_array( $data ) || ! isset( $data['status'] ) ) {
				$data           = is_array( $data ) ? $data : array();
				$data['status'] = self::status_for( $result->get_error_code() );
				$result         = new \WP_Error( $result->get_error_code(), $result->get_error_message(), $data );
			}
			$resp = rest_convert_error_to_response( $result );
			if ( isset( $data['retry_after'] ) ) {
				$resp->header( 'Retry-After', (string) (int) $data['retry_after'] );
			}
			return $resp;
		}
		if ( null === $result ) {
			return new \WP_REST_Response( null, 204 );
		}
		return new \WP_REST_Response( $result, $status );
	}

	public static function status_for( $code ) {
		$map = array(
			'VALIDATION_FAILED' => 422,
			'NOT_FOUND'         => 404,
			'FORBIDDEN'         => 403,
			'CONFLICT'          => 409,
			'BUSY'              => 503,
			'INTERNAL_ERROR'    => 500,
		);
		return isset( $map[ $code ] ) ? $map[ $code ] : 400;
	}
}
