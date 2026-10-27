<?php
/** Providers: Gemini and OpenAI-compatible request/response mapping, provider chain and pricing (HTTP mocked in-process). */
use RarIaa\Providers\GeminiProvider;
use RarIaa\Providers\OpenaiProvider;
use RarIaa\Providers\ProviderFactory;
use RarIaa\Settings;

$captured = array();
$reply    = array();
$mock     = static function ( $pre, $args, $url ) use ( &$captured, &$reply ) {
	$captured = array( 'url' => $url, 'args' => $args, 'body' => json_decode( $args['body'], true ) );
	return array( 'headers' => array(), 'body' => wp_json_encode( $reply['body'] ), 'response' => array( 'code' => $reply['code'], 'message' => '' ), 'cookies' => array() );
};
add_filter( 'pre_http_request', $mock, 10, 3 );

// Gemini: request shape.
$reply = array( 'code' => 200, 'body' => array( 'candidates' => array( array( 'content' => array( 'parts' => array( array( 'text' => 'thinking…', 'thought' => true ), array( 'text' => '{"suggestions":[]}' ) ) ) ) ), 'usageMetadata' => array( 'promptTokenCount' => 120, 'candidatesTokenCount' => 30, 'thoughtsTokenCount' => 50 ), 'modelVersion' => 'gemini-3.1-flash-lite' ) );
$g     = new GeminiProvider( 'g-key', 'gemini-3.1-flash-lite' );
$r     = $g->complete( array( 'system' => 'SYS', 'user' => 'USER' ), array( 'max_tokens' => 500, 'timeout' => 10 ) );
t_eq( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.1-flash-lite:generateContent', $captured['url'], 'gemini endpoint' );
t_eq( 'g-key', $captured['args']['headers']['x-goog-api-key'], 'gemini key in header, not URL' );
t_ok( false === strpos( $captured['url'], 'g-key' ), 'key never in the URL' );
t_eq( 'SYS', $captured['body']['systemInstruction']['parts'][0]['text'], 'system instruction' );
t_eq( 'USER', $captured['body']['contents'][0]['parts'][0]['text'], 'user content' );
t_eq( 'application/json', $captured['body']['generationConfig']['responseMimeType'], 'JSON output requested' );
t_eq( '{"suggestions":[]}', $r['text'], 'thought parts are dropped' );
t_eq( 120, $r['input_tokens'], 'gemini input tokens' );
t_eq( 80, $r['output_tokens'], 'thinking tokens counted as output' );

// Gemini: quota error → not retryable, quota flag set (fallback trigger).
$reply = array( 'code' => 429, 'body' => array( 'error' => array( 'status' => 'RESOURCE_EXHAUSTED' ) ) );
$r     = $g->complete( array( 'system' => 'S', 'user' => 'U' ), array( 'max_tokens' => 10, 'timeout' => 5 ) );
t_ok( is_wp_error( $r ), 'quota error returned' );
t_eq( 'PROVIDER_ERROR', $r->get_error_code(), 'quota error code' );
t_ok( ! empty( $r->get_error_data()['quota'] ) && empty( $r->get_error_data()['retryable'] ), 'quota flagged, not retried' );
// Blocked prompt.
$reply = array( 'code' => 200, 'body' => array( 'promptFeedback' => array( 'blockReason' => 'SAFETY' ) ) );
$r     = $g->complete( array( 'system' => 'S', 'user' => 'U' ), array( 'max_tokens' => 10, 'timeout' => 5 ) );
t_eq( 'PROVIDER_BAD_OUTPUT', is_wp_error( $r ) ? $r->get_error_code() : '', 'blocked prompt → bad output' );

// OpenAI-compatible (Groq/OpenRouter): base URL, max_tokens, no response_format.
$reply = array( 'code' => 200, 'body' => array( 'choices' => array( array( 'message' => array( 'content' => 'OK' ) ) ), 'usage' => array( 'prompt_tokens' => 9, 'completion_tokens' => 1 ), 'model' => 'llama-x' ) );
$c     = new OpenaiProvider( 'c-key', 'llama-x', 'https://api.groq.com/openai/v1/' );
$r     = $c->complete( array( 'system' => 'S', 'user' => 'U' ), array( 'max_tokens' => 64, 'timeout' => 5 ) );
t_eq( 'https://api.groq.com/openai/v1/chat/completions', $captured['url'], 'compat endpoint' );
t_eq( 'compat', $c->id(), 'compat id' );
t_eq( 64, $captured['body']['max_tokens'], 'compat uses max_tokens' );
t_ok( ! isset( $captured['body']['response_format'] ), 'compat sends no response_format' );
t_eq( 'Bearer c-key', $captured['args']['headers']['Authorization'], 'compat bearer key' );
$o = new OpenaiProvider( 'o-key', 'gpt-x' );
$o->complete( array( 'system' => 'S', 'user' => 'U' ), array( 'max_tokens' => 64, 'timeout' => 5 ) );
t_eq( 'https://api.openai.com/v1/chat/completions', $captured['url'], 'openai endpoint unchanged' );
t_eq( 'json_object', $captured['body']['response_format']['type'], 'openai keeps JSON mode' );
remove_filter( 'pre_http_request', $mock, 10 );

// Settings: providers, free tier needs consent, fallback must differ.
$saved = Settings::all();
t_ok( isset( Settings::PROVIDERS['gemini'], Settings::PROVIDERS['compat'] ), 'gemini and compat registered' );
$e = Settings::update( array( 'free_providers' => array( 'gemini' ), 'free_tier_consent' => 0 ) );
t_ok( isset( $e['free_tier_consent'] ), 'free tier without consent refused' );
$e = Settings::update( array( 'free_providers' => array( 'anthropic' ), 'free_tier_consent' => 1 ) );
t_eq( array(), (array) Settings::get( 'free_providers' ), 'anthropic cannot be marked free' );
$e = Settings::update( array( 'provider' => 'gemini', 'fallback_provider' => 'gemini' ) );
t_ok( isset( $e['fallback_provider'] ), 'fallback must differ from primary' );
$e = Settings::update( array( 'compat_base_url' => 'http://insecure.example/v1' ) );
t_ok( isset( $e['compat_base_url'] ), 'compat base URL must be https' );
$e = Settings::update( array( 'compat_model' => 'meta-llama/llama-3.3-70b-instruct:free' ) );
t_eq( array(), $e, 'OpenRouter-style model id accepted' );
Settings::update( array( 'provider' => 'gemini', 'fallback_provider' => 'anthropic', 'free_providers' => array( 'gemini' ), 'free_tier_consent' => 1, 'prices' => array( 'anthropic' => array( 'in' => 0.1, 'out' => 0.5 ), 'gemini' => array( 'in' => 0.25, 'out' => 1.5 ) ) ) );
t_ok( Settings::is_free( 'gemini' ), 'gemini free with consent' );
t_eq( array( 0.1, 0.5 ), Settings::provider_price( 'anthropic' ), 'per-provider price' );

// Chain (stub mode in tests): two stubs, free primary costs 0, fallback paid.
$chain = ProviderFactory::chain();
t_eq( 2, is_array( $chain ) ? count( $chain ) : 0, 'primary + fallback chain' );
t_eq( 'gemini', ProviderFactory::settings_id( $chain[0] ), 'stub maps to primary id' );
t_eq( 'anthropic', ProviderFactory::settings_id( $chain[1] ), 'stub maps to fallback id' );
t_eq( array( 0.0, 0.0 ), ProviderFactory::price( $chain[0] ), 'free primary costs 0' );
t_eq( array( 0.1, 0.5 ), ProviderFactory::price( $chain[1] ), 'fallback uses its own price' );
// Legacy 0.1.0 price pair still applies to the then-selected provider.
update_option( Settings::OPTION, array_merge( $saved, array( 'provider' => 'anthropic', 'prices' => array(), 'price_in_usd_per_mtok' => 1, 'price_out_usd_per_mtok' => 5 ) ), false );
Settings::flush();
t_eq( array( 1.0, 5.0 ), Settings::provider_price( 'anthropic' ), 'legacy price pair read for 0.1.0 provider' );
update_option( Settings::OPTION, $saved, false );
Settings::flush();
