<?php
/** T1.06 / T1.11 — atomic usage counters and cost reservations (v0.2 §19, audit F-10). */
use RarIaa\Limits\Budget;
use RarIaa\Limits\RateLimiter;
global $wpdb;
t_reset();
RarIaa\Settings::update( array( 'monthly_cap_usd' => 1.0 ) ); // 1,000,000 micros
t_eq( 1000000, Budget::cap_micros(), 'cap in micros' );
t_eq( 6000, Budget::micros( 1200, 5 ), 'micros = tokens × USD per million tokens' );
t_ok( true === Budget::reserve( 'r-1', 600000 ), 'first reservation fits' );
t_eq( 'BUDGET_EXCEEDED', Budget::reserve( 'r-2', 600000 )->get_error_code(), 'second would exceed cap → refused' );
list( $spent, $reserved ) = Budget::month_totals();
t_eq( 600000, $reserved, 'reserved counted' );
Budget::settle( 'r-1', 100, 50, 1000, false );
list( $spent, $reserved ) = Budget::month_totals();
t_eq( array( 1000, 0 ), array( $spent, $reserved ), 'settled: actual cost kept, reservation released' );
t_ok( true === Budget::reserve( 'r-3', 600000 ), 'room again after settlement' );
// Crash recovery: the request died without settling.
$wpdb->query( $wpdb->prepare( 'UPDATE ' . RarIaa\Db::t( 'cost_reservations' ) . " SET expires_at = %s WHERE request_id = 'r-3'", RarIaa\Db::now( -5 ) ) );
t_eq( 1, Budget::release_expired(), 'expired reservation released' );
list( , $reserved ) = Budget::month_totals();
t_eq( 0, $reserved, 'reserved back to zero after crash recovery' );
Budget::settle( 'r-3', 10, 10, 50, false );
t_eq( 1050, Budget::month_totals()[0], 'late settlement still records real spend' );
// Two processes race for the last room under the cap: exactly one wins.
t_reset();
RarIaa\Settings::update( array( 'monthly_cap_usd' => 1.0 ) );
$load  = getenv( 'WP_LOAD' ) ?: ABSPATH . 'wp-load.php';
$procs = array();
$files = array();
for ( $i = 0; $i < 6; $i++ ) {
	$out     = tempnam( sys_get_temp_dir(), 'race' );
	$files[] = $out;
	$code    = "\$_SERVER['HTTP_HOST']='localhost'; require '{$load}'; \$r = RarIaa\\Limits\\Budget::reserve('race-{$i}', 700000); file_put_contents('{$out}', true === \$r ? 'ok' : \$r->get_error_code());";
	$procs[] = proc_open( array( PHP_BINARY, '-r', $code ), array(), $pipes );
}
foreach ( $procs as $p ) {
	proc_close( $p );
}
$results = array_map( 'file_get_contents', $files );
array_map( 'unlink', $files );
t_eq( 1, count( array_keys( $results, 'ok', true ) ), 'exactly one of six near-cap reservations succeeded: ' . implode( ',', $results ) );
// Daily request limit counted at reservation.
RarIaa\Settings::update( array( 'daily_limit' => 1, 'monthly_cap_usd' => 100 ) );
t_eq( 'DAILY_LIMIT', Budget::reserve( 'race-x', 1 )->get_error_code(), 'daily limit enforced at reservation' );
// Rate limiter: 50 parallel hits → exact count.
t_reset();
$procs = array();
for ( $i = 0; $i < 20; $i++ ) {
	$code    = "\$_SERVER['HTTP_HOST']='localhost'; require '{$load}'; RarIaa\\Limits\\RateLimiter::hit(77, 'race', 1000);";
	$procs[] = proc_open( array( PHP_BINARY, '-r', $code ), array(), $pipes );
}
foreach ( $procs as $p ) {
	proc_close( $p );
}
$n = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(count) FROM ' . RarIaa\Db::t( 'rate_buckets' ) . ' WHERE bucket_key LIKE %s', 'u77:race:%' ) );
t_eq( 20, $n, '20 parallel hits counted exactly' );
t_reset();
