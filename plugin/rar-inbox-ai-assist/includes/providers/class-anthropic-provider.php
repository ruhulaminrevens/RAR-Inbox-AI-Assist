<?php
/**
 * Anthropic Messages API (TRD §8).
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

class AnthropicProvider implements Provider {

	const URL     = 'https://api.anthropic.com/v1/messages';
	const VERSION = '2023-06-01';

	private $key;
	private $model;

	public function __construct( $key, $model ) {
		$this->key   = (string) $key;
		$this->model = (string) $model;
	}

	public function id() {
		return 'anthropic';
	}

	public function model() {
		return $this->model;
	}

	public function complete( array $prompt, array $opts ) {
		$body  = array(
			'model'      => $this->model,
			'max_tokens' => (int) $opts['max_tokens'],
			'system'     => $prompt['system'],
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt['user'] ) ),
		);
		$start = microtime( true );
		$res   = wp_remote_post(
			self::URL,
			array(
				'timeout' => (int) $opts['timeout'],
				'headers' => array(
					'x-api-key'         => $this->key,
					'anthropic-version' => self::VERSION,
					'content-type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) {
			$timeout = false !== stripos( $res->get_error_message(), 'timed out' ) || false !== stripos( $res->get_error_message(), 'timeout' );
			return new \WP_Error( $timeout ? 'PROVIDER_TIMEOUT' : 'PROVIDER_ERROR', $timeout ? 'AI provider timed out (no answer in ' . (int) $opts['timeout'] . ' s).' : 'Could not reach the AI provider.', array( 'status' => $timeout ? 504 : 502, 'retryable' => true, 'latency_ms' => $ms ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$type = is_array( $json ) && isset( $json['error']['type'] ) ? (string) $json['error']['type'] : 'http_' . $code;
			return new \WP_Error( 'PROVIDER_ERROR', ProviderFactory::http_error_message( $code, $type ), array( 'status' => 502, 'provider_status' => $code, 'retryable' => $code >= 500 || 529 === $code, 'quota' => 429 === $code, 'latency_ms' => $ms ) );
		}
		$text = '';
		if ( is_array( $json ) && isset( $json['content'] ) && is_array( $json['content'] ) ) {
			foreach ( $json['content'] as $block ) {
				if ( isset( $block['type'], $block['text'] ) && 'text' === $block['type'] ) {
					$text .= $block['text'];
				}
			}
		}
		return array(
			'text'          => $text,
			'input_tokens'  => isset( $json['usage']['input_tokens'] ) ? (int) $json['usage']['input_tokens'] : 0,
			'output_tokens' => isset( $json['usage']['output_tokens'] ) ? (int) $json['usage']['output_tokens'] : 0,
			'model'         => isset( $json['model'] ) ? (string) $json['model'] : $this->model,
			'latency_ms'    => $ms,
		);
	}
}
