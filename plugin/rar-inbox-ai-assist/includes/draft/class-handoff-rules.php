<?php
/**
 * Handoff detection by keyword rules OR the model flag (FR-09, BR-04, ADR-07).
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Util;

defined( 'ABSPATH' ) || exit;

class HandoffRules {

	/** reason => phrases (matched on normalised text: lowercase, ASCII digits, punctuation removed). */
	public static function rules() {
		return array(
			'credit_outstanding'   => array( 'বকেয়া', 'বাকি', 'পাওনা', 'লেজার', 'ক্রেডিট', 'baki', 'bakir', 'due', 'outstanding', 'credit', 'ledger', 'balance', 'hisab', 'হিসাব' ),
			'discount_negotiation' => array( 'ছাড়', 'ডিসকাউন্ট', 'কমিশন', 'কম দামে', 'দাম কম', 'কমানো', 'কম হবে', 'discount', 'commission', 'dam kom', 'kom dam', 'kom hobe', 'komano', 'less price', 'best price', 'last price', 'offer', 'অফার' ),
			'order_confirmation'   => array( 'অর্ডার কনফার্ম', 'অর্ডার দিতে', 'অর্ডার দিব', 'অর্ডার দেব', 'অর্ডার করব', 'অর্ডার করতে', 'পাঠিয়ে দিন', 'পাঠিয়ে দেন', 'order confirm', 'confirm order', 'order dibo', 'order dite', 'order korbo', 'order korte', 'place order', 'pathiye din', 'booking', 'বুকিং' ),
			'complaint'            => array( 'অভিযোগ', 'নষ্ট', 'খারাপ', 'নকল', 'ভুল পণ্য', 'ঠকিয়েছেন', 'complain', 'complaint', 'kharap', 'nosto', 'fake', 'wrong product', 'problem', 'সমস্যা', 'somossa' ),
			'damage_return'        => array( 'ভাঙা', 'ভেঙে', 'ফেরত', 'রিপ্লেস', 'ড্যামেজ', 'লিক', 'return', 'ferot', 'bhanga', 'vanga', 'bhenge', 'venge', 'replace', 'damage', 'damaged', 'broken', 'leak', 'refund', 'রিফান্ড' ),
		);
	}

	/** Rule reason for the latest customer text, or null. */
	public static function match( $customer_text ) {
		$norm = ' ' . Util::normalize( $customer_text ) . ' ';
		foreach ( self::rules() as $reason => $phrases ) {
			foreach ( $phrases as $p ) {
				$pn = Util::normalize( $p );
				if ( '' === $pn ) {
					continue;
				}
				// Latin phrases must match whole words; Bangla phrases may carry suffixes (বাকির, ছাড়ের).
				$latin = (bool) preg_match( '/^[a-z0-9 ]+$/', $pn );
				if ( ( $latin && false !== strpos( $norm, ' ' . $pn . ' ' ) ) || ( ! $latin && false !== strpos( $norm, $pn ) ) ) {
					return $reason;
				}
			}
		}
		return null;
	}

	/**
	 * Final handoff for one suggestion.
	 *
	 * @return array( 'needed' => bool, 'reason' => string|null )
	 */
	public static function decide( $rule_reason, $model_needs, $model_reason, $price_withheld ) {
		if ( $rule_reason ) {
			return array( 'needed' => true, 'reason' => $rule_reason );
		}
		if ( $price_withheld ) {
			return array( 'needed' => true, 'reason' => 'unknown_dealer_price' );
		}
		if ( $model_needs ) {
			return array( 'needed' => true, 'reason' => $model_reason ? $model_reason : 'other' );
		}
		return array( 'needed' => false, 'reason' => null );
	}
}
