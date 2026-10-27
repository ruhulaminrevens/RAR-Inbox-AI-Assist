<?php
/** T2.02 / T2.03 / T2.05 — KB maker-checker, serving window, price activation and rollback. */
use RarIaa\Domain\KbRepository;
use RarIaa\Domain\DealerRepository;
use RarIaa\Domain\PriceRepository;

t_reset();
$a1 = t_user( 'administrator', 'iaa_admin' );
$a2 = t_user( 'administrator', 'iaa_admin2' );
$id = KbRepository::create( array( 'title' => 'Delivery', 'category' => 'delivery', 'keywords' => 'delivery, ডেলিভারি', 'answer_bn' => 'ডেলিভারি ২-৩ দিন।' ), $a1 );
t_ok( is_int( $id ), 'KB created' );
t_eq( 0, count( KbRepository::serving() ), 'draft is not served' );
$e = KbRepository::get( $id );
t_eq( 'FORBIDDEN', KbRepository::approve( $id, $a1, (int) $e->row_version )->get_error_code(), 'editor cannot approve own version when another admin exists' );
t_ok( true === KbRepository::approve( $id, $a2, (int) $e->row_version ), 'second admin approves' );
t_eq( 1, count( KbRepository::serving() ), 'approved is served' );
$e = KbRepository::get( $id );
KbRepository::update( $id, array( 'title' => 'Delivery', 'category' => 'delivery', 'keywords' => 'delivery', 'answer_bn' => 'ডেলিভারি ৫ দিন।' ), $a1, (int) $e->row_version );
$serv = KbRepository::serving();
t_eq( 'ডেলিভারি ২-৩ দিন।', $serv[0]->answer_bn, 'approved v1 keeps serving while v2 is draft' );
t_eq( 'CONFLICT', KbRepository::update( $id, array( 'title' => 'X', 'category' => 'delivery', 'answer_bn' => 'y' ), $a1, 1 )->get_error_code(), 'stale row_version → conflict' );
$e = KbRepository::get( $id );
KbRepository::approve( $id, $a2, (int) $e->row_version );
$serv = KbRepository::serving();
t_eq( 'ডেলিভারি ৫ দিন।', $serv[0]->answer_bn, 'v2 served after approval' );
$vers = KbRepository::versions( $id );
t_eq( 'superseded', end( $vers )->state, 'v1 superseded' );
$f = KbRepository::create( array( 'title' => 'Future', 'category' => 'policy', 'answer_bn' => 'x', 'effective_from' => '2099-01-01' ), $a1 );
$e = KbRepository::get( $f );
KbRepository::approve( $f, $a2, (int) $e->row_version );
$p = KbRepository::create( array( 'title' => 'Past', 'category' => 'policy', 'answer_bn' => 'y', 'effective_from' => '2020-01-01', 'effective_to' => '2020-12-31' ), $a1 );
$e = KbRepository::get( $p );
KbRepository::approve( $p, $a2, (int) $e->row_version );
t_eq( 1, count( KbRepository::serving() ), 'future and expired entries not served' );
t_eq( 'VALIDATION_FAILED', KbRepository::create( array( 'title' => 'Bad', 'category' => 'policy', 'answer_bn' => 'x', 'effective_from' => '2026-05-01', 'effective_to' => '2026-04-01' ), $a1 )->get_error_code(), 'end before start rejected' );
KbRepository::archive( $id, $a1 );
t_eq( 0, count( KbRepository::serving() ), 'archived not served' );
KbRepository::restore( $id, $a1 );
t_eq( 'draft', KbRepository::get( $id )->status, 'restored as draft' );

// Dealers and prices.
$tier = DealerRepository::save_tier( array( 'code' => 'a', 'name' => 'Distributor' ), $a1 );
t_eq( 'A', DealerRepository::tier( $tier )->code, 'tier code upper-cased' );
t_eq( 'VALIDATION_FAILED', DealerRepository::save_tier( array( 'code' => 'A', 'name' => 'Dup' ), $a1 )->get_error_code(), 'duplicate tier code' );
$d = DealerRepository::save_dealer( array( 'dealer_code' => 'd-1', 'name' => 'X Traders', 'tier_id' => $tier, 'district_code' => 'BD-13' ), $a1 );
t_ok( is_int( $d ), 'dealer saved' );
t_eq( 'VALIDATION_FAILED', DealerRepository::save_dealer( array( 'dealer_code' => 'D-1', 'name' => 'Y', 'tier_id' => $tier ), $a1 )->get_error_code(), 'duplicate dealer code' );
t_eq( 'VALIDATION_FAILED', DealerRepository::save_dealer( array( 'dealer_code' => 'D-2', 'name' => 'Y', 'tier_id' => $tier, 'district_code' => 'Dhaka' ), $a1 )->get_error_code(), 'bad district code' );

$mk = function ( $price ) use ( $tier, $a1 ) {
	$f = tempnam( sys_get_temp_dir(), 'csv' );
	file_put_contents( $f, "item_code,product_name,unit,price_bdt\nA1,Coil,carton,{$price}\n" );
	$p = PriceRepository::parse_csv( $f );
	unlink( $f );
	return PriceRepository::save_draft( $tier, "List {$price}", '2026-10-01', $p, $a1 );
};
$l1 = $mk( 100 );
$l2 = $mk( 200 );
t_ok( true === PriceRepository::activate( $l1, $a1 ), 'activate l1' );
t_ok( true === PriceRepository::activate( $l2, $a1 ), 'activate l2' );
t_eq( (int) $l2, (int) PriceRepository::active_for_tier( $tier )->id, 'l2 active' );
t_eq( 'archived', PriceRepository::get_list( $l1 )->status, 'l1 archived' );
t_eq( (int) $l1, (int) PriceRepository::get_list( $l2 )->previous_list_id, 'previous recorded' );
t_eq( 'CONFLICT', PriceRepository::activate( $l1, $a1 )->get_error_code(), 'archived list cannot be activated as draft' );
t_ok( true === PriceRepository::activate( $l1, $a1, true ), 're-activate previous' );
t_eq( (int) $l1, (int) PriceRepository::active_for_tier( $tier )->id, 'rolled back to l1' );
t_eq( 'CONFLICT', PriceRepository::activate( $l2, $a1, true )->get_error_code(), 'cannot re-activate a list that is not the previous one' );
// Parallel activation race: 8 processes activate two drafts at once → exactly one active.
$l3 = $mk( 300 );
$l4 = $mk( 400 );
$load  = getenv( 'WP_LOAD' ) ?: ABSPATH . 'wp-load.php';
$procs = array();
for ( $i = 0; $i < 8; $i++ ) {
	$lid     = 0 === $i % 2 ? $l3 : $l4;
	$code    = "\$_SERVER['HTTP_HOST']='localhost'; require '{$load}'; RarIaa\\Domain\\PriceRepository::activate({$lid}, {$a1});";
	$procs[] = proc_open( array( PHP_BINARY, '-r', $code ), array(), $pipes );
}
foreach ( $procs as $p ) {
	proc_close( $p );
}
global $wpdb;
t_eq( 1, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'price_lists' ) . ' WHERE tier_id = %d AND active_flag = 1', $tier ) ), 'parallel activations leave exactly one active list' );
t_reset();
