<?php
/** v0.2.1 — reply language from recent messages, no handoff for an unknown product, staff note (FR-29), primary time cap. */
global $wpdb;
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_shop_v021' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'per_minute' => 60, 'allow_public_mrp' => 0 ) );
$post = function ( array $body ) use ( $tok ) {
	return t_http( 'POST', '/drafts', $tok, array_merge( array( 'source' => 'paste' ), $body ), array( 'Idempotency-Key' => t_uuid() ) );
};
$log = function ( $draft_id ) use ( $wpdb ) {
	return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RarIaa\Db::t( 'draft_log' ) . ' WHERE draft_uuid = %s', $draft_id ) );
};
$c = function ( $text ) {
	return array( 'side' => 'customer', 'text' => $text );
};

// 1. Language: a short English-looking last message follows the earlier Bangla message.
$r = $post( array( 'messages' => array( $c( 'এই প্রোডাক্ট টা available আছে?' ), $c( 'Price?' ) ) ) );
t_eq( 200, $r['status'], 'lang: 200' );
t_eq( 'bn', $r['json']['suggestions'][0]['language'], 'Bangla then "Price?" → Bangla reply' );
t_eq( 'bn', $log( $r['json']['draft_id'] )->language, 'log language bn' );
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ) ) );
t_eq( 'bn', $r['json']['suggestions'][0]['language'], '"Price koto?" alone → Bangla reply' );

// 2. Price asked but no product recognised → ask which product, no manager handoff.
t_eq( false, $r['json']['suggestions'][0]['handoff']['needed'], 'unknown product: no handoff' );
t_eq( 0, (int) $log( $r['json']['draft_id'] )->handoff_needed, 'log: no handoff' );
// The model flagging unknown_dealer_price for an unknown product is not a handoff either.
t_stub( t_sug( 'কোন পণ্যটি জানতে চান, জানাবেন?', array(), array( 'needs_human' => true, 'handoff_reason' => 'unknown_dealer_price' ) ) );
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ) ) );
t_eq( false, $r['json']['suggestions'][0]['handoff']['needed'], 'model unknown_dealer_price without product: no handoff' );
// Other model reasons still hand off.
t_stub( t_sug( 'আমরা নিশ্চিত করে জানাচ্ছি।', array(), array( 'needs_human' => true, 'handoff_reason' => 'complaint' ) ) );
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ) ) );
t_eq( 'complaint', $r['json']['suggestions'][0]['handoff']['reason'], 'other model handoff kept' );

// 3. Staff note identifies the product in a photo.
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => 'ছবির পণ্য: A TO Z Jumbo' ) );
t_eq( 200, $r['status'], 'note: 200' );
$row = $log( $r['json']['draft_id'] );
t_contains( 'price:', (string) $row->facts_used, 'note matched the product' );
t_eq( 'unknown_dealer_price', $r['json']['suggestions'][0]['handoff']['reason'], 'known product, no public price → manager' );
RarIaa\Settings::update( array( 'allow_public_mrp' => 1 ) );
$r   = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => 'A TO Z Jumbo' ) );
$row = $log( $r['json']['draft_id'] );
t_contains( 'mrp:', (string) $row->facts_used, 'note + public price → public price fact supplied' );
t_eq( false, $r['json']['suggestions'][0]['handoff']['needed'], 'public price available → no handoff' );
RarIaa\Settings::update( array( 'allow_public_mrp' => 0 ) );

// The note reaches the prompt, never storage (masked logging on).
RarIaa\Settings::update( array( 'masked_logging' => 1 ) );
$marker = 'Qzv Note Marker';
$r      = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => $marker . ' A TO Z Jumbo' ) );
t_eq( 200, $r['status'], 'note with masked logging: 200' );
$hits = 0;
foreach ( $wpdb->get_col( "SHOW TABLES LIKE '" . $wpdb->esc_like( $wpdb->prefix . 'rar_iaa_' ) . "%'" ) as $table ) {
	foreach ( $wpdb->get_results( "SELECT * FROM `{$table}`", ARRAY_A ) as $rowa ) { // phpcs:ignore -- test scan.
		$hits += false !== stripos( wp_json_encode( $rowa, JSON_UNESCAPED_UNICODE ), $marker ) ? 1 : 0;
	}
}
$hits += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_value LIKE %s", '%' . $wpdb->esc_like( $marker ) . '%' ) );
t_eq( 0, $hits, 'staff note stored nowhere' );
RarIaa\Settings::update( array( 'masked_logging' => 0 ) );

// Note masking: a phone number typed in the note is masked like conversation text.
$prompt = RarIaa\Draft\PromptBuilder::suggestions( array( 'business_name' => 'X', 'kb' => array() ), array( array( 'side' => 'customer', 'text' => 'hi' ) ), 'bn', false, 'en', 3, 'A TO Z Jumbo' );
t_contains( "STAFF NOTE (from our staff member, not the customer):\n<<<\nA TO Z Jumbo\n>>>", $prompt['user'], 'note block in prompt' );
t_contains( '12. A STAFF NOTE', $prompt['system'], 'note rule in prompt' );
t_ok( false === strpos( RarIaa\Draft\PromptBuilder::suggestions( array( 'business_name' => 'X', 'kb' => array() ), array( array( 'side' => 'customer', 'text' => 'hi' ) ), 'bn', false, 'en', 3 )['user'], 'STAFF NOTE' ), 'no note → no block' );
$m = new RarIaa\Draft\Masker();
t_ok( false === strpos( $m->mask( 'call 01711-223344 Jumbo', array() ), '01711' ), 'masker hides a phone in a note' );

// Validation.
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => str_repeat( 'a', 201 ) ) );
t_eq( 422, $r['status'], 'note > 200 → 422' );
t_ok( isset( $r['json']['data']['fields']['staff_note'] ), 'note field error' );
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => 123 ) );
t_eq( 422, $r['status'], 'non-string note → 422' );
$r = $post( array( 'messages' => array( $c( 'Price koto?' ) ), 'staff_note' => null ) );
t_eq( 200, $r['status'], 'null note accepted' );

// 4. Primary capped so the fallback still runs: the primary fails after 12.5 s.
RarIaa\Settings::update(
	array(
		'provider'          => 'gemini',
		'fallback_provider' => 'anthropic',
		'free_providers'    => array( 'gemini' ),
		'free_tier_consent' => 1,
		'prices'            => array( 'anthropic' => array( 'in' => 1, 'out' => 5 ) ),
	)
);
$ok = array( 'text' => wp_json_encode( array( 'suggestions' => t_sug( 'আমাদের অফিস সকাল ৯টা থেকে সন্ধ্যা ৬টা পর্যন্ত খোলা থাকে।', array( 'kb:' . $s['kb'] ) ) ), JSON_UNESCAPED_UNICODE ) );
t_stub_raw( array( array( 'error' => 'PROVIDER_TIMEOUT', 'delay_ms' => 12500 ), $ok ) );
$t = microtime( true );
$r = $post( array( 'messages' => array( $c( 'Apnader office koyta porjonto khola?' ) ) ) );
t_eq( 200, $r['status'], 'slow primary → fallback answers (200)' );
t_eq( true, $r['json']['ai']['fallback'], 'fallback used after a 12.5 s primary failure' );
t_ok( microtime( true ) - $t < 20, 'within the 20 s budget' );
delete_option( 'rar_iaa_stub_next' );

// 5. Readable provider error text.
t_contains( 'overloaded', RarIaa\Providers\ProviderFactory::http_error_message( 503, 'UNAVAILABLE' ), '503 explained' );
t_contains( 'limit', RarIaa\Providers\ProviderFactory::http_error_message( 429, 'RESOURCE_EXHAUSTED' ), '429 explained' );
t_contains( 'model name', RarIaa\Providers\ProviderFactory::http_error_message( 404, 'NOT_FOUND' ), '404 explained' );
t_contains( 'API key', RarIaa\Providers\ProviderFactory::http_error_message( 403, 'PERMISSION_DENIED' ), '403 explained' );
t_eq( 0, strpos( RarIaa\Providers\ProviderFactory::http_error_message( 503, 'unavailable' ), 'AI provider error: unavailable' ), 'prefix kept' );
