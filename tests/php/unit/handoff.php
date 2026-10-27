<?php
/** T2.13 — handoff rules (bn / Banglish / en). */
use RarIaa\Draft\HandoffRules;

$cases = array(
	array( 'আমার বকেয়া কত?', 'credit_outstanding' ),
	array( 'baki koto ache amar?', 'credit_outstanding' ),
	array( 'what is my outstanding balance', 'credit_outstanding' ),
	array( 'লেজার পাঠান', 'credit_outstanding' ),
	array( 'একটু ছাড় দেন ভাই', 'discount_negotiation' ),
	array( 'discount pabo?', 'discount_negotiation' ),
	array( 'dam kom hobe na?', 'discount_negotiation' ),
	array( 'কমিশন কত পার্সেন্ট?', 'discount_negotiation' ),
	array( 'last price koto', 'discount_negotiation' ),
	array( 'অর্ডার কনফার্ম করেন', 'order_confirmation' ),
	array( '50 carton order dite chai', 'order_confirmation' ),
	array( 'please confirm order', 'order_confirmation' ),
	array( 'order korbo 20 ctn', 'order_confirmation' ),
	array( 'মাল খারাপ ছিল', 'complaint' ),
	array( 'product fake mone hocche', 'complaint' ),
	array( 'complain korte chai', 'complaint' ),
	array( 'কয়েল ভাঙা এসেছে', 'damage_return' ),
	array( 'ferot dite chai', 'damage_return' ),
	array( 'return policy?', 'damage_return' ),
	array( 'packet leak', 'damage_return' ),
	array( 'অফিস কয়টা পর্যন্ত খোলা?', null ),
	array( 'jumbo er dam koto', null ),
	array( 'ki ki product ache?', null ),
	array( 'thanks', null ),
	array( 'delivery kobe hobe?', null ),
	array( 'price list diben?', null ),
);
foreach ( $cases as $c ) {
	t_eq( $c[1], HandoffRules::match( $c[0] ), "handoff: {$c[0]}" );
}
t_eq( array( 'needed' => true, 'reason' => 'complaint' ), HandoffRules::decide( 'complaint', false, null, false ), 'rule wins' );
t_eq( array( 'needed' => true, 'reason' => 'unknown_dealer_price' ), HandoffRules::decide( null, false, null, true ), 'price withheld' );
t_eq( array( 'needed' => true, 'reason' => 'missing_facts' ), HandoffRules::decide( null, true, 'missing_facts', false ), 'model flag' );
t_eq( array( 'needed' => false, 'reason' => null ), HandoffRules::decide( null, false, null, false ), 'none' );
