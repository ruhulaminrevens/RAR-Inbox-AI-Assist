<?php
/**
 * Builds the configured provider chain: primary, then an optional fallback that is used when the
 * primary fails (quota/rate limit on a free tier, outage, timeout, unusable output).
 *
 * @package RarIaa
 */

namespace RarIaa\Providers;

use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class ProviderFactory {

	/** @return Provider|\WP_Error */
	public static function make( $id = null ) {
		if ( self::stub_mode() ) {
			return new StubProvider( null === $id ? 'stub' : 'stub:' . $id );
		}
		$id  = null === $id ? (string) Settings::get( 'provider' ) : (string) $id;
		$key = Settings::api_key( $id );
		if ( ! isset( Settings::PROVIDERS[ $id ] ) ) {
			return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'Unknown AI provider.', array( 'status' => 503 ) );
		}
		if ( '' === $key ) {
			return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', Settings::PROVIDERS[ $id ] . ': API key is not set.', array( 'status' => 503 ) );
		}
		switch ( $id ) {
			case 'openai':
				$model = (string) Settings::get( 'openai_model' );
				return '' === $model ? new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'OpenAI model is not set.', array( 'status' => 503 ) ) : new OpenaiProvider( $key, $model );
			case 'gemini':
				$model = (string) Settings::get( 'gemini_model' );
				return '' === $model ? new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'Gemini model is not set.', array( 'status' => 503 ) ) : new GeminiProvider( $key, $model );
			case 'compat':
				$model = (string) Settings::get( 'compat_model' );
				$base  = (string) Settings::get( 'compat_base_url' );
				if ( '' === $model || '' === $base ) {
					return new \WP_Error( 'PROVIDER_NOT_CONFIGURED', 'OpenAI-compatible provider: base URL and model are required.', array( 'status' => 503 ) );
				}
				return new OpenaiProvider( $key, $model, $base );
			default:
				return new AnthropicProvider( $key, (string) Settings::get( 'anthropic_model' ) );
		}
	}

	/**
	 * Usable providers in order (primary, fallback). Misconfigured ones are skipped; the error of the
	 * primary is returned when none is usable.
	 *
	 * @return Provider[]|\WP_Error
	 */
	public static function chain() {
		$ids      = array( (string) Settings::get( 'provider' ) );
		$fallback = (string) Settings::get( 'fallback_provider' );
		if ( '' !== $fallback && $fallback !== $ids[0] ) {
			$ids[] = $fallback;
		}
		$chain = array();
		$first = null;
		foreach ( $ids as $i => $id ) {
			$p = self::stub_mode() ? new StubProvider( 0 === $i ? 'stub' : 'stub:fallback' ) : self::make( $id );
			if ( is_wp_error( $p ) ) {
				$first = $first ? $first : $p;
				continue;
			}
			if ( ! self::prices_configured( $p ) ) {
				$first = $first ? $first : new \WP_Error( 'PROVIDER_NOT_CONFIGURED', Settings::PROVIDERS[ $id ] . ': token prices are not set (or mark it as free tier), so the budget cannot be enforced.', array( 'status' => 503 ) );
				continue;
			}
			$chain[] = $p;
		}
		return $chain ? $chain : $first;
	}

	/** Settings id behind a provider object (stub chain maps to the configured ids). */
	public static function settings_id( Provider $p ) {
		$id = $p->id();
		if ( 'stub' === $id ) {
			return (string) Settings::get( 'provider' );
		}
		if ( 'stub:fallback' === $id ) {
			return (string) Settings::get( 'fallback_provider' );
		}
		return $id;
	}

	/** USD per million tokens (in, out) for a provider; free tier and the stub cost 0. */
	public static function price( Provider $p ) {
		if ( 0 === strpos( $p->id(), 'stub' ) ) {
			$id = self::settings_id( $p );
			return Settings::is_free( $id ) ? array( 0.0, 0.0 ) : Settings::provider_price( $id );
		}
		return Settings::is_free( $p->id() ) ? array( 0.0, 0.0 ) : Settings::provider_price( $p->id() );
	}

	/** True when the budget can be enforced: prices set, or a consented free tier, or the stub. */
	public static function prices_configured( Provider $p ) {
		if ( 0 === strpos( $p->id(), 'stub' ) ) {
			return true;
		}
		if ( Settings::is_free( $p->id() ) ) {
			return true;
		}
		list( $in, $out ) = Settings::provider_price( $p->id() );
		return $in > 0 && $out > 0;
	}

	private static function stub_mode() {
		return defined( 'RAR_IAA_PROVIDER' ) && 'stub' === RAR_IAA_PROVIDER && StubProvider::allowed();
	}
}
