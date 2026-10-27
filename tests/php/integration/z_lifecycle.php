<?php
/** T4.10 — deactivate keeps data; uninstall deletes only when the setting is on. Runs last. */
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/plugin.php';
t_reset();
$s = t_seed();
deactivate_plugins( 'rar-inbox-ai-assist/rar-inbox-ai-assist.php', false );
t_ok( ! wp_next_scheduled( 'rar_iaa_daily_maintenance' ), 'cron cleared on deactivate' );
t_eq( array(), RarIaa\Installer::missing_tables(), 'tables kept after deactivate' );
activate_plugin( 'rar-inbox-ai-assist/rar-inbox-ai-assist.php', '', false, false );
t_ok( (bool) wp_next_scheduled( 'rar_iaa_daily_maintenance' ), 'cron scheduled again' );
t_eq( 2, count( RarIaa\Domain\KbRepository::serving() ), 'data intact after reactivation' );
// Uninstall with the default setting keeps everything.
define( 'WP_UNINSTALL_PLUGIN', 'rar-inbox-ai-assist/rar-inbox-ai-assist.php' );
include RAR_IAA_DIR . '/uninstall.php';
t_eq( array(), RarIaa\Installer::missing_tables(), 'uninstall keeps data by default' );
// With delete-data on, uninstall drops tables, options and capabilities.
RarIaa\Settings::update( array( 'delete_data_on_uninstall' => 1 ) );
include RAR_IAA_DIR . '/uninstall.php';
t_eq( count( RarIaa\Db::TABLES ), count( RarIaa\Installer::missing_tables() ), 'all tables dropped' );
t_ok( false === get_option( 'rar_iaa_settings' ), 'settings deleted' );
t_ok( ! get_role( 'administrator' )->has_cap( 'rar_iaa_use' ), 'capabilities removed' );
// Re-install for later runs.
RarIaa\Installer::activate();
t_eq( array(), RarIaa\Installer::missing_tables(), 'reinstalled' );
