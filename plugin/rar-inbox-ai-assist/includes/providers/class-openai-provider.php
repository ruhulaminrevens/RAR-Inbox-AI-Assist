<?php
/**
 * OpenAI Chat Completions (TRD §8). Model entered by the admin.
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

class OpenaiProvider implements Provider {

	const URL = 'https://api.openai.com/v1/chat/completions';

	private $key;
	private $model;

	public function __construct( $key, $model ) {
		$this->key   = (string) $key;
		$this->model = (string) $model;
	}

	public function id() {
		return 'openai';
	}

	public function model() {
		return $this->model;
	}

	public function complete( array $prompt, array $opts ) {
		$body  = array(
			'model'                 => $this->model,
			'max_completion_tokens' => (int) $opts['max_tokens'],
			'response_format'       => array( 'type' => 'json_object' ),
			'messages'              => array(
				array( 'role' => 'system', 'content' => $prompt['system'] ),
				array( 'role' => 'user', 'content' => $prompt['user'] ),
			),
		);
		$start = microtime( true );
		$res   = wp_remote_post(
			self::URL,
			array(
				'timeout' => (int) $opts['timeout'],
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);
		$ms    = (int) round( ( microtime( true ) - $start ) * 1000 );
		if ( is_wp_error( $res ) ) {
			$timeout = false !== stripos( $res->get_error_message(), 'time' );
			return new \WP_Error( $timeout ? 'PROVIDER_TIMEOUT' : 'PROVIDER_ERROR', $timeout ? 'AI provider timed out.' : 'Could not reach the AI provider.', array( 'status' => $timeout ? 504 : 502, 'retryable' => true, 'latency_ms' => $ms ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$type = is_array( $json ) && isset( $json['error']['code'] ) ? (string) $json['error']['code'] : 'http_' . $code;
			return new \WP_Error( 'PROVIDER_ERROR', 'AI provider error: ' . $type, array( 'status' => 502, 'provider_status' => $code, 'retryable' => $code >= 500, 'latency_ms' => $ms ) );
		}
		return array(
			'text'          => isset( $json['choices'][0]['message']['content'] ) ? (string) $json['choices'][0]['message']['content'] : '',
			'input_tokens'  => isset( $json['usage']['prompt_tokens'] ) ? (int) $json['usage']['prompt_tokens'] : 0,
			'output_tokens' => isset( $json['usage']['completion_tokens'] ) ? (int) $json['usage']['completion_tokens'] : 0,
			'model'         => isset( $json['model'] ) ? (string) $json['model'] : $this->model,
			'latency_ms'    => $ms,
		);
	}
}
