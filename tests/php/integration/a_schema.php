<?php
/** T1.01 / T1.02 — schema, idempotent migration, DB-enforced one active list per tier. */
global $wpdb;
t_eq( array(), RarIaa\Installer::missing_tables(), 'all 13 tables exist' );
t_eq( RAR_IAA_SCHEMA_VERSION, (int) get_option( 'rar_iaa_schema_version' ), 'schema version recorded' );
t_ok( RarIaa\Installer::migrate( true ), 'forced re-migration is a no-op success' );
$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . RarIaa\Db::t( 'draft_log' ) );
foreach ( array( 'suggestion_count', 'selected_variant_id', 'variant_guard_summary', 'facts_used', 'input_masked' ) as $c ) {
	t_ok( in_array( $c, $cols, true ), "draft_log.{$c}" );
}
$cols = $wpdb->get_col( 'SHOW COLUMNS FROM ' . RarIaa\Db::t( 'usage_daily' ) );
t_ok( in_array( 'reserved_cost_usd_micros', $cols, true ), 'usage_daily.reserved_cost_usd_micros' );
t_ok( (bool) get_option( 'rar_iaa_thread_secret' ), 'thread secret created' );
t_reset();
$t   = RarIaa\Db::t( 'price_lists' );
$row = array( 'tier_id' => 99, 'name' => 'x', 'status' => 'active', 'active_flag' => 1, 'effective_from' => '2026-01-01', 'file_sha256' => str_repeat( 'a', 64 ), 'row_count' => 1, 'imported_by' => 1, 'imported_at' => RarIaa\Db::now() );
t_ok( (bool) $wpdb->insert( $t, $row ), 'first active list for tier inserted' );
$wpdb->suppress_errors( true );
t_ok( ! $wpdb->insert( $t, $row ), 'second active list for same tier rejected by unique key' );
$wpdb->suppress_errors( false );
$row['active_flag'] = null;
$row['status']      = 'archived';
t_ok( (bool) $wpdb->insert( $t, $row ) && (bool) $wpdb->insert( $t, $row ), 'many archived lists allowed (NULL flag)' );
t_reset();
