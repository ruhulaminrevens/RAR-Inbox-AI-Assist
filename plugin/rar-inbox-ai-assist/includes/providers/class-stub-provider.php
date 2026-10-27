<?php
/**
 * Test provider. Loads only when WP_ENVIRONMENT_TYPE is local or development (TRD §17).
 *
 * Builds suggestions from the facts in the prompt. A test may script the next response with the
 * option rar_iaa_stub_next = JSON {"text": "...", "delay_ms": 0, "input_tokens": 0, "output_tokens": 0}
 * (or a JSON list of such objects for consecutive calls); each entry is consumed once.
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

class StubProvider implements Provider {

	public static function allowed() {
		return function_exists( 'wp_get_environment_type' ) && in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
	}

	public function id() {
		return 'stub';
	}

	public function model() {
		return 'stub-1';
	}

	public function complete( array $prompt, array $opts ) {
		if ( ! self::allowed() ) {
			return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'Stub provider is disabled in production.', array( 'status' => 503 ) );
		}
		$script = get_option( 'rar_iaa_stub_next' );
		if ( $script ) {
			$s = json_decode( (string) $script, true );
			// A JSON list scripts several calls in order.
			if ( is_array( $s ) && isset( $s[0] ) ) {
				$next = array_slice( $s, 1 );
				$s    = $s[0];
				if ( $next ) {
					update_option( 'rar_iaa_stub_next', wp_json_encode( $next, JSON_UNESCAPED_UNICODE ), false );
				} else {
					delete_option( 'rar_iaa_stub_next' );
				}
			} else {
				delete_option( 'rar_iaa_stub_next' );
			}
			if ( ! empty( $s['delay_ms'] ) ) {
				usleep( (int) $s['delay_ms'] * 1000 );
			}
			if ( isset( $s['error'] ) ) {
				return new \WP_Error( (string) $s['error'], 'Scripted error', array( 'status' => 502, 'retryable' => ! empty( $s['retryable'] ) ) );
			}
			if ( isset( $s['text'] ) ) {
				return array(
					'text'          => (string) $s['text'],
					'input_tokens'  => isset( $s['input_tokens'] ) ? (int) $s['input_tokens'] : 100,
					'output_tokens' => isset( $s['output_tokens'] ) ? (int) $s['output_tokens'] : 100,
					'model'         => 'stub-1',
					'latency_ms'    => isset( $s['delay_ms'] ) ? (int) $s['delay_ms'] : 1,
				);
			}
		}
		$facts = array();
		if ( preg_match( '/FACTS \(JSON\):\n(.+?)\n\n/s', $prompt['user'], $m ) ) {
			$facts = json_decode( $m[1], true );
		}
		$en     = false !== strpos( $prompt['system'], 'Reply language: English' );
		$answer = '';
		$used   = array();
		if ( ! empty( $facts['kb'] ) ) {
			foreach ( $facts['kb'] as $kb ) {
				if ( ! in_array( $kb['category'], array( 'greeting', 'contact' ), true ) ) {
					$answer = $en && ! empty( $kb['answer_en'] ) ? $kb['answer_en'] : $kb['answer_bn'];
					$used[] = $kb['id'];
					break;
				}
			}
		}
		// A price question answered from supplied product facts only (dealer tier price when linked).
		if ( '' === $answer && ! empty( $facts['products'] ) && preg_match( '/(dam|daam|দাম|price|rate|রেট|koto)/iu', $prompt['user'] ) ) {
			foreach ( $facts['products'] as $p ) {
				if ( isset( $p['price_bdt'] ) ) {
					$amount = isset( $p['price_display'] ) ? $p['price_display'] : '৳' . $p['price_bdt'];
					$answer = $en ? "{$p['product']}: {$amount} per {$p['unit']}." : "{$p['product']}: {$amount} প্রতি {$p['unit']}।";
					$used[] = $p['id'];
					break;
				}
			}
		}
		if ( '' === $answer ) {
			$answer = $en ? 'We will confirm and let you know shortly.' : 'আমরা নিশ্চিত করে জানাচ্ছি।';
		}
		$greet = ( false !== stripos( $prompt['user'], 'assalam' ) || false !== strpos( $prompt['user'], 'আসসালামু' ) ) ? ( $en ? 'Wa alaikum assalam. ' : 'ওয়ালাইকুম আসসালাম। ' ) : '';
		preg_match( '/Write exactly (\d) suggestions/', $prompt['system'], $cm );
		$ids = array( 'professional', 'friendly', 'short' );
		if ( isset( $cm[1] ) && '4' === $cm[1] ) {
			$ids[] = 'sales_focused';
		}
		if ( preg_match( '/"variant_id":"([a-z_]+)","reply":"…"/', $prompt['system'], $vm ) && false !== strpos( $prompt['system'], 'rewrite ONE reply' ) ) {
			$ids = array( $vm[1] );
		}
		$out = array();
		foreach ( $ids as $i => $id ) {
			$prefix = array( 'professional' => '', 'friendly' => ( $en ? 'Sure! ' : 'জি! ' ), 'short' => '', 'sales_focused' => '' );
			$out[]  = array(
				'variant_id'     => $id,
				'reply'          => $greet . $prefix[ $id ] . $answer,
				'language'       => $en ? 'en' : 'bn',
				'needs_human'    => false,
				'handoff_reason' => null,
				'facts_used'     => $used,
				'missing_info'   => array(),
			);
		}
		return array(
			'text'          => wp_json_encode( array( 'suggestions' => $out ), JSON_UNESCAPED_UNICODE ),
			'input_tokens'  => (int) ceil( strlen( $prompt['system'] . $prompt['user'] ) / 4 ),
			'output_tokens' => 120 * count( $ids ),
			'model'         => 'stub-1',
			'latency_ms'    => 1,
		);
	}
}
