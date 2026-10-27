<?php
/** T2.04 — CSV import validation (BR-10). */
use RarIaa\Domain\PriceRepository;

function t_csv( $content ) {
	$f = tempnam( sys_get_temp_dir(), 'csv' );
	file_put_contents( $f, $content );
	$r = PriceRepository::parse_csv( $f );
	unlink( $f );
	return $r;
}
$h = "item_code,product_name,unit,price_bdt\n";
$r = t_csv( $h . "A1,Coil,carton,1250\nA2,Spray,pcs,\"১,২৫০.৫০\"\n" );
t_eq( array(), $r['errors'], 'valid file no errors' );
t_eq( 2, count( $r['rows'] ), 'two rows' );
t_eq( 1250.5, $r['rows'][1]['price_bdt'], 'bangla digits + grouping parsed' );
t_eq( 'hidden', $r['rows'][0]['stock_mode'], 'default stock mode hidden' );
$r = t_csv( "\xEF\xBB\xBF" . $h . "A1,Coil,carton,৳1250\n" );
t_eq( array(), $r['errors'], 'BOM and taka sign accepted' );
$r = t_csv( "item_code,product_name,unit\nA1,Coil,carton\n" );
t_ok( count( $r['errors'] ) > 0 && 'price_bdt' === $r['errors'][0][1], 'missing required column' );
$r = t_csv( $h . "A1,Coil,carton,0\nA1,Coil2,carton,abc\n,NoCode,carton,5\n" );
$cols = array_column( $r['errors'], 1 );
t_ok( in_array( 'price_bdt', $cols, true ) && in_array( 'item_code', $cols, true ), 'zero/invalid price, duplicate and empty code' );
t_eq( 4, count( $r['errors'] ), 'four row errors' );
$r = t_csv( "item_code,product_name,unit,price_bdt,colour\nA1,Coil,carton,5,red\n" );
t_ok( 'colour' === $r['errors'][0][1], 'unknown column rejected' );
$r = t_csv( $h . "A1,Coil,carton,5\n" . "" );
t_eq( array(), $r['errors'], 'trailing newline ok' );
$r = t_csv( "item_code,product_name,unit,price_bdt,stock_mode,manual_stock_status\nA1,Coil,carton,5,manual,\nA2,Coil,carton,5,wc,\n" );
t_eq( 2, count( $r['errors'] ), 'manual needs status; wc needs product id' );
$r = t_csv( "\xFF\xFE" . $h );
t_ok( count( $r['errors'] ) > 0, 'non UTF-8 rejected' );
$rows = $h;
for ( $i = 0; $i <= 5000; $i++ ) {
	$rows .= "C{$i},Item,pcs,1\n";
}
$r = t_csv( $rows );
t_ok( (bool) array_filter( $r['errors'], function ( $e ) { return false !== strpos( $e[2], '5,000' ); } ), 'more than 5000 rows rejected' );
t_eq( 1250.0, PriceRepository::parse_money( '1,250 tk' ), 'money tk' );
t_eq( null, PriceRepository::parse_money( '12.345' ), 'three decimals rejected' );
