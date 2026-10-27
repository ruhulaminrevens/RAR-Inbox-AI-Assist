<?php
/** T3.13–T3.19 / T4.02 — wp-admin screens and admin-post actions over HTTP with a real login. */
global $wpdb;
t_reset();
$s    = t_seed();
$pass = 'Adm1n-' . wp_generate_password( 12, false );
wp_set_password( $pass, $s['admin'] );
$shop     = t_user( 'shop_manager', 'iaa_shop' );
$shoppass = 'Sh0p-' . wp_generate_password( 12, false );
wp_set_password( $shoppass, $shop );
$site = preg_replace( '#/index\.php.*$#', '', getenv( 'IAA_TEST_URL' ) ?: 'http://127.0.0.1:8899/index.php' );

function t_browser( $site, $login, $pwd ) {
	$jar = tempnam( sys_get_temp_dir(), 'jar' );
	$req = function ( $method, $path, $fields = null, $multipart = false ) use ( $site, $jar ) {
		$ch = curl_init( $site . $path );
		curl_setopt_array( $ch, array( CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_TIMEOUT => 60 ) );
		if ( null !== $fields ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $multipart ? $fields : http_build_query( $fields ) );
		}
		$raw  = curl_exec( $ch );
		$code = curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$hs   = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );
		return array( 'status' => $code, 'headers' => substr( $raw, 0, $hs ), 'body' => substr( $raw, $hs ) );
	};
	$req( 'GET', '/wp-login.php' );
	$r = $req( 'POST', '/wp-login.php', array( 'log' => $login, 'pwd' => $pwd, 'wp-submit' => 'Log In', 'testcookie' => 1 ) );
	return array( $req, 302 === $r['status'] );
}
function t_nonce( $html, $action ) {
	// The form for $action carries its own _wpnonce right after the hidden action input.
	if ( preg_match( '/name="action" value="' . preg_quote( $action, '/' ) . '"><input type="hidden" id="_wpnonce" name="_wpnonce" value="([0-9a-f]+)"/', $html, $m ) ) {
		return $m[1];
	}
	return '';
}

list( $req, $ok ) = t_browser( $site, 'iaa_admin', $pass );
t_ok( $ok, 'admin logged in' );
foreach ( array( 'rar-iaa', 'rar-iaa-kb', 'rar-iaa-dealers', 'rar-iaa-prices', 'rar-iaa-tokens', 'rar-iaa-logs', 'rar-iaa-settings' ) as $page ) {
	$r = $req( 'GET', '/wp-admin/admin.php?page=' . $page );
	t_eq( 200, $r['status'], "admin page {$page} 200" );
	t_ok( false === stripos( $r['body'], 'Fatal error' ) && false === stripos( $r['body'], 'Warning:' ), "{$page} renders without PHP errors" );
}
// KB create via the form.
$page  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-kb&action=new' );
$nonce = t_nonce( $page['body'], 'rar_iaa_kb_save' );
t_ok( '' !== $nonce, 'KB form nonce present' );
$r = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_kb_save', '_wpnonce' => $nonce, 'id' => 0, 'row_version' => 0, 'title' => 'Payment', 'category' => 'payment', 'keywords' => 'bkash, payment, পেমেন্ট', 'answer_bn' => 'বিকাশ বা ব্যাংকে অগ্রিম পেমেন্ট।', 'effective_from' => '2026-01-01', 'submit_review' => 1 ) );
t_eq( 302, $r['status'], 'KB save redirects' );
$id = (int) $wpdb->get_var( 'SELECT entry_id FROM ' . RarIaa\Db::t( 'kb_versions' ) . " WHERE title = 'Payment'" );
t_ok( $id > 0, 'KB entry stored' );
t_eq( 1, (int) RarIaa\Domain\KbRepository::get( $id )->pending_review, 'submitted for approval' );
// Missing nonce is refused.
$r = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_kb_archive', 'id' => $id ) );
t_ok( 403 === $r['status'] || false !== stripos( $r['body'], 'link you followed has expired' ), 'admin action without nonce refused' );
t_eq( 'draft', RarIaa\Domain\KbRepository::get( $id )->status, 'nothing archived' );
// Settings save with an invalid value keeps old settings and shows errors.
$page  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-settings' );
$nonce = t_nonce( $page['body'], 'rar_iaa_settings_save' );
$r     = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_settings_save', '_wpnonce' => $nonce, 'per_minute' => 999 ) );
t_refresh_settings();
t_ok( 999 !== (int) RarIaa\Settings::get( 'per_minute' ), 'invalid setting rejected' );
$r = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_settings_save', '_wpnonce' => $nonce, 'per_minute' => 10, 'key_anthropic' => 'sk-ant-test-1234567890abcd', 'ai_enabled' => 1 ) );
t_refresh_settings();
t_eq( 10, (int) RarIaa\Settings::get( 'per_minute' ), 'valid setting saved' );
t_eq( 'sk-ant-test-1234567890abcd', RarIaa\Settings::api_key( 'anthropic' ), 'key stored encrypted and readable server-side' );
t_ok( false === strpos( (string) get_option( 'rar_iaa_secret_anthropic' ), 'sk-ant-test' ), 'key not stored in plain text' );
$page = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-settings' );
t_ok( false === strpos( $page['body'], 'sk-ant-test-1234567890abcd' ), 'key never printed in the settings page' );
t_contains( 'sk-a…abcd', $page['body'], 'masked key shown' );
t_ok( false === strpos( (string) $wpdb->get_var( 'SELECT GROUP_CONCAT(after_json) FROM ' . RarIaa\Db::t( 'audit_log' ) ), 'sk-ant-test' ), 'key never in audit log' );
// Kill switch.
$home  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa' );
$nonce = t_nonce( $home['body'], 'rar_iaa_kill_switch' );
$req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_kill_switch', '_wpnonce' => $nonce, 'state' => 'off' ) );
t_refresh_settings();
t_ok( ! RarIaa\Settings::service_enabled(), 'kill switch stops the service' );
RarIaa\Settings::update( array( 'service_enabled' => 1 ) );
// Token page: created token shown once, not stored in plain text anywhere.
$page  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-tokens' );
preg_match( '/name="_wpnonce" value="([0-9a-f]+)"/', $page['body'], $m );
$r = $req( 'POST', '/wp-admin/admin.php?page=rar-iaa-tokens', array( '_wpnonce' => $m[1], 'iaa_create_token' => 1, 'label' => 'Office PC', 'days' => 90 ) );
t_ok( (bool) preg_match( '/value="(iaa_[a-z0-9]{8}_[A-Za-z0-9]{40})"/', $r['body'], $tm ), 'new token shown once' );
$again = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-tokens' );
t_ok( false === strpos( $again['body'], $tm[1] ), 'token not shown again' );
t_eq( 0, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( substr( $tm[1], 13 ) ) . '%' ) ), 'token secret not in options/transients' );
// Price import (multipart) with errors → nothing saved; valid → draft saved.
$page  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-prices&action=import' );
$nonce = t_nonce( $page['body'], 'rar_iaa_price_import' );
$bad   = tempnam( sys_get_temp_dir(), 'csv' );
file_put_contents( $bad, "item_code,product_name,unit,price_bdt\nX1,Bad,carton,abc\n" );
$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'price_lists' ) );
$req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_price_import', '_wpnonce' => $nonce, 'tier_id' => $s['tier'], 'name' => 'Bad list', 'effective_from' => '2026-11-01', 'csv' => new CURLFile( $bad, 'text/csv', 'bad.csv' ) ), true );
t_eq( $before, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'price_lists' ) ), 'file with errors saves nothing' );
$page = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-prices&action=import' );
t_contains( 'Must be a number above 0', $page['body'], 'row error shown' );
file_put_contents( $bad, "item_code,product_name,unit,price_bdt\nX1,Good,carton,1300\n" );
$nonce = t_nonce( $page['body'], 'rar_iaa_price_import' );
$r     = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_price_import', '_wpnonce' => $nonce, 'tier_id' => $s['tier'], 'name' => 'Nov list', 'effective_from' => '2026-11-01', 'csv' => new CURLFile( $bad, 'text/csv', 'good.csv' ) ), true );
unlink( $bad );
t_ok( (bool) preg_match( '/action=view&id=(\d+)/', $r['headers'], $lm ), 'valid file → redirected to the draft list' );
t_eq( 'draft', RarIaa\Domain\PriceRepository::get_list( (int) $lm[1] )->status, 'saved as draft' );
$view  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-prices&action=view&id=' . $lm[1] );
$nonce = t_nonce( $view['body'], 'rar_iaa_price_activate' );
$req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_price_activate', '_wpnonce' => $nonce, 'id' => $lm[1] ) );
t_eq( (int) $lm[1], (int) RarIaa\Domain\PriceRepository::active_for_tier( $s['tier'] )->id, 'activated from admin' );
// Logs export is formula-safe.
RarIaa\Domain\DraftLogRepository::insert( array( 'draft_uuid' => t_uuid(), 'request_id' => t_uuid(), 'user_id' => $s['admin'], 'source' => 'paste', 'status' => 'error', 'error_code' => '=HYPERLINK("x")' ) );
$logs  = $req( 'GET', '/wp-admin/admin.php?page=rar-iaa-logs' );
$nonce = t_nonce( $logs['body'], 'rar_iaa_logs_export' );
$csv   = $req( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_logs_export', '_wpnonce' => $nonce, 'from' => RarIaa\Db::dhaka_date(), 'to' => RarIaa\Db::dhaka_date() ) );
t_contains( 'text/csv', $csv['headers'], 'CSV download' );
t_contains( "'=HYPERLINK", $csv['body'], 'formula cells prefixed' );

// Shop manager: no settings, no approval, no activation.
list( $sreq, $ok ) = t_browser( $site, 'iaa_shop', $shoppass );
t_ok( $ok, 'shop manager logged in' );
$r = $sreq( 'GET', '/wp-admin/admin.php?page=rar-iaa-settings' );
t_ok( 200 !== $r['status'] || false !== stripos( $r['body'], 'not allowed' ), 'shop manager cannot open settings' );
$kb = $sreq( 'GET', '/wp-admin/admin.php?page=rar-iaa-kb&action=edit&id=' . $id );
t_ok( false === strpos( $kb['body'], 'value="rar_iaa_kb_approve"' ), 'no approve button for shop manager' );
$r = $sreq( 'POST', '/wp-admin/admin-post.php', array( 'action' => 'rar_iaa_kb_approve', '_wpnonce' => 'x', 'id' => $id, 'row_version' => 1 ) );
t_eq( 403, $r['status'], 'shop manager approve refused' );
