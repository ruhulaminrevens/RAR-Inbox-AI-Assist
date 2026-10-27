<?php
/** T2.21 — zero unmasked persistence: markers never reach tables, options, transients or logs (v0.2 §16). */
global $wpdb;
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_shop' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'per_minute' => 60 ) );
$phone  = '01799' . wp_rand( 100000, 999999 );
$email  = 'marker' . wp_rand( 1000, 9999 ) . '@example.test';
$secret = 'ZQXMARKER' . wp_rand( 1000, 9999 );
$name   = 'Karimuddin Zqx';
$body   = array(
	'source'                => 'paste',
	'customer_display_name' => $name,
	'messages'              => array( array( 'side' => 'customer', 'text' => "Assalamu alaikum, ami {$name}. {$secret} amar number {$phone}, mail {$email}. Office koyta porjonto khola?" ) ),
);
$debug_before = is_readable( WP_CONTENT_DIR . '/debug.log' ) ? filesize( WP_CONTENT_DIR . '/debug.log' ) : 0;
// Stub echoes the customer's phone back so we also test the unmask/remask path.
t_stub( t_sug( 'ওয়ালাইকুম আসসালাম। আমরা [PHONE_1] নম্বরে যোগাযোগ করব। অফিস সকাল ৯টা থেকে সন্ধ্যা ৬টা।', array( 'kb:' . $s['kb'] ) ) );
$r = t_http( 'POST', '/drafts', $tok, $body, array( 'Idempotency-Key' => t_uuid(), 'X-IAA-Thread' => '1063479:t_77' ) );
t_eq( 200, $r['status'], 'draft ok' );
t_contains( $phone, $r['json']['suggestions'][0]['reply'], 'placeholder unmasked in the response (request memory only)' );
t_eq( 'UNVERIFIED_NUMBER', $r['json']['suggestions'][0]['guard']['warnings'][0]['code'], 'echoed customer number is flagged' );
$r2 = t_http( 'POST', '/drafts', $tok, $body, array( 'Idempotency-Key' => t_uuid() ) );
t_ok( ! isset( $r2['json']['additional_data'] ), 'no additional_data in responses' );

$scan = function ( $with_secret = true ) use ( $wpdb, $phone, $email, $secret, $name ) {
	$hits    = array();
	$markers = $with_secret ? array( $phone, $email, $secret, $name ) : array( $phone, $email, $name );
	foreach ( RarIaa\Db::TABLES as $short ) {
		$t    = RarIaa\Db::t( $short );
		$cols = $wpdb->get_col( "SHOW COLUMNS FROM {$t}" );
		foreach ( $cols as $c ) {
			foreach ( $markers as $m ) {
				$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE CAST({$c} AS CHAR) LIKE %s", '%' . $wpdb->esc_like( $m ) . '%' ) );
				if ( $n ) {
					$hits[] = "{$short}.{$c}";
				}
			}
		}
	}
	foreach ( $markers as $m ) {
		$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( $m ) . '%' ) );
		if ( $n ) {
			$hits[] = 'options';
		}
	}
	return $hits;
};
t_eq( array(), $scan(), 'no raw marker in any plugin table or wp_options (masked logging off)' );
RarIaa\Settings::update( array( 'masked_logging' => 1 ) );
t_stub( t_sug( 'আমরা [PHONE_1] নম্বরে কল দেব।' ) );
$r = t_http( 'POST', '/drafts', $tok, $body, array( 'Idempotency-Key' => t_uuid() ) );
$row = $wpdb->get_row( 'SELECT * FROM ' . RarIaa\Db::t( 'draft_log' ) . " WHERE status = 'ok' ORDER BY id DESC LIMIT 1" );
t_contains( '[PHONE_1]', $row->input_masked, 'masked input stored with placeholders when enabled' );
t_contains( '[EMAIL_1]', $row->input_masked, 'email placeholder' );
t_contains( '[CUSTOMER]', $row->input_masked, 'name placeholder' );
t_contains( '[PHONE_1]', $row->reply_masked, 'reply re-masked for the log' );
t_eq( array(), $scan( false ), 'masked logging keeps message words but no phone, e-mail or name' );
$log = is_readable( WP_CONTENT_DIR . '/debug.log' ) ? (string) file_get_contents( WP_CONTENT_DIR . '/debug.log', false, null, $debug_before ) : '';
t_ok( false === strpos( $log, $phone ) && false === strpos( $log, $secret ), 'no marker in debug.log' );
$log2 = defined( 'WP_DEBUG_LOG' ) && is_string( WP_DEBUG_LOG ) && is_readable( WP_DEBUG_LOG ) ? (string) file_get_contents( WP_DEBUG_LOG ) : '';
t_ok( false === strpos( $log2, $phone ) && false === strpos( $log2, $secret ), 'no marker in WP_DEBUG_LOG file' );
