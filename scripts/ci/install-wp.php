<?php
/** Fresh WordPress install + plugin activation for test sites (scripts/ci/setup-wp.sh). */
$dir  = getenv( 'WP_DIR' );
$url  = rtrim( (string) getenv( 'SITE_URL' ), '/' );
$host = (string) parse_url( $url, PHP_URL_HOST ) . ( parse_url( $url, PHP_URL_PORT ) ? ':' . parse_url( $url, PHP_URL_PORT ) : '' );
$_SERVER['HTTP_HOST']   = $host;
$_SERVER['REQUEST_URI'] = '/';
define( 'WP_INSTALLING', true );
require $dir . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( ! is_blog_installed() ) {
	wp_install( 'IAA Test', 'admin', 'admin@example.test', false, '', wp_generate_password( 24 ) );
}
update_option( 'siteurl', $url );
update_option( 'home', $url );
$r = activate_plugin( 'rar-inbox-ai-assist/rar-inbox-ai-assist.php', '', false, false );
if ( is_wp_error( $r ) ) {
	fwrite( STDERR, 'Activation failed: ' . $r->get_error_message() . "\n" );
	exit( 1 );
}
$missing = RarIaa\Installer::missing_tables();
if ( $missing ) {
	fwrite( STDERR, 'Missing tables: ' . implode( ', ', $missing ) . "\n" );
	exit( 1 );
}
echo 'WordPress ' . get_bloginfo( 'version' ) . ' installed at ' . $url . "; plugin active, schema " . get_option( 'rar_iaa_schema_version' ) . ' on ' . $GLOBALS['wpdb']->db_server_info() . "\n";
