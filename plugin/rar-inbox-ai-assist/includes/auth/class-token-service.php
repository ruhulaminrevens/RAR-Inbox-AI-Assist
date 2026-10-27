<?php
/**
 * Device tokens for the extension (TRD §4.1, Schema §4.8).
 *
 * Format: iaa_{prefix8}_{secret40}. Only the prefix and an HMAC of the secret are stored.
 *
 * @package RarIaa
 */

namespace RarIaa\Auth;

use RarIaa\Audit;
use RarIaa\Db;
use RarIaa\Lock;

defined( 'ABSPATH' ) || exit;

class TokenService {

	const MAX_ACTIVE   = 3;
	const PATTERN      = '/^iaa_([a-z0-9]{8})_([A-Za-z0-9]{40})$/';
	const EXPIRY_DAYS  = array( 30, 90, 180 );

	private static function pepper() {
		return hash_hmac( 'sha256', 'rar-iaa-token', wp_salt( 'auth' ) );
	}

	private static function hash_secret( $secret ) {
		return hash_hmac( 'sha256', $secret, self::pepper() );
	}

	private static function random_string( $length, $alphabet ) {
		$out = '';
		$max = strlen( $alphabet ) - 1;
		for ( $i = 0; $i < $length; $i++ ) {
			$out .= $alphabet[ random_int( 0, $max ) ];
		}
		return $out;
	}

	/**
	 * Create a token. Returns array( 'token' => plaintext shown once, 'id' => row id ) or WP_Error.
	 */
	public static function create( $user_id, $label, $days = 180 ) {
		global $wpdb;
		$label = trim( sanitize_text_field( (string) $label ) );
		if ( '' === $label || strlen( $label ) > 60 ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Label is required (max 60 characters).' );
		}
		if ( ! in_array( (int) $days, self::EXPIRY_DAYS, true ) ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Expiry must be 30, 90 or 180 days.' );
		}
		$lock = 'tokens_u' . (int) $user_id;
		if ( ! Lock::acquire( $lock, 5, 30 ) ) {
			return new \WP_Error( 'BUSY', 'Please try again.' );
		}
		try {
			$table  = Db::t( 'tokens' );
			$active = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND revoked_at IS NULL AND expires_at > %s", $user_id, Db::now() )
			);
			if ( $active >= self::MAX_ACTIVE ) {
				return new \WP_Error( 'VALIDATION_FAILED', 'You already have 3 active tokens. Revoke one first.' );
			}
			for ( $attempt = 0; $attempt < 5; $attempt++ ) {
				$prefix = self::random_string( 8, 'abcdefghijklmnopqrstuvwxyz0123456789' );
				$secret = self::random_string( 40, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789' );
				$ok     = $wpdb->insert(
					$table,
					array(
						'user_id'      => (int) $user_id,
						'token_prefix' => $prefix,
						'token_hash'   => self::hash_secret( $secret ),
						'label'        => $label,
						'created_at'   => Db::now(),
						'expires_at'   => Db::now( (int) $days * DAY_IN_SECONDS ),
					)
				);
				if ( $ok ) {
					$id = (int) $wpdb->insert_id;
					Audit::record( 'token.create', 'token', $id, null, array( 'user_id' => (int) $user_id, 'label' => $label, 'prefix' => $prefix, 'days' => (int) $days ) );
					return array( 'token' => 'iaa_' . $prefix . '_' . $secret, 'id' => $id, 'prefix' => $prefix );
				}
			}
			return new \WP_Error( 'INTERNAL_ERROR', 'Could not create token.' );
		} finally {
			Lock::release( $lock );
		}
	}

	/**
	 * Verify a presented token. Returns the token row (object) or WP_Error with an AUTH_* code.
	 */
	public static function verify( $presented ) {
		global $wpdb;
		if ( ! is_string( $presented ) || ! preg_match( self::PATTERN, $presented, $m ) ) {
			return new \WP_Error( 'AUTH_INVALID', 'Invalid token.', array( 'status' => 401 ) );
		}
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Db::t( 'tokens' ) . ' WHERE token_prefix = %s', $m[1] ) );
		if ( ! $row || ! hash_equals( $row->token_hash, self::hash_secret( $m[2] ) ) ) {
			return new \WP_Error( 'AUTH_INVALID', 'Invalid token.', array( 'status' => 401 ) );
		}
		if ( null !== $row->revoked_at ) {
			return new \WP_Error( 'AUTH_REVOKED', 'This token has been revoked.', array( 'status' => 401 ) );
		}
		if ( strtotime( $row->expires_at . ' UTC' ) <= time() ) {
			return new \WP_Error( 'AUTH_EXPIRED', 'This token has expired.', array( 'status' => 401 ) );
		}
		if ( ! get_userdata( (int) $row->user_id ) ) {
			return new \WP_Error( 'AUTH_INVALID', 'Invalid token.', array( 'status' => 401 ) );
		}
		if ( ! $row->last_used_at || strtotime( $row->last_used_at . ' UTC' ) < time() - 300 ) {
			$wpdb->update( Db::t( 'tokens' ), array( 'last_used_at' => Db::now() ), array( 'id' => (int) $row->id ) );
		}
		return $row;
	}

	/**
	 * Revoke. When $owner_id is given the token must belong to that user (horizontal-access guard).
	 */
	public static function revoke( $token_id, $actor_id, $owner_id = null ) {
		global $wpdb;
		$table = Db::t( 'tokens' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $token_id ) );
		if ( ! $row || ( null !== $owner_id && (int) $row->user_id !== (int) $owner_id ) ) {
			return new \WP_Error( 'NOT_FOUND', 'Token not found.', array( 'status' => 404 ) );
		}
		if ( null !== $row->revoked_at ) {
			return true;
		}
		$wpdb->update( $table, array( 'revoked_at' => Db::now(), 'revoked_by' => (int) $actor_id ), array( 'id' => (int) $token_id ) );
		Audit::record( 'token.revoke', 'token', (int) $token_id, array( 'user_id' => (int) $row->user_id, 'prefix' => $row->token_prefix ), array( 'revoked_by' => (int) $actor_id ), $actor_id );
		return true;
	}

	public static function list_for( $user_id = null ) {
		global $wpdb;
		$table = Db::t( 'tokens' );
		if ( null === $user_id ) {
			return $wpdb->get_results( "SELECT id, user_id, token_prefix, label, created_at, expires_at, last_used_at, revoked_at FROM {$table} ORDER BY id DESC LIMIT 500" );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT id, user_id, token_prefix, label, created_at, expires_at, last_used_at, revoked_at FROM {$table} WHERE user_id = %d ORDER BY id DESC", $user_id ) );
	}

	public static function status_of( $row ) {
		if ( null !== $row->revoked_at ) {
			return 'revoked';
		}
		return strtotime( $row->expires_at . ' UTC' ) <= time() ? 'expired' : 'active';
	}
}
