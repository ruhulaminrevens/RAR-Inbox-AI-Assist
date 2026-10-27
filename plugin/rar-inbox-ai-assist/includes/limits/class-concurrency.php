<?php
/**
 * AI call concurrency slots (TRD §6 step 5, ADR-08). Default one slot site-wide.
 *
 * @package RarIaa
 */

namespace RarIaa\Limits;

use RarIaa\Lock;
use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class Concurrency {

	/** Slot lease for the SQLite fallback: provider timeout (20 s) + repair retry + margin. */
	const LEASE = 75;

	/** @return int|false slot number held, or false when all slots are busy */
	public static function acquire() {
		$max = Settings::max_concurrent();
		for ( $n = 1; $n <= $max; $n++ ) {
			if ( Lock::acquire( 'ai_slot_' . $n, 0, self::LEASE ) ) {
				return $n;
			}
		}
		return false;
	}

	public static function release( $slot ) {
		if ( $slot ) {
			Lock::release( 'ai_slot_' . (int) $slot );
		}
	}
}
