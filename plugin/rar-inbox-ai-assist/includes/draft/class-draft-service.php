<?php
/**
 * Draft pipeline orchestration (TRD §6, v0.2 §19).
 *
 * Privacy: raw and unmasked texts exist only in local variables of this request. Nothing written
 * to the database, options, transients or logs contains them; masked text is written only when
 * the admin enables masked logging.
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Auth\RestAuth;
use RarIaa\Db;
use RarIaa\Domain\DraftLogRepository;
use RarIaa\Domain\KbRepository;
use RarIaa\Domain\ThreadLinkRepository;
use RarIaa\Limits\Budget;
use RarIaa\Limits\Concurrency;
use RarIaa\Limits\RateLimiter;
use RarIaa\Providers\ProviderFactory;
use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class DraftService {

	const MAX_MESSAGES   = 20;
	const MAX_MSG_CHARS  = 2000;
	const MAX_TOTAL      = 12000;
	const TIMEOUT        = 20;
	const IDEM_TTL       = 600;
	const REWRITE_MODES  = array( 'shorter', 'polite', 'formal', 'bn', 'en' );

	/** @var string */
	private $request_id;

	public function __construct( $request_id ) {
		$this->request_id = $request_id;
	}

	/* ================================================================== Public API */

	/**
	 * POST /drafts.
	 *
	 * @param int         $user_id
	 * @param array       $body
	 * @param string|null $idem       Idempotency-Key header.
	 * @param string|null $thread_raw X-IAA-Thread header.
	 * @return array|\WP_Error
	 */
	public function create( $user_id, array $body, $idem, $thread_raw ) {
		$gate = $this->gates();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$input = $this->validate_create( $body );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		$claim = $this->claim( $user_id, $idem );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		$result = $this->run_create( $user_id, $input, $thread_raw );
		$this->finish_claim( $user_id, $idem, $result );
		return $result;
	}

	/** POST /drafts/{id}/rewrite. */
	public function rewrite( $user_id, $draft_uuid, array $body, $idem ) {
		$gate = $this->gates();
		if ( is_wp_error( $gate ) ) {
			return $gate;
		}
		$parent = DraftLogRepository::find_owned( $draft_uuid, $user_id );
		if ( ! $parent || 'ok' !== $parent->status ) {
			return new \WP_Error( 'NOT_FOUND', 'Draft not found.', array( 'status' => 404 ) );
		}
		$fields = array();
		$mode   = isset( $body['mode'] ) ? (string) $body['mode'] : '';
		if ( ! in_array( $mode, self::REWRITE_MODES, true ) ) {
			$fields['mode'] = 'Use shorter, polite, formal, bn or en.';
		}
		$text = isset( $body['current_text'] ) && is_string( $body['current_text'] ) ? trim( $body['current_text'] ) : '';
		if ( '' === $text || Util::strlen( $text ) > self::MAX_MSG_CHARS ) {
			$fields['current_text'] = '1–2000 characters.';
		}
		$variant = isset( $body['variant_id'] ) ? (string) $body['variant_id'] : '';
		if ( ! in_array( $variant, PromptBuilder::variant_ids( 4 ), true ) ) {
			$fields['variant_id'] = 'Unknown variant.';
		}
		$extra = array_diff( array_keys( $body ), array( 'mode', 'current_text', 'variant_id' ) );
		if ( $extra ) {
			$fields['_'] = 'Unknown fields: ' . implode( ', ', $extra );
		}
		if ( $fields ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Invalid request.', array( 'status' => 422, 'fields' => $fields ) );
		}
		$claim = $this->claim( $user_id, $idem );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		$result = $this->run_rewrite( $user_id, $parent, $mode, $text, $variant );
		$this->finish_claim( $user_id, $idem, $result );
		return $result;
	}

	/** POST /drafts/{id}/outcome. */
	public static function outcome( $user_id, $draft_uuid, array $body ) {
		$fields  = array();
		$outcome = isset( $body['outcome'] ) ? (string) $body['outcome'] : '';
		if ( ! in_array( $outcome, DraftLogRepository::OUTCOMES, true ) ) {
			$fields['outcome'] = 'Invalid outcome.';
		}
		$variant = isset( $body['selected_variant_id'] ) && null !== $body['selected_variant_id'] ? (string) $body['selected_variant_id'] : '';
		if ( '' !== $variant && ! in_array( $variant, PromptBuilder::variant_ids( 4 ), true ) ) {
			$fields['selected_variant_id'] = 'Unknown variant.';
		}
		if ( 'rejected' !== $outcome && '' === $variant ) {
			$fields['selected_variant_id'] = 'Required unless rejected.';
		}
		$reason = isset( $body['reject_reason'] ) && null !== $body['reject_reason'] ? (string) $body['reject_reason'] : '';
		if ( '' !== $reason && ! in_array( $reason, DraftLogRepository::REJECT_REASONS, true ) ) {
			$fields['reject_reason'] = 'Invalid reason.';
		}
		$dist = isset( $body['edit_distance'] ) ? $body['edit_distance'] : 0;
		if ( ! is_int( $dist ) || $dist < 0 || $dist > 2000 ) {
			$fields['edit_distance'] = '0–2000.';
		}
		$extra = array_diff( array_keys( $body ), array( 'outcome', 'selected_variant_id', 'reject_reason', 'insert_failed', 'edit_distance' ) );
		if ( $extra ) {
			$fields['_'] = 'Unknown fields: ' . implode( ', ', $extra );
		}
		if ( $fields ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Invalid request.', array( 'status' => 422, 'fields' => $fields ) );
		}
		return DraftLogRepository::set_outcome(
			$draft_uuid,
			$user_id,
			array(
				'outcome'             => $outcome,
				'selected_variant_id' => $variant,
				'reject_reason'       => 'rejected' === $outcome ? $reason : '',
				'insert_failed'       => ! empty( $body['insert_failed'] ),
				'edit_distance'       => (int) $dist,
			)
		);
	}

	/* ================================================================== Steps */

	private function gates() {
		if ( ! Settings::service_enabled() ) {
			return new \WP_Error( 'SERVICE_DISABLED', 'Service is turned off by the administrator.', array( 'status' => 503 ) );
		}
		if ( ! Settings::get( 'ai_enabled' ) ) {
			return new \WP_Error( 'AI_DISABLED', 'AI suggestions are turned off. Quick answers still work.', array( 'status' => 503 ) );
		}
		return true;
	}

	private function validate_create( array $body ) {
		$fields  = array();
		$allowed = array( 'source', 'messages', 'options', 'adapter', 'customer_display_name' );
		$extra   = array_diff( array_keys( $body ), $allowed );
		if ( $extra ) {
			$fields['_'] = 'Unknown fields: ' . implode( ', ', $extra );
		}
		$source = isset( $body['source'] ) ? (string) $body['source'] : 'paste';
		if ( ! in_array( $source, array( 'capture', 'selection', 'paste' ), true ) ) {
			$fields['source'] = 'Use capture, selection or paste.';
		}
		$msgs = isset( $body['messages'] ) && is_array( $body['messages'] ) ? array_values( $body['messages'] ) : array();
		if ( count( $msgs ) < 1 || count( $msgs ) > self::MAX_MESSAGES ) {
			$fields['messages'] = '1–20 messages.';
		}
		$clean    = array();
		$customer = 0;
		foreach ( $msgs as $i => $m ) {
			if ( ! is_array( $m ) || ! isset( $m['side'], $m['text'] ) || ! in_array( $m['side'], array( 'customer', 'business' ), true ) || ! is_string( $m['text'] ) ) {
				$fields[ 'messages.' . $i ] = 'Each message needs side (customer|business) and text.';
				continue;
			}
			$extra_m = array_diff( array_keys( $m ), array( 'side', 'text', 'at' ) );
			if ( $extra_m ) {
				$fields[ 'messages.' . $i ] = 'Unknown fields: ' . implode( ', ', $extra_m );
				continue;
			}
			$text = trim( str_replace( "\0", '', $m['text'] ) );
			if ( '' === $text || Util::strlen( $text ) > self::MAX_MSG_CHARS ) {
				$fields[ 'messages.' . $i ] = 'Text must be 1–2000 characters.';
				continue;
			}
			if ( isset( $m['at'] ) && null !== $m['at'] && ( ! is_string( $m['at'] ) || false === strtotime( $m['at'] ) ) ) {
				$fields[ 'messages.' . $i ] = 'at must be an ISO 8601 time or null.';
				continue;
			}
			if ( 'customer' === $m['side'] ) {
				$customer++;
			}
			$clean[] = array( 'side' => $m['side'], 'text' => $text );
		}
		if ( $msgs && ! $customer && empty( $fields ) ) {
			$fields['messages'] = 'At least one customer message is needed.';
		}
		$lang = isset( $body['options']['language'] ) ? (string) $body['options']['language'] : 'auto';
		if ( ! in_array( $lang, array( 'auto', 'bn', 'en' ), true ) ) {
			$fields['options.language'] = 'Use auto, bn or en.';
		}
		$name = isset( $body['customer_display_name'] ) && is_string( $body['customer_display_name'] ) ? trim( $body['customer_display_name'] ) : '';
		if ( Util::strlen( $name ) > 100 ) {
			$fields['customer_display_name'] = 'Max 100 characters.';
		}
		$adapter = array( 'version' => null, 'strategy' => null );
		if ( isset( $body['adapter'] ) && is_array( $body['adapter'] ) ) {
			$adapter['version']  = isset( $body['adapter']['version'] ) ? substr( sanitize_text_field( (string) $body['adapter']['version'] ), 0, 30 ) : null;
			$adapter['strategy'] = isset( $body['adapter']['strategy'] ) ? substr( sanitize_text_field( (string) $body['adapter']['strategy'] ), 0, 20 ) : null;
		}
		if ( $fields ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Invalid request.', array( 'status' => 422, 'fields' => $fields ) );
		}
		// Trim oldest messages beyond the total budget.
		$trimmed = 0;
		while ( count( $clean ) > 1 && array_sum( array_map( static function ( $m ) { return Util::strlen( $m['text'] ); }, $clean ) ) > self::MAX_TOTAL ) {
			array_shift( $clean );
			$trimmed++;
		}
		return array( 'source' => $source, 'messages' => $clean, 'language' => $lang, 'name' => $name, 'adapter' => $adapter, 'trimmed' => $trimmed );
	}

	/** Atomic idempotency claim: metadata only (key hash, status, draft id), never response text. */
	private function claim( $user_id, $idem ) {
		global $wpdb;
		if ( ! Util::is_uuid( (string) $idem ) ) {
			return new \WP_Error( 'VALIDATION_FAILED', 'Idempotency-Key header (UUID v4) is required.', array( 'status' => 422, 'fields' => array( 'Idempotency-Key' => 'Required UUID v4.' ) ) );
		}
		$hash  = hash( 'sha256', $user_id . ':' . $idem );
		$table = Db::t( 'rate_buckets' );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE bucket_key = %s AND expires_at < %s", 'idem:' . substr( $hash, 0, 60 ), Db::now() ) );
		$suppress = $wpdb->suppress_errors( true );
		$ok       = $wpdb->query( $wpdb->prepare( "INSERT INTO {$table} (bucket_key, count, expires_at) VALUES (%s, 1, %s)", 'idem:' . substr( $hash, 0, 60 ), Db::now( self::IDEM_TTL ) ) );
		$wpdb->suppress_errors( $suppress );
		if ( $ok ) {
			return true;
		}
		$meta = get_transient( 'rar_iaa_idem_' . substr( $hash, 0, 32 ) );
		if ( is_array( $meta ) && 'done' === $meta['status'] ) {
			return new \WP_Error( 'REQUEST_ALREADY_PROCESSED', 'This request was already processed. Make a new request.', array( 'status' => 409, 'draft_id' => $meta['draft_id'] ) );
		}
		return new \WP_Error( 'REQUEST_IN_PROGRESS', 'The same request is still being processed.', array( 'status' => 409, 'retry_after' => 3 ) );
	}

	private function finish_claim( $user_id, $idem, $result ) {
		global $wpdb;
		$hash = hash( 'sha256', $user_id . ':' . $idem );
		if ( is_wp_error( $result ) ) {
			// Nothing was produced: allow a retry with the same key (e.g. after BUSY).
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'rate_buckets' ) . ' WHERE bucket_key = %s', 'idem:' . substr( $hash, 0, 60 ) ) );
			return;
		}
		set_transient( 'rar_iaa_idem_' . substr( $hash, 0, 32 ), array( 'status' => 'done', 'draft_id' => $result['draft_id'] ), self::IDEM_TTL );
	}

	private function limits_and_provider( $user_id ) {
		$wait = RateLimiter::hit( $user_id, 'drafts', (int) Settings::get( 'per_minute' ) );
		if ( $wait ) {
			return new \WP_Error( 'RATE_LIMITED', 'Too many requests. Wait a moment.', array( 'status' => 429, 'retry_after' => $wait ) );
		}
		return ProviderFactory::chain();
	}

	private function run_create( $user_id, array $in, $thread_raw ) {
		$t0       = microtime( true );
		$provider = $this->limits_and_provider( $user_id );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$key  = ThreadLinkRepository::parse_key( (string) $thread_raw );
		$hash = $key ? ThreadLinkRepository::hash( $key ) : null;
		$link = $hash ? ThreadLinkRepository::get( $hash ) : null;

		$masker   = new Masker();
		$names    = '' !== $in['name'] ? array( $in['name'] ) : array();
		$masked   = array();
		$customer = array();
		foreach ( $in['messages'] as $m ) {
			$masked[] = array( 'side' => $m['side'], 'text' => $masker->mask( $m['text'], $names ) );
			if ( 'customer' === $m['side'] ) {
				$customer[] = $m['text'];
			}
		}
		$last_customer = end( $customer );
		$recent_masked = array();
		foreach ( array_slice( array_reverse( $masked ), 0, 6 ) as $m ) {
			if ( 'customer' === $m['side'] ) {
				$recent_masked[] = $m['text'];
			}
		}
		$recent_text = implode( "\n", array_slice( $recent_masked, 0, 3 ) );

		$detected            = Language::detect( $last_customer );
		list( $lang, $latin ) = Language::reply_language( $detected, $in['language'], Settings::get( 'banglish_reply_script' ) );
		$greeted             = Language::greeted_salam( $last_customer );

		list( $facts, $meta ) = FactSelector::select( $recent_text, $link );
		$count                = (int) Settings::get( 'suggestion_count' );
		$expected             = PromptBuilder::variant_ids( $count );
		$prompt               = PromptBuilder::suggestions( $facts, $masked, $lang, $latin, Settings::get( 'reply_digits' ), $count );

		$call = $this->call_provider( $provider, $prompt, $expected, $meta['supplied'] );
		if ( is_wp_error( $call ) ) {
			$this->log_error( $user_id, $call, 'source', $in['source'], $hash, $meta, $provider, $t0 );
			return self::public_error( $call );
		}

		$rule_reason    = HandoffRules::match( implode( "\n", array_slice( $customer, -2 ) ) );
		$price_withheld = $meta['price_asked'] && null === $meta['price_list_id'] && empty( $facts['public_prices'] );
		$suggestions    = array();
		$summary        = array();
		foreach ( $call['items'] as $s ) {
			$out           = $this->finish_suggestion( $s, $masker, $facts, $customer, $greeted, $rule_reason, $price_withheld );
			$suggestions[] = $out;
			$summary[ $out['variant_id'] ] = array(
				'guard'    => $out['guard']['result'],
				'warnings' => count( $out['guard']['warnings'] ),
				'handoff'  => $out['handoff']['needed'] ? $out['handoff']['reason'] : null,
				'used'     => array_map( static function ( $f ) { return $f['type'] . ':' . $f['id']; }, $out['facts_used'] ),
			);
		}

		$draft_uuid = Util::uuid4();
		$this->log_ok( $user_id, $draft_uuid, null, $in['source'], null, $hash, $meta, $provider, $call, $suggestions, $summary, $lang, $masker, $masked, $in['adapter'], $t0, count( $expected ) );
		$used_kb = array();
		foreach ( $summary as $v ) {
			foreach ( $v['used'] as $u ) {
				if ( 0 === strpos( $u, 'kb:' ) ) {
					$used_kb[] = (int) substr( $u, 3 );
				}
			}
		}
		KbRepository::mark_used( array_unique( $used_kb ) );
		if ( $link ) {
			ThreadLinkRepository::touch( $hash );
		}
		$warnings = array();
		if ( $in['trimmed'] ) {
			$warnings[] = array( 'code' => 'INPUT_TRIMMED', 'value' => (string) $in['trimmed'] );
		}
		return $this->response( $draft_uuid, null, $suggestions, $link, $facts['audience'], (bool) $key, $in['trimmed'], $warnings, $user_id, $call );
	}

	private function run_rewrite( $user_id, $parent, $mode, $text, $variant ) {
		$t0       = microtime( true );
		$provider = $this->limits_and_provider( $user_id );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$link     = $parent->thread_hash ? ThreadLinkRepository::get( $parent->thread_hash ) : null;
		$supplied = json_decode( (string) $parent->facts_used, true );
		list( $facts, $meta, $changed ) = FactSelector::from_ids( is_array( $supplied ) ? $supplied : array(), $link );
		$masker   = new Masker();
		$masked   = $masker->mask( $text );
		$lang     = Language::detect( $text );
		list( $lang, $latin ) = Language::reply_language( $lang, 'auto', Settings::get( 'banglish_reply_script' ) );
		$prompt   = PromptBuilder::rewrite( $facts, $masked, $mode, $variant, $lang, $latin, Settings::get( 'reply_digits' ) );
		$call     = $this->call_provider( $provider, $prompt, array( $variant ), $meta['supplied'] );
		if ( is_wp_error( $call ) ) {
			$this->log_error( $user_id, $call, 'rewrite', $mode, $parent->thread_hash, $meta, $provider, $t0, (int) $parent->id );
			return self::public_error( $call );
		}
		$greeted     = Language::greeted_salam( $text );
		$out         = $this->finish_suggestion( $call['items'][0], $masker, $facts, array(), $greeted, null, false );
		$suggestions = array( $out );
		$summary     = array(
			$variant => array(
				'guard'    => $out['guard']['result'],
				'warnings' => count( $out['guard']['warnings'] ),
				'handoff'  => $out['handoff']['needed'] ? $out['handoff']['reason'] : null,
				'used'     => array_map( static function ( $f ) { return $f['type'] . ':' . $f['id']; }, $out['facts_used'] ),
			),
		);
		$draft_uuid = Util::uuid4();
		$this->log_ok( $user_id, $draft_uuid, (int) $parent->id, 'rewrite', $mode, $parent->thread_hash, $meta, $provider, $call, $suggestions, $summary, $out['language'], $masker, array( array( 'side' => 'business', 'text' => $masked ) ), array( 'version' => null, 'strategy' => null ), $t0, 1 );
		$warnings = array();
		if ( $changed ) {
			$warnings[] = array( 'code' => 'FACTS_CHANGED' );
		}
		$resp                    = $this->response( $draft_uuid, $parent->draft_uuid, $suggestions, $link, $facts['audience'], (bool) $parent->thread_hash, 0, $warnings, $user_id, $call );
		return $resp;
	}

	/**
	 * Walk the provider chain (primary, then fallback) until one returns valid suggestions.
	 * Fallback is tried for provider failures (quota/rate limit, outage, timeout, unusable output)
	 * when enough of the time budget is left; never for our own budget/daily limits.
	 * Returns array( items, input_tokens, output_tokens, model, provider, fallback_used, provider_ms, cost ).
	 */
	private function call_provider( array $chain, array $prompt, array $expected, array $supplied ) {
		$start  = microtime( true );
		$totals = array( 'in' => 0, 'out' => 0, 'ms' => 0, 'cost' => 0 );
		$error  = null;
		$last   = $chain[0];
		foreach ( $chain as $i => $provider ) {
			if ( $i > 0 && ( microtime( true ) - $start ) > self::TIMEOUT - 10 ) {
				break;
			}
			$last = $provider;
			$rid  = 0 === $i ? $this->request_id : Util::uuid4();
			$r    = $this->call_one( $provider, $rid, $prompt, $expected, $supplied, $start );
			$totals['in']   += $r['input_tokens'];
			$totals['out']  += $r['output_tokens'];
			$totals['ms']   += $r['provider_ms'];
			$totals['cost'] += $r['cost'];
			if ( null === $r['error'] ) {
				return array(
					'items'         => $r['items'],
					'input_tokens'  => $totals['in'],
					'output_tokens' => $totals['out'],
					'model'         => $r['model'],
					'provider'      => ProviderFactory::settings_id( $provider ),
					'fallback_used' => $i > 0,
					'provider_ms'   => $totals['ms'],
					'cost'          => $totals['cost'],
				);
			}
			$error = $r['error'];
			if ( in_array( $error->get_error_code(), array( 'BUDGET_EXCEEDED', 'DAILY_LIMIT', 'BUSY' ), true ) ) {
				break;
			}
		}
		$data = (array) $error->get_error_data();
		return new \WP_Error(
			$error->get_error_code(),
			$error->get_error_message(),
			array_merge(
				$data,
				array(
					'tokens_in'   => $totals['in'],
					'tokens_out'  => $totals['out'],
					'provider_ms' => $totals['ms'],
					'cost'        => $totals['cost'],
					'provider'    => ProviderFactory::settings_id( $last ),
					'model'       => $last->model(),
				)
			)
		);
	}

	/**
	 * One provider: reserve worst-case cost (the call plus one repair), take a concurrency slot,
	 * call (one retry for transient errors, one repair for invalid JSON), settle the budget.
	 */
	private function call_one( $provider, $reservation_id, array $prompt, array $expected, array $supplied, $start ) {
		$max_out            = (int) Settings::get( 'max_output_tokens' );
		$est_in             = Util::strlen( $prompt['system'] . $prompt['user'] ) + 200;
		list( $p_in, $p_out ) = ProviderFactory::price( $provider );
		$out                = array( 'items' => null, 'input_tokens' => 0, 'output_tokens' => 0, 'model' => $provider->model(), 'provider_ms' => 0, 'cost' => 0, 'error' => null );
		$reserve            = 2 * ( Budget::micros( $est_in, $p_in ) + Budget::micros( $max_out, $p_out ) );
		$res                = Budget::reserve( $reservation_id, $reserve );
		if ( is_wp_error( $res ) ) {
			$out['error'] = $res;
			return $out;
		}
		$slot = Concurrency::acquire();
		if ( false === $slot ) {
			Budget::settle( $reservation_id, 0, 0, 0, true );
			$out['error'] = new \WP_Error( 'BUSY', 'Server busy. Try again in a few seconds.', array( 'status' => 503, 'retry_after' => 3 ) );
			return $out;
		}
		$in_tok  = 0;
		$out_tok = 0;
		$ms      = 0;
		$error   = null;
		$items   = null;
		$model   = $provider->model();
		$actual  = 0;
		try {
			$attempts = 0;
			$current  = $prompt;
			$repaired = false;
			$first    = microtime( true );
			while ( true ) {
				$attempts++;
				$remaining = self::TIMEOUT - (int) ( microtime( true ) - $start );
				$r         = $provider->complete( $current, array( 'max_tokens' => $max_out, 'timeout' => max( 5, $remaining ) ) );
				if ( is_wp_error( $r ) ) {
					$data = $r->get_error_data();
					$ms  += isset( $data['latency_ms'] ) ? (int) $data['latency_ms'] : 0;
					if ( ! empty( $data['retryable'] ) && $attempts < 2 && ( microtime( true ) - $first ) < 8 ) {
						continue;
					}
					$error = $r;
					break;
				}
				$in_tok  += $r['input_tokens'];
				$out_tok += $r['output_tokens'];
				$ms      += $r['latency_ms'];
				$model    = $r['model'];
				$items    = ResponseParser::parse( $r['text'], $expected, $supplied );
				if ( null !== $items ) {
					break;
				}
				if ( $repaired ) {
					$error = new \WP_Error( 'PROVIDER_BAD_OUTPUT', 'The AI answer was not in the required format.', array( 'status' => 502 ) );
					break;
				}
				$repaired = true;
				$current  = array(
					'system' => $prompt['system'],
					'user'   => $prompt['user'] . "\n\nYour previous answer was invalid. It must be ONLY the JSON object with exactly " . count( $expected ) . ' suggestions with variant_id values: ' . implode( ', ', $expected ) . '.',
				);
			}
		} finally {
			Concurrency::release( $slot );
			$actual = Budget::micros( $in_tok, $p_in ) + Budget::micros( $out_tok, $p_out );
			Budget::settle( $reservation_id, $in_tok, $out_tok, $actual, null !== $error );
		}
		return array( 'items' => $items, 'input_tokens' => $in_tok, 'output_tokens' => $out_tok, 'model' => $model, 'provider_ms' => $ms, 'cost' => $actual, 'error' => $error );
	}

	/** Unmask, guard, handoff and label one suggestion. */
	private function finish_suggestion( array $s, Masker $masker, array $facts, array $customer, $greeted, $rule_reason, $price_withheld ) {
		$reply = $masker->unmask( $s['reply'] );
		$guard = FactGuard::check( $reply, $facts, $customer );
		if ( 'blocked' === $guard['result'] ) {
			$reply = HoldingReply::text( $s['language'], $greeted );
		}
		$handoff = HandoffRules::decide( $rule_reason, $s['needs_human'], $s['handoff_reason'], $price_withheld );
		return array(
			'variant_id' => $s['variant_id'],
			'reply'      => $reply,
			'language'   => $s['language'],
			'guard'      => $guard,
			'handoff'    => $handoff,
			'facts_used' => 'blocked' === $guard['result'] ? array() : $this->labels( $s['facts_used'], $facts ),
		);
	}

	private function labels( array $ids, array $facts ) {
		$index = array();
		foreach ( $facts['kb'] as $k ) {
			$index[ $k['id'] ] = $k['title'];
		}
		foreach ( $facts['products'] as $p ) {
			$index[ $p['id'] ] = $p['product'] . ( isset( $p['price_display'] ) ? ' — ' . $p['price_display'] . '/' . $p['unit'] : '' );
		}
		foreach ( $facts['public_prices'] as $p ) {
			$index[ $p['id'] ] = $p['product'] . ' — MRP ' . $p['mrp_display'] . '/' . $p['unit'];
		}
		$out = array();
		foreach ( $ids as $id ) {
			if ( isset( $index[ $id ] ) ) {
				list( $type, $num ) = explode( ':', $id );
				$out[]              = array( 'type' => $type, 'id' => (int) $num, 'label' => $index[ $id ] );
			}
		}
		return $out;
	}

	private function response( $draft_uuid, $parent_uuid, array $suggestions, $link, $audience, $bound, $trimmed, array $warnings, $user_id, array $call = array() ) {
		$today = Budget::today();
		$out   = array(
			'draft_id'           => $draft_uuid,
			'request_id'         => $this->request_id,
			'suggestions'        => $suggestions,
			'default_variant_id' => null,
			'audience'           => $audience,
			'dealer'             => ( $link && 'dealer' === $audience ) ? array( 'id' => (int) $link->dealer_id, 'code' => $link->dealer_code, 'name' => $link->dealer_name, 'tier' => $link->tier_code ) : null,
			'thread_bound'       => (bool) $bound,
			'trimmed'            => (int) $trimmed,
			'warnings'           => $warnings,
			'usage'              => array( 'today' => (int) $today->requests, 'daily_limit' => (int) Settings::get( 'daily_limit' ) ),
		);
		if ( isset( $call['provider'] ) ) {
			// Which AI answered (no model internals, no cost): shown as a small note in the panel.
			$pid       = (string) $call['provider'];
			$out['ai'] = array(
				'provider' => $pid,
				'label'    => isset( Settings::PROVIDERS[ $pid ] ) ? Settings::PROVIDERS[ $pid ] : $pid,
				'free'     => Settings::is_free( $pid ),
				'fallback' => ! empty( $call['fallback_used'] ),
			);
		}
		if ( $parent_uuid ) {
			$out['parent_draft_id'] = $parent_uuid;
		}
		return $out;
	}

	/* ================================================================== Logging (metadata / masked only) */

	private function log_ok( $user_id, $uuid, $parent_id, $source, $mode, $hash, array $meta, $provider, array $call, array $suggestions, array $summary, $lang, Masker $masker, array $masked_input, array $adapter, $t0, $count ) {
		$worst    = 'pass';
		$warnings = 0;
		$handoff  = null;
		foreach ( $suggestions as $s ) {
			if ( 'blocked' === $s['guard']['result'] ) {
				$worst = 'blocked';
			} elseif ( 'warned' === $s['guard']['result'] && 'blocked' !== $worst ) {
				$worst = 'warned';
			}
			$warnings += count( $s['guard']['warnings'] );
			if ( $s['handoff']['needed'] && ! $handoff ) {
				$handoff = $s['handoff']['reason'];
			}
		}
		$masked_log = (bool) Settings::get( 'masked_logging' );
		$token      = RestAuth::token();
		DraftLogRepository::insert(
			array(
				'draft_uuid'            => $uuid,
				'parent_draft_id'       => $parent_id,
				'request_id'            => $this->request_id,
				'user_id'               => (int) $user_id,
				'token_id'              => $token ? (int) $token->id : null,
				'thread_hash'           => $hash,
				'dealer_id'             => $meta['dealer_id'],
				'tier_id'               => $meta['tier_id'],
				'price_list_id'         => $meta['price_list_id'],
				'source'                => $source,
				'rewrite_mode'          => $mode,
				'language'              => $lang,
				'provider'              => substr( (string) $call['provider'], 0, 20 ),
				'model'                 => substr( (string) $call['model'], 0, 60 ),
				'prompt_version'        => PromptBuilder::PROMPT_VERSION,
				'status'                => 'ok',
				'guard_result'          => $worst,
				'warnings_count'        => $warnings,
				'handoff_needed'        => $handoff ? 1 : 0,
				'handoff_reason'        => $handoff,
				'facts_used'            => Util::json( array_values( $meta['supplied'] ) ),
				'suggestion_count'      => (int) $count,
				'variant_guard_summary' => Util::json( $summary ),
				'input_masked'          => $masked_log ? Util::json( $masked_input ) : null,
				'reply_masked'          => $masked_log ? Util::json( array_map( static function ( $s ) use ( $masker ) { return array( 'variant_id' => $s['variant_id'], 'reply' => $masker->remask( $s['reply'] ) ); }, $suggestions ) ) : null,
				'tokens_in'             => (int) $call['input_tokens'],
				'tokens_out'            => (int) $call['output_tokens'],
				'cost_usd_micros'       => (int) $call['cost'],
				'latency_ms'            => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
				'provider_latency_ms'   => (int) $call['provider_ms'],
				'adapter_version'       => $adapter['version'],
				'adapter_strategy'      => $adapter['strategy'],
			)
		);
	}

	private function log_error( $user_id, \WP_Error $e, $source, $mode_or_source, $hash, array $meta, $provider, $t0, $parent_id = null ) {
		$d     = (array) $e->get_error_data();
		$token = RestAuth::token();
		DraftLogRepository::insert(
			array(
				'draft_uuid'          => Util::uuid4(),
				'parent_draft_id'     => $parent_id,
				'request_id'          => $this->request_id,
				'user_id'             => (int) $user_id,
				'token_id'            => $token ? (int) $token->id : null,
				'thread_hash'         => $hash,
				'dealer_id'           => $meta['dealer_id'],
				'tier_id'             => $meta['tier_id'],
				'price_list_id'       => $meta['price_list_id'],
				'source'              => 'rewrite' === $source ? 'rewrite' : $mode_or_source,
				'rewrite_mode'        => 'rewrite' === $source ? $mode_or_source : null,
				'provider'            => substr( (string) ( isset( $e->get_error_data()['provider'] ) ? $e->get_error_data()['provider'] : ProviderFactory::settings_id( $provider[0] ) ), 0, 20 ),
				'model'               => substr( (string) ( isset( $e->get_error_data()['model'] ) ? $e->get_error_data()['model'] : $provider[0]->model() ), 0, 60 ),
				'prompt_version'      => PromptBuilder::PROMPT_VERSION,
				'status'              => 'error',
				'error_code'          => substr( $e->get_error_code(), 0, 40 ),
				'facts_used'          => Util::json( array_values( $meta['supplied'] ) ),
				'tokens_in'           => isset( $d['tokens_in'] ) ? (int) $d['tokens_in'] : 0,
				'tokens_out'          => isset( $d['tokens_out'] ) ? (int) $d['tokens_out'] : 0,
				'cost_usd_micros'     => isset( $d['cost'] ) ? (int) $d['cost'] : 0,
				'latency_ms'          => (int) round( ( microtime( true ) - $t0 ) * 1000 ),
				'provider_latency_ms' => isset( $d['provider_ms'] ) ? (int) $d['provider_ms'] : 0,
			)
		);
	}

	/** Client-safe copy of an error: drops usage and provider internals. */
	/** Error for the client: allowlisted data only (never tokens, cost, model, provider internals). */
	private static function public_error( \WP_Error $e ) {
		$d = array_intersect_key( (array) $e->get_error_data(), array_flip( array( 'status', 'retry_after', 'fields', 'draft_id', 'request_id', 'min_version' ) ) );
		return new \WP_Error( $e->get_error_code(), $e->get_error_message(), $d );
	}
}
