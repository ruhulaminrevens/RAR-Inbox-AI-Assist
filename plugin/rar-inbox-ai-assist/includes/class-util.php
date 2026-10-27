<?php
/**
 * Small pure helpers (no WordPress state).
 *
 * @package RarIaa
 */

namespace RarIaa;

defined( 'ABSPATH' ) || exit;

class Util {

	const BN_DIGITS = array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' );

	public static function uuid4() {
		$b    = random_bytes( 16 );
		$b[6] = chr( ( ord( $b[6] ) & 0x0f ) | 0x40 );
		$b[8] = chr( ( ord( $b[8] ) & 0x3f ) | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $b ), 4 ) );
	}

	public static function is_uuid( $v ) {
		return is_string( $v ) && (bool) preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $v );
	}

	/** Bangla digits → ASCII digits. */
	public static function ascii_digits( $s ) {
		return str_replace( self::BN_DIGITS, array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), (string) $s );
	}

	/** ASCII digits → Bangla digits. */
	public static function bn_digits( $s ) {
		return str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), self::BN_DIGITS, (string) $s );
	}

	public static function strlen( $s ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( (string) $s, 'UTF-8' ) : strlen( (string) $s );
	}

	public static function substr( $s, $start, $len = null ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $s, $start, $len, 'UTF-8' ) : substr( (string) $s, $start, $len );
	}

	public static function lower( $s ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $s, 'UTF-8' ) : strtolower( (string) $s );
	}

	/** Lowercase, ASCII digits, punctuation → spaces, collapsed whitespace. */
	public static function normalize( $s ) {
		$s = self::lower( self::ascii_digits( $s ) );
		$s = preg_replace( '/[^\p{L}\p{M}\p{N}]+/u', ' ', $s );
		return trim( preg_replace( '/\s+/u', ' ', (string) $s ) );
	}

	/** Tokens of a normalised string. */
	public static function tokens( $s ) {
		$n = self::normalize( $s );
		return '' === $n ? array() : explode( ' ', $n );
	}

	/** Bangladesh-style grouping: 125000 → 1,25,000. */
	public static function bd_group( $number ) {
		$neg  = $number < 0;
		$str  = number_format( abs( (float) $number ), 2, '.', '' );
		$int  = strstr( $str, '.', true );
		$dec  = substr( strstr( $str, '.' ), 1 );
		$last = substr( $int, -3 );
		$rest = substr( $int, 0, -3 );
		if ( '' !== $rest ) {
			$rest = preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', $rest );
			$last = $rest . ',' . $last;
		}
		$out = ( '00' === $dec ) ? $last : $last . '.' . $dec;
		return ( $neg ? '-' : '' ) . $out;
	}

	/** Levenshtein-based similarity ratio for short ASCII/UTF-8 tokens (0–1). */
	public static function similarity( $a, $b ) {
		$a = (string) $a;
		$b = (string) $b;
		if ( $a === $b ) {
			return 1.0;
		}
		$max = max( strlen( $a ), strlen( $b ) );
		if ( 0 === $max || $max > 255 ) {
			return 0.0;
		}
		return 1 - ( levenshtein( $a, $b ) / $max );
	}

	public static function json( $v ) {
		return wp_json_encode( $v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	/** Compare semantic versions (x.y.z). */
	public static function version_lt( $a, $b ) {
		return version_compare( (string) $a, (string) $b, '<' );
	}
}
