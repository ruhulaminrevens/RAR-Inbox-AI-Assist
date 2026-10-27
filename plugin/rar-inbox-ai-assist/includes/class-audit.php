<?php
/**
 * Audit log of administrative changes (FR-19, NFR-12).
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Audit {

	const REDACT = array( 'token_hash', 'token', 'api_key', 'anthropic_api_key', 'openai_api_key', 'secret', 'password' );

	public static function record( $action, $object_type, $object_id = null, $before = null, $after = null, $user_id = null ) {
		global $wpdb;
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$wpdb->insert(
			Db::t( 'audit_log' ),
			array(
				'user_id'     => null === $user_id ? get_current_user_id() : (int) $user_id,
				'action'      => substr( (string) $action, 0, 50 ),
				'object_type' => substr( (string) $object_type, 0, 30 ),
				'object_id'   => null === $object_id ? null : (int) $object_id,
				'before_json' => null === $before ? null : Util::json( self::redact( $before ) ),
				'after_json'  => null === $after ? null : Util::json( self::redact( $after ) ),
				'ip_hash'     => '' === $ip ? null : hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ),
				'created_at'  => Db::now(),
			)
		);
	}

	public static function redact( $data ) {
		if ( is_object( $data ) ) {
			$data = (array) $data;
		}
		if ( ! is_array( $data ) ) {
			return $data;
		}
		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) && ( in_array( strtolower( $k ), self::REDACT, true ) || 0 === strpos( strtolower( $k ), 'secret' ) ) ) {
				$data[ $k ] = '[redacted]';
			} elseif ( is_array( $v ) || is_object( $v ) ) {
				$data[ $k ] = self::redact( $v );
			}
		}
		return $data;
	}
}
