<?php
/** Provider chain over HTTP: free primary → paid fallback, costs, logging, no fallback for our own limits. */
global $wpdb;
t_reset();
$s    = t_seed();
$shop = t_user( 'shop_manager', 'iaa_shop_prov' );
$tok  = t_token( $shop );
$msg  = array( 'source' => 'paste', 'messages' => array( array( 'side' => 'customer', 'text' => 'Apnader office koyta porjonto khola?' ) ) );
$post = function () use ( $tok, $msg ) {
	return t_http( 'POST', '/drafts', $tok, $msg, array( 'Idempotency-Key' => t_uuid() ) );
};
$last_log = function () use ( $wpdb ) {
	return $wpdb->get_row( 'SELECT provider, model, cost_usd_micros, status, error_code FROM ' . RarIaa\Db::t( 'draft_log' ) . ' ORDER BY id DESC LIMIT 1' );
};
RarIaa\Settings::update(
	array(
		'per_minute'        => 60,
		'provider'          => 'gemini',
		'fallback_provider' => 'anthropic',
		'free_providers'    => array( 'gemini' ),
		'free_tier_consent' => 1,
		'prices'            => array( 'anthropic' => array( 'in' => 1, 'out' => 5 ) ),
	)
);
$ok = array( 'text' => wp_json_encode( array( 'suggestions' => t_sug( 'আমাদের অফিস সকাল ৯টা থেকে সন্ধ্যা ৬টা পর্যন্ত খোলা থাকে।', array( 'kb:' . $s['kb'] ) ) ), JSON_UNESCAPED_UNICODE ), 'input_tokens' => 1000, 'output_tokens' => 200 );

// 1. Free primary answers: cost 0, provider gemini.
t_stub_raw( array( $ok ) );
$r = $post();
t_eq( 200, $r['status'], 'free primary: 200' );
t_eq( 'gemini', $r['json']['ai']['provider'], 'answered by gemini' );
t_eq( true, $r['json']['ai']['free'], 'marked free' );
t_eq( false, $r['json']['ai']['fallback'], 'no fallback' );
t_ok( ! isset( $r['json']['ai']['model'] ) && ! isset( $r['json']['usage']['cost'] ), 'no model internals or cost in response' );
$log = $last_log();
t_eq( 'gemini', $log->provider, 'log: provider gemini' );
t_eq( 0, (int) $log->cost_usd_micros, 'log: free call costs 0' );

// 2. Free quota exhausted → paid fallback answers.
t_stub_raw( array( array( 'error' => 'PROVIDER_ERROR', 'quota' => 1 ), $ok ) );
$r = $post();
t_eq( 200, $r['status'], 'fallback: 200' );
t_eq( 'anthropic', $r['json']['ai']['provider'], 'answered by fallback' );
t_eq( true, $r['json']['ai']['fallback'], 'fallback flagged' );
t_eq( 'pass', $r['json']['suggestions'][0]['guard']['result'], 'fallback suggestion guarded' );
$log = $last_log();
t_eq( 'anthropic', $log->provider, 'log: fallback provider' );
t_eq( 'stub-fallback-1', $log->model, 'log: fallback model' );
t_eq( (int) round( 1000 * 1 + 200 * 5 ), (int) $log->cost_usd_micros, 'log: only the paid call is charged' );
$res = $wpdb->get_results( 'SELECT status FROM ' . RarIaa\Db::t( 'cost_reservations' ) . " WHERE status = 'reserved'" );
t_eq( 0, count( $res ), 'no reservation left open' );

// 3. Both fail → error from the last provider, logged without text.
t_stub_raw( array( array( 'error' => 'PROVIDER_ERROR', 'quota' => 1 ), array( 'error' => 'PROVIDER_ERROR' ) ) );
$r = $post();
t_eq( 'PROVIDER_ERROR', $r['json']['code'], 'both failed: provider error' );
t_ok( ! isset( $r['json']['data']['tokens_in'] ) && ! isset( $r['json']['data']['cost'] ) && ! isset( $r['json']['data']['model'] ), 'error carries no internals' );
$log = $last_log();
t_eq( 'anthropic', $log->provider, 'log: last provider tried' );

// 4. No fallback configured → primary error returned.
RarIaa\Settings::update( array( 'fallback_provider' => '' ) );
t_stub_raw( array( array( 'error' => 'PROVIDER_ERROR', 'quota' => 1 ), $ok ) );
$r = $post();
t_eq( 'PROVIDER_ERROR', $r['json']['code'], 'no fallback: error' );
t_eq( 'gemini', $last_log()->provider, 'log: gemini' );
delete_option( 'rar_iaa_stub_next' );

// 5. Our own daily limit never triggers a fallback call.
RarIaa\Settings::update( array( 'fallback_provider' => 'anthropic', 'daily_limit' => 1 ) );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . RarIaa\Db::t( 'usage_daily' ) . ' SET requests = 5 WHERE day = %s', RarIaa\Db::dhaka_date() ) );
$before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'cost_reservations' ) );
$r      = $post();
t_eq( 'DAILY_LIMIT', $r['json']['code'], 'daily limit returned' );
t_eq( $before, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'cost_reservations' ) ), 'no fallback reservation after our own limit' );
RarIaa\Settings::update( array( 'daily_limit' => 300 ) );

// 6. Paid provider without prices is not usable; free tier without consent is not free.
RarIaa\Settings::update( array( 'provider' => 'anthropic', 'fallback_provider' => '', 'prices' => array() ) );
RarIaa\Settings::update( array( 'price_in_usd_per_mtok' => 0, 'price_out_usd_per_mtok' => 0 ) );
t_ok( false === RarIaa\Providers\ProviderFactory::prices_configured( new RarIaa\Providers\AnthropicProvider( 'k', 'm' ) ), 'paid provider without prices is not configured' );
RarIaa\Settings::update( array( 'free_providers' => array(), 'free_tier_consent' => 0 ) );
t_ok( ! RarIaa\Settings::is_free( 'gemini' ), 'not free without flag' );
