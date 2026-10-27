<?php
/**
 * Reply language detection (FR-10, TRD §6 step 7).
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class Language {

	/** Messages with at most this many words are "short" for detect_recent(). */
	const SHORT = 4;

	/** Common Banglish words; two hits (or one in a very short message) mark a Latin-script message as Banglish. */
	const BANGLISH = array( 'ami', 'amar', 'apni', 'apnar', 'apnader', 'apnara', 'ache', 'achhe', 'acche', 'ase', 'koto', 'koyta', 'kobe', 'kothay', 'dam', 'daam', 'din', 'diben', 'dibo', 'den', 'deben', 'chai', 'lagbe', 'hobe', 'hbe', 'ki', 'vai', 'bhai', 'bhaiya', 'vaiya', 'ajke', 'aj', 'kal', 'khola', 'bondho', 'porjonto', 'theke', 'jabe', 'pathan', 'pathaben', 'korte', 'korbo', 'kore', 'kivabe', 'kemne', 'na', 'ji', 'jii', 'thik', 'accha', 'acha', 'dhonnobad', 'onek', 'ekta', 'duita', 'tin', 'kom', 'beshi', 'shob', 'sob', 'nibo', 'nite', 'pabo', 'paowa', 'jay', 'jaabe', 'assalamu', 'alaikum', 'salam', 'vaia', 'apu', 'dada' );

	/** Script of a text: bn, banglish or en. */
	public static function detect( $text ) {
		$text = (string) $text;
		preg_match_all( '/[\x{0980}-\x{09FF}]/u', $text, $bn );
		preg_match_all( '/[A-Za-z]/', $text, $lat );
		$b = count( $bn[0] );
		$l = count( $lat[0] );
		if ( $b + $l === 0 ) {
			return 'bn';
		}
		if ( $b / ( $b + $l ) > 0.3 ) {
			return 'bn';
		}
		$hits   = 0;
		$tokens = Util::tokens( $text );
		foreach ( $tokens as $tok ) {
			if ( in_array( $tok, self::BANGLISH, true ) ) {
				$hits++;
			}
		}
		// "Price koto?" — one Banglish word in a two-word message is enough.
		return ( $hits >= 2 || ( $hits >= 1 && $hits * 3 >= count( $tokens ) ) ) ? 'banglish' : 'en';
	}

	/**
	 * Language of the latest customer messages (oldest first). A short English-looking last message
	 * ("Price?", "Hello", "ok") follows the language of the customer's previous two messages.
	 */
	public static function detect_recent( array $customer_texts ) {
		$texts = array_values( array_filter( array_map( 'strval', $customer_texts ), 'strlen' ) );
		if ( ! $texts ) {
			return 'bn';
		}
		$last = self::detect( end( $texts ) );
		if ( 'en' !== $last || count( Util::tokens( end( $texts ) ) ) > self::SHORT ) {
			return $last;
		}
		foreach ( array_reverse( array_slice( array_slice( $texts, 0, -1 ), -2 ) ) as $t ) {
			$d = self::detect( $t );
			if ( 'en' !== $d ) {
				return $d;
			}
		}
		return $last;
	}

	/** Reply language for the model: bn or en (+ whether Latin-script Bangla is wanted). */
	public static function reply_language( $detected, $override, $banglish_script ) {
		if ( in_array( $override, array( 'bn', 'en' ), true ) ) {
			return array( $override, false );
		}
		if ( 'banglish' === $detected ) {
			return array( 'bn', 'latin' === $banglish_script );
		}
		return array( 'en' === $detected ? 'en' : 'bn', false );
	}

	/** Did the customer greet with salam? (BR-12) */
	public static function greeted_salam( $text ) {
		return (bool) preg_match( '/(assalamu|asslamu|assalam|salamu|আসসালামু|আস্সালামু|সালাম|السلام)/iu', (string) $text );
	}
}
