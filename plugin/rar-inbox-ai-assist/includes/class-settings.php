<?php
/**
 * Typed settings with defaults, validation and provider-key storage.
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Settings {

	const OPTION = 'rar_iaa_settings';

	/** @var array|null */
	private static $cache = null;

	public static function defaults() {
		return array(
			'service_enabled'          => 1,
			'ai_enabled'               => 1,
			'provider'                 => 'anthropic',
			'anthropic_model'          => 'claude-haiku-5-5',
			'openai_model'             => '',
			'price_in_usd_per_mtok'    => 0.0,
			'price_out_usd_per_mtok'   => 0.0,
			'max_output_tokens'        => 1600,
			'suggestion_count'         => 3,
			'per_minute'               => 6,
			'daily_limit'              => 300,
			'monthly_cap_usd'          => 10.0,
			'max_concurrent'           => 1,
			'retention_days'           => 30,
			'banglish_reply_script'    => 'bn',
			'reply_digits'             => 'en',
			'allow_public_mrp'         => 0,
			'exact_stock'              => 0,
			'masked_logging'           => 0,
			'live_inbox'               => 0,
			'extra_use_roles'          => array(),
			'extra_link_roles'         => array(),
			'delete_data_on_uninstall' => 0,
			'business_name'            => 'Nabiad Distribution Limited',
			'holding_reply_bn'         => 'ধন্যবাদ। আপনার প্রশ্নের সঠিক তথ্য নিশ্চিত করে কিছুক্ষণের মধ্যে জানাচ্ছি।',
			'holding_reply_en'         => 'Thank you. We will confirm the correct information and get back to you shortly.',
			'banned_phrases'           => "১০০% গ্যারান্টি\nগ্যারান্টি দিচ্ছি\nguarantee\nguaranteed\nঅবশ্যই পৌঁছে যাবে\nfree delivery\nফ্রি ডেলিভারি",
			'last_test_result'         => '',
			'last_test_at'             => '',
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/** Validate and merge a partial update. Returns array of errors (field => message). */
	public static function update( array $input ) {
		$current = self::all();
		$errors  = array();
		$clean   = $current;
		$rules   = array(
			'per_minute'             => array( 1, 60 ),
			'daily_limit'            => array( 1, 5000 ),
			'max_concurrent'         => array( 1, 3 ),
			'retention_days'         => array( 7, 90 ),
			'max_output_tokens'      => array( 300, 4000 ),
			'suggestion_count'       => array( 3, 4 ),
		);
		foreach ( $rules as $k => $range ) {
			if ( array_key_exists( $k, $input ) ) {
				$v = filter_var( $input[ $k ], FILTER_VALIDATE_INT );
				if ( false === $v || $v < $range[0] || $v > $range[1] ) {
					$errors[ $k ] = sprintf( 'Must be a whole number from %d to %d.', $range[0], $range[1] );
				} else {
					$clean[ $k ] = $v;
				}
			}
		}
		foreach ( array( 'monthly_cap_usd' => array( 0.5, 1000 ), 'price_in_usd_per_mtok' => array( 0, 1000 ), 'price_out_usd_per_mtok' => array( 0, 1000 ) ) as $k => $range ) {
			if ( array_key_exists( $k, $input ) ) {
				$v = filter_var( $input[ $k ], FILTER_VALIDATE_FLOAT );
				if ( false === $v || $v < $range[0] || $v > $range[1] ) {
					$errors[ $k ] = sprintf( 'Must be a number from %s to %s.', $range[0], $range[1] );
				} else {
					$clean[ $k ] = round( $v, 4 );
				}
			}
		}
		foreach ( array( 'service_enabled', 'ai_enabled', 'allow_public_mrp', 'exact_stock', 'masked_logging', 'live_inbox', 'delete_data_on_uninstall' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$clean[ $k ] = empty( $input[ $k ] ) ? 0 : 1;
			}
		}
		$enums = array(
			'provider'              => array( 'anthropic', 'openai' ),
			'banglish_reply_script' => array( 'bn', 'latin' ),
			'reply_digits'          => array( 'en', 'bn' ),
		);
		foreach ( $enums as $k => $allowed ) {
			if ( array_key_exists( $k, $input ) ) {
				if ( in_array( $input[ $k ], $allowed, true ) ) {
					$clean[ $k ] = $input[ $k ];
				} else {
					$errors[ $k ] = 'Invalid choice.';
				}
			}
		}
		foreach ( array( 'anthropic_model', 'openai_model' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$v = trim( (string) $input[ $k ] );
				if ( '' !== $v && ! preg_match( '/^[A-Za-z0-9._:-]{2,80}$/', $v ) ) {
					$errors[ $k ] = 'Model ID may contain letters, digits, dot, dash, underscore and colon only.';
				} else {
					$clean[ $k ] = $v;
				}
			}
		}
		foreach ( array( 'business_name' => 120, 'holding_reply_bn' => 500, 'holding_reply_en' => 500, 'banned_phrases' => 4000 ) as $k => $max ) {
			if ( array_key_exists( $k, $input ) ) {
				$v = trim( sanitize_textarea_field( (string) $input[ $k ] ) );
				if ( Util::strlen( $v ) > $max ) {
					$errors[ $k ] = sprintf( 'Maximum %d characters.', $max );
				} elseif ( '' === $v && 'banned_phrases' !== $k ) {
					$errors[ $k ] = 'Required.';
				} else {
					$clean[ $k ] = $v;
				}
			}
		}
		foreach ( array( 'extra_use_roles', 'extra_link_roles' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$roles = array();
				foreach ( (array) $input[ $k ] as $role ) {
					$role = sanitize_key( $role );
					if ( $role && ! in_array( $role, array( 'administrator', 'shop_manager' ), true ) && get_role( $role ) ) {
						$roles[] = $role;
					}
				}
				$clean[ $k ] = array_values( array_unique( $roles ) );
			}
		}
		foreach ( array( 'last_test_result', 'last_test_at' ) as $k ) {
			if ( array_key_exists( $k, $input ) ) {
				$clean[ $k ] = substr( sanitize_text_field( (string) $input[ $k ] ), 0, 200 );
			}
		}
		if ( $errors ) {
			return $errors;
		}
		update_option( self::OPTION, $clean, false );
		self::$cache = null;
		return array();
	}

	/** Reset the in-memory cache (tests). */
	public static function flush() {
		self::$cache = null;
	}

	/** Kill switch: constant or setting. */
	public static function service_enabled() {
		if ( defined( 'RAR_IAA_DISABLE' ) && RAR_IAA_DISABLE ) {
			return false;
		}
		return (bool) self::get( 'service_enabled' );
	}

	public static function max_concurrent() {
		if ( defined( 'RAR_IAA_MAX_CONCURRENT' ) ) {
			return max( 1, min( 3, (int) RAR_IAA_MAX_CONCURRENT ) );
		}
		return (int) self::get( 'max_concurrent' );
	}

	/* ---------------------------------------------------------------- Provider keys */

	/** Where the key for a provider comes from: 'constant', 'option' or ''. */
	public static function key_source( $provider ) {
		$const = 'RAR_IAA_' . strtoupper( $provider ) . '_API_KEY';
		if ( defined( $const ) && '' !== (string) constant( $const ) ) {
			return 'constant';
		}
		return get_option( 'rar_iaa_secret_' . $provider ) ? 'option' : '';
	}

	/** Plain key for server-side use only. Never return this from any endpoint. */
	public static function api_key( $provider ) {
		$const = 'RAR_IAA_' . strtoupper( $provider ) . '_API_KEY';
		if ( defined( $const ) && '' !== (string) constant( $const ) ) {
			return (string) constant( $const );
		}
		$stored = get_option( 'rar_iaa_secret_' . $provider );
		if ( ! $stored || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
			return '';
		}
		$raw = base64_decode( (string) $stored, true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, self::secret_box_key() );
		return false === $plain ? '' : $plain;
	}

	public static function store_api_key( $provider, $key ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			delete_option( 'rar_iaa_secret_' . $provider );
			return;
		}
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$box   = sodium_crypto_secretbox( $key, $nonce, self::secret_box_key() );
		update_option( 'rar_iaa_secret_' . $provider, base64_encode( $nonce . $box ), false );
	}

	/** Masked display: first 4 and last 4 characters. */
	public static function masked_key( $provider ) {
		$key = self::api_key( $provider );
		if ( strlen( $key ) < 12 ) {
			return '' === $key ? '' : '••••';
		}
		return substr( $key, 0, 4 ) . '…' . substr( $key, -4 );
	}

	private static function secret_box_key() {
		return hash( 'sha256', 'rar-iaa-secretbox|' . wp_salt( 'secure_auth' ), true );
	}
}
