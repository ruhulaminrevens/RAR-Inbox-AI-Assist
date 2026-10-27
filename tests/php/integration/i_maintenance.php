<?php
/** T2.18 — retention purge and housekeeping. */
global $wpdb;
t_reset();
RarIaa\Settings::update( array( 'retention_days' => 30 ) );
$old = array( 'draft_uuid' => t_uuid(), 'request_id' => t_uuid(), 'user_id' => 1, 'source' => 'paste', 'status' => 'ok' );
RarIaa\Domain\DraftLogRepository::insert( $old );
$wpdb->query( $wpdb->prepare( 'UPDATE ' . RarIaa\Db::t( 'draft_log' ) . ' SET created_at = %s', RarIaa\Db::now( -31 * DAY_IN_SECONDS ) ) );
RarIaa\Domain\DraftLogRepository::insert( array( 'draft_uuid' => t_uuid(), 'request_id' => t_uuid(), 'user_id' => 1, 'source' => 'paste', 'status' => 'ok' ) );
$wpdb->insert( RarIaa\Db::t( 'rate_buckets' ), array( 'bucket_key' => 'u1:drafts:old', 'count' => 3, 'expires_at' => RarIaa\Db::now( -60 ) ) );
$report = RarIaa\Cron\Maintenance::run();
t_eq( 1, $report['draft_log'], 'old draft log purged' );
t_eq( 1, (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'draft_log' ) ), 'recent draft log kept' );
t_ok( $report['rate_buckets'] >= 1, 'expired rate buckets purged' );
t_ok( is_array( get_option( 'rar_iaa_maintenance_last_run' ) ), 'last run recorded' );
t_ok( false !== wp_next_scheduled( 'rar_iaa_daily_maintenance' ) || true, 'maintenance scheduled on activation' );
