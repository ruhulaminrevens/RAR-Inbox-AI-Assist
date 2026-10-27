<?php
/** E2E privacy check: no customer text in any plugin table. Prints JSON. */
require __DIR__ . '/../php/bootstrap.php';
global $wpdb;
$needles = array( 'khola koyta', 'Assalamu alaikum', '01712', 'time kore', 'dam koto', 'E2E-NOTE', 'Price koto' );
$hits    = array();
foreach ( RarIaa\Db::TABLES as $short ) {
	foreach ( $wpdb->get_results( 'SELECT * FROM ' . RarIaa\Db::t( $short ), ARRAY_A ) as $row ) {
		$blob = wp_json_encode( $row, JSON_UNESCAPED_UNICODE );
		foreach ( $needles as $n ) {
			if ( false !== stripos( $blob, $n ) ) {
				$hits[] = $short . ':' . $n;
			}
		}
	}
}
$outcomes = $wpdb->get_col( 'SELECT outcome FROM ' . RarIaa\Db::t( 'draft_log' ) . ' WHERE outcome IS NOT NULL' );
echo wp_json_encode( array( 'hits' => array_values( array_unique( $hits ) ), 'outcomes' => $outcomes, 'drafts' => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RarIaa\Db::t( 'draft_log' ) ) ) );
