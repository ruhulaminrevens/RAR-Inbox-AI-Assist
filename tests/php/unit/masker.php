<?php
/** T2.07 — masking cases (ASCII/Bangla digits, +88, separators, landline, e-mail, IDs, addresses, names). */
use RarIaa\Draft\Masker;

$cases = array(
	array( 'আমার নম্বর 01700000001', '[PHONE_1]', '01700000001' ),
	array( 'call 01700-000001 please', '[PHONE_1]', '01700-000001' ),
	array( 'number +8801711223344', '[PHONE_1]', '+8801711223344' ),
	array( 'number 8801711223344', '[PHONE_1]', '8801711223344' ),
	array( 'number +88 01711-223344', '[PHONE_1]', '+88 01711-223344' ),
	array( 'নম্বর ০১৭১১২২৩৩৪৪', '[PHONE_1]', '০১৭১১২২৩৩৪৪' ),
	array( 'নম্বর ০১৭১১-২২৩৩৪৪', '[PHONE_1]', '০১৭১১-২২৩৩৪৪' ),
	array( 'ph 01911 223 344', '[PHONE_1]', '01911 223 344' ),
	array( 'office 02-57318841', '[PHONE_1]', '02-57318841' ),
	array( 'office 031-2556677', '[PHONE_1]', '031-2556677' ),
	array( 'mail me rahim.traders@gmail.com', '[EMAIL_1]', 'rahim.traders@gmail.com' ),
	array( 'bkash trx 8N7A6B5C4D and acc 1234567890123', '[NUMBER_1]', '1234567890123' ),
	array( 'ঠিকানা: রোড ১২, বাসা ৫, মিরপুর', '[ADDRESS_1]', null ),
	array( 'address: House 12, Road 5, Banani', '[ADDRESS_1]', null ),
	array( 'House 45 road 7 dhanmondi te pathaben', '[ADDRESS_1]', null ),
);
foreach ( $cases as $i => $c ) {
	$m   = new Masker();
	$out = $m->mask( $c[0] );
	if ( null === $c[1] ) {
		continue;
	}
	t_contains( $c[1], $out, "mask case {$i}: placeholder in '{$out}'" );
	if ( $c[2] ) {
		t_ok( false === strpos( $out, $c[2] ), "mask case {$i}: original removed" );
		t_contains( $c[2], $m->unmask( $out ), "mask case {$i}: unmask restores" );
	}
}
// 12 digits is not a BD mobile, but still a long number.
$m = new Masker();
t_contains( '[NUMBER_1]', $m->mask( 'id 019112233445' ), '12-digit run masked as number' );

// Must NOT be masked: prices, quantities, dates, short numbers.
$keep = array( 'দাম ১২৫০ টাকা', 'price 1,250 tk', '50 carton lagbe', '27-10-2026 e asben?', '৩ কার্টন', 'order 120 pcs', 'rate 1100/-', 'সকাল ৯টা' );
foreach ( $keep as $k ) {
	$m = new Masker();
	t_eq( $k, $m->mask( $k ), "not masked: {$k}" );
}
// Clauses with cue words but no number are kept (e.g. "কোন জেলায় ডেলিভারি দেন?").
$m = new Masker();
t_eq( 'কোন জেলায় ডেলিভারি দেন? রোড কোথায়?', $m->mask( 'কোন জেলায় ডেলিভারি দেন? রোড কোথায়?' ), 'cue word without number kept' );

// Display names.
$m   = new Masker();
$out = $m->mask( 'Ataur Rahman Arif bhai, Ataur ekhane', array( 'Ataur Rahman Arif' ) );
t_ok( false === strpos( $out, 'Ataur' ), 'display name and first name masked' );
t_contains( '[CUSTOMER]', $out, 'customer placeholder' );
// Same number twice → same placeholder.
$m   = new Masker();
$out = $m->mask( '01711223344 and again 01711223344' );
t_eq( 2, substr_count( $out, '[PHONE_1]' ), 'repeated number reuses placeholder' );
// Remask for logging.
t_eq( 'call [PHONE_1]', $m->remask( 'call 01711223344' ), 'remask' );
