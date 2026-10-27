<?php
/**
 * Minimal test harness. Runs under `wp eval-file` or plain `php` (set WP_LOAD to wp-load.php).
 *
 * Environment:
 *   WP_LOAD       path to wp-load.php (plain php runs)
 *   IAA_TEST_URL  REST base for HTTP tests, e.g. http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1
 *                 (the server must run with WP_ENVIRONMENT_TYPE=local and RAR_IAA_PROVIDER=stub)
 */

if ( ! defined( 'ABSPATH' ) ) {
	$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$load                  = getenv( 'WP_LOAD' );
	if ( ! $load || ! is_readable( $load ) ) {
		fwrite( STDERR, "Set WP_LOAD to the path of wp-load.php\n" );
		exit( 2 );
	}
	// Same provider mode as the test server (the stub is refused outside local/development sites).
	if ( ! defined( 'RAR_IAA_PROVIDER' ) ) {
		define( 'RAR_IAA_PROVIDER', 'stub' );
	}
	require $load;
}

// WooCommerce normally provides the shop_manager role; create it for WooCommerce-less test sites.
if ( ! get_role( 'shop_manager' ) ) {
	add_role( 'shop_manager', 'Shop manager', array( 'read' => true ) );
	RarIaa\Capabilities::sync();
}

// WooCommerce redirects the first wp-admin visit after activation to its setup wizard
// (transient _wc_activation_redirect). That is not plugin behaviour; keep admin tests deterministic.
if ( class_exists( 'WooCommerce' ) ) {
	delete_transient( '_wc_activation_redirect' );
}

if ( ! function_exists( 't_ok' ) ) {
	$GLOBALS['T'] = array( 'pass' => 0, 'fail' => 0, 'failures' => array(), 'file' => '' );

	function t_ok( $cond, $name ) {
		if ( $cond ) {
			$GLOBALS['T']['pass']++;
		} else {
			$GLOBALS['T']['fail']++;
			$GLOBALS['T']['failures'][] = basename( $GLOBALS['T']['file'] ) . ': ' . $name;
			fwrite( STDOUT, "  FAIL: {$name}\n" );
		}
		return $cond;
	}

	function t_eq( $expected, $actual, $name ) {
		$ok = $expected === $actual;
		if ( ! $ok ) {
			$name .= ' (expected ' . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . ')';
		}
		return t_ok( $ok, $name );
	}

	function t_contains( $needle, $haystack, $name ) {
		return t_ok( false !== strpos( (string) $haystack, (string) $needle ), $name );
	}

	/** Empty all plugin tables and restore default settings (fresh state per file). */
	function t_reset() {
		global $wpdb;
		foreach ( RarIaa\Db::TABLES as $short ) {
			$wpdb->query( 'DELETE FROM ' . RarIaa\Db::t( $short ) );
		}
		delete_option( RarIaa\Settings::OPTION );
		add_option( RarIaa\Settings::OPTION, RarIaa\Settings::defaults(), '', 'no' );
		RarIaa\Settings::flush();
		RarIaa\Settings::update( array( 'price_in_usd_per_mtok' => 1, 'price_out_usd_per_mtok' => 5 ) );
		delete_option( 'rar_iaa_stub_next' );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_%rar\\_iaa\\_%'" );
		wp_cache_flush();
	}

	/** Re-read settings written by another process (server). */
	function t_refresh_settings() {
		wp_cache_delete( RarIaa\Settings::OPTION, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		RarIaa\Settings::flush();
	}

	function t_user( $role, $login ) {
		$u = get_user_by( 'login', $login );
		if ( $u ) {
			$u->set_role( $role );
			return (int) $u->ID;
		}
		return (int) wp_insert_user( array( 'user_login' => $login, 'user_pass' => wp_generate_password(), 'user_email' => $login . '@example.test', 'role' => $role ) );
	}

	/** Seed one tier, dealer, approved KB entries and an active price list. Returns ids. */
	function t_seed() {
		$admin    = t_user( 'administrator', 'iaa_admin' );
		$approver = t_user( 'administrator', 'iaa_admin2' );
		wp_set_current_user( $admin );
		$kb = RarIaa\Domain\KbRepository::create( array( 'title' => 'Office hours', 'category' => 'hours', 'keywords' => 'khola, koyta, porjonto, সময়, office hours, খোলা', 'answer_bn' => 'আমাদের অফিস শনি থেকে বৃহস্পতিবার সকাল ৯টা থেকে সন্ধ্যা ৬টা পর্যন্ত খোলা থাকে।', 'answer_en' => 'Our office is open Saturday to Thursday, 9 am to 6 pm.' ), $admin );
		$e  = RarIaa\Domain\KbRepository::get( $kb );
		RarIaa\Domain\KbRepository::approve( $kb, $approver, (int) $e->row_version );
		$kb2 = RarIaa\Domain\KbRepository::create( array( 'title' => 'Contact', 'category' => 'contact', 'keywords' => 'phone, number, যোগাযোগ', 'answer_bn' => 'যোগাযোগ: 01700-000001', 'answer_en' => 'Contact: 01700-000001' ), $admin );
		$e2  = RarIaa\Domain\KbRepository::get( $kb2 );
		RarIaa\Domain\KbRepository::approve( $kb2, $approver, (int) $e2->row_version );
		$tier   = RarIaa\Domain\DealerRepository::save_tier( array( 'code' => 'B', 'name' => 'Dealer' ), $admin );
		$dealer = RarIaa\Domain\DealerRepository::save_dealer( array( 'dealer_code' => 'D-014', 'name' => 'Rahim Traders', 'tier_id' => $tier ), $admin );
		$csv    = tempnam( sys_get_temp_dir(), 'iaa' );
		file_put_contents( $csv, "item_code,product_name,unit,price_bdt,aliases,mrp_bdt,stock_mode,manual_stock_status\nATZ-JMB,A TO Z Jumbo,carton,1250,জাম্বো|jumbo,1400,manual,in_stock\nSIG-NEEM,SIGNAL Neem,carton,1100,neem|নিম,,manual,limited\n" );
		$parsed = RarIaa\Domain\PriceRepository::parse_csv( $csv );
		$list   = RarIaa\Domain\PriceRepository::save_draft( $tier, 'Dealer list', '2026-10-01', $parsed, $admin );
		RarIaa\Domain\PriceRepository::activate( $list, $admin );
		unlink( $csv );
		return array( 'admin' => $admin, 'kb' => $kb, 'kb_contact' => $kb2, 'tier' => $tier, 'dealer' => $dealer, 'list' => $list );
	}

	/** HTTP call to the running test server. */
	function t_http( $method, $path, $token = null, $body = null, array $headers = array() ) {
		$base = getenv( 'IAA_TEST_URL' ) ?: 'http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1';
		$url  = 0 === strpos( $path, 'http' ) ? $path : $base . $path;
		$h    = array( 'Accept: application/json' );
		if ( $token ) {
			$h[] = 'Authorization: Bearer ' . $token;
		}
		foreach ( $headers as $k => $v ) {
			$h[] = $k . ': ' . $v;
		}
		$ch = curl_init( $url );
		curl_setopt_array( $ch, array( CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 40 ) );
		if ( null !== $body ) {
			$h[] = 'Content-Type: application/json';
			curl_setopt( $ch, CURLOPT_POSTFIELDS, is_string( $body ) ? $body : json_encode( $body, JSON_UNESCAPED_UNICODE ) );
		}
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $h );
		$raw  = curl_exec( $ch );
		$code = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		$hs   = (int) curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		curl_close( $ch );
		$head = substr( (string) $raw, 0, $hs );
		$json = json_decode( substr( (string) $raw, $hs ), true );
		return array( 'status' => $code, 'headers' => $head, 'json' => $json, 'raw' => substr( (string) $raw, $hs ) );
	}

	function t_uuid() {
		return RarIaa\Util::uuid4();
	}

	function t_token( $user_id, $label = 'test' ) {
		$r = RarIaa\Auth\TokenService::create( $user_id, $label . wp_rand( 1, 99999 ), 30 );
		return $r['token'];
	}

	/** Script the stub provider's next call(s). Written straight to the DB because the server process deletes it. */
	function t_stub_raw( $value ) {
		global $wpdb;
		$wpdb->replace( $wpdb->options, array( 'option_name' => 'rar_iaa_stub_next', 'option_value' => is_string( $value ) ? $value : wp_json_encode( $value, JSON_UNESCAPED_UNICODE ), 'autoload' => 'off' ) );
		wp_cache_delete( 'rar_iaa_stub_next', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	function t_stub( array $suggestions, $extra = array() ) {
		t_stub_raw( array_merge( array( 'text' => wp_json_encode( array( 'suggestions' => $suggestions ), JSON_UNESCAPED_UNICODE ) ), $extra ) );
	}

	function t_sug( $replies, $facts = array(), $extra = array() ) {
		$ids = array( 'professional', 'friendly', 'short' );
		$out = array();
		foreach ( $ids as $i => $id ) {
			$out[] = array_merge( array( 'variant_id' => $id, 'reply' => is_array( $replies ) ? $replies[ $i ] : $replies, 'language' => 'bn', 'needs_human' => false, 'handoff_reason' => null, 'facts_used' => $facts, 'missing_info' => array() ), $extra );
		}
		return $out;
	}
}
