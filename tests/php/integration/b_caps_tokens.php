<?php
/** T1.03 / T1.04 — capability mapping and token service. */
use RarIaa\Auth\TokenService;
use RarIaa\Capabilities;
use RarIaa\Settings;

t_reset();
RarIaa\Capabilities::sync();
$admin = t_user( 'administrator', 'iaa_admin' );
$shop  = t_user( 'shop_manager', 'iaa_shop' );
$staff = t_user( 'editor', 'iaa_editor' );
$cust  = t_user( 'subscriber', 'iaa_sub' );
foreach ( Capabilities::all() as $c ) {
	t_ok( user_can( $admin, $c ), "admin has {$c}" );
}
t_ok( user_can( $shop, Capabilities::USE ) && user_can( $shop, Capabilities::MANAGE_KB ), 'shop manager uses & edits KB' );
t_ok( ! user_can( $shop, Capabilities::APPROVE_KB ) && ! user_can( $shop, Capabilities::ACTIVATE_PRICES ) && ! user_can( $shop, Capabilities::MANAGE_SETTINGS ), 'shop manager cannot approve/activate/settings' );
t_ok( ! user_can( $staff, Capabilities::USE ), 'editor has nothing by default' );
t_ok( ! user_can( $cust, Capabilities::USE ), 'subscriber has nothing' );
Settings::update( array( 'extra_use_roles' => array( 'editor', 'administrator' ) ) );
Capabilities::sync();
t_ok( user_can( $staff, Capabilities::USE ) && ! user_can( $staff, Capabilities::LINK_DEALER ), 'extra role gets use only' );
t_eq( array( 'editor' ), Settings::get( 'extra_use_roles' ), 'administrator cannot be an extra role' );
Settings::update( array( 'extra_use_roles' => array() ) );
Capabilities::sync();
t_ok( ! user_can( $staff, Capabilities::USE ), 'removed again' );

$r = TokenService::create( $shop, 'PC 1', 30 );
t_ok( is_array( $r ) && preg_match( TokenService::PATTERN, $r['token'] ), 'token created with format' );
global $wpdb;
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RarIaa\Db::t( 'tokens' ) . ' WHERE id = %d', $r['id'] ) );
t_ok( false === strpos( $row->token_hash, substr( $r['token'], 13 ) ), 'secret not stored in plain text' );
t_eq( (int) $row->id, (int) TokenService::verify( $r['token'] )->id, 'verify ok' );
t_eq( 'AUTH_INVALID', TokenService::verify( substr( $r['token'], 0, -1 ) . ( 'a' === substr( $r['token'], -1 ) ? 'b' : 'a' ) )->get_error_code(), 'wrong secret' );
t_eq( 'AUTH_INVALID', TokenService::verify( 'nonsense' )->get_error_code(), 'bad format' );
TokenService::create( $shop, 'PC 2', 90 );
TokenService::create( $shop, 'PC 3', 180 );
t_eq( 'VALIDATION_FAILED', TokenService::create( $shop, 'PC 4', 30 )->get_error_code(), 'max 3 active tokens' );
t_eq( 'VALIDATION_FAILED', TokenService::create( $shop, 'PC 5', 45 )->get_error_code(), 'expiry must be 30/90/180' );
t_ok( is_wp_error( TokenService::revoke( $r['id'], $staff, $staff ) ), 'cannot revoke someone else\'s token' );
TokenService::revoke( $r['id'], $shop, $shop );
t_eq( 'AUTH_REVOKED', TokenService::verify( $r['token'] )->get_error_code(), 'revoked' );
$e = TokenService::create( $admin, 'Expired', 30 );
$wpdb->update( RarIaa\Db::t( 'tokens' ), array( 'expires_at' => RarIaa\Db::now( -10 ) ), array( 'id' => $e['id'] ) );
t_eq( 'AUTH_EXPIRED', TokenService::verify( $e['token'] )->get_error_code(), 'expired' );
$audit = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'audit_log' ) . " WHERE action IN ('token.create','token.revoke')" );
t_ok( $audit >= 4, 'token create/revoke audited' );
$json = (string) $wpdb->get_var( 'SELECT GROUP_CONCAT(after_json) FROM ' . RarIaa\Db::t( 'audit_log' ) );
t_ok( false === strpos( $json, substr( $r['token'], 13 ) ), 'token secret never in audit log' );
