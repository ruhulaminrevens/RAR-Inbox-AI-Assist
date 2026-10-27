<?php
/**
 * Strict parsing of the model JSON (TRD §6 step 12, v0.2 §19: invalid count/duplicate IDs fail closed).
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class ResponseParser {

	const HANDOFF_REASONS = array( 'order_confirmation', 'credit_outstanding', 'discount_negotiation', 'complaint', 'damage_return', 'unknown_dealer_price', 'missing_facts', 'other' );

	/**
	 * @param string   $text     Raw model text.
	 * @param string[] $expected Required variant ids (exact set).
	 * @param string[] $supplied Fact ids that were supplied.
	 * @return array|null list of normalised suggestions keyed in $expected order, or null when invalid
	 */
	public static function parse( $text, array $expected, array $supplied ) {
		$text = trim( (string) $text );
		$text = preg_replace( '/^```(?:json)?\s*|\s*```$/', '', $text );
		$a    = strpos( $text, '{' );
		$b    = strrpos( $text, '}' );
		if ( false === $a || false === $b || $b <= $a ) {
			return null;
		}
		$json = json_decode( substr( $text, $a, $b - $a + 1 ), true );
		if ( ! is_array( $json ) || ! isset( $json['suggestions'] ) || ! is_array( $json['suggestions'] ) ) {
			return null;
		}
		if ( count( $json['suggestions'] ) !== count( $expected ) ) {
			return null;
		}
		$by = array();
		foreach ( $json['suggestions'] as $s ) {
			if ( ! is_array( $s ) || ! isset( $s['variant_id'], $s['reply'] ) || ! is_string( $s['variant_id'] ) || ! is_string( $s['reply'] ) ) {
				return null;
			}
			$id = $s['variant_id'];
			if ( ! in_array( $id, $expected, true ) || isset( $by[ $id ] ) ) {
				return null;
			}
			$reply = trim( $s['reply'] );
			if ( '' === $reply || Util::strlen( $reply ) > 1500 ) {
				return null;
			}
			$facts = array();
			if ( isset( $s['facts_used'] ) && is_array( $s['facts_used'] ) ) {
				foreach ( $s['facts_used'] as $f ) {
					if ( is_string( $f ) && in_array( $f, $supplied, true ) && ! in_array( $f, $facts, true ) ) {
						$facts[] = $f;
					}
				}
			}
			$reason = isset( $s['handoff_reason'] ) && in_array( $s['handoff_reason'], self::HANDOFF_REASONS, true ) ? $s['handoff_reason'] : null;
			$by[ $id ] = array(
				'variant_id'     => $id,
				'reply'          => $reply,
				'language'       => ( isset( $s['language'] ) && 'en' === $s['language'] ) ? 'en' : 'bn',
				'needs_human'    => ! empty( $s['needs_human'] ),
				'handoff_reason' => $reason,
				'facts_used'     => $facts,
				'missing_info'   => isset( $s['missing_info'] ) && is_array( $s['missing_info'] ) ? array_slice( array_map( 'strval', $s['missing_info'] ), 0, 5 ) : array(),
			);
		}
		$out = array();
		foreach ( $expected as $id ) {
			$out[] = $by[ $id ];
		}
		return $out;
	}
}
