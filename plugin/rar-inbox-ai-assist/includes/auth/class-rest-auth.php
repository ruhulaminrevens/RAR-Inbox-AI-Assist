<?php
/**
 * Token authentication scoped to the rar-iaa/v1 REST namespace (TRD §4, v0.2 §19).
 *
 * Three layers keep an IAA token from authenticating anything else:
 *  1. determine_current_user only acts when the request path/route is inside rar-iaa/v1;
 *  2. rest_authentication_errors re-asserts the token user after core cookie checks;
 *  3. rest_pre_dispatch refuses any non-rar-iaa route on a token-authenticated request.
 *
 * @package RarIaa
 */

namespace RarIaa\Auth;

defined( 'ABSPATH' ) || exit;

class RestAuth {

	const NS = 'rar-iaa/v1';

	/** @var object|null token row for this request */
	private static $token = null;
	/** @var \WP_Error|null */
	private static $error = null;
	/** @var bool */
	private static $attempted = false;

	public static function register() {
		add_filter( 'determine_current_user', array( __CLASS__, 'determine_user' ), 30 );
		add_filter( 'rest_authentication_errors', array( __CLASS__, 'auth_errors' ), 150 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'pre_dispatch' ), 5, 3 );
	}

	/** Presented token from Authorization: Bearer or X-IAA-Token. */
	public static function presented_token() {
		$candidates = array();
		foreach ( array( 'HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION' ) as $k ) {
			if ( ! empty( $_SERVER[ $k ] ) ) {
				$candidates[] = (string) wp_unslash( $_SERVER[ $k ] ); // phpcs:ignore
			}
		}
		foreach ( $candidates as $h ) {
			if ( preg_match( '/^Bearer\s+(iaa_\S+)$/', trim( $h ), $m ) ) {
				return array( $m[1], 'authorization' );
			}
		}
		if ( ! empty( $_SERVER['HTTP_X_IAA_TOKEN'] ) ) {
			return array( trim( (string) wp_unslash( $_SERVER['HTTP_X_IAA_TOKEN'] ) ), 'x-iaa-token' ); // phpcs:ignore
		}
		return array( null, null );
	}

	/** True only when the current HTTP request targets the rar-iaa/v1 namespace. */
	public static function is_our_request() {
		$route = null;
		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$route = (string) wp_unslash( $_GET['rest_route'] ); // phpcs:ignore
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$path   = (string) wp_parse_url( (string) wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ); // phpcs:ignore
			$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';
			$pos    = strpos( $path, $prefix );
			if ( false !== $pos ) {
				$route = '/' . substr( $path, $pos + strlen( $prefix ) );
			}
		}
		if ( null === $route ) {
			return false;
		}
		$route = rawurldecode( $route );
		if ( false !== strpos( $route, '..' ) || false !== strpos( $route, '//' ) ) {
			return false;
		}
		return 0 === strpos( $route, '/' . self::NS . '/' ) || '/' . self::NS === rtrim( $route, '/' );
	}

	private static function authenticate() {
		if ( self::$attempted ) {
			return;
		}
		self::$attempted = true;
		list( $token ) = self::presented_token();
		if ( null === $token || ! self::is_our_request() ) {
			return;
		}
		$row = TokenService::verify( $token );
		if ( is_wp_error( $row ) ) {
			self::$error = $row;
			return;
		}
		self::$token = $row;
	}

	public static function determine_user( $user_id ) {
		self::authenticate();
		if ( self::$token ) {
			return (int) self::$token->user_id;
		}
		return $user_id;
	}

	public static function auth_errors( $result ) {
		self::authenticate();
		if ( self::$error ) {
			return self::$error;
		}
		if ( self::$token ) {
			// Core cookie checks may have reset the user; the token is authoritative on our namespace.
			wp_set_current_user( (int) self::$token->user_id );
			return true;
		}
		return $result;
	}

	public static function pre_dispatch( $result, $server, $request ) {
		if ( self::$token && 0 !== strpos( ltrim( $request->get_route(), '/' ), self::NS ) ) {
			return new \WP_Error( 'FORBIDDEN', 'This token is valid only for Inbox AI Assist.', array( 'status' => 403 ) );
		}
		return $result;
	}

	/** Token row for this request (or null for cookie/admin requests). */
	public static function token() {
		return self::$token;
	}

	public static function auth_header_used() {
		list( , $how ) = self::presented_token();
		return $how;
	}

	/** Test helper: reset per-request state. */
	public static function reset() {
		self::$token     = null;
		self::$error     = null;
		self::$attempted = false;
	}
}
