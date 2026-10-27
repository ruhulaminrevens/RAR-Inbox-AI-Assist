<?php
/**
 * Runs every test file and prints a summary. Exit code 1 on any failure.
 *   WP_LOAD=/path/to/wp-load.php IAA_TEST_URL=... php tests/php/run-all.php [unit|integration]
 */
while ( ob_get_level() > 0 ) {
	ob_end_flush();
}
// A crash or wp_die() before the summary must never look like success.
$GLOBALS['IAA_RUN_DONE'] = false;
register_shutdown_function(
	static function () {
		if ( empty( $GLOBALS['IAA_RUN_DONE'] ) ) {
			$e = error_get_last();
			fwrite( STDOUT, "\nRUNNER DIED before the summary" . ( $e ? ': ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line'] : '' ) . "\n" );
			if ( getenv( 'GITHUB_ACTIONS' ) ) {
				fwrite( STDOUT, "::error title=PHP test runner died::" . ( $e ? str_replace( array( "\r", "\n", '%' ), array( ' ', ' ', '%25' ), $e['message'] ) : 'no summary (wp_die or exit inside a test)' ) . "\n" );
			}
			exit( 1 );
		}
	}
);
require __DIR__ . '/bootstrap.php';
$filter = isset( $argv[1] ) ? $argv[1] : '';
$files  = array_merge( glob( __DIR__ . '/unit/*.php' ), glob( __DIR__ . '/integration/*.php' ) );
sort( $files );
foreach ( $files as $f ) {
	if ( $filter && false === strpos( $f, '/' . $filter . '/' ) && false === strpos( basename( $f ), $filter ) ) {
		continue;
	}
	$GLOBALS['T']['file'] = $f;
	$before               = $GLOBALS['T']['pass'] + $GLOBALS['T']['fail'];
	$failb                = $GLOBALS['T']['fail'];
	echo basename( dirname( $f ) ) . '/' . basename( $f ) . "\n";
	require $f;
	echo '  ' . ( $GLOBALS['T']['pass'] + $GLOBALS['T']['fail'] - $before ) . ' checks, ' . ( $GLOBALS['T']['fail'] - $failb ) . " failed\n";
	fflush( STDOUT );
}
echo "\nTOTAL: {$GLOBALS['T']['pass']} passed, {$GLOBALS['T']['fail']} failed\n";
foreach ( $GLOBALS['T']['failures'] as $f ) {
	echo " - {$f}\n";
	if ( getenv( 'GITHUB_ACTIONS' ) ) {
		// Surface failures as annotations (visible without downloading logs).
		echo '::error title=PHP test failure::' . str_replace( array( "\r", "\n", '%' ), array( ' ', ' ', '%25' ), $f ) . "\n";
	}
}
$GLOBALS['IAA_RUN_DONE'] = true;
exit( $GLOBALS['T']['fail'] ? 1 : 0 );
