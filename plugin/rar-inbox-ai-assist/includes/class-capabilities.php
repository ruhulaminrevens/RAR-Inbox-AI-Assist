<?php
/**
 * Capabilities and role mapping (PRD §9, Schema §10).
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Capabilities {

	const USE              = 'rar_iaa_use';
	const LINK_DEALER      = 'rar_iaa_link_dealer';
	const MANAGE_KB        = 'rar_iaa_manage_kb';
	const APPROVE_KB       = 'rar_iaa_approve_kb';
	const MANAGE_PRICES    = 'rar_iaa_manage_prices';
	const ACTIVATE_PRICES  = 'rar_iaa_activate_prices';
	const VIEW_LOGS        = 'rar_iaa_view_logs';
	const MANAGE_SETTINGS  = 'rar_iaa_manage_settings';

	public static function all() {
		return array( self::USE, self::LINK_DEALER, self::MANAGE_KB, self::APPROVE_KB, self::MANAGE_PRICES, self::ACTIVATE_PRICES, self::VIEW_LOGS, self::MANAGE_SETTINGS );
	}

	/** Default role → capability mapping. manage_settings is administrator-only and never assignable elsewhere. */
	public static function role_map() {
		return array(
			'administrator' => self::all(),
			'shop_manager'  => array( self::USE, self::LINK_DEALER, self::MANAGE_KB, self::MANAGE_PRICES, self::VIEW_LOGS ),
		);
	}

	/** Apply mapping including the extra staff roles chosen in settings. Idempotent. */
	public static function sync() {
		$wanted = self::role_map();
		foreach ( (array) Settings::get( 'extra_use_roles' ) as $role ) {
			$wanted[ $role ][] = self::USE;
		}
		foreach ( (array) Settings::get( 'extra_link_roles' ) as $role ) {
			$wanted[ $role ][] = self::LINK_DEALER;
		}
		$roles = wp_roles();
		foreach ( $roles->role_objects as $name => $role ) {
			$should = isset( $wanted[ $name ] ) ? array_unique( $wanted[ $name ] ) : array();
			foreach ( self::all() as $cap ) {
				$has = $role->has_cap( $cap );
				if ( in_array( $cap, $should, true ) && ! $has ) {
					$role->add_cap( $cap );
				} elseif ( ! in_array( $cap, $should, true ) && $has ) {
					$role->remove_cap( $cap );
				}
			}
		}
	}

	public static function remove_all() {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/** Short capability names the user holds (for GET /me). */
	public static function short_list( $user_id ) {
		$out = array();
		foreach ( self::all() as $cap ) {
			if ( user_can( $user_id, $cap ) ) {
				$out[] = substr( $cap, strlen( 'rar_iaa_' ) );
			}
		}
		return $out;
	}

	/** Number of users who hold the administrator role. */
	public static function admin_count() {
		$counts = count_users();
		return isset( $counts['avail_roles']['administrator'] ) ? (int) $counts['avail_roles']['administrator'] : 0;
	}
}
