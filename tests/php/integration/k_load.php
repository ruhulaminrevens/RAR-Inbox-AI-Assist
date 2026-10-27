<?php
/** T4.07 — parallel draft requests: only one provider call in flight; the rest get BUSY quickly. Needs a multi-worker server (PHP_CLI_SERVER_WORKERS ≥ 4). */
global $wpdb;
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_shop' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'per_minute' => 60 ) );
$scripts = array();
for ( $i = 0; $i < 6; $i++ ) {
	$scripts[] = array( 'delay_ms' => 2500, 'text' => wp_json_encode( array( 'suggestions' => t_sug( 'অফিস সকাল ৯টা থেকে সন্ধ্যা ৬টা।' ) ), JSON_UNESCAPED_UNICODE ) );
}
t_stub_raw( $scripts );
$base = getenv( 'IAA_TEST_URL' ) ?: 'http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1';
$mh   = curl_multi_init();
$hs   = array();
$t0   = microtime( true );
for ( $i = 0; $i < 4; $i++ ) {
	$ch = curl_init( $base . '/drafts' );
	curl_setopt_array(
		$ch,
		array(
			CURLOPT_POST           => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_HTTPHEADER     => array( 'Authorization: Bearer ' . $tok, 'Content-Type: application/json', 'Idempotency-Key: ' . t_uuid() ),
			CURLOPT_POSTFIELDS     => wp_json_encode( array( 'messages' => array( array( 'side' => 'customer', 'text' => 'office koyta porjonto khola' ) ) ) ),
		)
	);
	curl_multi_add_handle( $mh, $ch );
	$hs[] = $ch;
}
do {
	curl_multi_exec( $mh, $running );
	curl_multi_select( $mh, 0.1 );
} while ( $running );
$codes = array();
$times = array();
foreach ( $hs as $ch ) {
	$codes[] = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
	$times[] = curl_getinfo( $ch, CURLINFO_TOTAL_TIME );
	curl_multi_remove_handle( $mh, $ch );
}
$elapsed = microtime( true ) - $t0;
sort( $codes );
t_ok( in_array( 200, $codes, true ), 'at least one request served: ' . implode( ',', $codes ) );
t_ok( ! array_diff( $codes, array( 200, 503 ) ), 'no other status codes (no 500s)' );
// Every 200 took ≥ 2.5 s of provider time; if two 200s overlapped, the slot did not hold.
$served = count( array_keys( $codes, 200, true ) );
if ( $elapsed < 2.5 * $served - 0.3 ) {
	t_ok( false, "two provider calls overlapped ({$served} served in " . round( $elapsed, 2 ) . ' s)' );
} else {
	t_ok( true, 'provider calls never overlapped' );
}
if ( $elapsed < 5.0 ) {
	t_ok( count( array_keys( $codes, 503, true ) ) >= 1, 'parallel requests beyond the single slot get 503 BUSY' );
	$busy_fast = array_filter( $times, function ( $t ) { return $t < 2.0; } );
	t_ok( count( $busy_fast ) >= 1, 'BUSY answers return quickly' );
} else {
	echo "  (note: the test server queued the requests instead of running them in parallel; BUSY checks skipped)\n";
}
t_eq( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'cost_reservations' ) . " WHERE status = 'reserved'" ), 'no reservation left open' );
t_eq( 0, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'rate_buckets' ) . " WHERE bucket_key LIKE 'lock:%'" ), 'no lock left behind' );
delete_option( 'rar_iaa_stub_next' );
echo '  (load: ' . count( $codes ) . ' parallel, ' . round( $elapsed, 2 ) . " s)\n";
