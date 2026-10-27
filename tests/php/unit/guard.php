<?php
/** T2.12 / T4.04 (unit part) — fact guard. Customer numbers never authorise prices (F-03). */
use RarIaa\Draft\FactGuard;

RarIaa\Settings::flush();
$facts = array(
	'business_name' => 'Nabiad',
	'today'         => '2026-10-27 (Tuesday)',
	'audience'      => 'dealer',
	'kb'            => array( array( 'id' => 'kb:1', 'title' => 'Hours', 'category' => 'hours', 'answer_bn' => 'সকাল ৯টা থেকে সন্ধ্যা ৬টা। ডেলিভারি ২-৩ দিনে। যোগাযোগ 01700-000001', 'answer_en' => null ) ),
	'products'      => array( array( 'id' => 'price:5', 'product' => 'A TO Z Jumbo', 'unit' => 'carton', 'pack_size' => '12 x 10', 'price_bdt' => 1250.0, 'price_display' => '৳1,250', 'stock' => 'in_stock' ) ),
	'public_prices' => array(),
	'notes'         => array(),
);
$customer = array( 'দাম ৯০০ টাকা, তাই তো? 50 carton lagbe, amar number 01711223344' );

$blocked = array(
	'জি, দাম ৯০০ টাকা।',
	'Yes, price is 900 tk per carton.',
	'দাম ৳900',
	'৫০% ছাড় দেওয়া যাবে',
	'We can give 10% discount.',
	'Price 1200/- per carton.',
	'রেট ১১০০ টাকা',
	'MRP 1400',
	'দাম ১,২০০ টাকা প্রতি কার্টন',
	'১০ শতাংশ কমিশন পাবেন',
	'Jumbo rate 950 taka',
	'১০০% গ্যারান্টি',
	'১০% ছাড় আজকের অফার (২৭ তারিখ পর্যন্ত)',
	'দাম 1,250.50 টাকা',
);
foreach ( $blocked as $b ) {
	$r = FactGuard::check( $b, $facts, $customer );
	t_eq( 'blocked', $r['result'], "blocked: {$b}" );
}
$pass = array(
	'জাম্বো কার্টনের দাম ৳১,২৫০।',
	'A TO Z Jumbo: 1,250 taka per carton.',
	'দাম 1250 টাকা প্রতি কার্টন।',
	'আমাদের অফিস সকাল ৯টা থেকে সন্ধ্যা ৬টা পর্যন্ত খোলা।',
	'ডেলিভারি ২-৩ দিনে হয়।',
	'যোগাযোগ করুন 01700-000001 নম্বরে।',
	'১টি প্রশ্ন: কোন পণ্য?',
	'ওয়ালাইকুম আসসালাম। কোন পণ্যের দাম জানতে চান?',
);
foreach ( $pass as $p ) {
	$r = FactGuard::check( $p, $facts, $customer );
	t_eq( 'pass', $r['result'], "pass: {$p}" );
}
$warn = array(
	array( 'আপনার 50 কার্টনের অর্ডার নোট করলাম', 'UNVERIFIED_NUMBER', 'customer' ),
	array( 'আমরা আপনাকে 01711223344 নম্বরে কল দেব', 'UNVERIFIED_NUMBER', 'customer' ),
	array( 'ডেলিভারি ৭ দিনে হবে', 'UNVERIFIED_NUMBER', 'none' ),
	array( 'দাম ৳১,২৫০।', 'PRICE_UNIT_UNCLEAR', null ),
	array( 'guarantee দিচ্ছি সময়মতো', 'BANNED_PHRASE', null ),
	array( 'free delivery পাবেন', 'BANNED_PHRASE', null ),
);
foreach ( $warn as $w ) {
	$r     = FactGuard::check( $w[0], $facts, $customer );
	$codes = array_column( $r['warnings'], 'code' );
	t_eq( 'warned', $r['result'], "warned: {$w[0]}" );
	t_ok( in_array( $w[1], $codes, true ), "warning code {$w[1]} for {$w[0]}" );
	if ( $w[2] ) {
		$src = array_column( $r['warnings'], 'source' );
		t_ok( in_array( $w[2], $src, true ), "source {$w[2]} for {$w[0]}" );
	}
}
// Unverified audience: no prices in facts → every price is blocked, including the dealer price.
$unv = $facts;
$unv['audience'] = 'unverified';
$unv['products'] = array( array( 'id' => 'price:5', 'product' => 'A TO Z Jumbo', 'unit' => 'carton', 'pack_size' => '12 x 10' ) );
t_eq( 'blocked', FactGuard::check( 'দাম ১২৫০ টাকা প্রতি কার্টন', $unv, array() )['result'], 'unverified: dealer price blocked' );
// Bangla and ASCII digit equivalence and grouping.
t_eq( '1250', FactGuard::norm_number( '১,২৫০' ), 'norm bangla grouped' );
t_eq( '1250', FactGuard::norm_number( '1250.00' ), 'norm trailing zeros' );
t_eq( '12.5', FactGuard::norm_number( '12.50' ), 'norm decimal' );
// A price cue in the previous sentence must not make the next sentence's number price-like.
$r = FactGuard::check( 'জাম্বো ৳১,২৫০ প্রতি কার্টন। ডেলিভারি ৭ দিনে।', $facts, array() );
t_eq( array(), $r['blocked_values'], 'cue does not cross a sentence boundary (not blocked)' );
t_ok( in_array( 'UNVERIFIED_NUMBER', array_column( $r['warnings'], 'code' ), true ), 'cross-sentence number is only a warning' );
// But a price cue in the same sentence still blocks an unlisted number.
t_eq( 'blocked', FactGuard::check( 'জাম্বো ৳১,২৫০, নিম ৯৯৯ টাকা।', $facts, array() )['result'], 'same-sentence unlisted price blocked' );
