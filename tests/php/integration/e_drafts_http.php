<?php
/** T2.14–T2.17 — draft pipeline over HTTP with the stub provider. */
global $wpdb;
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_shop' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'per_minute' => 60 ) );
$msg  = array( 'source' => 'paste', 'messages' => array( array( 'side' => 'customer', 'text' => 'Assalamu alaikum. Apnader office ajke khola koyta porjonto?' ) ) );
$draft = function ( $body = null, $headers = array(), $token = null ) use ( $tok, $msg ) {
	return t_http( 'POST', '/drafts', $token ?: $tok, $body ?: $msg, array_merge( array( 'Idempotency-Key' => t_uuid() ), $headers ) );
};

$r = $draft();
t_eq( 200, $r['status'], 'draft 200' );
$j = $r['json'];
t_eq( array( 'professional', 'friendly', 'short' ), array_column( $j['suggestions'], 'variant_id' ), 'three variants in order' );
t_ok( 0 === strpos( $j['suggestions'][0]['reply'], 'ওয়ালাইকুম আসসালাম' ), 'salam answered' );
t_contains( '৯টা', $j['suggestions'][0]['reply'], 'uses approved hours fact' );
t_eq( 'pass', $j['suggestions'][0]['guard']['result'], 'guard pass' );
t_eq( 'kb', $j['suggestions'][0]['facts_used'][0]['type'], 'fact chip type' );
t_eq( 'unverified', $j['audience'], 'paste without thread → unverified' );
t_eq( false, $j['thread_bound'], 'unbound' );
t_eq( null, $j['dealer'], 'no dealer' );
$first_id = $j['draft_id'];

// Four variants when configured.
RarIaa\Settings::update( array( 'suggestion_count' => 4 ) );
$r = $draft();
t_eq( 4, count( $r['json']['suggestions'] ), 'four variants' );
RarIaa\Settings::update( array( 'suggestion_count' => 3 ) );

// Idempotency: same key twice.
$key = t_uuid();
$r1  = $draft( null, array( 'Idempotency-Key' => $key ) );
$r2  = $draft( null, array( 'Idempotency-Key' => $key ) );
t_eq( 200, $r1['status'], 'first with key' );
t_eq( 409, $r2['status'], 'replay refused' );
t_eq( 'REQUEST_ALREADY_PROCESSED', $r2['json']['code'], 'replay code' );
t_eq( $r1['json']['draft_id'], $r2['json']['data']['draft_id'], 'replay returns draft id only' );
t_ok( ! isset( $r2['json']['suggestions'] ), 'replay does not return text' );
$r = t_http( 'POST', '/drafts', $tok, $msg );
t_eq( 'VALIDATION_FAILED', $r['json']['code'], 'Idempotency-Key required' );

// Validation.
$bad = array(
	array( 'messages' => array() ),
	array( 'messages' => array( array( 'side' => 'business', 'text' => 'hi' ) ) ),
	array( 'messages' => array( array( 'side' => 'customer', 'text' => str_repeat( 'x', 2001 ) ) ) ),
	array( 'messages' => array( array( 'side' => 'robot', 'text' => 'x' ) ) ),
	array( 'messages' => array( array( 'side' => 'customer', 'text' => 'x' ) ), 'evil' => 1 ),
	array( 'messages' => array( array( 'side' => 'customer', 'text' => 'x', 'extra' => 1 ) ) ),
	array( 'messages' => array( array( 'side' => 'customer', 'text' => 'x' ) ), 'options' => array( 'language' => 'fr' ) ),
);
foreach ( $bad as $i => $b ) {
	$r = $draft( $b );
	t_eq( 422, $r['status'], "invalid body {$i} → 422" );
}

// Thread binding and dealer link.
$thread = '1063479641694:t_1001';
$r      = t_http( 'GET', '/threads/current/link', $tok, null, array( 'X-IAA-Thread' => $thread ) );
t_eq( array( 'linked' => false ), $r['json'], 'not linked yet' );
$r = $draft( null, array( 'X-IAA-Thread' => $thread ) );
t_eq( true, $r['json']['thread_bound'], 'bound when thread header sent' );
t_eq( 'unverified', $r['json']['audience'], 'one tier but unlinked → still unverified (F-08)' );
$r = t_http( 'PUT', '/threads/current/link', $tok, array( 'dealer_id' => $s['dealer'], 'expected_dealer_id' => null ), array( 'X-IAA-Thread' => $thread ) );
t_eq( 200, $r['status'], 'link created' );
t_eq( 'D-014', $r['json']['dealer']['code'], 'link shape' );
t_ok( false === strpos( $r['raw'], hash_hmac( 'sha256', $thread, (string) get_option( 'rar_iaa_thread_secret' ) ) ), 'thread hash never returned' );
$r = t_http( 'PUT', '/threads/current/link', $tok, array( 'dealer_id' => $s['dealer'], 'expected_dealer_id' => null ), array( 'X-IAA-Thread' => $thread ) );
t_eq( 'LINK_CONFLICT', $r['json']['code'], 'stale expected → conflict' );
$price_msg = array( 'source' => 'paste', 'messages' => array( array( 'side' => 'customer', 'text' => 'jumbo carton er dam koto?' ) ) );
$r         = $draft( $price_msg, array( 'X-IAA-Thread' => $thread ) );
t_eq( 'dealer', $r['json']['audience'], 'linked → dealer audience' );
t_eq( 'D-014', $r['json']['dealer']['code'], 'dealer returned' );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RarIaa\Db::t( 'draft_log' ) . ' WHERE draft_uuid = %s', $r['json']['draft_id'] ) );
t_contains( 'price:', $row->facts_used, 'dealer price fact supplied for linked conversation' );
$r   = $draft( $price_msg );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . RarIaa\Db::t( 'draft_log' ) . ' WHERE draft_uuid = %s', $r['json']['draft_id'] ) );
t_ok( false === strpos( (string) $row->facts_used, 'mrp:' ), 'no MRP for unverified while public MRP off' );
t_eq( 'unknown_dealer_price', $r['json']['suggestions'][0]['handoff']['reason'], 'price asked by unverified contact → handoff' );
$r = t_http( 'GET', '/threads/current/link', $tok, null, array( 'X-IAA-Thread' => 'bad key' ) );
t_eq( 422, $r['status'], 'bad thread header 422' );
$r = t_http( 'DELETE', '/threads/current/link', $tok, null, array( 'X-IAA-Thread' => $thread ) );
t_eq( 204, $r['status'], 'unlink' );
// Dealer search.
$r = t_http( 'GET', '/dealers&search=rah', $tok );
t_eq( 'D-014', $r['json'][0]['code'], 'dealer search by name' );
t_ok( false !== strpos( $r['headers'], 'X-WP-Total: 1' ), 'total header' );
$r = t_http( 'GET', '/dealers&search=r', $tok );
t_eq( 422, $r['status'], 'search min 2 chars' );

// Outcome once, owner only.
$r = t_http( 'POST', "/drafts/{$first_id}/outcome", $tok, array( 'outcome' => 'copied', 'selected_variant_id' => 'friendly', 'insert_failed' => false, 'edit_distance' => 0 ) );
t_eq( 204, $r['status'], 'outcome recorded' );
$r = t_http( 'POST', "/drafts/{$first_id}/outcome", $tok, array( 'outcome' => 'inserted', 'selected_variant_id' => 'friendly' ) );
t_eq( 'OUTCOME_ALREADY_SET', $r['json']['code'], 'outcome only once' );
t_eq( 'friendly', $wpdb->get_var( $wpdb->prepare( 'SELECT selected_variant_id FROM ' . RarIaa\Db::t( 'draft_log' ) . ' WHERE draft_uuid = %s', $first_id ) ), 'selected variant stored' );
$other = t_token( $s['admin'] );
$r     = $draft();
$r2    = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/outcome", $other, array( 'outcome' => 'copied', 'selected_variant_id' => 'short' ) );
t_eq( 404, $r2['status'], 'other user cannot set outcome' );
$r2 = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/rewrite", $other, array( 'mode' => 'shorter', 'current_text' => 'x', 'variant_id' => 'short' ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 404, $r2['status'], 'other user cannot rewrite' );
$r2 = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/outcome", $tok, array( 'outcome' => 'copied' ) );
t_eq( 422, $r2['status'], 'selected variant required unless rejected' );
$r2 = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/outcome", $tok, array( 'outcome' => 'rejected', 'reject_reason' => 'tone' ) );
t_eq( 204, $r2['status'], 'reject without variant' );

// Rewrite.
$r  = $draft();
$rw = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/rewrite", $tok, array( 'mode' => 'shorter', 'current_text' => $r['json']['suggestions'][0]['reply'], 'variant_id' => 'professional' ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 200, $rw['status'], 'rewrite 200' );
t_eq( 1, count( $rw['json']['suggestions'] ), 'rewrite returns one suggestion' );
t_eq( $r['json']['draft_id'], $rw['json']['parent_draft_id'], 'parent id' );
$rw = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/rewrite", $tok, array( 'mode' => 'louder', 'current_text' => 'x', 'variant_id' => 'professional' ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 422, $rw['status'], 'bad rewrite mode' );
t_stub( array( array( 'variant_id' => 'professional', 'reply' => 'দাম ৫০০ টাকা', 'language' => 'bn' ) ) );
$rw = t_http( 'POST', "/drafts/{$r['json']['draft_id']}/rewrite", $tok, array( 'mode' => 'polite', 'current_text' => 'ঠিক আছে', 'variant_id' => 'professional' ), array( 'Idempotency-Key' => t_uuid() ) );
t_eq( 'blocked', $rw['json']['suggestions'][0]['guard']['result'], 'rewrite is guarded too' );

// Provider output problems.
t_stub_raw( array( 'text' => 'not json' ) );
$r = $draft();
t_eq( 200, $r['status'], 'one invalid answer is repaired by a second call' );
t_stub_raw( array( array( 'text' => 'not json' ), array( 'text' => '{"suggestions":[]}' ) ) );
$r = $draft();
t_eq( 'PROVIDER_BAD_OUTPUT', $r['json']['code'], 'two invalid answers fail closed' );
t_ok( ! isset( $r['json']['data']['tokens_in'] ), 'internal usage data not exposed in errors' );
t_stub_raw( array( array( 'error' => 'PROVIDER_ERROR', 'retryable' => true ) ) );
$r = $draft();
t_eq( 200, $r['status'], 'transient provider error retried once' );
t_stub_raw( array( array( 'error' => 'PROVIDER_ERROR', 'retryable' => false ) ) );
$r = $draft();
t_eq( 'PROVIDER_ERROR', $r['json']['code'], 'non-retryable provider error returned' );
$err = $wpdb->get_row( 'SELECT * FROM ' . RarIaa\Db::t( 'draft_log' ) . " WHERE status = 'error' ORDER BY id DESC LIMIT 1" );
t_eq( 'PROVIDER_ERROR', $err->error_code, 'provider error logged' );

// Limits.
RarIaa\Settings::update( array( 'per_minute' => 1 ) );
$wpdb->query( 'DELETE FROM ' . RarIaa\Db::t( 'rate_buckets' ) );
$draft();
$r = $draft();
t_eq( 429, $r['status'], 'per-minute limit' );
t_eq( 'RATE_LIMITED', $r['json']['code'], 'RATE_LIMITED' );
t_ok( (bool) preg_match( '/Retry-After: \d+/i', $r['headers'] ), 'Retry-After header' );
RarIaa\Settings::update( array( 'per_minute' => 60, 'daily_limit' => 1 ) );
$r = $draft();
t_eq( 'DAILY_LIMIT', $r['json']['code'], 'daily limit' );
RarIaa\Settings::update( array( 'daily_limit' => 300, 'monthly_cap_usd' => 0.5, 'price_in_usd_per_mtok' => 1000, 'price_out_usd_per_mtok' => 1000 ) );
$r = $draft();
t_eq( 'BUDGET_EXCEEDED', $r['json']['code'], 'budget reservation refuses when worst case exceeds cap' );
RarIaa\Settings::update( array( 'monthly_cap_usd' => 10, 'price_in_usd_per_mtok' => 0, 'price_out_usd_per_mtok' => 0 ) );
// Stub ignores prices; a real provider needs them. Simulate by switching the server? Covered in unit budget test.
RarIaa\Settings::update( array( 'price_in_usd_per_mtok' => 1, 'price_out_usd_per_mtok' => 5, 'ai_enabled' => 0 ) );
$r = $draft();
t_eq( 'AI_DISABLED', $r['json']['code'], 'AI disabled' );
$r = t_http( 'GET', '/kb/search&q=office', $tok );
t_eq( 200, $r['status'], 'quick answers still work with AI off' );
t_eq( 'Office hours', $r['json'][0]['title'], 'quick answer found' );
$r = t_http( 'POST', '/kb/' . $s['kb'] . '/used', $tok );
t_eq( 204, $r['status'], 'kb used' );
RarIaa\Settings::update( array( 'ai_enabled' => 1, 'service_enabled' => 0 ) );
$r = $draft();
t_eq( 'SERVICE_DISABLED', $r['json']['code'], 'kill switch' );
RarIaa\Settings::update( array( 'service_enabled' => 1 ) );
// BUSY: hold the only AI slot from here.
RarIaa\Lock::acquire( 'ai_slot_1', 0, 60 );
$r = $draft();
t_eq( 503, $r['status'], 'slot busy → 503' );
t_eq( 'BUSY', $r['json']['code'], 'BUSY' );
RarIaa\Lock::release( 'ai_slot_1' );
$r = $draft();
t_eq( 200, $r['status'], 'free slot works again' );
$res = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'cost_reservations' ) . " WHERE status = 'reserved'" );
t_eq( '0', (string) $res, 'no reservation left open' );
$u = RarIaa\Limits\Budget::today();
t_eq( 0, (int) $u->reserved_cost_usd_micros, 'reserved counter back to zero' );
t_ok( (int) $u->cost_usd_micros > 0, 'actual cost recorded' );
