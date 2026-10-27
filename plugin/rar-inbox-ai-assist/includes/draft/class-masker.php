<?php
/**
 * PII masking before any provider call (FR-03, TRD §6 step 6, audit F-11).
 *
 * The placeholder → original map lives only in request memory and is discarded after unmasking.
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class Masker {

	const D = '[0-9০-৯]';

	/** @var array<string,string> placeholder => original */
	private $map = array();
	/** @var array<string,int> */
	private $counters = array();
	/** @var array<string,string> original => placeholder */
	private $reverse = array();

	private function placeholder( $type, $original ) {
		$key = $type . '|' . $original;
		if ( isset( $this->reverse[ $key ] ) ) {
			return $this->reverse[ $key ];
		}
		$this->counters[ $type ] = isset( $this->counters[ $type ] ) ? $this->counters[ $type ] + 1 : 1;
		$ph                      = '[' . $type . '_' . $this->counters[ $type ] . ']';
		$this->map[ $ph ]        = $original;
		$this->reverse[ $key ]   = $ph;
		return $ph;
	}

	/** Mask one text. $names are display-name strings to hide (ephemeral input). */
	public function mask( $text, array $names = array() ) {
		$d    = self::D;
		$text = (string) $text;

		// E-mail.
		$text = preg_replace_callback(
			'/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u',
			function ( $m ) {
				return $this->placeholder( 'EMAIL', $m[0] );
			},
			$text
		);
		// Bangladesh mobile numbers: optional +88 / 88, then 01[3-9] + 8 digits, spaces/hyphens allowed.
		$text = preg_replace_callback(
			"/(?<![0-9০-৯])(?:\+?(?:88|৮৮)[\s\-]?)?[0০][1১][3-9৩-৯](?:[\s\-]?{$d}){8}(?![0-9০-৯])/u",
			function ( $m ) {
				return $this->placeholder( 'PHONE', $m[0] );
			},
			$text
		);
		// Landlines: 0 + 1–2 digit area code + 6–8 digits.
		$text = preg_replace_callback(
			"/(?<![0-9০-৯])[0০]{$d}{1,2}[\s\-]{$d}{6,8}(?![0-9০-৯])/u",
			function ( $m ) {
				return $this->placeholder( 'PHONE', $m[0] );
			},
			$text
		);
		// Long digit runs (account / transaction IDs).
		$text = preg_replace_callback(
			"/(?<![0-9০-৯]){$d}{10,}(?![0-9০-৯])/u",
			function ( $m ) {
				return $this->placeholder( 'NUMBER', $m[0] );
			},
			$text
		);
		// Explicit address labels: "ঠিকানা: …" / "address: …" up to the end of the clause.
		$text = preg_replace_callback(
			'/(ঠিকানা|address|addr)\s*[:\-–]\s*([^\n।!?]+)/iu',
			function ( $m ) {
				return $m[1] . ': ' . $this->placeholder( 'ADDRESS', trim( $m[2] ) );
			},
			$text
		);
		// Clauses with an address cue word and a number (house/road numbers).
		$cues  = '(বাসা|বাড়ি|রোড|সড়ক|হোল্ডিং|সেক্টর|ব্লক|ফ্ল্যাট|লেন|গলি|গ্রাম|\\b(?:house|road|rd|holding|sector|block|flat|apt|lane|village)\\b)';
		$parts = preg_split( '/([\n।!?,;]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i % 2 && preg_match( '/' . $cues . '/iu', $part ) && preg_match( "/{$d}/u", $part ) && false === strpos( $part, '[' ) ) {
				$trim        = trim( $part );
				$parts[ $i ] = str_replace( $trim, $this->placeholder( 'ADDRESS', $trim ), $part );
			}
		}
		$text = implode( '', $parts );
		// Display names.
		foreach ( $names as $name ) {
			$name = trim( (string) $name );
			if ( Util::strlen( $name ) < 2 ) {
				continue;
			}
			$candidates = array( $name );
			$first      = preg_split( '/\s+/u', $name );
			if ( isset( $first[0] ) && Util::strlen( $first[0] ) >= 3 ) {
				$candidates[] = $first[0];
			}
			foreach ( $candidates as $cand ) {
				$text = preg_replace( '/(?<![\p{L}\p{M}])' . preg_quote( $cand, '/' ) . '(?![\p{L}\p{M}])/iu', '[CUSTOMER]', $text );
			}
			$this->map['[CUSTOMER]'] = $name;
		}
		return $text;
	}

	/** Put the originals back (request memory only). */
	public function unmask( $text ) {
		if ( ! $this->map ) {
			return (string) $text;
		}
		return strtr( (string) $text, $this->map );
	}

	/** Re-mask a reply with this request's map (for masked logging). */
	public function remask( $text ) {
		$out = (string) $text;
		foreach ( $this->map as $ph => $orig ) {
			if ( '' !== $orig ) {
				$out = str_replace( $orig, $ph, $out );
			}
		}
		return $out;
	}

	public function count() {
		return count( $this->map );
	}

	/** Customer-supplied originals (numbers only), for the guard. */
	public function originals() {
		return array_values( $this->map );
	}
}
