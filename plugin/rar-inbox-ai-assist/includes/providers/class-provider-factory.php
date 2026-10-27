<?php
/**
 * Chooses the configured provider.
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class ProviderFactory {

	/** @return Provider|\WP_Error */
	public static function make() {
		if ( defined( 'RAR_IAA_PROVIDER' ) && 'stub' === RAR_IAA_PROVIDER && StubProvider::allowed() ) {
			return new StubProvider();
		}
		$provider = (string) Settings::get( 'provider' );
		$key      = Settings::api_key( $provider );
		if ( '' === $key ) {
			return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'AI provider key is not set.', array( 'status' => 503 ) );
		}
		if ( 'openai' === $provider ) {
			$model = (string) Settings::get( 'openai_model' );
			if ( '' === $model ) {
				return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'OpenAI model is not set.', array( 'status' => 503 ) );
			}
			return new OpenaiProvider( $key, $model );
		}
		return new AnthropicProvider( $key, (string) Settings::get( 'anthropic_model' ) );
	}

	/** True when token prices are set (required for the budget reservation) or the stub is active. */
	public static function prices_configured( Provider $p ) {
		if ( 'stub' === $p->id() ) {
			return true;
		}
		return (float) Settings::get( 'price_in_usd_per_mtok' ) > 0 && (float) Settings::get( 'price_out_usd_per_mtok' ) > 0;
	}
}
