<?php
/**
 * Wiring.
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function boot() {
		Installer::maybe_upgrade();
		Auth\RestAuth::register();
		Rest\Api::register();
		add_action( 'rar_iaa_daily_maintenance', array( Cron\Maintenance::class, 'run' ) );
		add_action( 'shutdown', array( Lock::class, 'release_all' ) );
		if ( is_admin() ) {
			Admin\Menu::register();
			Admin\Actions::register();
		}
	}
}
