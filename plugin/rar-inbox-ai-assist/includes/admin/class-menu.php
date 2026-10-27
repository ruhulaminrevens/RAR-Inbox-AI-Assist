<?php
/**
 * wp-admin menu (AppFlow §2.2).
 *
 * @package RarIaa
 */

namespace RarIaa\Admin;

use RarIaa\Capabilities;

defined( 'ABSPATH' ) || exit;

class Menu {

	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		$top_cap = Capabilities::USE;
		add_menu_page( 'Inbox AI Assist', 'Inbox AI Assist', $top_cap, 'rar-iaa', array( Pages::class, 'home' ), 'dashicons-format-chat', 58 );
		add_submenu_page( 'rar-iaa', 'Overview', 'Overview', $top_cap, 'rar-iaa', array( Pages::class, 'home' ) );
		add_submenu_page( 'rar-iaa', 'Knowledge base', 'Knowledge base', Capabilities::MANAGE_KB, 'rar-iaa-kb', array( Pages::class, 'kb' ) );
		add_submenu_page( 'rar-iaa', 'Dealers & tiers', 'Dealers & tiers', Capabilities::MANAGE_PRICES, 'rar-iaa-dealers', array( Pages::class, 'dealers' ) );
		add_submenu_page( 'rar-iaa', 'Price lists', 'Price lists', Capabilities::MANAGE_PRICES, 'rar-iaa-prices', array( Pages::class, 'prices' ) );
		add_submenu_page( 'rar-iaa', 'Access tokens', 'Access tokens', Capabilities::USE, 'rar-iaa-tokens', array( Pages::class, 'tokens' ) );
		add_submenu_page( 'rar-iaa', 'Logs', 'Logs', Capabilities::VIEW_LOGS, 'rar-iaa-logs', array( Pages::class, 'logs' ) );
		add_submenu_page( 'rar-iaa', 'Settings', 'Settings', Capabilities::MANAGE_SETTINGS, 'rar-iaa-settings', array( Pages::class, 'settings' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( (string) $hook, 'rar-iaa' ) ) {
			return;
		}
		wp_enqueue_style( 'rar-iaa-admin', plugins_url( 'assets/admin.css', RAR_IAA_FILE ), array(), RAR_IAA_VERSION );
		wp_enqueue_script( 'rar-iaa-admin', plugins_url( 'assets/admin.js', RAR_IAA_FILE ), array(), RAR_IAA_VERSION, true );
	}
}
