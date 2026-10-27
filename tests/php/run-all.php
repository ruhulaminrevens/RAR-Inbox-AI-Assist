<?php
/**
 * Runs every test file and prints a summary. Exit code 1 on any failure.
 *   WP_LOAD=/path/to/wp-load.php IAA_TEST_URL=... php tests/php/run-all.php [unit|integration]
 */
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
}
echo "\nTOTAL: {$GLOBALS['T']['pass']} passed, {$GLOBALS['T']['fail']} failed\n";
foreach ( $GLOBALS['T']['failures'] as $f ) {
	echo " - {$f}\n";
}
exit( $GLOBALS['T']['fail'] ? 1 : 0 );
