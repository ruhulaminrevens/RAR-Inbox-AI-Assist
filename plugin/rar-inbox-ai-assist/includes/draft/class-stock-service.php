<?php
/**
 * Stock buckets (FR-06, BR-03). Exact quantity only when the admin enables it (OD-3).
 *
 * @package RarIaa
 */

namespace RarIaa\Draft;

use RarIaa\Settings;

defined( 'ABSPATH' ) || exit;

class StockService {

	/** @return array( bucket, quantity|null ) */
	public static function for_item( $item ) {
		if ( 'manual' === $item->stock_mode ) {
			return array( $item->manual_stock_status ? $item->manual_stock_status : 'unknown', null );
		}
		if ( 'wc' !== $item->stock_mode || ! $item->wc_product_id || ! function_exists( 'wc_get_product' ) ) {
			return array( 'unknown', null );
		}
		$product = wc_get_product( (int) $item->wc_product_id );
		if ( ! $product ) {
			return array( 'unknown', null );
		}
		$status = $product->get_stock_status();
		if ( 'outofstock' === $status ) {
			return array( 'out_of_stock', null );
		}
		if ( ! $product->managing_stock() ) {
			return array( 'instock' === $status || 'onbackorder' === $status ? 'in_stock' : 'unknown', null );
		}
		$qty = (int) $product->get_stock_quantity();
		if ( $qty <= 0 ) {
			return array( 'out_of_stock', null );
		}
		$low = (int) get_option( 'woocommerce_notify_low_stock_amount', 2 );
		if ( method_exists( $product, 'get_low_stock_amount' ) && '' !== (string) $product->get_low_stock_amount() ) {
			$low = (int) $product->get_low_stock_amount();
		}
		$bucket = $qty <= $low ? 'limited' : 'in_stock';
		return array( $bucket, Settings::get( 'exact_stock' ) ? $qty : null );
	}
}
