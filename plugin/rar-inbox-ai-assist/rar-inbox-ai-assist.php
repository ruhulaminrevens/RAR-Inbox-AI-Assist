<?php
/**
 * Plugin Name:       RAR Inbox AI Assist
 * Description:       Fact-checked reply suggestions for Meta Business Suite inbox staff. Approved knowledge base, dealer price lists, masking, budget limits. Never sends messages.
 * Version:           0.2.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Nabiad Distribution Limited
 * License:           GPL-2.0-or-later
 * Text Domain:       rar-inbox-ai-assist
 * WC requires at least: 8.0
 *
 * @package RarIaa
 */

defined( 'ABSPATH' ) || exit;

define( 'RAR_IAA_VERSION', '0.2.0' );
define( 'RAR_IAA_SCHEMA_VERSION', 3 );
define( 'RAR_IAA_FILE', __FILE__ );
define( 'RAR_IAA_DIR', __DIR__ );
define( 'RAR_IAA_MIN_CLIENT', '0.1.0' );

spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, 'RarIaa\\' ) ) {
			return;
		}
		$parts = explode( '\\', substr( $class, 7 ) );
		$name  = array_pop( $parts );
		$file  = 'class-' . strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $name ) ) . '.php';
		$dir   = $parts ? strtolower( implode( '/', $parts ) ) . '/' : '';
		$path  = RAR_IAA_DIR . '/includes/' . $dir . $file;
		if ( ! is_readable( $path ) ) {
			$path = RAR_IAA_DIR . '/includes/' . $dir . 'interface-' . substr( $file, 6 );
		}
		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'RarIaa\\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RarIaa\\Installer', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( 'RarIaa\\Plugin', 'boot' ) );
