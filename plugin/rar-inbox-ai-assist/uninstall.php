<?php
/**
 * Uninstall: data is kept unless "Delete all data on uninstall" is on (FR-22).
 *
 * @package RarIaa
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/rar-inbox-ai-assist.php';

$rar_iaa_settings = get_option( 'rar_iaa_settings', array() );
wp_clear_scheduled_hook( 'rar_iaa_daily_maintenance' );
if ( is_array( $rar_iaa_settings ) && ! empty( $rar_iaa_settings['delete_data_on_uninstall'] ) ) {
	RarIaa\Capabilities::remove_all();
	RarIaa\Installer::drop_all();
}
