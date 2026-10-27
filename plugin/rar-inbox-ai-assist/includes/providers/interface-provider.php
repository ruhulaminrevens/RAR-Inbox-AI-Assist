<?php
/**
 * Provider contract (TRD §6 step 11).
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

defined( 'ABSPATH' ) || exit;

interface Provider {

	/**
	 * Run one completion.
	 *
	 * @param array $prompt array( 'system' => string, 'user' => string ).
	 * @param array $opts   array( 'max_tokens' => int, 'timeout' => int seconds ).
	 * @return array|\WP_Error array( 'text', 'input_tokens', 'output_tokens', 'model', 'latency_ms' )
	 */
	public function complete( array $prompt, array $opts );

	/** Provider id: anthropic | openai | stub. */
	public function id();

	/** Model id used. */
	public function model();
}
