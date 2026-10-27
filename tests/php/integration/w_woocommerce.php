<?php
/** Stock buckets from real WooCommerce products, HPOS on and off (FR-06, BR-03, NFR-06). Skipped without WooCommerce. */
if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
	echo "  (skipped: WooCommerce not active)\n";
	return;
}
use RarIaa\Draft\StockService;
use RarIaa\Domain\PriceRepository;

t_reset();
$s = t_seed();
wp_set_current_user( $s['admin'] );

$make = function ( $name, $args ) {
	$p = new WC_Product_Simple();
	$p->set_name( $name );
	$p->set_regular_price( '100' );
	foreach ( $args as $k => $v ) {
		$p->{"set_{$k}"}( $v );
	}
	return $p->save();
};
$plenty  = $make( 'E2E Plenty', array( 'manage_stock' => true, 'stock_quantity' => 50 ) );
$few     = $make( 'E2E Few', array( 'manage_stock' => true, 'stock_quantity' => 2, 'low_stock_amount' => 3 ) );
$none    = $make( 'E2E None', array( 'manage_stock' => true, 'stock_quantity' => 0 ) );
$flag_in = $make( 'E2E Flag in', array( 'manage_stock' => false, 'stock_status' => 'instock' ) );
$flag_out = $make( 'E2E Flag out', array( 'manage_stock' => false, 'stock_status' => 'outofstock' ) );

$item = function ( $id ) {
	return (object) array( 'stock_mode' => 'wc', 'wc_product_id' => $id, 'manual_stock_status' => null );
};
RarIaa\Settings::update( array( 'exact_stock' => 0 ) );
t_eq( array( 'in_stock', null ), StockService::for_item( $item( $plenty ) ), 'managed qty 50 → in_stock, quantity hidden' );
t_eq( array( 'limited', null ), StockService::for_item( $item( $few ) ), 'qty 2 ≤ low 3 → limited' );
t_eq( array( 'out_of_stock', null ), StockService::for_item( $item( $none ) ), 'qty 0 → out_of_stock' );
t_eq( array( 'in_stock', null ), StockService::for_item( $item( $flag_in ) ), 'unmanaged instock → in_stock' );
t_eq( array( 'out_of_stock', null ), StockService::for_item( $item( $flag_out ) ), 'unmanaged outofstock → out_of_stock' );
t_eq( array( 'unknown', null ), StockService::for_item( $item( 999999 ) ), 'missing product → unknown' );
RarIaa\Settings::update( array( 'exact_stock' => 1 ) );
t_eq( array( 'in_stock', 50 ), StockService::for_item( $item( $plenty ) ), 'exact quantity only when enabled' );
RarIaa\Settings::update( array( 'exact_stock' => 0 ) );

// CSV import validates WooCommerce product IDs.
$csv = tempnam( sys_get_temp_dir(), 'iaa' );
file_put_contents( $csv, "item_code,product_name,unit,price_bdt,wc_product_id,stock_mode\nW-1,E2E Plenty,carton,1250,{$plenty},wc\nW-2,Ghost,carton,900,999999,wc\n" );
$parsed = PriceRepository::parse_csv( $csv );
unlink( $csv );
t_eq( array(), $parsed['errors'], 'valid wc rows import' );
t_ok( in_array( 'No WooCommerce product with this ID.', array_column( $parsed['warnings'], 2 ), true ), 'unknown product ID warned' );

// HPOS compatibility declared and drafts work with HPOS on and off.
$compat = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables', true );
t_ok( in_array( 'rar-inbox-ai-assist/rar-inbox-ai-assist.php', $compat['compatible'], true ), 'HPOS compatibility declared' );
$shop = t_user( 'shop_manager', 'iaa_shop_wc' );
$tok  = t_token( $shop );
RarIaa\Settings::update( array( 'per_minute' => 60 ) );
foreach ( array( 'yes', 'no' ) as $hpos ) {
	update_option( 'woocommerce_custom_orders_table_enabled', $hpos );
	$r = t_http( 'POST', '/drafts', $tok, array( 'source' => 'paste', 'messages' => array( array( 'side' => 'customer', 'text' => 'office kokhon khola?' ) ) ), array( 'Idempotency-Key' => t_uuid() ) );
	t_eq( 200, $r['status'], "draft works with HPOS={$hpos}" );
	$r = t_http( 'GET', str_replace( '/rar-iaa/v1', '/wc/v3/products', getenv( 'IAA_TEST_URL' ) ?: 'http://127.0.0.1:8899/index.php?rest_route=/rar-iaa/v1' ), $tok );
	t_ok( in_array( $r['status'], array( 401, 403 ), true ), "IAA token cannot read WooCommerce REST (HPOS={$hpos})" );
}
foreach ( array( $plenty, $few, $none, $flag_in, $flag_out ) as $id ) {
	wp_delete_post( $id, true );
}
