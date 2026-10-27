<?php
/**
 * Daily maintenance: retention purges and reservation recovery (FR-19, Schema §6).
 *
 * @package RarIaa
 */

namespace RarIaa\Cron;

use RarIaa\Db;
use RarIaa\Domain\DraftLogRepository;
use RarIaa\Limits\Budget;
use RarIaa\Limits\RateLimiter;
use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class Maintenance {

	public static function run() {
		global $wpdb;
		$report                      = array();
		$report['draft_log']         = DraftLogRepository::purge_older_than( (int) Settings::get( 'retention_days' ) );
		$report['rate_buckets']      = RateLimiter::purge_expired();
		$report['reservations']      = Budget::release_expired();
		$report['usage_daily']       = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'usage_daily' ) . ' WHERE day < %s', Db::dhaka_date( time() - 400 * DAY_IN_SECONDS ) ) );
		$report['audit_log']         = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'audit_log' ) . ' WHERE created_at < %s', Db::now( -730 * DAY_IN_SECONDS ) ) );
		$report['tokens']            = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'tokens' ) . ' WHERE (revoked_at IS NOT NULL AND revoked_at < %s) OR expires_at < %s', Db::now( -400 * DAY_IN_SECONDS ), Db::now( -400 * DAY_IN_SECONDS ) ) );
		$report['cost_reservations'] = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . Db::t( 'cost_reservations' ) . " WHERE status <> 'reserved' AND created_at < %s", Db::now( -400 * DAY_IN_SECONDS ) ) );
		update_option( 'rar_iaa_maintenance_last_run', array( 'at' => Db::now(), 'report' => $report ), false );
		return $report;
	}
}
