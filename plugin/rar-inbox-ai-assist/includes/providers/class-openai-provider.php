<?php
/**
 * OpenAI Chat Completions (TRD §8). Model entered by the admin.
 *
 * Also used for OpenAI-compatible APIs (Groq, OpenRouter, …) with a custom base URL: those get
 * `max_tokens` and no `response_format`, which not every compatible model supports.
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

class OpenaiProvider implements Provider {

	const URL = 'https://api.openai.com/v1/chat/completions';

	private $key;
	private $model;
	private $url;
	private $id;

	/**
	 * @param string      $key      API key.
	 * @param string      $model    Model id.
	 * @param string|null $base_url OpenAI-compatible base URL (…/v1); null = OpenAI itself.
	 */
	public function __construct( $key, $model, $base_url = null ) {
		$this->key   = (string) $key;
		$this->model = (string) $model;
		$this->url   = null === $base_url ? self::URL : rtrim( (string) $base_url, '/' ) . '/chat/completions';
		$this->id    = null === $base_url ? 'openai' : 'compat';
	}

	public function id() {
		return $this->id;
	}

	public function model() {
		return $this->model;
	}

	public function complete( array $prompt, array $opts ) {
		$body = array(
			'model'    => $this->model,
			'messages' => array(
				array( 'role' => 'system', 'content' => $prompt['system'] ),
				array( 'role' => 'user', 'content' => $prompt['user'] ),
			),
		);
		if ( 'openai' === $this->id ) {
			$body['max_completion_tokens'] = (int) $opts['max_tokens'];
			$body['response_format']       = array( 'type' => 'json_object' );
		} else {
			$body['max_tokens'] = (int) $opts['max_tokens'];
		}
		$start = microtime( true );
		$res   = wp_remote_post(
			$this->url,
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
			return new \WP_Error( $timeout ? 'PROVIDER_TIMEOUT' : 'PROVIDER_ERROR', $timeout ? 'AI provider timed out (no answer in ' . (int) $opts['timeout'] . ' s).' : 'Could not reach the AI provider.', array( 'status' => $timeout ? 504 : 502, 'retryable' => true, 'latency_ms' => $ms ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$json = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			$type = is_array( $json ) && isset( $json['error']['code'] ) && is_scalar( $json['error']['code'] ) ? (string) $json['error']['code'] : 'http_' . $code;
			return new \WP_Error( 'PROVIDER_ERROR', ProviderFactory::http_error_message( $code, $type ), array( 'status' => 502, 'provider_status' => $code, 'retryable' => $code >= 500, 'quota' => 429 === $code, 'latency_ms' => $ms ) );
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
