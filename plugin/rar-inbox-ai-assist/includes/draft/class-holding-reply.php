<?php
/**
 * Safe, fact-free holding reply used when a suggestion is blocked.
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class HoldingReply {

	public static function text( $lang, $greeted ) {
		if ( 'en' === $lang ) {
			return ( $greeted ? 'Wa alaikum assalam. ' : '' ) . Settings::get( 'holding_reply_en' );
		}
		return ( $greeted ? 'ওয়ালাইকুম আসসালাম। ' : '' ) . Settings::get( 'holding_reply_bn' );
	}
}
