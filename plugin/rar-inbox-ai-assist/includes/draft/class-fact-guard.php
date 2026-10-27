<?php
/**
 * Fact guard (FR-08, TRD §6 step 14, audit F-03, v0.2 §19).
 *
 * Every number in a suggestion is classified:
 *  - price-like (near ৳/টাকা/tk/taka/BDT//-/দাম/price/rate) or a percentage → must equal a number in
 *    the supplied facts, otherwise the suggestion is BLOCKED. A number the customer wrote never
 *    authorises a business price.
 *  - other numbers → must be in the facts; if only the customer wrote it, or nobody did → WARNING.
 *  - integers 0–3 that are not price-like are ignored (counts such as "1টি").
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Settings;
use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class FactGuard {

	const PRICE_CUE    = '/(৳|টাকা|টাঃ|tk\b|tk\.|taka|bdt|\/-|দাম|মূল্য|price|rate|রেট|mrp)/iu';
	const PERCENT_CUE  = '/^\s?(%|শতাংশ|percent|পার্সেন্ট|পারসেন্ট)/iu';
	const COMMERCIAL   = '/(discount|ডিসকাউন্ট|ছাড় দে|ছাড় দি|ছাড় পাব|কমিশন|commission|% off|ফ্রি দে|free gift)/iu';
	const UNIT_SYNONYM = array(
		'carton' => array( 'carton', 'ctn', 'কার্টন', 'কাটুন' ),
		'dozen'  => array( 'dozen', 'dz', 'ডজন' ),
		'pcs'    => array( 'pcs', 'pc', 'piece', 'পিস', 'টি' ),
		'piece'  => array( 'piece', 'pcs', 'pc', 'পিস' ),
		'box'    => array( 'box', 'বক্স' ),
		'packet' => array( 'packet', 'pack', 'প্যাকেট' ),
		'kg'     => array( 'kg', 'কেজি' ),
	);

	/** Normalised numeric value of a matched number string. */
	public static function norm_number( $raw ) {
		$s = str_replace( ',', '', Util::ascii_digits( $raw ) );
		if ( false !== strpos( $s, '.' ) ) {
			$s = rtrim( rtrim( $s, '0' ), '.' );
		}
		$s = ltrim( $s, '0' );
		return '' === $s ? '0' : $s;
	}

	/** All numbers in a text: list of array( value, raw, char_pos, char_len ). */
	public static function numbers( $text ) {
		$text = (string) $text;
		$out  = array();
		if ( ! preg_match_all( '/[0-9০-৯]+(?:[.,][0-9০-৯]+)*/u', $text, $m, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}
		foreach ( $m[0] as $hit ) {
			$raw   = $hit[0];
			$pos   = Util::strlen( substr( $text, 0, $hit[1] ) );
			$out[] = array( self::norm_number( $raw ), $raw, $pos, Util::strlen( $raw ) );
		}
		return $out;
	}

	public static function number_set( $text ) {
		$set = array();
		foreach ( self::numbers( $text ) as $n ) {
			$set[ $n[0] ] = true;
			// "1,250.50" also yields its integer part for loose comparisons.
			if ( false !== strpos( $n[0], '.' ) ) {
				$set[ strstr( $n[0], '.', true ) ] = true;
			}
		}
		return $set;
	}

	/**
	 * @param string   $reply           Unmasked suggestion text.
	 * @param array    $facts           Facts array given to the model.
	 * @param string[] $customer_texts  Unmasked customer messages.
	 * @return array( 'result' => pass|warned|blocked, 'warnings' => [], 'blocked_values' => [] )
	 */
	public static function check( $reply, array $facts, array $customer_texts ) {
		$facts_text = Util::json( $facts );
		$fact_set   = self::number_set( $facts_text );
		// A price may only equal a listed price/MRP or a price written in an approved KB answer; a
		// percentage only a percentage written in an approved KB answer. Never the date line, pack
		// sizes or the customer's text.
		list( $kb_prices, $kb_percents ) = self::kb_commercial( $facts );
		$cust_set   = self::number_set( implode( "\n", $customer_texts ) );
		$warnings   = array();
		$blocked    = array();
		$price_map  = self::price_units( $facts );

		foreach ( self::numbers( $reply ) as $n ) {
			list( $value, $raw, $pos, $len ) = $n;
			list( $before, $after ) = self::windows( $reply, $pos, $len );
			$is_price = (bool) preg_match( self::PRICE_CUE, $before . ' ' . $after );
			$is_pct   = (bool) preg_match( self::PERCENT_CUE, $after );
			$context  = trim( Util::substr( $reply, max( 0, $pos - 15 ), $len + 30 ) );
			if ( $is_price || $is_pct ) {
				$allowed = $is_pct ? isset( $kb_percents[ $value ] ) : ( isset( $price_map[ $value ] ) || isset( $kb_prices[ $value ] ) );
				if ( ! $allowed ) {
					$blocked[] = $raw;
				} elseif ( $is_price && isset( $price_map[ $value ] ) && ! self::unit_near( $reply, $pos, $len, $price_map[ $value ] ) ) {
					$warnings[] = array( 'code' => 'PRICE_UNIT_UNCLEAR', 'value' => $raw, 'context' => $context );
				}
				continue;
			}
			if ( isset( $fact_set[ $value ] ) ) {
				continue;
			}
			if ( ctype_digit( $value ) && (int) $value <= 3 ) {
				continue;
			}
			$warnings[] = array(
				'code'    => 'UNVERIFIED_NUMBER',
				'value'   => $raw,
				'context' => $context,
				'source'  => isset( $cust_set[ $value ] ) ? 'customer' : 'none',
			);
		}

		$facts_lower = Util::lower( $facts_text );
		foreach ( preg_split( '/\r?\n/', (string) Settings::get( 'banned_phrases' ) ) as $phrase ) {
			$phrase = trim( $phrase );
			if ( '' !== $phrase && false !== strpos( Util::lower( $reply ), Util::lower( $phrase ) ) && false === strpos( $facts_lower, Util::lower( $phrase ) ) ) {
				$warnings[] = array( 'code' => 'BANNED_PHRASE', 'value' => $phrase );
			}
		}
		if ( preg_match( self::COMMERCIAL, $reply, $cm ) && ! preg_match( self::COMMERCIAL, $facts_text ) ) {
			$warnings[] = array( 'code' => 'COMMERCIAL_CLAIM', 'value' => $cm[0] );
		}

		$result = $blocked ? 'blocked' : ( $warnings ? 'warned' : 'pass' );
		return array( 'result' => $result, 'warnings' => $warnings, 'blocked_values' => array_values( array_unique( $blocked ) ) );
	}

	/** Cue windows around a number (12 characters each side), cut at sentence boundaries. */
	private static function windows( $text, $pos, $len ) {
		$before = Util::substr( $text, max( 0, $pos - 12 ), min( 12, $pos ) );
		$after  = Util::substr( $text, $pos + $len, 12 );
		$parts  = preg_split( '/[।!?\n]|\.\s/u', $before );
		$before = (string) end( $parts );
		$parts  = preg_split( '/[।!?\n]|\.\s/u', $after );
		$after  = (string) $parts[0];
		return array( $before, $after );
	}

	/** Price-like and percentage numbers written in approved KB answers. */
	private static function kb_commercial( array $facts ) {
		$prices   = array();
		$percents = array();
		foreach ( isset( $facts['kb'] ) ? $facts['kb'] : array() as $kb ) {
			foreach ( array( 'answer_bn', 'answer_en' ) as $f ) {
				$text = isset( $kb[ $f ] ) ? (string) $kb[ $f ] : '';
				foreach ( self::numbers( $text ) as $n ) {
					list( $before, $after ) = self::windows( $text, $n[2], $n[3] );
					if ( preg_match( self::PERCENT_CUE, $after ) ) {
						$percents[ $n[0] ] = true;
					} elseif ( preg_match( self::PRICE_CUE, $before . ' ' . $after ) ) {
						$prices[ $n[0] ] = true;
					}
				}
			}
		}
		return array( $prices, $percents );
	}

	/** price value → list of units for prices/MRPs in the facts. */
	private static function price_units( array $facts ) {
		$map = array();
		foreach ( array( 'products' => 'price_bdt', 'public_prices' => 'mrp_bdt' ) as $group => $field ) {
			if ( empty( $facts[ $group ] ) ) {
				continue;
			}
			foreach ( $facts[ $group ] as $p ) {
				if ( isset( $p[ $field ] ) ) {
					$v           = self::norm_number( number_format( (float) $p[ $field ], 2, '.', '' ) );
					$map[ $v ][] = (string) $p['unit'];
				}
			}
		}
		return $map;
	}

	private static function unit_near( $reply, $pos, $len, array $units ) {
		$window = Util::lower( Util::substr( $reply, max( 0, $pos - 40 ), $len + 80 ) );
		foreach ( $units as $u ) {
			$u     = Util::lower( trim( $u ) );
			$forms = isset( self::UNIT_SYNONYM[ $u ] ) ? self::UNIT_SYNONYM[ $u ] : array( $u );
			$forms[] = $u;
			foreach ( $forms as $f ) {
				if ( '' !== $f && false !== strpos( $window, $f ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
