<?php
/**
 * Google Gemini API generateContent (free tier or paid). Model entered by the admin
 * (default gemini-3.1-flash-lite).
 *
 * Free tier ("Unpaid Services"): Google may use and human-review inputs and outputs. The plugin
 * only sends masked text, and the admin must give explicit consent before a provider is marked free.
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

class GeminiProvider implements Provider {

	const BASE = 'https://generativelanguage.googleapis.com/v1beta/models/';

	private $key;
	private $model;

	public function __construct( $key, $model ) {
		$this->key   = (string) $key;
		$this->model = (string) $model;
	}

	public function id() {
		return 'gemini';
	}

	public function model() {
		return $this->model;
	}

	public function complete( array $prompt, array $opts ) {
		$body  = array(
			'systemInstruction' => array( 'parts' => array( array( 'text' => $prompt['system'] ) ) ),
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array( array( 'text' => $prompt['user'] ) ),
				),
			),
			'generationConfig'  => array(
				// Thinking models spend part of the output budget on reasoning; leave headroom.
				'maxOutputTokens'  => (int) $opts['max_tokens'] * 2,
				'responseMimeType' => 'application/json',
				'temperature'      => 0.4,
			),
		);
		$start = microtime( true );
		$res   = wp_remote_post(
			self::BASE . rawurlencode( $this->model ) . ':generateContent',
			array(
				'timeout' => (int) $opts['timeout'],
				'headers' => array(
					'x-goog-api-key' => $this->key,
					'Content-Type'   => 'application/json',
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
			$type = is_array( $json ) && isset( $json['error']['status'] ) && is_scalar( $json['error']['status'] ) ? (string) $json['error']['status'] : 'http_' . $code;
			return new \WP_Error( 'PROVIDER_ERROR', ProviderFactory::http_error_message( $code, $type ), array( 'status' => 502, 'provider_status' => $code, 'retryable' => $code >= 500, 'quota' => 429 === $code, 'latency_ms' => $ms ) );
		}
		if ( ! empty( $json['promptFeedback']['blockReason'] ) ) {
			return new \WP_Error( 'PROVIDER_BAD_OUTPUT', 'The AI provider refused the request (' . sanitize_key( (string) $json['promptFeedback']['blockReason'] ) . ').', array( 'status' => 502, 'latency_ms' => $ms ) );
		}
		$text = '';
		if ( isset( $json['candidates'][0]['content']['parts'] ) && is_array( $json['candidates'][0]['content']['parts'] ) ) {
			foreach ( $json['candidates'][0]['content']['parts'] as $part ) {
				if ( isset( $part['text'] ) && empty( $part['thought'] ) ) {
					$text .= (string) $part['text'];
				}
			}
		}
		$usage = isset( $json['usageMetadata'] ) && is_array( $json['usageMetadata'] ) ? $json['usageMetadata'] : array();
		return array(
			'text'          => $text,
			'input_tokens'  => isset( $usage['promptTokenCount'] ) ? (int) $usage['promptTokenCount'] : 0,
			// Thinking tokens are billed as output.
			'output_tokens' => ( isset( $usage['candidatesTokenCount'] ) ? (int) $usage['candidatesTokenCount'] : 0 ) + ( isset( $usage['thoughtsTokenCount'] ) ? (int) $usage['thoughtsTokenCount'] : 0 ),
			'model'         => isset( $json['modelVersion'] ) ? (string) $json['modelVersion'] : $this->model,
			'latency_ms'    => $ms,
		);
	}
}
