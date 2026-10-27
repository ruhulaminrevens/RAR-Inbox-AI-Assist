<?php
/**
 * E2E seed: fresh plugin state, one staff token. Prints JSON {token, dealer}.
 * Usage: WP_LOAD=/path/wp-load.php php tests/e2e/seed.php [live]
 */
require __DIR__ . '/../php/bootstrap.php';
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_e2e_staff' );
RarIaa\Settings::update( array( 'per_minute' => 60, 'live_inbox' => in_array( 'live', $argv, true ) ? 1 : 0 ) );
$pw = wp_generate_password( 20, false );
wp_set_password( $pw, $s['admin'] );
echo wp_json_encode( array( 'token' => t_token( $shop, 'e2e' ), 'dealer' => $s['dealer'], 'admin_login' => 'iaa_admin', 'admin_pw' => $pw, 'siteurl' => get_option( 'siteurl' ) ) );
